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
        $company = $this->registry('siren.json')->company('901821074');

        self::assertSame('GLITCH ART', $company->name);
        self::assertSame(CompanyStatus::ACTIVE, $company->status);
        self::assertSame('5499', $company->legalForm->code);
        self::assertSame('SARL', (string) $company->legalForm);
        self::assertSame('62.02A', $company->activity->code);
        self::assertSame('J', $company->activity->section);
        self::assertSame('PME', $company->category);
        self::assertFalse($company->headcount->isEmployer(), 'NN: no employee');
        self::assertSame('2021-07-03', $company->createdOn->format('Y-m-d'));
        self::assertSame('FR53901821074', $company->vatNumber, 'from its SIREN');
        self::assertSame('90182107400019', $company->headOffice->identifier);
        self::assertSame('5 RUE VINCENT SCOTTO', $company->headOffice->address->street);
        self::assertSame('ILLKIRCH-GRAFFENSTADEN', $company->headOffice->address->city);
        self::assertEqualsWithDelta(48.5313533, $company->headOffice->address->latitude, 1e-6);
        self::assertSame('MARCO MEYER', (string) $company->managers[0]);
        self::assertSame('Gérant', $company->managers[0]->role);
        self::assertSame('annuaire-entreprises', $company->source);
        self::assertSame('901821074', $company->raw['siren'], 'the whole answer kept');
    }

    public function testAnEstablishmentBySiret(): void
    {
        $company = $this->registry('siret.json')->company('90182107400019');

        self::assertTrue($company->establishment('90182107400019')->headOffice);
    }

    public function testEverythingTheAnswerSays(): void
    {
        $company = $this->registry('large.json')->company('732829320');

        self::assertSame('AT', $company->acronym);
        self::assertSame('SAS', (string) $company->legalForm);
        self::assertSame('100-199', (string) $company->headcount);
        self::assertSame(2023, $company->headcount->year);
        self::assertSame([12, 9], [$company->establishmentCount, $company->openEstablishmentCount]);
        self::assertSame(['ATELIER'], $company->headOffice->signs);
        self::assertSame("L'Atelier", $company->headOffice->name);
        self::assertSame(CompanyStatus::CLOSED, $company->establishment('73282932000082')->status);
        self::assertSame('2020-06-30', $company->establishment('73282932000082')->closedOn->format('Y-m-d'));

        [$president, $auditor] = $company->managers;
        self::assertTrue($president->person);
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
        self::assertNull((new AnnuaireEntreprises(new MockHttpClient(new MockResponse('{"results":[],"total_results":0}'))))->company('901821074'));
    }

    public function testTooManyCalls(): void
    {
        $registry = new AnnuaireEntreprises(new MockHttpClient(new MockResponse('Too Many Requests.', ['http_code' => 429, 'response_headers' => ['Retry-After: 2']])));

        try {
            $registry->company('901821074');
            self::fail('unavailable');
        } catch (UnavailableException $e) {
            self::assertSame(2, $e->retryAfter);
        }
    }

    public function testSupports(): void
    {
        $registry = new AnnuaireEntreprises(new MockHttpClient());
        self::assertTrue($registry->supports('901821074'));
        self::assertTrue($registry->supports('90182107400019'));
        self::assertFalse($registry->supports('901821075'), 'its key wrong');
        self::assertFalse($registry->supports('DE123456789'));
    }

    private function registry(string $fixture): AnnuaireEntreprises
    {
        return new AnnuaireEntreprises(new MockHttpClient(new MockResponse(file_get_contents(__DIR__.'/Fixtures/'.$fixture))));
    }
}
