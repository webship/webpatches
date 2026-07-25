# Security

## What the module does

Web Patches is a **read-only reporter**. It reads JSON files that already exist
on the server — the root `composer.json`, the Composer Patches patches file, an
admin-configured extra file, and `composer.lock` — and renders what they
declare.

## What the module never does

- It never applies, downloads or writes a patch.
- It never fetches a URL. Patch URLs, issue links and merge request links are
  derived as strings and rendered as links for a human to follow.
- It never executes anything from the files it reads.

## Access

Both permissions are flagged `restrict access: TRUE`, because the pages disclose
information that is useful to an attacker:

- **View the Web Patches report** — the report reveals absolute server paths
  (the project root, the declaration files) and the exact installed versions of
  patched packages, which makes known-vulnerability lookups easy.
- **Administer Web Patches** — the settings form accepts an arbitrary server
  file path. The file's content is only ever parsed as JSON and rendered as
  patch declarations through the escaping render pipeline, but the setting
  still lets an administrator point the report at any readable JSON file on the
  server, so it belongs to highly trusted users only.

Grant both permissions to administrative roles only.

## Output handling

Everything read from disk is treated as untrusted:

- Descriptions, package names, versions, paths and URLs are rendered with
  `#plain_text` or auto-escaped table cells — never raw markup.
- A patch URL only becomes a link when it is a well formed `http(s)` URL;
  anything else (including other URI schemes) is shown as plain text.
- Package names only become project/Packagist links when they match Composer's
  own package-name pattern, so a malformed name is never interpolated into a
  URL.
- Issue and merge request links are built from an extracted numeric id on fixed
  hosts (`drupal.org`, `git.drupalcode.org`) — no string from disk reaches the
  URL except the validated project machine name and digits.

## Caching

The report carries `#cache max-age 0`: the declarations live on disk outside
Drupal's cache invalidation, and a stale report about patch state would be worse
than an uncached one. The page is admin-only, so the render cost is not an
attack surface for anonymous traffic.
