# omnistate/annuaire-entreprises

French companies for [omnisense/omnistate](https://github.com/glitchr-omnisense/omnistate), from the
State's [Recherche d'entreprises API](https://recherche-entreprises.api.gouv.fr/docs/) - the one
behind [annuaire-entreprises.data.gouv.fr](https://annuaire-entreprises.data.gouv.fr), which joins
INSEE's Sirene and INPI's national register (RNE). One endpoint, for a lookup and a search alike:

| Operation | Request |
|---|---|
| `company($identifier)` | `GET https://recherche-entreprises.api.gouv.fr/search?q=<identifier>&page=1&per_page=1` |
| `search($query, $limit)` | `GET https://recherche-entreprises.api.gouv.fr/search?q=<query>&page=1&per_page=<limit, 1 to 25>` |

The address is the constant `Omnistate\AnnuaireEntreprises\AnnuaireEntreprises::URL`. There is no
sandbox: the API answers the real register.

## Installation

```sh
composer require omnisense/omnistate omnistate/annuaire-entreprises
```

Requires PHP 8.2, `omnisense/omnistate` (1.x) and `symfony/http-client` (6.4, 7 or 8). In a
Symfony application with `Omnistate\Bridge\Symfony\OmnistateBundle`, the register is registered
(tag `omnistate.company_registry`) as soon as the package is installed; nothing to configure.

## Credentials

None: the API is open, without an account or a key. It allows about seven calls a second per
address; past that it answers HTTP 429.

## Options

| Argument | Type | Default | |
|---|---|---|---|
| `http` | `Symfony\Contracts\HttpClient\HttpClientInterface` | required | the client to call with |
| `timeout` | float | `10` | seconds the API has to answer (the bundle's `omnistate.timeout`) |

## Capabilities

| Contract / question | Supported |
|---|---|
| `CompanyRegistryInterface::company()` | yes: a SIREN or a SIRET whose key is right |
| `CompanyRegistryInterface::search()` | yes: anything the API searches on (names, addresses, identifiers) |
| VAT, professionals, facilities, civil records, Internet | no |

`name()` is `annuaire-entreprises`, the `source` of every `Company`.

## Behaviour

**What it takes.** `supports()` is `Siren::isValid()` or `Siret::isValid()`: nine or fourteen
digits whose key is right, spaces, dots and dashes ignored. Called directly, `company()` expects
the identifier without them (see [Errors](#errors)); through the facade it is normalized first,
and a French VAT number is looked up by its SIREN.

**A company.** The identifier is the search's `q`, one result asked; the result is the company
when its `siren` is the identifier's first nine digits, else `null`. A SIRET thus gives its
company, with the establishments the API says matched in `establishments`.

**A search.** `per_page` is the limit kept between 1 and 25; only the first page is asked. Each
result is a `Company`, as for a lookup.

**Mapping.** Everything the answer says is mapped, and `raw` keeps the whole result:

| `Company` | From |
|---|---|
| `identifier` | `siren` |
| `name` | `nom_complet`, else `nom_raison_sociale`, else the SIREN |
| `legalName` | `nom_raison_sociale`, else the name |
| `acronym` | `sigle` |
| `status` | `CLOSED` when `etat_administratif` is `C`, else `ACTIVE` |
| `country` | `FR` |
| `legalForm` | `nature_juridique`, named from `LegalForms::FORMS` |
| `activity` | `activite_principale` (classification `NAF rev. 2`), `section_activite_principale` |
| `category` | `categorie_entreprise` (`PME`, `ETI`, `GE`) |
| `headcount` | `tranche_effectif_salarie` and `annee_tranche_effectif_salarie`, read with `Headcounts::RANGES` |
| `createdOn`, `closedOn`, `updatedAt` | `date_creation`, `date_fermeture`, `date_mise_a_jour` |
| `headOffice` | `siege` |
| `establishments` | `matching_etablissements`, the head office left out |
| `establishmentCount`, `openEstablishmentCount` | `nombre_etablissements`, `nombre_etablissements_ouverts` |
| `managers` | `dirigeants` |
| `finances` | `finances`: revenue (`ca`) and net income (`resultat_net`) by year, newest first, EUR |
| `labels` | the `complements` flags that are `true`, below |
| `collectiveAgreements` | `complements.liste_idcc` |
| `vatNumber` | `Siren::toVatNumber()` of the SIREN: derived, VIES not asked |

An establishment: `siret`, `est_siege`, `CLOSED` when `etat_administratif` is `F`, the street
from `numero_voie`, `indice_repetition`, `type_voie` and `libelle_voie`, `code_postal`,
`libelle_commune` (else `libelle_commune_etranger`), country `FR` unless `code_pays_etranger` is
set (then `null`), `adresse` as the one-line form, `latitude`, `longitude`, `nom_commercial`,
`liste_enseignes` as signs, its activity, headcount and dates.

A manager is a person unless `type_dirigeant` is `personne morale`: a person has `nom`, `prenoms`,
`qualite` as role, `annee_de_naissance`, `nationalite`; a company `denomination` (else `nom`) and
its `siren` as `identifier`.

The labels, from the answer's `complements`:

| Flag | Label | Flag | Label |
|---|---|---|---|
| `est_ess` | `ess` | `est_entrepreneur_spectacle` | `performing_arts` |
| `est_rge` | `rge` | `est_entrepreneur_individuel` | `sole_proprietor` |
| `est_bio` | `bio` | `est_finess` | `finess` |
| `est_qualiopi` | `qualiopi` | `est_uai` | `uai` |
| `est_organisme_formation` | `training_organization` | `est_patrimoine_vivant` | `living_heritage` |
| `est_societe_mission` | `mission_company` | `est_achats_responsables` | `responsible_purchasing` |
| `est_siae` | `siae` | `est_alim_confiance` | `alim_confiance` |
| `est_association` | `association` | `egapro_renseignee` | `egapro` |
| `est_service_public` | `public_service` | | |

`LegalForms::FORMS` names 45 of INSEE's legal categories (level III) with their short names
(`5499` SARL, `5710` SAS, `5720` SASU, `5498` EURL, `6540` SCI, `9220` association déclarée...);
another code keeps its number, without a name. `LegalForms::of(?string $code): ?LegalForm`.
`Headcounts::RANGES` reads INSEE's sixteen range codes (`NN` and `00`: none, `01`: 1 to 2... `53`:
10,000 and more); `Headcounts::of(?string $code, mixed $year): ?Headcount`.

**Caching.** None of its own: the facade's cache keeps its answers.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnistate\AnnuaireEntreprises\AnnuaireEntreprises;
use Omnistate\Exception\UnavailableException;
use Symfony\Component\HttpClient\HttpClient;

$registry = new AnnuaireEntreprises(HttpClient::create(), timeout: 5);

var_dump($registry->supports('356000000'), $registry->supports('35600000000048'), $registry->supports('356 000 000'));

try {
    $company = $registry->company('356000000');
    printf("%s, %s, %s, %d establishments, head office %s\n", $company->name, $company->legalForm?->label, $company->category, $company->establishmentCount, $company->headOffice?->identifier);

    foreach ($registry->search('boulangerie strasbourg', 3) as $found) {
        printf("  %s %-35s %s, %d matching establishment(s) besides the head office\n", $found->identifier, $found->name, $found->headOffice?->address?->city, count($found->establishments));
    }
} catch (UnavailableException $e) {
    echo 'Recherche d\'entreprises did not answer: ', $e->getMessage(), ' (retry after ', $e->retryAfter ?? '-', " s)\n";
}
```

```
bool(true)
bool(false)
bool(true)
LA POSTE, SA nationale à conseil d'administration, GE, 13131 establishments, head office 35600000000048
  478455793 BOULANGERIES BG (B B G)             CHATEAURENARD, 1 matching establishment(s) besides the head office
  403052111 BOULANGERIES PAUL                   MARCQ-EN-BARŒUL, 6 matching establishment(s) besides the head office
  343759148 SCI MAISON DE LA BOULANGERIE 2      STRASBOURG, 0 matching establishment(s) besides the head office
```

(run on 2026-10-25 in omnistate's Docker harness, against the API. The second line is
[a defect](#verified-and-not): La Poste's head office SIRET, as the API writes it, is refused.)

Through the facade, which normalizes identifiers and keeps answers:

```php
$omnistate = new Omnistate\Omnistate(companies: [$registry]);
$omnistate->company('FR39356000000');   // the SIREN 356000000
```

In Symfony nothing is to be configured: `Omnistate\Omnistate` is autowired with this register.

## Errors

| The API answers | The register |
|---|---|
| HTTP 200 with results | the companies; `company()` `null` when none is the identifier's |
| HTTP 400 or 404 | `null` from `company()`, `[]` from `search()` |
| HTTP 429 | `UnavailableException` (`Recherche d'entreprises: too many calls (about seven a second).`), `retryAfter` from `Retry-After`, else 1 |
| HTTP 401, 403, another 4xx | `UnavailableException` (`... refused the call (403).`) |
| HTTP 5xx | `UnavailableException` (`... answered 503.`) |
| no answer, a body that is not JSON | `UnavailableException` (`... did not answer: ...`), the HTTP client's exception as previous |

An identifier it does not support is never asked: through the facade it is a
`NotSupportedException`.

## Verified, and not

Tested offline: `Tests/AnnuaireEntreprisesTest.php` (a company by SIREN with every field, an
establishment by SIRET, a large answer with acronym, signs, closed establishments, a company as
manager, finances newest first and labels, an unknown company, a 429 with its `Retry-After`,
`supports()`) on answers of the API recorded in `Tests/Fixtures`, and
`Tests/AnnuaireEntreprisesContractTest.php`, omnistate's conformance suite.

Run live on 2026-10-25 (the example above, and the core manual's company examples).

Defects found in the code:

- `AnnuaireEntreprises.php:76` - `company()` compares the result's SIREN with the identifier's
  first nine characters as given, while `supports()` ignores spaces, dots and dashes: called
  directly, `company('356 000 000')` is supported and answers `null`. Through the facade, which
  normalizes first, it is found.
- In omnisense/omnistate, `Identifier/Siret.php:29-30` - La Poste's head office SIRET
  `35600000000048`, as this API gives it, fails `Siret::isValid()`: `supports()` refuses it (the
  example's second line).

## Responsibility

An independent, unofficial client, not affiliated with the DINUM (Annuaire des Entreprises), the
INSEE or the INPI. Whoever runs it complies with the Annuaire des Entreprises's terms
([CGU](https://annuaire-entreprises.data.gouv.fr/cgu)): see the README's Responsibility section.
