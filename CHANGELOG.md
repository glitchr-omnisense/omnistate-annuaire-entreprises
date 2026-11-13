# Changelog

## [Unreleased]

## [1.0.1] - 2026-11-13

### Changed
- The README's example and the test fixtures `siren.json`, `siret.json`: DANONE (552032534), as
  Recherche d'entreprises answered on 2026-11-13 with the officers left out, instead of a small
  company whose record named its officer; the managers' parsing is checked on the invented record
  of `large.json`.

## [1.0.0] - 2026-11-13

### Added
- `policy.json`, the register's usage policy in omnisense/contracts' schema (named in
  `composer.json`, `extra.omnisense.policy`), given by `usagePolicy()` (`DeclaresUsagePolicyInterface`):
  limits 7 a second; the licence (Licence Ouverte 2.0) and its credit declared, the other rights
  unknown until the CGU are read again. Requires
  `omnisense/contracts`.
- A 429 is thrown with `$status` 429: through the facade, the next calls wait for its `retryAfter`.
- PHPStan at level max, its baseline what the package had on 2026-11-10.

### Changed
- Requires `omnisense/contracts: ^1.0`, `omnisense/omnistate: ^1.0`: stable versions, so `composer require omnistate/annuaire-entreprises` installs in a project at the default minimum-stability. `extra.branch-alias` names 1.x (`1.x-dev`); the CI (`.github/workflows/ci.yml`, glitchr-studio/ci's shared workflow) and the release on a tag (`release.yml`).
- PHPStan at level max with an empty baseline, types only (`Headcounts::RANGES` and
  `LegalForms::FORMS` keyed `int|string`, as PHP keeps their numeric codes).

### Fixed
- `company()` called directly reads the identifier as `supports()` does: spaces, dots and dashes
  are dropped before asking, so `company('356 000 000')` finds La Poste, where it answered `null`
  (the result's SIREN was compared with the identifier's first nine characters as typed).
- An answer whose `results`, or a company's `dirigeants`, is not a list is an
  `UnavailableException` (the family's rule on a body that is not what it should be), where PHP
  threw a `TypeError`.
