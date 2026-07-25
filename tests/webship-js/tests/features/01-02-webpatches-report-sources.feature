# REASONS canvas
#
# Requirements  Before trusting the patch list, a site owner needs to know where
#               the list came from: which declaration files were read, and which
#               installed packages were allowed to contribute patches. Done when
#               the report names every declaration file with its status, and
#               names every package that declares patches with the reason it is
#               allowed or not.
# Entities      Declaration file (root composer.json, patches file, custom
#               patches file), patch provider package, allowlist
#               (extra.composer-patches.allowed-dependency-patches).
# Approach      Read the two tables of the "Patching sources" section by their
#               headings and assert their shape, their statuses and their order.
# Structure     PatchesController::buildSourcesTable and buildProvidersTable,
#               inside a collapsed <details> titled "Patching sources".
# Operations    Open the report. Read the "Declaration files" table. Read the
#               "Packages declaring patches" table.
# Norms         Sources are secondary detail, so the section starts collapsed.
#               The module keeps its own package out of the report, so a reader
#               is never told that the reporting module patches anything.
# Safeguards    The lock file is not a declaration file and must never be shown
#               as one: every patch it contributes already names its declaring
#               package in the Patches table, so a "composer.lock" row would be
#               a second, contradictory answer to "where did this come from".

@webpatches @local
Feature: Web Patches report patching sources
  As a site owner
  I want the report to name where each patch declaration was read from
  So that I can tell a declared patch from an ignored one and know why

  Background:
    Given the Web Patches settings are reset to the site defaults
      And I am logged in to Drupal as the administrator
     When I go to "/admin/reports/webpatches"

  @critical
  Scenario: The patching sources are secondary detail and start collapsed
    Then the "Patching sources" section should be collapsed
     And the "Patching sources" section should hold 2 tables

  @critical
  Scenario: The declaration files are listed with the status of each file
    Then the "Declaration files" table should have the columns:
      | Source | File | Status |
     And the "Declaration files" table should have 3 rows
     And the "Declaration files" table should have the rows:
      | Root composer.json  | /var/www/html/composer.json | Read      |
      | Patches file        | /var/www/html/patches.json  | Not found |
      | Custom patches file | n/a                         | Disabled  |

  @critical
  Scenario: The lock file is not listed as a declaration file
    Then the "Declaration files" table should not mention "composer.lock"
     And the "Declaration files" table should not mention "Installed dependency packages"
     And the "Declaration files" table should have 3 rows

  @critical
  Scenario: Every package that declares patches is listed with its verdict
    Then the "Packages declaring patches" table should have the columns:
      | Package | Version | Patches | Status | Reason |
     And the "Packages declaring patches" table should have the rows:
      | webship/drupal-patches |  |  | Allowed | In extra.composer-patches.allowed-dependency-patches. |
      | webship/patches        |  |  | Allowed | In extra.composer-patches.allowed-dependency-patches. |

  @critical
  Scenario: The report does not count itself as a patch provider
    Then the "Packages declaring patches" table should not mention "drupal/webpatches"
     And the "Packages declaring patches" table should have 2 rows

  Scenario: Every package that declares patches links to its project page
    Then every package in the "Packages declaring patches" table should link to its project page
     And every link in the "Packages declaring patches" table should open in a new tab
