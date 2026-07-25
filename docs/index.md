# Web Patches

Web Patches shows the patches — and the ignored patches — declared for a
Drupal site, right in the admin UI.

Patches are applied by Composer, through
[cweagans/composer-patches](https://github.com/cweagans/composer-patches) and
the [webship/patches](https://github.com/webship/patches) Composer plugin.
This module does not apply, download or write patches — it never even fetches
a URL. It reads the same declarations Composer reads and applies the same
allowlist and ignore rules, so a site owner can finally *see* what is patched
and what was filtered out, without opening a terminal.

## Quick start

1. `composer require drupal/webpatches`
2. Enable the module.
3. Visit **Reports → Web Patches** (`/admin/reports/webpatches`).

Two permissions gate the pages, both restricted to trusted roles:
*View the Web Patches report* and *Administer Web Patches*.

## What you get

- **[The report](report.md)** — the patching sources with the allowlist
  verdict for each package, a `patches.lock.json` sync check, every declared
  patch linked to its drupal.org project, issue and merge request, and the
  ignored patches with the reason each one is not applied.
- **[Configuration](configuration.md)** — which declaration files are read,
  the custom patches file, and the only-installed filter.
- **[Security](security.md)** — what the module does and does not do, and why
  the permissions are restricted.
- **[Testing](testing.md)** — the PHPUnit suites and the webship-js BDD suite
  that runs against a live site.

## Requirements

- Drupal core `^11.1`
- Works with [cweagans/composer-patches](https://github.com/cweagans/composer-patches)
  v2 (and reads the v1 declaration keys too)

## Ecosystem

Web Patches is part of the [Webship](https://www.drupal.org/project/webship)
ecosystem:

| Package | Role |
| --- | --- |
| [webship/patches](https://github.com/webship/patches) | The curated contrib patch list and Composer plugin (allowlist, wildcard ignore, patches-ignore) |
| [webship/drupal-patches](https://github.com/webship/drupal-patches) | The curated Drupal core patches, one branch per core minor |
| drupal/webpatches | This module — the report |
