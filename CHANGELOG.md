# Changelog

## Unreleased (1.x)

### Fixed
- `company()` called directly reads the identifier as `supports()` does: spaces, dots and dashes
  are dropped before asking, so `company('356 000 000')` finds La Poste, where it answered `null`
  (the result's SIREN was compared with the identifier's first nine characters as typed).
