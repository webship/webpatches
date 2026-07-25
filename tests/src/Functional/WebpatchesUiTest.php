<?php

namespace Drupal\Tests\webpatches\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Web Patches report and settings pages.
 *
 * @group webpatches
 */
class WebpatchesUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['webpatches', 'help'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the report is protected by its permission.
   */
  public function testReportAccess(): void {
    $this->drupalGet('admin/reports/webpatches');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['view webpatches report']));
    $this->drupalGet('admin/reports/webpatches');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Patches');
    $this->assertSession()->pageTextContains('Ignored patches');
    $this->assertSession()->pageTextContains('Patching sources');
    $this->assertSession()->pageTextContains('patches.lock.json');
  }

  /**
   * Tests that the help page renders through the OOP hook.
   */
  public function testHelpPage(): void {
    $this->drupalLogin($this->drupalCreateUser(['access help pages']));
    $this->drupalGet('admin/help/webpatches');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Web Patches shows the patches and the ignored patches declared for this site.');
  }

  /**
   * Tests that the settings form saves its values.
   */
  public function testSettingsForm(): void {
    $this->drupalGet('admin/config/development/webpatches');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['administer webpatches']));
    $this->drupalGet('admin/config/development/webpatches');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'sources[root_composer]' => TRUE,
      'sources[patches_composer]' => FALSE,
      'sources[custom_file]' => FALSE,
      'sources[dependency_packages]' => TRUE,
      'only_installed_packages' => FALSE,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $config = $this->config('webpatches.settings');
    $this->assertTrue($config->get('sources.root_composer'));
    $this->assertFalse($config->get('sources.patches_composer'));
    $this->assertFalse($config->get('only_installed_packages'));
  }

  /**
   * Tests that a non-existing custom patches file is rejected.
   */
  public function testCustomFilePathValidation(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer webpatches']));
    $this->drupalGet('admin/config/development/webpatches');
    $this->submitForm([
      'sources[custom_file]' => TRUE,
      'custom_file_path' => 'this-file-does-not-exist.json',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('does not exist');
  }

}
