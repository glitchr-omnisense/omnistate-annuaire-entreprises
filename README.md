# omnistate/annuaire-entreprises

French companies from the State's [Recherche d'entreprises API](https://recherche-entreprises.api.gouv.fr/docs/) -
the one behind [annuaire-entreprises.data.gouv.fr](https://annuaire-entreprises.data.gouv.fr): INSEE's Sirene and
INPI's national register (RNE), free, no key, about seven calls a second. For [glitchr/omnistate](../core).

```php
$company = (new AnnuaireEntreprises($httpClient))->company('901821074');   // or a SIRET
$company->name, $company->legalForm, $company->status, $company->activity, $company->category,
$company->headcount, $company->headOffice, $company->establishments, $company->managers,
$company->finances, $company->labels /* ess, rge, qualiopi... */, $company->collectiveAgreements,
$company->vatNumber /* from its SIREN */, $company->raw /* the whole answer */;
```

No framework needed: the package requires `glitchr/omnistate` and `symfony/http-client`.
`$httpClient` is the HTTP client to call with - the application's, `HttpClient::create()` in
plain PHP, a `MockHttpClient` in a test.

Too many calls throw `UnavailableException` with the `Retry-After` the API gave.

## License

MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
