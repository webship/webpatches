# feat: Show the declared and ignored patches of the site in the admin UI

Ready to file as a work item on <https://git.drupalcode.org/project/webpatches/-/issues>
(Webship projects use GitLab work items, not the drupal.org node issue queue).
Matches the `.gitlab/issue_templates/addition.md` shape.

---

## Problem/Motivation

Web Patches ships a hard-coded `extra.patches` list in its own `composer.json`, and keeps the patch files on a `patches` branch of the project repository. Every site that installs the module therefore has two Drupal core patches and a Default Content patch forced onto it, whether or not that site wants them.

At the same time the module offers no way for a site owner to see which patches are actually declared for their site, or which declared patches are filtered out before Composer applies them.

Patch curation for Webship now lives in `webship/webship-patches` and `webship/drupal-core-patches`, which is where a curated list belongs. What Web Patches is missing is the other half: the report.

## Steps to reproduce

1. Install `drupal/webpatches` on a Drupal ~11.4.0 site.
2. Run `composer install` and note that two `drupal/core` patches and a `drupal/default_content` patch are applied, contributed by the module itself.
3. Look for a page in the admin UI that lists the patches of the site — there is none.

## Proposed resolution

- Remove `extra.patches` from `composer.json`, so the module stops forcing patches onto the sites that install it.
- Remove the `patches` branch, which is only referenced by that list.
- Add a report at **Reports → Web Patches** (`/admin/reports/webpatches`, permission *View the Web Patches report*) listing the declaration sources, every declared patch with the package and the source that declared it, and every ignored patch with the reason it is not applied.
- Read the same declarations Composer reads: the root `composer.json`, the file referenced by `extra.patches-file` (`patches.composer.json`), an extra file at a configurable path, and the `extra.patches` of installed dependency packages.
- Apply the same allowlist and ignore rules the `webship/webship-patches` Composer plugin applies — `extra.composer-patches.allowed-dependency-patches`, `extra.composer-patches.ignore-dependency-patches` and `extra.patches-ignore` — so the report matches what Composer actually does.
- Add a settings form at **Configuration → Development → Web Patches** (permission *Administer Web Patches*) to choose the sources and the custom file path, and to limit the report to packages installed on the site.

The module still does not apply, download or write patches. Composer does that.

## Remaining tasks

- ✅ Remove `extra.patches` from `composer.json`
- ✅ Add the patches collector service
- ✅ Add the report and the settings form
- ✅ Unit and functional test coverage
- ❌ Delete the `patches` branch on the `drupal` and `github` remotes
- ❌ Code review and merge

## User interface changes

Two new admin pages:

- **Reports → Web Patches** — the patches report.
- **Configuration → Development → Web Patches** — the source settings.

Two new permissions: *View the Web Patches report* and *Administer Web Patches*.

## API changes

New service `webpatches.collector` (`\Drupal\webpatches\PatchesCollectorInterface`), which exposes `getSources()`, `getPatches()`, `getIgnoredPatches()` and `getProjectRoot()`.

## Data model changes

New config object `webpatches.settings` with `sources`, `custom_file_path` and `only_installed_packages`.

## Release notes snippet

Web Patches no longer carries its own patch list. It now reports the patches and the ignored patches declared for the site, at Reports → Web Patches, with the sources configurable at Configuration → Development → Web Patches.

## Checkpoints

- [x] File an issue about this project
- [x] Addition/Change/Update/Fix to this project
- [x] Testing to ensure no regression
- [x] Automated unit/functional testing coverage
- [x] Developer Documentation support on feature change/addition
- [ ] User Guide Documentation support on feature change/addition
- [ ] Accessibility and Readability
- [ ] Code review by maintainers
- [ ] Full testing and approval
- [ ] Credit contributors
- [ ] Review with the product owner
- [ ] Update Release Notes
- [ ] Release
