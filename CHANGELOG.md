# Changelog

## Unreleased (1.x)

### Added
- `policy.json`, the register's usage policy in omnisense/contracts' schema (named in
  `composer.json`, `extra.omnisense.policy`), given by `usagePolicy()` (`DeclaresUsagePolicyInterface`):
  limits 7 a second; the licence (Licence Ouverte 2.0) and its credit declared, the other rights
  unknown until the CGU are read again. Requires
  `omnisense/contracts`.
- A 429 is thrown with `$status` 429: through the facade, the next calls wait for its `retryAfter`.
- PHPStan at level max, its baseline what the package had on 2026-11-10.

### Fixed
- `company()` called directly reads the identifier as `supports()` does: spaces, dots and dashes
  are dropped before asking, so `company('356 000 000')` finds La Poste, where it answered `null`
  (the result's SIREN was compared with the identifier's first nine characters as typed).
