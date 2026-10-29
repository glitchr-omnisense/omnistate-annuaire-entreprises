<?php

namespace Omnistate\AnnuaireEntreprises;

use Omnistate\Exception\UnavailableException;
use Omnistate\Identifier\Siren;
use Omnistate\Identifier\Siret;
use Omnistate\Model\Activity;
use Omnistate\Model\Address;
use Omnistate\Model\Company;
use Omnistate\Model\CompanyStatus;
use Omnistate\Model\Establishment;
use Omnistate\Model\FinancialYear;
use Omnistate\Model\Manager;
use Omnistate\Registry\CompanyRegistryInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * French companies, from the State's Recherche d'entreprises API - the one
 * behind annuaire-entreprises.data.gouv.fr: INSEE's Sirene and INPI's
 * national register (RNE) together, free and without a key, about seven
 * calls a second. A SIREN gives the company, a SIRET the company with that
 * establishment among its establishments; anything else is searched.
 *
 * Everything the answer says is mapped - identity, legal form, status,
 * activity, size, head office and establishments with their coordinates,
 * managers, revenue and results by year, labels (ESS, RGE, Qualiopi...),
 * collective agreements - and the VAT number its SIREN makes. The whole
 * answer stays in Company::$raw.
 */
final class AnnuaireEntreprises implements CompanyRegistryInterface
{
    public const URL = 'https://recherche-entreprises.api.gouv.fr/search';

    /** complements' flags, as labels. */
    private const LABELS = [
        'est_ess' => 'ess',
        'est_rge' => 'rge',
        'est_bio' => 'bio',
        'est_qualiopi' => 'qualiopi',
        'est_organisme_formation' => 'training_organization',
        'est_societe_mission' => 'mission_company',
        'est_siae' => 'siae',
        'est_association' => 'association',
        'est_service_public' => 'public_service',
        'est_entrepreneur_spectacle' => 'performing_arts',
        'est_entrepreneur_individuel' => 'sole_proprietor',
        'est_finess' => 'finess',
        'est_uai' => 'uai',
        'est_patrimoine_vivant' => 'living_heritage',
        'est_achats_responsables' => 'responsible_purchasing',
        'est_alim_confiance' => 'alim_confiance',
        'egapro_renseignee' => 'egapro',
    ];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly float $timeout = 10,
    ) {
    }

    public function name(): string
    {
        return 'annuaire-entreprises';
    }

    public function supports(string $identifier): bool
    {
        return Siren::isValid($identifier) || Siret::isValid($identifier);
    }

    public function company(string $identifier): ?Company
    {
        // As supports() reads it: spaces, dots and dashes are not the identifier.
        $identifier = Siret::normalize($identifier) ?? Siren::normalize($identifier) ?? $identifier;
        foreach ($this->ask($identifier, 1) as $result) {
            if ($result['siren'] === substr($identifier, 0, 9)) {
                return $this->toCompany($result);
            }
        }

        return null;
    }

    public function search(string $query, int $limit = 10): array
    {
        return array_map(fn (array $result) => $this->toCompany($result), $this->ask($query, max(1, min(25, $limit))));
    }

    /** @return list<array<string, mixed>> */
    private function ask(string $query, int $perPage): array
    {
        try {
            $response = $this->http->request('GET', self::URL, [
                'query' => ['q' => $query, 'page' => 1, 'per_page' => $perPage],
                'timeout' => $this->timeout,
            ]);
            $status = $response->getStatusCode();
            if (429 === $status) {
                $retry = $response->getHeaders(false)['retry-after'][0] ?? null;

                throw new UnavailableException('Recherche d\'entreprises: too many calls (about seven a second).', is_numeric($retry) ? (int) $retry : 1);
            }
            if ($status >= 500) {
                throw new UnavailableException(sprintf('Recherche d\'entreprises answered %d.', $status));
            }
            if (400 === $status || 404 === $status) {
                return [];
            }
            if ($status >= 400) {
                throw new UnavailableException(sprintf('Recherche d\'entreprises refused the call (%d).', $status));
            }
            $answer = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new UnavailableException('Recherche d\'entreprises did not answer: '.$e->getMessage(), null, $e);
        }

        return array_values(array_filter($answer['results'] ?? [], 'is_array'));
    }

    /** @param array<string, mixed> $r */
    private function toCompany(array $r): Company
    {
        $head = \is_array($r['siege'] ?? null) ? $this->establishment($r['siege']) : null;
        $establishments = [];
        foreach ($r['matching_etablissements'] ?? [] as $e) {
            if (\is_array($e) && ($e['siret'] ?? null) !== $head?->identifier) {
                $establishments[] = $this->establishment($e);
            }
        }

        $finances = [];
        foreach (\is_array($r['finances'] ?? null) ? $r['finances'] : [] as $year => $figures) {
            $finances[] = new FinancialYear((int) $year, self::float($figures['ca'] ?? null), self::float($figures['resultat_net'] ?? null));
        }
        usort($finances, fn (FinancialYear $a, FinancialYear $b) => $b->year <=> $a->year);

        $complements = \is_array($r['complements'] ?? null) ? $r['complements'] : [];
        $labels = [];
        foreach (self::LABELS as $flag => $label) {
            if (true === ($complements[$flag] ?? null)) {
                $labels[] = $label;
            }
        }

        $name = (string) ($r['nom_complet'] ?? $r['nom_raison_sociale'] ?? $r['siren']);

        return new Company(
            identifier: (string) $r['siren'],
            name: $name,
            status: 'C' === ($r['etat_administratif'] ?? null) ? CompanyStatus::CLOSED : CompanyStatus::ACTIVE,
            country: 'FR',
            source: $this->name(),
            legalName: self::string($r['nom_raison_sociale'] ?? null) ?? $name,
            acronym: self::string($r['sigle'] ?? null),
            legalForm: LegalForms::of(self::string($r['nature_juridique'] ?? null)),
            activity: self::activity($r['activite_principale'] ?? null, $r['section_activite_principale'] ?? null),
            category: self::string($r['categorie_entreprise'] ?? null),
            headcount: Headcounts::of(self::string($r['tranche_effectif_salarie'] ?? null), $r['annee_tranche_effectif_salarie'] ?? null),
            createdOn: self::date($r['date_creation'] ?? null),
            closedOn: self::date($r['date_fermeture'] ?? null),
            headOffice: $head,
            establishments: $establishments,
            establishmentCount: isset($r['nombre_etablissements']) ? (int) $r['nombre_etablissements'] : null,
            openEstablishmentCount: isset($r['nombre_etablissements_ouverts']) ? (int) $r['nombre_etablissements_ouverts'] : null,
            managers: array_values(array_map(fn (array $m) => $this->manager($m), array_filter($r['dirigeants'] ?? [], 'is_array'))),
            finances: $finances,
            labels: $labels,
            collectiveAgreements: array_values(array_map('strval', \is_array($complements['liste_idcc'] ?? null) ? $complements['liste_idcc'] : [])),
            vatNumber: Siren::isValid((string) $r['siren']) ? Siren::toVatNumber((string) $r['siren']) : null,
            updatedAt: self::date($r['date_mise_a_jour'] ?? null),
            raw: $r,
        );
    }

    /** @param array<string, mixed> $e */
    private function establishment(array $e): Establishment
    {
        $street = trim(implode(' ', array_filter([
            self::string($e['numero_voie'] ?? null), self::string($e['indice_repetition'] ?? null),
            self::string($e['type_voie'] ?? null), self::string($e['libelle_voie'] ?? null),
        ])));

        return new Establishment(
            identifier: (string) $e['siret'],
            headOffice: (bool) ($e['est_siege'] ?? false),
            status: 'F' === ($e['etat_administratif'] ?? null) ? CompanyStatus::CLOSED : CompanyStatus::ACTIVE,
            address: new Address(
                street: '' !== $street ? $street : null,
                postalCode: self::string($e['code_postal'] ?? null),
                city: self::string($e['libelle_commune'] ?? null) ?? self::string($e['libelle_commune_etranger'] ?? null),
                country: self::string($e['code_pays_etranger'] ?? null) ? null : 'FR',
                full: self::string($e['adresse'] ?? null),
                latitude: self::float($e['latitude'] ?? null),
                longitude: self::float($e['longitude'] ?? null),
            ),
            name: self::string($e['nom_commercial'] ?? null),
            signs: array_values(array_map('strval', \is_array($e['liste_enseignes'] ?? null) ? $e['liste_enseignes'] : [])),
            activity: self::activity($e['activite_principale'] ?? null),
            headcount: Headcounts::of(self::string($e['tranche_effectif_salarie'] ?? null), $e['annee_tranche_effectif_salarie'] ?? null),
            createdOn: self::date($e['date_creation'] ?? null),
            closedOn: self::date($e['date_fermeture'] ?? null),
        );
    }

    /** @param array<string, mixed> $m */
    private function manager(array $m): Manager
    {
        $person = 'personne morale' !== ($m['type_dirigeant'] ?? null);

        return new Manager(
            name: (string) ($person ? ($m['nom'] ?? '') : ($m['denomination'] ?? $m['nom'] ?? '')),
            role: self::string($m['qualite'] ?? null),
            person: $person,
            firstNames: $person ? self::string($m['prenoms'] ?? null) : null,
            identifier: $person ? null : self::string($m['siren'] ?? null),
            birthYear: is_numeric($m['annee_de_naissance'] ?? null) ? (int) $m['annee_de_naissance'] : null,
            nationality: self::string($m['nationalite'] ?? null),
        );
    }

    private static function activity(mixed $code, mixed $section = null): ?Activity
    {
        return \is_string($code) && '' !== $code ? new Activity($code, 'NAF rev. 2', null, \is_string($section) ? $section : null) : null;
    }

    private static function string(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    private static function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
