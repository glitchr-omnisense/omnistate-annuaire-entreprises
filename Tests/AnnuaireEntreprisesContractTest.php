<?php

namespace Omnistate\AnnuaireEntreprises\Tests;

use Omnistate\AnnuaireEntreprises\AnnuaireEntreprises;
use Omnistate\Testing\RegistryContractTests;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Omnistate's conformance suite, on the register's recorded answers (Tests/Fixtures). */
final class AnnuaireEntreprisesContractTest extends TestCase
{
    use RegistryContractTests;

    protected function registry(HttpClientInterface $http): object
    {
        return new AnnuaireEntreprises($http);
    }

    protected function recordedAnswers(): array
    {
        return ['*' => static fn (string $method, string $url) => new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.match (true) {
            default => 'siren.json',
        }), ['response_headers' => ['content-type' => 'application/json']])];
    }
}
