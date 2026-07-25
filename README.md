# Web Patches

Provides additional local patching functionality for websites.

Web Patches shows the patches and the ignored patches declared for a site.

Patches themselves are applied by Composer, through
[`cweagans/composer-patches`](https://github.com/cweagans/composer-patches) and the
[`webship/patches`](https://github.com/webship/patches) Composer plugin.
This module does not apply, download or write patches. It reads the same declarations
Composer reads and applies the same allowlist and ignore rules, so a site owner can see
what is declared for the site and what is filtered out.

## The report

**Reports → Web Patches** (`/admin/reports/webpatches`, permission
*View the Web Patches report*) lists:

- **Sources** — each declaration source, its file path, and whether it was read.
- **Patches** — every declared patch, the package it applies to, the patch file, and
  the source or dependency package that declared it.
- **Ignored patches** — every declared patch that is *not* applied, with the reason:
  the declaring package is not in the allowlist, it is matched by
  `extra.composer-patches.ignore-dependency-patches`, or the patch URL is listed in
  `extra.patches-ignore`.

## The sources

**Configuration → Development → Web Patches**
(`/admin/config/development/webpatches`, permission *Administer Web Patches*) selects
which declarations are read:

| Source | What it reads |
| --- | --- |
| Root composer.json | `extra.patches` of the project `composer.json` |
| Patches file | the file referenced by `extra.patches-file`, which defaults to `patches.composer.json` |
| Custom patches file | any extra file at a configured path, either `{"patches": {…}}` or a `composer.json` shaped file with `extra.patches` |
| Installed dependency packages | `extra.patches` of the packages in `composer.lock`, filtered by the allowlist and the ignore rules |

By default the report lists only patches for packages that are actually installed on
the site, so it shows the patches that matter for this site rather than every patch a
dependency happens to carry. Turn *Only list patches for packages installed on this
site* off to see the full declaration.

## Dependency patches

A dependency package only contributes its `extra.patches` when its name matches
`extra.composer-patches.allowed-dependency-patches` in the root `composer.json`.
When that key is absent the default allowlist is used:

```json
{
  "extra": {
    "composer-patches": {
      "allowed-dependency-patches": [
        "webship/patches",
        "webship/drupal-patches"
      ]
    }
  }
}
```

A single patch of an allowed package can be dropped with `extra.patches-ignore`:

```json
{
  "extra": {
    "patches-ignore": {
      "webship/patches": {
        "drupal/redirect": [
          "https://raw.githubusercontent.com/webship/patches/refs/heads/patches/redirect--2026-07-05--2879648--mr-202.patch"
        ]
      }
    }
  }
}
```

Both appear on the report under **Ignored patches** with the matching reason.
