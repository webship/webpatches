# REASONS canvas
#
# Requirements  The Web Patches report tells a site owner what Composer has been
#               told to patch on this site. It is an administrative report and
#               must never leak the dependency and patch inventory of the site to
#               a visitor. Done when the report renders for a user holding "view
#               webpatches report" and is refused for everybody else.
# Entities      Report page, administrator, anonymous visitor, permission
#               "view webpatches report".
# Approach      Drive the real page in a browser: an authenticated administrator
#               sees the report shell, an anonymous visitor is refused.
# Structure     Route webpatches.list at /admin/reports/webpatches, served by
#               PatchesController::listPatches, marked _admin_route.
# Operations    Open the report as an administrator. Open it anonymously.
# Norms         Page has a title; the report explains itself before listing data.
# Safeguards    Anonymous access is refused, not merely empty.

@webpatches @local
Feature: Web Patches report access
  As a site owner
  I want the Web Patches report to be an administrative page
  So that the patch inventory of my site is not public

  @critical
  Scenario: An administrator can open the Web Patches report
    Given I am logged in to Drupal as the administrator
    When I go to "/admin/reports/webpatches"
    Then I should be on "/admin/reports/webpatches"
     And I should see "Web Patches"
     And I should see "Patches are applied by Composer, not by this module."
     And there should be no JavaScript errors

  @critical @security
  Scenario: An anonymous visitor is refused the Web Patches report
    Given I am an anonymous user
    When I go to "/admin/reports/webpatches"
    Then I should see "Access denied"
     And I should not see "Patching sources"

  @critical @security
  Scenario: An anonymous visitor is refused the Web Patches settings form
    Given I am an anonymous user
    When I go to "/admin/config/development/webpatches"
    Then I should see "Access denied"
     And I should not see "Patch declaration sources"

  @a11y
  Scenario: The Web Patches report is structured for assistive technology
    Given I am logged in to Drupal as the administrator
    When I go to "/admin/reports/webpatches"
    Then the page should have a title
     And the page should have exactly one h1
