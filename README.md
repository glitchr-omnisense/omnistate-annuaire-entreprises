# omnistate/annuaire-entreprises

French companies from the State's [Recherche d'entreprises API](https://recherche-entreprises.api.gouv.fr/docs/) -
the one behind [annuaire-entreprises.data.gouv.fr](https://annuaire-entreprises.data.gouv.fr): INSEE's Sirene and
INPI's national register (RNE), free, no key, about seven calls a second. For [omniscient/omnistate](../core).

```php
$company = (new AnnuaireEntreprises($httpClient))->company('901821074');   // or a SIRET
$company->name, $company->legalForm, $company->status, $company->activity, $company->category,
$company->headcount, $company->headOffice, $company->establishments, $company->managers,
$company->finances, $company->labels /* ess, rge, qualiopi... */, $company->collectiveAgreements,
$company->vatNumber /* from its SIREN */, $company->raw /* the whole answer */;
```

No framework needed: the package requires `omniscient/omnistate` and `symfony/http-client`.
`$httpClient` is the HTTP client to call with - the application's, `HttpClient::create()` in
plain PHP, a `MockHttpClient` in a test.

Too many calls throw `UnavailableException` with the `Retry-After` the API gave.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the DINUM (Annuaire des Entreprises), the INSEE or the INPI. It reaches their service
on behalf of whoever runs it, and it does not redistribute their data beyond the short excerpts
recorded as test fixtures. Its MIT licence covers this package's code only and grants no right to
the service or to its data.

Whoever installs and runs this package is responsible for complying with the Annuaire des
Entreprises's terms ([CGU](https://annuaire-entreprises.data.gouv.fr/cgu)), for obtaining whatever
they require (an account, a key, a licence or subscription, an attribution or a citation, a rate
limit to respect), and for any use made of what it returns. The authors publish it free of charge,
make no commercial use of it, and accept no liability for how it is used (see [LICENSE](LICENSE)).

## Documentation

[docs/](docs/index.md): the service, the options, what it supports, how it maps the answers, errors, verified and not.

## License

MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
