### Problem/Motivation

Web Patches ships a hard-coded `extra.patches` list in its own `composer.json`, and keeps the patch files on a `patches` branch of this repository. Every site that installs the module has two Drupal core patches and a Default Content patch forced onto it, whether or not that site wants them.

At the same time the module offers no way for a site owner to see which patches are actually declared for their site, or which declared patches are filtered out before Composer applies them.

Patch curation for Webship now lives in `webship/webship-patches` and `webship/drupal-core-patches`, which is where a curated list belongs. What Web Patches is missing is the other half: the report.

Steps to reproduce

1. Install `drupal/webpatches` on a Drupal ~11.4.0 site.
2. Run `composer install` and note that two `drupal/core` patches and a `drupal/default_content` patch are applied, contributed by the module itself.
3. Look for a page in the admin UI that lists the patches of the site — there is none.

### Proposed resolution

- Remove `extra.patches` from `composer.json`, so the module stops forcing patches onto the sites that install it.
- Remove the `patches` branch, which is only referenced by that list.
- Add a report at **Reports → Web Patches** (`/admin/reports/webpatches`, permission *View the Web Patches report*) listing the declaration sources, every declared patch with the package and the source that declared it, and every ignored patch with the reason it is not applied.
- Read the same declarations Composer reads: the root `composer.json`, the file referenced by `extra.patches-file` (`patches.composer.json`), an extra file at a configurable path, and the `extra.patches` of installed dependency packages.
- Apply the same allowlist and ignore rules the `webship/webship-patches` Composer plugin applies — `extra.composer-patches.allowed-dependency-patches`, `extra.composer-patches.ignore-dependency-patches` and `extra.patches-ignore` — so the report matches what Composer actually does.
- Add a settings form at **Configuration → Development → Web Patches** (permission *Administer Web Patches*) to choose the sources and the custom file path, and to limit the report to packages installed on the site.

The module still does not apply, download or write patches. Composer does that.

### Checkpoints
- [x] File an issue
- [x] Addition for a new supported feature
- [x] Testing to ensure no regression
- [x] Automated unit testing coverage
- [x] Automated functional testing coverage
- [ ] UX/UI designer responsibilities
- [ ] Readability
- [ ] Accessibility
- [ ] Performance
- [ ] Security
- [x] Documentation
- [ ] Reviewed by human
- [ ] Code review by maintainers
- [ ] Full testing and approval
- [ ] Credit contributors
- [ ] Review with the product owner
- [ ] Release Notes
- [ ] Release

### API changes

New service `webpatches.collector` (`\Drupal\webpatches\PatchesCollectorInterface`), exposing `getSources()`, `getPatches()`, `getIgnoredPatches()` and `getProjectRoot()`.

Two new permissions: *View the Web Patches report* and *Administer Web Patches*.

Two new routes: `webpatches.list` (`/admin/reports/webpatches`) and `webpatches.settings` (`/admin/config/development/webpatches`).

### Data model changes

New config object `webpatches.settings` with `sources`, `custom_file_path` and `only_installed_packages`.

### Release notes snippet

feat: [#PLACEHOLDER](https://git.drupalcode.org/project/webpatches/-/work_items/PLACEHOLDER) Show the declared and ignored patches of the site in the admin UI
