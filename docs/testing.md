# Testing

Three suites cover the module. All commands assume a DDEV site with the
module at `web/modules/contrib/webpatches` and `drupal/core-dev` installed.

## Unit tests

The collector (sources, allowlist, ignore rules, the lock comparison, the
own-package exclusion) and the link derivation (issue ids, merge request ids,
project URLs) — no Drupal bootstrap needed:

```bash
ddev exec ./bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/contrib/webpatches/tests/src/Unit
```

## Functional tests

The report and settings routes with their permissions, the settings form save
and validation, and the help page rendered through the attribute-discovered
OOP hook:

```bash
ddev exec 'SIMPLETEST_BASE_URL=http://web SIMPLETEST_DB=mysql://db:db@db/db \
  ./bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/contrib/webpatches/tests/src/Functional'
```

## The webship-js BDD suite

[`tests/webship-js/`](https://git.drupalcode.org/project/webpatches/-/tree/11.0.x/tests/webship-js)
is a [webship-js](https://github.com/webship/webship-js) (Playwright +
Cucumber-js) suite that drives a **live site** in a real browser: access
control, the patching sources tables, the link rules of the Patches table,
the ignored patches table, and the settings form with its validation.

```bash
cd tests/webship-js
npm install
LAUNCH_URL=https://your-site.ddev.site npm test
```

The Drupal session comes from `ddev drush uli`, walked once per run and
replayed from cached cookies. `npm run test:critical` runs the `@critical`
subset; the HTML report lands in `tests/reports/`.

## Coding standards

```bash
ddev exec ./bin/phpcs --standard=web/modules/contrib/webpatches/.phpcs.xml \
  --extensions=php,module,yml,install \
  web/modules/contrib/webpatches/src web/modules/contrib/webpatches/tests
```
