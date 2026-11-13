<?php

namespace Omnistate\AnnuaireEntreprises\Tests;

use Omnistate\AnnuaireEntreprises\AnnuaireEntreprises;
use Omnistate\Exception\UnavailableException;
use Omnistate\Model\CompanyStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AnnuaireEntreprisesTest extends TestCase
{
    public function testACompanyBySiren(): void
    {
        $company = $this->registry('siren.json')->company('552032534');
        self::assertNotNull($company);

        self::assertSame('DANONE', $company->name);
        self::assertSame(CompanyStatus::ACTIVE, $company->status);
        self::assertSame('5599', $company->legalForm?->code);
        self::assertSame('SA', (string) $company->legalForm);
        self::assertSame('70.10Z', $company->activity?->code);
        self::assertSame('M', $company->activity->section);
        self::assertSame('GE', $company->category);
        self::assertTrue($company->headcount?->isEmployer(), '42: 1000 to 1999 employees');
        self::assertSame('1955-01-01', $company->createdOn?->format('Y-m-d'));
        self::assertSame('FR27552032534', $company->vatNumber, 'from its SIREN');
        self::assertSame('55203253400703', $company->headOffice?->identifier);
        self::assertSame('59 RUE LA FAYETTE', $company->headOffice->address?->street);
        self::assertSame('PARIS', $company->headOffice->address->city);
        self::assertEqualsWithDelta(48.8763540, $company->headOffice->address->latitude, 1e-6);
        self::assertSame([], $company->managers, 'the recorded answer\'s officers left out');
        self::assertSame('annuaire-entreprises', $company->source);
        self::assertSame('552032534', $company->raw['siren'], 'the whole answer kept');
    }

    /** What supports() takes, company() finds: spaces, dots and dashes are not the identifier. */
    public function testACompanyByAnIdentifierAsTyped(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $url;

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/siren.json'));
        });
        $registry = new AnnuaireEntreprises($http);

        self::assertTrue($registry->supports('552 032 534'));
        self::assertSame('DANONE', $registry->company('552 032 534')?->name);
        self::assertSame('DANONE', $registry->company('552.032.534-00703')?->name);
        self::assertStringContainsString('q=552032534&', $requests[0], 'asked as the register writes it');
    }

    public function testAnEstablishmentBySiret(): void
    {
        $company = $this->registry('siret.json')->company('55203253400703');
        self::assertNotNull($company);

        self::assertTrue($company->establishment('55203253400703')?->headOffice);
    }

    public function testEverythingTheAnswerSays(): void
    {
        $company = $this->registry('large.json')->company('732829320');
        self::assertNotNull($company);

        self::assertSame('AT', $company->acronym);
        self::assertSame('SAS', (string) $company->legalForm);
        self::assertSame('100-199', (string) $company->headcount);
        self::assertSame(2023, $company->headcount?->year);
        self::assertSame([12, 9], [$company->establishmentCount, $company->openEstablishmentCount]);
        self::assertSame(['ATELIER'], $company->headOffice?->signs);
        self::assertSame("L'Atelier", $company->headOffice->name);
        self::assertSame(CompanyStatus::CLOSED, $company->establishment('73282932000082')?->status);
        self::assertSame('2020-06-30', $company->establishment('73282932000082')->closedOn?->format('Y-m-d'));

        [$president, $auditor] = $company->managers;
        self::assertTrue($president->person);
        self::assertSame('JEANNE DUPONT', (string) $president, 'an invented person: first names, then name');
        self::assertSame('Président', $president->role);
        self::assertSame(1970, $president->birthYear);
        self::assertFalse($auditor->person, 'a company');
        self::assertSame('CABINET AUDIT', $auditor->name);
        self::assertSame('552100554', $auditor->identifier);

        self::assertSame([2023, 2022], array_map(fn ($year) => $year->year, $company->finances), 'newest first');
        self::assertSame(1500000.0, $company->finances[0]->revenue);
        self::assertSame(-20000.0, $company->finances[0]->netIncome);
        self::assertSame(['ess', 'qualiopi', 'mission_company', 'egapro'], $company->labels);
        self::assertSame(['1486'], $company->collectiveAgreements);
    }

    public function testNotKnown(): void
    {
        self::assertNull((new AnnuaireEntreprises(new MockHttpClient(new MockResponse('{"results":[],"total_results":0}'))))->company('552032534'));
    }

    public function testTooManyCalls(): void
    {
        $registry = new AnnuaireEntreprises(new MockHttpClient(new MockResponse('Too Many Requests.', ['http_code' => 429, 'response_headers' => ['Retry-After: 2']])));

        try {
            $registry->company('552032534');
            self::fail('unavailable');
        } catch (UnavailableException $e) {
            self::assertSame(2, $e->retryAfter);
        }
    }

    public function testAnAnswerNotShapedAsTheRegistersIsUnavailable(): void
    {
        foreach (['{"results":"none"}', '{"results":[{"siren":"552032534","dirigeants":"none"}]}'] as $body) {
            try {
                (new AnnuaireEntreprises(new MockHttpClient(new MockResponse($body))))->company('552032534');
                self::fail('unavailable: '.$body);
            } catch (UnavailableException $e) {
                self::assertStringStartsWith('Recherche d\'entreprises answered', $e->getMessage());
            }
        }
    }

    public function testSupports(): void
    {
        $registry = new AnnuaireEntreprises(new MockHttpClient());
        self::assertTrue($registry->supports('552032534'));
        self::assertTrue($registry->supports('55203253400703'));
        self::assertFalse($registry->supports('552032535'), 'its key wrong');
        self::assertFalse($registry->supports('DE123456789'));
    }

    private function registry(string $fixture): AnnuaireEntreprises
    {
        return new AnnuaireEntreprises(new MockHttpClient(new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture))));
    }
}
