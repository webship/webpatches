# REASONS canvas
#
# Requirements  A patch that was declared but will not be applied is the single
#               most confusing thing about Composer patching. The report must
#               show those separately, and must say who declared each one and why
#               it was dropped. Done when the ignored patches are listed with
#               their declaring package and the reason.
# Entities      Ignored patch, declaring package, ignore reason, allowlist.
# Approach      Read the "Ignored patches" section and assert its shape, its
#               count and the reason text of its rows.
# Structure     PatchesController::buildIgnoredTable, inside an open <details>
#               titled "Ignored patches (N)".
# Operations    Open the report. Read the "Ignored patches" table. Read the note
#               under the report.
# Norms         The section is open, because a silently ignored patch is exactly
#               what the reader came for. The note explains the filter that is in
#               force and links to where it can be changed.
# Safeguards    When nothing is ignored the section still renders and says so,
#               rather than disappearing and leaving the reader to guess whether
#               the check ran at all.

@webpatches @local
Feature: Web Patches report ignored patches
  As a site owner
  I want the patches that were declared but dropped to be listed with a reason
  So that I am never surprised by a patch that Composer silently did not apply

  Background:
    Given the Web Patches settings are reset to the site defaults
      And I am logged in to Drupal as the administrator
     When I go to "/admin/reports/webpatches"

  @critical
  Scenario: The ignored patches are shown, not hidden
    Then the "Ignored patches" section should be open
     And the "Ignored patches" section title should count the rows of its table
     And the "Ignored patches" table should have the columns:
      | Package | Patch | Declared by | Reason |

  @critical
  Scenario: Nothing is ignored when every declaring package is on the allowlist
    Then the "Packages declaring patches" table should not mention "Not allowed"
     And the "Ignored patches" table should be empty and say "No declared patch is ignored on this site."

  @critical
  Scenario: The report keeps its own package out of both tables
    Then the "Packages declaring patches" table should not mention "drupal/webpatches"
     And the "Ignored patches" table should not mention "drupal/webpatches"

  @critical
  Scenario: The report says it only lists patches for installed packages
    Then I should see "Only patches for packages installed on this site are listed."
     And the "Web Patches settings" link should contain "/admin/config/development/webpatches"

  Scenario: The note leads to the settings form
    When I follow "Web Patches settings"
    Then I should be on "/admin/config/development/webpatches"
     And I should see "Patch declaration sources"
