# Configuration

All settings live at **Configuration → Development → Web Patches**
(`/admin/config/development/webpatches`) and are stored in the
`webpatches.settings` config object.

## Patch declaration sources

| Setting | Config key | Default | What it enables |
| --- | --- | --- | --- |
| Root composer.json | `sources.root_composer` | on | `extra.patches` of the project `composer.json` |
| Patches file | `sources.patches_composer` | on | the separate patches file (see below) |
| Custom patches file | `sources.custom_file` | off | an extra file at `custom_file_path` |
| Installed dependency packages | `sources.dependency_packages` | on | `extra.patches` of the packages in `composer.lock` |

### Which patches file is read

The key moved between the two Composer Patches versions, and the report follows
the same precedence Composer does:

1. `extra.composer-patches.patches-file` — Composer Patches **v2**. Its default
   is `patches.json`.
2. `extra.patches-file` — Composer Patches **v1**, top level.
3. With neither declared, the first conventional file that exists next to the
   root `composer.json`: `patches.json`, then `patches.composer.json`.

### The custom patches file

`custom_file_path` may be absolute or relative to the Composer project root.
The file may be a bare patches file:

```json
{
  "patches": {
    "drupal/redirect": {
      "Issue #2879648: Redirects from aliased paths aren't triggered": "patches/redirect--2026-07-05--2879648--mr-202.patch"
    }
  }
}
```

or a `composer.json` shaped file carrying the list under `extra.patches`. A path
that does not exist is rejected by the settings form.

## Only installed packages

`only_installed_packages` (default on) hides patches whose target package is not
in the site's `composer.lock`, so the report shows the patches that matter for
this site rather than every patch a dependency happens to carry. The note at the
bottom of the report says when this filter is active.

## The dependency allowlist and ignore rules

These are **Composer** settings in the root `composer.json`, not module
settings — the report only reads them:

| Key | Effect |
| --- | --- |
| `extra.composer-patches.allowed-dependency-patches` | Only packages matching one of these patterns (fnmatch, wildcards allowed) contribute their `extra.patches`. Default: `webship/patches`, `webship/drupal-patches`. |
| `extra.composer-patches.ignore-dependency-patches` | Packages matching one of these patterns are excluded even when allowlisted. |
| `extra.patches-ignore` | Drops individual patch URLs declared by a given package: `{ "<source-pkg>": { "<target-pkg>": ["<url>"] } }`. |

The verdict for every installed package that declares patches is shown on the
report under **Patching sources → Packages declaring patches**. For example, a
site that installs [AI Context](https://www.drupal.org/project/ai_context) —
which has shipped its own Drupal Canvas patch in `extra.patches` — sees
`drupal/ai_context` listed there as *Not allowed*, because it is not in the
allowlist.

## The links on the report

Every link is derived, never fetched:

| Link | Derived from |
| --- | --- |
| Package → drupal.org project | `drupal/<name>` → `drupal.org/project/<name>`; `drupal/core` → project `drupal`; non-Drupal packages → Packagist |
| Issue | the first `#1234567` (6–8 digits) in the patch description → `drupal.org/node/<id>` |
| Patch file | the declared URL itself, linked only when it is a well formed http(s) URL |
| Merge request | `--mr-<id>` in the patch file name + the patched package's project → `git.drupalcode.org/project/<project>/-/merge_requests/<id>` |

A description with no issue reference, a file name with no `--mr-<id>`, or a
non-Drupal package simply renders without that link.
