<?php

namespace Drupal\Tests\webpatches\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\webpatches\PatchLinks;

/**
 * Tests the links derived for a patch on the Web Patches report.
 *
 * @coversDefaultClass \Drupal\webpatches\PatchLinks
 *
 * @group webpatches
 */
class PatchLinksTest extends UnitTestCase {

  /**
   * Tests that a drupal.org issue reference is found in a description.
   *
   * @dataProvider providerIssue
   * @covers ::issueId
   * @covers ::issueUrl
   */
  public function testIssue(string $description, ?string $expected): void {
    $this->assertSame($expected, PatchLinks::issueId($description));
    $this->assertSame(
      $expected === NULL ? NULL : 'https://www.drupal.org/node/' . $expected,
      PatchLinks::issueUrl($description)
    );
  }

  /**
   * Data provider for testIssue().
   */
  public static function providerIssue(): array {
    return [
      'Issue prefix' => ['Issue #2879648: Redirects from aliased paths', '2879648'],
      'commit type prefix' => ['fix: #3562288 PHP 8.4 nullable parameters', '3562288'],
      'six digits' => ['Issue #123456: Something', '123456'],
      'no issue' => ['Fix a fatal error with no issue', NULL],
      'too short to be a node id' => ['Issue #123: Something', NULL],
      'a bare number is not an issue' => ['Fixes 2879648 somewhere', NULL],
    ];
  }

  /**
   * Tests that the merge request id is read from a patch file name.
   *
   * @dataProvider providerMergeRequest
   * @covers ::mergeRequestId
   * @covers ::mergeRequestUrl
   */
  public function testMergeRequest(string $package, string $url, ?string $id, ?string $expected): void {
    $this->assertSame($id, PatchLinks::mergeRequestId($url));
    $this->assertSame($expected, PatchLinks::mergeRequestUrl($package, $url));
  }

  /**
   * Data provider for testMergeRequest().
   */
  public static function providerMergeRequest(): array {
    return [
      'contrib module' => [
        'drupal/redirect',
        'https://raw.githubusercontent.com/webship/patches/refs/heads/patches/redirect--2026-07-05--2879648--mr-202.patch',
        '202',
        'https://git.drupalcode.org/project/redirect/-/merge_requests/202',
      ],
      'core maps to the drupal project' => [
        'drupal/core',
        'https://raw.githubusercontent.com/webship/drupal-patches/refs/heads/patches/drupal-core--2026-07-03--2701575--mr-16205.patch',
        '16205',
        'https://git.drupalcode.org/project/drupal/-/merge_requests/16205',
      ],
      'no merge request in the file name' => [
        'drupal/entity_clone',
        'https://www.drupal.org/files/issues/2023-10-17/3388460-7.patch',
        NULL,
        NULL,
      ],
      'not a drupal package' => [
        'openai-php/client',
        'https://example.com/openai-php--client--2025-12-30--mr-725.patch',
        '725',
        NULL,
      ],
    ];
  }

  /**
   * Tests the project and package page links.
   *
   * @dataProvider providerPackageUrl
   * @covers ::drupalProject
   * @covers ::packageUrl
   */
  public function testPackageUrl(string $package, ?string $project, ?string $expected): void {
    $this->assertSame($project, PatchLinks::drupalProject($package));
    $this->assertSame($expected, PatchLinks::packageUrl($package));
  }

  /**
   * Data provider for testPackageUrl().
   */
  public static function providerPackageUrl(): array {
    return [
      'contrib module' => [
        'drupal/redirect',
        'redirect',
        'https://www.drupal.org/project/redirect',
      ],
      'core' => [
        'drupal/core',
        'drupal',
        'https://www.drupal.org/project/drupal',
      ],
      'non drupal package falls back to packagist' => [
        'neilime/php-css-lint',
        NULL,
        'https://packagist.org/packages/neilime/php-css-lint',
      ],
      'not a vendor/name pair' => ['nonsense', NULL, NULL],
    ];
  }

}
