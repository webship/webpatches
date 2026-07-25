'use strict';

// Drupal authentication helpers for the webshipprep DDEV site.
//
// The Web Patches pages are admin-only, and this site has no known password
// for user 1, so the session is established with a one-time login link
// produced by `ddev drush uli`. Those links are single use: once a link has
// logged a user in, the user's `login` timestamp changes and the hash in the
// link stops validating. Rather than burning one link per scenario (a `ddev
// drush` round trip costs about a second), the first scenario of a run walks
// through a fresh link and the resulting cookies are cached in module scope,
// then replayed into every later scenario's browser context.

const { Given, AfterAll } = require('@cucumber/cucumber');
const { execFileSync } = require('child_process');
const path = require('path');
const assert = require('assert');

// The DDEV project this suite tests. tests/webship-js lives inside it.
const SITE_ROOT = path.resolve(__dirname, '../../..');

let cachedCookies = null;

/**
 * Returns a fresh one-time login URL for user 1.
 *
 * @param {string} uri
 *   The absolute site URI to build the link against.
 *
 * @return {string}
 *   The one-time login URL.
 */
function oneTimeLoginUrl(uri) {
  const output = execFileSync(
    'ddev',
    ['drush', 'uli', `--uri=${uri}`],
    { cwd: SITE_ROOT, encoding: 'utf8', timeout: 120000 }
  );
  const url = output.trim().split('\n').pop().trim();
  if (!/^https?:\/\//.test(url)) {
    throw new Error(`ddev drush uli did not return a login URL, got: ${output}`);
  }
  return url;
}

/**
 * Establish an authenticated Drupal session for the current scenario.
 *
 * Example #1: Given I am logged in to Drupal as the administrator
 * Example #2: Given I am logged in to Drupal as the administrator
 *               And I am on "/admin/reports/webpatches"
 * Example #3: Given we are logged in to Drupal as the administrator
 * Example #4: Given I am logged in to Drupal as the administrator
 *               When I go to "/admin/config/development/webpatches"
 * Example #5: Given I am logged in to Drupal as the administrator
 *               Then I should see "Web Patches"
 *
 */
Given(/^(I |we )*am logged in to Drupal as the administrator$/, async function (pronoun) {
  if (cachedCookies) {
    await this.context.addCookies(cachedCookies);
    return;
  }
  const url = oneTimeLoginUrl(this.launchUrl);
  await this.page.goto(url, { waitUntil: 'domcontentloaded' });
  const state = await this.context.storageState();
  cachedCookies = state.cookies;
  assert.ok(
    cachedCookies.some((cookie) => /^S?SESS/.test(cookie.name)),
    'Logging in with the one-time link did not produce a Drupal session cookie.'
  );
});

/**
 * Writes the site defaults into webpatches.settings.
 */
function resetWebpatchesSettings() {
  const php = "\\Drupal::configFactory()->getEditable('webpatches.settings')"
    + "->set('sources', ['root_composer' => TRUE, 'patches_composer' => TRUE, 'custom_file' => FALSE, 'dependency_packages' => TRUE])"
    + "->set('custom_file_path', '')"
    + "->set('only_installed_packages', TRUE)"
    + '->save();';
  execFileSync(
    'ddev',
    ['drush', 'php:eval', php],
    { cwd: SITE_ROOT, encoding: 'utf8', timeout: 120000 }
  );
}

/**
 * Reset the Web Patches configuration to the site defaults.
 *
 * Scenarios that submit the settings form change stored configuration, so
 * every settings scenario starts from this known state instead of depending
 * on whatever the previous scenario left behind.
 *
 * Example #1: Given the Web Patches settings are reset to the site defaults
 * Example #2: Given the Web Patches settings are reset to the site defaults
 *               And I am logged in to Drupal as the administrator
 * Example #3: Given we reset the Web Patches settings to the site defaults
 * Example #4: Given the Web Patches settings are reset to the site defaults
 *               When I go to "/admin/config/development/webpatches"
 * Example #5: Given the Web Patches settings are reset to the site defaults
 *               Then I should see "Web Patches"
 *
 */
Given(/^(?:(?:I |we )*reset the|the) Web Patches settings (?:are )?(?:to |reset to )?the site defaults$/, function () {
  resetWebpatchesSettings();
});

/**
 * Leave the site's Web Patches configuration as the suite found it.
 *
 * Settings scenarios save real configuration, so the run resets it once at
 * the end rather than leaving the site in whatever state the last scenario
 * happened to save.
 */
AfterAll(function () {
  resetWebpatchesSettings();
});
