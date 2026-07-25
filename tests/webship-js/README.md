# Web Patches functional test suite (webship-js)

BDD acceptance tests for the **Web Patches** admin report of the
`drupal/webpatches` module, run against this DDEV site with
[webship-js](https://webship.co/docs/webship-js/2.0.x) (Playwright + Cucumber-js).

## Pages under test

| Page                                    | Permission                |
|-----------------------------------------|---------------------------|
| `/admin/reports/webpatches`             | `view webpatches report`  |
| `/admin/config/development/webpatches`  | `administer webpatches`   |

## Running

```bash
cd tests/webship-js
npm install                 # first time only
npx playwright install chromium

npm test                    # whole suite
npm run test:critical       # the @critical smoke lane
npm run test:headed         # watch it in a real window
npm run generate-reports    # HTML report in tests/reports/

LAUNCH_URL=https://other.ddev.site npm test    # another site
```

Both pages are admin only. The suite has no password for user 1, so the step
`Given I am logged in to Drupal as the administrator` shells out to
`ddev drush uli` for a one-time login link, walks it once per run, and replays
the resulting session cookies into every later scenario. Because of that,
`ddev` must be on `PATH` and this DDEV project must be running.

Scenarios that touch `webpatches.settings` start from
`Given the Web Patches settings are reset to the site defaults`, which writes
the defaults with `ddev drush php:eval`, and the run resets them again when it
finishes. No scenario depends on what another scenario left behind.

## Layout

```
tests/features/
  01-01-webpatches-report-access.feature    access control + page shell
  01-02-webpatches-report-sources.feature   "Patching sources": declaration files + providers
  01-03-webpatches-report-patches.feature   "Patches": columns and every kind of link
  01-04-webpatches-report-ignored.feature   "Ignored patches" + the filter note
  02-01-webpatches-settings-form.feature    the settings form: fields, save, validation
tests/step-definitions/
  drupal-auth.js        login through `drush uli`, configuration reset
  webpatches-report.js  reads a report table by its heading and asserts its rules
```

## Tags

| Tag         | Meaning                                              |
|-------------|------------------------------------------------------|
| `@critical` | Smoke set, run on every change.                      |
| `@security` | Asserts a Safeguard: access control, safe links.     |
| `@a11y`     | Page structure for assistive technology.             |
| `@webpatches` | Everything in this suite.                          |
| `@local`    | Safe against a local DDEV site.                      |

## Why the assertions are written as rules

The patch set of this site changes every time `webship/patches` or
`webship/drupal-patches` is updated, so pinning URLs would make the suite go
red on every dependency bump for no reason. Instead the steps assert the rule
behind each link across every row of a table: a `drupal/*` package links to its
drupal.org project (with `drupal/core` mapping to the `drupal` project), a
`#NNNNNNN` reference links to that node, a `--mr-<id>` patch file links to that
merge request, and a patch file link names the file it points at. The suite
fails when the derivation breaks, not when the data moves.
