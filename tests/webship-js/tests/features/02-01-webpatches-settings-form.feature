# REASONS canvas
#
# Requirements  The settings form chooses which declarations the report reads. A
#               site owner must be able to turn each source on or off, point at a
#               custom patches file, and be stopped before saving a path that
#               does not exist. Done when the form saves, when the custom path
#               field only appears with its source, and when a bad path is
#               refused with a message that names the file.
# Entities      Source checkbox (root composer.json, patches file, custom patches
#               file, installed dependency packages), custom patches file path,
#               installed-packages filter, configuration webpatches.settings.
# Approach      Drive the real form: assert the fields, the conditional field,
#               a successful save and a failing validation.
# Structure     WebpatchesSettingsForm::buildForm, validateForm, submitForm;
#               route webpatches.settings at /admin/config/development/webpatches.
# Operations    Open the form. Save it. Reveal the custom path field. Save a
#               non-existent path. Turn the installed-packages filter off and see
#               the report note disappear.
# Norms         The custom path field is bound to its checkbox with #states, so
#               it is hidden until the source that uses it is enabled.
# Safeguards    A custom patches file path is only accepted when the file is
#               really there, so the report can never claim to have read a file
#               that does not exist.

@webpatches @local
Feature: Web Patches settings form
  As a site owner
  I want to choose which patch declarations the report reads
  So that the report matches how this project actually declares its patches

  Background:
    Given the Web Patches settings are reset to the site defaults
      And I am logged in to Drupal as the administrator
     When I go to "/admin/config/development/webpatches"

  @critical
  Scenario: The form offers every patch declaration source
    Then I should see "Patch declaration sources"
     And I should see "Root composer.json"
     And I should see "Patches file"
     And I should see "Custom patches file"
     And I should see "Installed dependency packages"
     And I should see "Only list patches for packages installed on this site"

  @critical
  Scenario: The form opens with the sources this site declares patches through
    Then the checkbox "#edit-sources-root-composer" should be checked
     And the checkbox "#edit-sources-patches-composer" should be checked
     And the checkbox "#edit-sources-dependency-packages" should be checked
     And the checkbox "#edit-sources-custom-file" should not be checked
     And the checkbox "#edit-only-installed-packages" should be checked

  @critical
  Scenario: The custom file path is asked for only when a custom file is used
    Then "#edit-custom-file-path" should not be visible
    When I check the checkbox "#edit-sources-custom-file"
    Then "#edit-custom-file-path" should be visible within 5 seconds
    When I uncheck the checkbox "#edit-sources-custom-file"
    Then "#edit-custom-file-path" should not be visible

  @critical
  Scenario: Saving the settings confirms that they were stored
    When I click the "Save configuration" button
    Then I should see "The configuration options have been saved."

  @critical @security
  Scenario: A custom patches file that is not there is refused
    When I check the checkbox "#edit-sources-custom-file"
     And I fill in the field "#edit-custom-file-path" with "no-such-patches-file.json"
     And I click the "Save configuration" button
    Then I should see "does not exist"
     And I should see "no-such-patches-file.json"
     And I should not see "The configuration options have been saved."

  Scenario: A custom patches file path is only validated when the custom source is on
    When I check the checkbox "#edit-sources-custom-file"
     And I fill in the field "#edit-custom-file-path" with "no-such-patches-file.json"
     And I uncheck the checkbox "#edit-sources-custom-file"
     And I click the "Save configuration" button
    Then I should see "The configuration options have been saved."
     And I should not see "does not exist"

  @critical
  Scenario: Turning off the installed-packages filter removes the note from the report
    When I uncheck the checkbox "#edit-only-installed-packages"
     And I click the "Save configuration" button
    Then I should see "The configuration options have been saved."
    When I go to "/admin/reports/webpatches"
    Then I should not see "Only patches for packages installed on this site are listed."

  Scenario: Turning off a declaration source drops the patches it contributed
    When I uncheck the checkbox "#edit-sources-dependency-packages"
     And I click the "Save configuration" button
    Then I should see "The configuration options have been saved."
    When I go to "/admin/reports/webpatches"
    Then the "Patches" table should be empty and say "No patches are declared for this site."
     And the "Patches" section title should count the rows of its table
