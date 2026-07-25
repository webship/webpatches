<?php

namespace Drupal\webpatches;

/**
 * Derives the links shown next to a patch on the Web Patches report.
 *
 * Everything here is derived from strings that come out of a composer.json,
 * so each method returns NULL rather than guessing when the input does not
 * carry enough information.
 */
class PatchLinks {

  /**
   * Matches a drupal.org issue reference in a patch description.
   *
   * Node ids on drupal.org are six to eight digits. Requiring the leading "#"
   * keeps comment counts, patch revisions and version numbers out of it.
   */
  const ISSUE_PATTERN = '/#(\d{6,8})\b/';

  /**
   * Matches the merge request id in a patch file name.
   *
   * The Webship convention is
   * "<package>--<date>--<issue>--mr-<id>.patch".
   */
  const MERGE_REQUEST_PATTERN = '/--mr-(\d+)\b/';

  /**
   * Matches a valid Composer package name.
   *
   * This is Composer's own package name pattern. Names come out of files on
   * disk, so anything that does not match it is refused rather than being
   * interpolated into a URL.
   */
  const PACKAGE_PATTERN = '{^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]?|-{0,2})[a-z0-9]+)*$}D';

  /**
   * Returns the drupal.org issue id referenced by a patch description.
   *
   * @param string $description
   *   The patch description.
   *
   * @return string|null
   *   The issue id, or NULL when the description carries none.
   */
  public static function issueId(string $description): ?string {
    return preg_match(self::ISSUE_PATTERN, $description, $matches) ? $matches[1] : NULL;
  }

  /**
   * Returns the drupal.org URL of the issue referenced by a description.
   *
   * @param string $description
   *   The patch description.
   *
   * @return string|null
   *   The issue URL, or NULL when the description carries no issue id.
   */
  public static function issueUrl(string $description): ?string {
    $id = self::issueId($description);
    return $id === NULL ? NULL : 'https://www.drupal.org/node/' . $id;
  }

  /**
   * Returns the merge request id carried by a patch file name.
   *
   * @param string $url
   *   The patch URL or path.
   *
   * @return string|null
   *   The merge request id, or NULL when the file name carries none.
   */
  public static function mergeRequestId(string $url): ?string {
    return preg_match(self::MERGE_REQUEST_PATTERN, basename($url), $matches) ? $matches[1] : NULL;
  }

  /**
   * Returns the git.drupalcode.org URL of a patch's merge request.
   *
   * The project is taken from the patched package rather than from the file
   * name, because the file name is only a convention while the package name
   * is what Composer actually resolved.
   *
   * @param string $package
   *   The patched package, for example "drupal/redirect".
   * @param string $url
   *   The patch URL or path.
   *
   * @return string|null
   *   The merge request URL, or NULL when either part cannot be derived.
   */
  public static function mergeRequestUrl(string $package, string $url): ?string {
    $id = self::mergeRequestId($url);
    $project = self::drupalProject($package);
    if ($id === NULL || $project === NULL) {
      return NULL;
    }
    return 'https://git.drupalcode.org/project/' . $project . '/-/merge_requests/' . $id;
  }

  /**
   * Returns the drupal.org project machine name of a package.
   *
   * @param string $package
   *   The Composer package name.
   *
   * @return string|null
   *   The project machine name, or NULL for a package that is not a
   *   drupal.org project.
   */
  public static function drupalProject(string $package): ?string {
    if (!preg_match(self::PACKAGE_PATTERN, $package) || !str_starts_with($package, 'drupal/')) {
      return NULL;
    }
    $name = substr($package, strlen('drupal/'));
    // Drupal core is published as drupal/core but its project is "drupal".
    return $name === 'core' ? 'drupal' : $name;
  }

  /**
   * Returns the project or package page of a patched package.
   *
   * Drupal.org for a Drupal project, Packagist for anything else, so every
   * package on the report is clickable.
   *
   * @param string $package
   *   The Composer package name.
   *
   * @return string|null
   *   The package URL, or NULL when the name is not a vendor/name pair.
   */
  public static function packageUrl(string $package): ?string {
    if (!preg_match(self::PACKAGE_PATTERN, $package)) {
      return NULL;
    }
    $project = self::drupalProject($package);
    if ($project !== NULL) {
      return 'https://www.drupal.org/project/' . $project;
    }
    return 'https://packagist.org/packages/' . $package;
  }

}
