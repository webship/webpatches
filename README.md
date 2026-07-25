# Web Patches

Provides additional local patching functionality for websites.

Web Patches shows the patches and the ignored patches declared for a site.

Patches themselves are applied by Composer, through
[`cweagans/composer-patches`](https://github.com/cweagans/composer-patches) and the
[`webship/patches`](https://github.com/webship/patches) Composer plugin.
This module does not apply, download or write patches — it never fetches a URL.
It reads the same declarations Composer reads and applies the same allowlist and
ignore rules, so a site owner can see what is declared for the site and what is
filtered out.

## The report

**Reports → Web Patches** (`/admin/reports/webpatches`, permission
*View the Web Patches report*) lists:

- **Patching sources** — the declaration files (with the path and whether each was
  read), and the installed packages that declare patches, each with the number of
  patches it declares and the verdict of the allowlist: *Allowed* or *Not allowed*,
  with the reason.
- **Patches** — every declared patch. The package links to its drupal.org project
  page (or Packagist for non-Drupal packages), a `#1234567` issue reference in the
  description links to the drupal.org issue, and under the description the patch
  file links to the file itself — plus an *MR !id* link to the merge request on
  git.drupalcode.org when the file name carries `--mr-<id>`.
- **Ignored patches** — every declared patch that is *not* applied, with the
  reason: the declaring package is not in the allowlist, it is matched by
  `extra.composer-patches.ignore-dependency-patches`, or the patch URL is listed
  in `extra.patches-ignore`.

By default the report lists only patches for packages that are actually installed
on the site. Turn *Only list patches for packages installed on this site* off in
the settings to see the full declaration.

## The sources

**Configuration → Development → Web Patches**
(`/admin/config/development/webpatches`, permission *Administer Web Patches*)
selects which declarations are read:

| Source | What it reads |
| --- | --- |
| Root composer.json | `extra.patches` of the project `composer.json` |
| Patches file | the separate patches file Composer Patches reads: `extra.composer-patches.patches-file` (v2, default `patches.json`) or the top level `extra.patches-file` (v1) |
| Custom patches file | any extra file at a configured path, either `{"patches": {…}}` or a `composer.json` shaped file with `extra.patches` |
| Installed dependency packages | `extra.patches` of the packages in `composer.lock`, filtered by the allowlist and the ignore rules |

## Dependency patches and the allowlist

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

### An example: AI Context

Some contrib modules ship their own `extra.patches`. The
[AI Context](https://www.drupal.org/project/ai_context) module
(`drupal/ai_context`), for example, has declared a Drupal Canvas patch in its own
`composer.json`. On a site that installs it, the report lists
`drupal/ai_context` under **Packages declaring patches** as *Not allowed* —
it is not in the allowlist — and Composer never applies its Canvas patch. That is
the point of the default-deny allowlist: a stale or unwanted patch URL inside a
dependency cannot break or alter your site just because you installed the module.

To accept such a package's patches anyway, add it to the allowlist:

```json
{
  "extra": {
    "composer-patches": {
      "allowed-dependency-patches": [
        "webship/patches",
        "webship/drupal-patches",
        "drupal/ai_context"
      ]
    }
  }
}
```

### Dropping a single patch

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

## Documentation

- [Configuration](docs/configuration.md) — every setting and the Composer keys
  the report understands.
- [Security](docs/security.md) — what the module does and does not do, and why
  both permissions are restricted.
