# REASONS canvas
#
# Requirements  The Patches table is the report. A site owner reading it must be
#               able to reach, in one click, the project being patched, the
#               drupal.org issue the patch belongs to, the patch file itself and
#               the merge request the file was taken from. Done when every one of
#               those links is present and correct for every row, derived from
#               the data rather than typed by hand.
# Entities      Patch, patched package, drupal.org project, drupal.org issue,
#               patch file, merge request, declaring package.
# Approach      Read the whole table once, then assert the RULE behind each kind
#               of link across every row, so the scenarios keep passing when the
#               patch set changes and start failing when the rule breaks.
# Structure     PatchesController::buildPatchesTable, buildPackageCell,
#               buildPatchCell, buildDescription, buildPatchLink; PatchLinks
#               holds the derivations.
# Operations    Open the report. Read the "Patches" section title and table.
#               Check the package links, the issue links, the patch file links
#               and the merge request links.
# Norms         The patch file lives under its own description rather than in a
#               separate column, so a long file name never widens the table.
#               Every outbound link opens in a new tab with rel="noopener".
# Safeguards    A description is rendered as plain text around its issue link, so
#               a patch description out of a composer.json can never inject
#               markup into an admin page.

@webpatches @local
Feature: Web Patches report patches table
  As a site owner
  I want each declared patch to link to its project, issue, file and merge request
  So that I can review what a patch changes without hunting for it

  Background:
    Given the Web Patches settings are reset to the site defaults
      And I am logged in to Drupal as the administrator
     When I go to "/admin/reports/webpatches"

  @critical
  Scenario: The patches are listed as the main content of the report
    Then the "Patches" section should be open
     And the "Patches" section title should count the rows of its table
     And the "Patches" table should have the columns:
      | Package | Patch | Declared in |

  @critical
  Scenario: The patch file sits under its description instead of in its own column
    Then the "Patches" table should not have a "File" column
     And the "Patches" table should not have a "Patch file" column
     And every patch file in the "Patches" table should link to the file it names

  @critical
  Scenario: Every patched package links to the page of the project it patches
    Then every package in the "Patches" table should link to its project page

  @critical
  Scenario: Drupal core is linked as the drupal project rather than as drupal/core
    Then the "Patches" table should have the rows:
      | drupal/core |  |  |
     And the "Patches" table should contain the link "drupal/core" pointing to "https://www.drupal.org/project/drupal"

  @critical
  Scenario: Every issue reference in a patch description links to that issue
    Then every issue reference in the "Patches" table should link to its drupal.org issue

  @critical
  Scenario: A patch taken from a merge request links to that merge request
    Then every merge request patch in the "Patches" table should link to its merge request

  @security
  Scenario: Every outbound link in the patches table opens safely in a new tab
    Then every link in the "Patches" table should open in a new tab

  Scenario: Each patch names the package that declared it
    Then the "Patches" table should have the rows:
      |  |  | webship/drupal-patches |
     And the "Patches" table should have the rows:
      |  |  | webship/patches |
