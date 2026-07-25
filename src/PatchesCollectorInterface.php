<?php

namespace Drupal\webpatches;

/**
 * Collects the patches and the ignored patches declared for this site.
 */
interface PatchesCollectorInterface {

  /**
   * Source id for the root composer.json of the project.
   */
  const SOURCE_ROOT = 'root_composer';

  /**
   * Source id for the patches file referenced by extra.patches-file.
   */
  const SOURCE_PATCHES_FILE = 'patches_composer';

  /**
   * Source id for the extra patches file configured in the module settings.
   */
  const SOURCE_CUSTOM = 'custom_file';

  /**
   * Source id for the patches contributed by installed dependency packages.
   */
  const SOURCE_DEPENDENCIES = 'dependency_packages';

  /**
   * Returns the sources the module reads patches from.
   *
   * @return array[]
   *   A list of source descriptors, keyed by source id, each with:
   *   - id: The source id.
   *   - label: The human readable label.
   *   - path: The absolute file path, or NULL when the source is not a file.
   *   - enabled: Whether the source is enabled in the module settings.
   *   - found: Whether the source could be read.
   */
  public function getSources(): array;

  /**
   * Returns the patches declared for this site.
   *
   * @return array[]
   *   A list of patches, each with:
   *   - package: The package the patch applies to.
   *   - description: The patch description.
   *   - url: The patch URL or relative file path.
   *   - source: The source id the patch was declared in.
   *   - provider: The package that declared the patch, for dependency patches.
   *   - installed: Whether the patched package is installed on this site.
   */
  public function getPatches(): array;

  /**
   * Returns the patches that are declared but filtered out.
   *
   * @return array[]
   *   A list of ignored patches, each with:
   *   - package: The package the patch would apply to.
   *   - description: The patch description, or NULL when the whole provider
   *     is filtered out.
   *   - url: The patch URL, or NULL when the whole provider is filtered out.
   *   - provider: The package that declared the patch.
   *   - reason: A human readable reason the patch is not applied.
   *   - installed: Whether the patched package is installed on this site.
   */
  public function getIgnoredPatches(): array;

  /**
   * Returns the installed packages that declare patches.
   *
   * These are the dependency patching sources Composer Patches resolves,
   * each with the verdict of the allowlist and the ignore rules.
   *
   * @return array[]
   *   A list of providers, each with:
   *   - package: The declaring package.
   *   - version: The installed version.
   *   - count: How many patches it declares.
   *   - allowed: Whether its patches are applied.
   *   - reason: Why it is allowed or not.
   */
  public function getPatchProviders(): array;

  /**
   * Returns the absolute path of the Composer project root.
   *
   * @return string|null
   *   The project root, or NULL when it could not be located.
   */
  public function getProjectRoot(): ?string;

}
