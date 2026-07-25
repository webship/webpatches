<?php

namespace Drupal\webpatches;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Reads the patches and the ignored patches declared for this site.
 *
 * The module never applies patches itself. Composer does that, through
 * cweagans/composer-patches and the webship/patches Composer plugin.
 * This service reads the same declarations Composer reads, and applies the
 * same allowlist and ignore rules, so the site owner can see which patches
 * are declared and which of them are filtered out.
 */
class PatchesCollector implements PatchesCollectorInterface {

  use StringTranslationTrait;

  /**
   * Packages whose extra.patches are applied when no allowlist is configured.
   *
   * Mirrors the default of the webship/patches Composer plugin.
   */
  const DEFAULT_ALLOWED_DEPENDENCY_PATCHES = [
    'webship/patches',
    'webship/drupal-patches',
  ];

  /**
   * The app root.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The resolved project root, or FALSE when it could not be located.
   *
   * @var string|false|null
   */
  protected $projectRoot;

  /**
   * The collected patches and ignored patches.
   *
   * @var array|null
   */
  protected $collected;

  /**
   * Constructs a PatchesCollector object.
   *
   * @param string $app_root
   *   The app root.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct($app_root, ConfigFactoryInterface $config_factory, TranslationInterface $string_translation) {
    $this->appRoot = $app_root;
    $this->configFactory = $config_factory;
    $this->setStringTranslation($string_translation);
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectRoot(): ?string {
    if ($this->projectRoot === NULL) {
      $this->projectRoot = FALSE;
      $candidate = $this->appRoot;
      // The docroot is usually a directory below the Composer project root,
      // but a project can also be installed with no separate docroot.
      for ($depth = 0; $depth < 4 && $candidate !== '' && $candidate !== '/'; $depth++) {
        if (is_file($candidate . '/composer.json')) {
          $this->projectRoot = $candidate;
          break;
        }
        $candidate = dirname($candidate);
      }
    }
    return $this->projectRoot ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    $config = $this->configFactory->get('webpatches.settings');
    $enabled = (array) $config->get('sources');
    $root = $this->getProjectRoot();

    $sources = [];
    $sources[self::SOURCE_ROOT] = [
      'id' => self::SOURCE_ROOT,
      'label' => $this->t('Root composer.json'),
      'path' => $root ? $root . '/composer.json' : NULL,
      'enabled' => !empty($enabled[self::SOURCE_ROOT]),
    ];
    $sources[self::SOURCE_PATCHES_FILE] = [
      'id' => self::SOURCE_PATCHES_FILE,
      'label' => $this->t('Patches file (patches.composer.json)'),
      'path' => $this->getPatchesFilePath(),
      'enabled' => !empty($enabled[self::SOURCE_PATCHES_FILE]),
    ];
    $sources[self::SOURCE_CUSTOM] = [
      'id' => self::SOURCE_CUSTOM,
      'label' => $this->t('Custom patches file'),
      'path' => $this->getCustomFilePath(),
      'enabled' => !empty($enabled[self::SOURCE_CUSTOM]),
    ];
    $sources[self::SOURCE_DEPENDENCIES] = [
      'id' => self::SOURCE_DEPENDENCIES,
      'label' => $this->t('Installed dependency packages'),
      'path' => $root ? $root . '/composer.lock' : NULL,
      'enabled' => !empty($enabled[self::SOURCE_DEPENDENCIES]),
    ];

    foreach ($sources as $id => $source) {
      $sources[$id]['found'] = !empty($source['path']) && is_file($source['path']);
    }
    return $sources;
  }

  /**
   * {@inheritdoc}
   */
  public function getPatches(): array {
    return $this->collect()['patches'];
  }

  /**
   * {@inheritdoc}
   */
  public function getIgnoredPatches(): array {
    return $this->collect()['ignored'];
  }

  /**
   * Collects the patches and the ignored patches.
   *
   * @return array
   *   An array with a "patches" and an "ignored" list.
   */
  protected function collect(): array {
    if ($this->collected !== NULL) {
      return $this->collected;
    }

    $config = $this->configFactory->get('webpatches.settings');
    $sources = $this->getSources();
    $root_extra = $this->readJson($sources[self::SOURCE_ROOT]['path'])['extra'] ?? [];
    $installed = $this->getInstalledPackages();
    $only_installed = (bool) $config->get('only_installed_packages');

    $patches = [];
    $ignored = [];

    // 1. The root composer.json.
    if ($sources[self::SOURCE_ROOT]['enabled']) {
      $this->addDeclaredPatches($patches, $root_extra['patches'] ?? [], self::SOURCE_ROOT, NULL);
    }

    // 2. The patches file referenced by extra.patches-file, which is
    //    conventionally patches.composer.json next to the root composer.json.
    if ($sources[self::SOURCE_PATCHES_FILE]['enabled'] && $sources[self::SOURCE_PATCHES_FILE]['found']) {
      $data = $this->readJson($sources[self::SOURCE_PATCHES_FILE]['path']);
      $this->addDeclaredPatches($patches, $data['patches'] ?? [], self::SOURCE_PATCHES_FILE, NULL);
    }

    // 3. The extra patches file configured in the module settings.
    if ($sources[self::SOURCE_CUSTOM]['enabled'] && $sources[self::SOURCE_CUSTOM]['found']) {
      $data = $this->readJson($sources[self::SOURCE_CUSTOM]['path']);
      // Accept both a bare {"patches": {...}} file and a composer.json shaped
      // file that carries the list under extra.patches.
      $declared = $data['patches'] ?? ($data['extra']['patches'] ?? []);
      $this->addDeclaredPatches($patches, $declared, self::SOURCE_CUSTOM, NULL);
    }

    // 4. The patches contributed by installed dependency packages, filtered
    //    the same way the webship/patches Composer plugin filters
    //    them.
    if ($sources[self::SOURCE_DEPENDENCIES]['enabled']) {
      $this->addDependencyPatches($patches, $ignored, $root_extra);
    }

    foreach ($patches as $delta => $patch) {
      $patches[$delta]['installed'] = isset($installed[$patch['package']]);
    }
    foreach ($ignored as $delta => $patch) {
      $ignored[$delta]['installed'] = $patch['package'] === NULL
        ? TRUE
        : isset($installed[$patch['package']]);
    }

    if ($only_installed) {
      $patches = array_values(array_filter($patches, fn(array $p) => $p['installed']));
      $ignored = array_values(array_filter($ignored, fn(array $p) => $p['installed']));
    }

    usort($patches, fn(array $a, array $b) => [$a['package'], $a['description']] <=> [$b['package'], $b['description']]);
    usort($ignored, fn(array $a, array $b) => [(string) $a['package'], (string) $a['description']] <=> [(string) $b['package'], (string) $b['description']]);

    $this->collected = ['patches' => $patches, 'ignored' => $ignored];
    return $this->collected;
  }

  /**
   * Flattens a Composer patches declaration into the patch list.
   *
   * @param array $patches
   *   The patch list to add to, by reference.
   * @param array $declared
   *   The declaration, keyed by package then by patch description.
   * @param string $source
   *   The source id the declaration came from.
   * @param string|null $provider
   *   The package that declared the patches, for dependency patches.
   */
  protected function addDeclaredPatches(array &$patches, array $declared, string $source, ?string $provider): void {
    foreach ($declared as $package => $package_patches) {
      if (!is_array($package_patches)) {
        continue;
      }
      foreach ($package_patches as $description => $url) {
        $patches[] = [
          'package' => (string) $package,
          'description' => (string) $description,
          'url' => (string) $url,
          'source' => $source,
          'provider' => $provider,
          'installed' => FALSE,
        ];
      }
    }
  }

  /**
   * Adds the dependency patches, and records the ones that are filtered out.
   *
   * @param array $patches
   *   The patch list to add to, by reference.
   * @param array $ignored
   *   The ignored patch list to add to, by reference.
   * @param array $root_extra
   *   The extra section of the root composer.json.
   */
  protected function addDependencyPatches(array &$patches, array &$ignored, array $root_extra): void {
    $composer_patches = $root_extra['composer-patches'] ?? [];
    $allowed = (array) ($composer_patches['allowed-dependency-patches'] ?? self::DEFAULT_ALLOWED_DEPENDENCY_PATCHES);
    $ignored_providers = (array) ($composer_patches['ignore-dependency-patches'] ?? []);
    $patches_ignore = (array) ($root_extra['patches-ignore'] ?? []);

    foreach ($this->getLockedPackages() as $package) {
      $name = $package['name'] ?? NULL;
      $declared = $package['extra']['patches'] ?? [];
      if ($name === NULL || !is_array($declared) || $declared === []) {
        continue;
      }

      if (!$this->matchesAny($name, $allowed)) {
        $ignored[] = [
          'package' => NULL,
          'description' => NULL,
          'url' => NULL,
          'provider' => $name,
          'reason' => $this->t('Not in extra.composer-patches.allowed-dependency-patches.'),
          'installed' => TRUE,
        ];
        continue;
      }

      if ($this->matchesAny($name, $ignored_providers)) {
        $ignored[] = [
          'package' => NULL,
          'description' => NULL,
          'url' => NULL,
          'provider' => $name,
          'reason' => $this->t('Matched by extra.composer-patches.ignore-dependency-patches.'),
          'installed' => TRUE,
        ];
        continue;
      }

      // extra.patches-ignore drops individual patch URLs of a provider.
      $ignored_urls = [];
      if (isset($patches_ignore[$name]) && is_array($patches_ignore[$name])) {
        foreach ($patches_ignore[$name] as $target => $urls) {
          $ignored_urls[$target] = is_array($urls) ? array_values($urls) : [(string) $urls];
        }
      }

      foreach ($declared as $target => $target_patches) {
        if (!is_array($target_patches)) {
          continue;
        }
        foreach ($target_patches as $description => $url) {
          $is_ignored = isset($ignored_urls[$target]) && in_array($url, $ignored_urls[$target], TRUE);
          $entry = [
            'package' => (string) $target,
            'description' => (string) $description,
            'url' => (string) $url,
            'source' => self::SOURCE_DEPENDENCIES,
            'provider' => $name,
            'installed' => FALSE,
          ];
          if ($is_ignored) {
            $entry['reason'] = $this->t('Listed in extra.patches-ignore.');
            $ignored[] = $entry;
          }
          else {
            $patches[] = $entry;
          }
        }
      }
    }
  }

  /**
   * Returns the packages of composer.lock.
   *
   * @return array[]
   *   The locked packages, including the dev packages.
   */
  protected function getLockedPackages(): array {
    $root = $this->getProjectRoot();
    if (!$root) {
      return [];
    }
    $lock = $this->readJson($root . '/composer.lock');
    if ($lock !== []) {
      return array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
    }
    // A site can be deployed without its composer.lock. Fall back to the
    // installed package list Composer writes into the vendor directory.
    $installed = $this->readJson($root . '/vendor/composer/installed.json');
    return $installed['packages'] ?? (is_array($installed) ? $installed : []);
  }

  /**
   * Returns the installed packages, keyed by package name.
   *
   * @return array
   *   The installed package versions, keyed by package name.
   */
  protected function getInstalledPackages(): array {
    $installed = [];
    foreach ($this->getLockedPackages() as $package) {
      if (!empty($package['name'])) {
        $installed[$package['name']] = $package['version'] ?? '';
      }
    }
    return $installed;
  }

  /**
   * Returns the path of the patches file declared by extra.patches-file.
   *
   * @return string|null
   *   The absolute path, or NULL when the project root is unknown.
   */
  protected function getPatchesFilePath(): ?string {
    $root = $this->getProjectRoot();
    if (!$root) {
      return NULL;
    }
    $extra = $this->readJson($root . '/composer.json')['extra'] ?? [];
    $declared = $extra['patches-file'] ?? 'patches.composer.json';
    return $this->resolvePath((string) $declared);
  }

  /**
   * Returns the path of the extra patches file from the module settings.
   *
   * @return string|null
   *   The absolute path, or NULL when no path is configured.
   */
  protected function getCustomFilePath(): ?string {
    $path = trim((string) $this->configFactory->get('webpatches.settings')->get('custom_file_path'));
    return $path === '' ? NULL : $this->resolvePath($path);
  }

  /**
   * Resolves a possibly relative path against the project root.
   *
   * @param string $path
   *   The path to resolve.
   *
   * @return string|null
   *   The absolute path, or NULL when the project root is unknown.
   */
  protected function resolvePath(string $path): ?string {
    if ($path !== '' && $path[0] === '/') {
      return $path;
    }
    $root = $this->getProjectRoot();
    return $root ? $root . '/' . ltrim($path, '/') : NULL;
  }

  /**
   * Reads and decodes a JSON file.
   *
   * @param string|null $path
   *   The absolute file path.
   *
   * @return array
   *   The decoded data, or an empty array when the file is missing or invalid.
   */
  protected function readJson(?string $path): array {
    if (!$path || !is_file($path) || !is_readable($path)) {
      return [];
    }
    $data = json_decode((string) file_get_contents($path), TRUE);
    return is_array($data) ? $data : [];
  }

  /**
   * Checks a package name against a list of fnmatch patterns.
   *
   * @param string $name
   *   The package name.
   * @param array $patterns
   *   The patterns to match against.
   *
   * @return bool
   *   TRUE when the name matches at least one pattern.
   */
  protected function matchesAny(string $name, array $patterns): bool {
    foreach ($patterns as $pattern) {
      // The Composer plugin matches these patterns with fnmatch(), so the
      // report has to use it too, or it would list a different set of
      // patches than the one Composer applies.
      // phpcs:ignore Drupal.Functions.DiscouragedFunctions.Discouraged
      if (fnmatch((string) $pattern, $name)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
