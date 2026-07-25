<?php

namespace Drupal\Tests\webpatches\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\webpatches\PatchesCollector;
use Drupal\webpatches\PatchesCollectorInterface;

/**
 * Tests that the collector reads and filters patch declarations.
 *
 * @coversDefaultClass \Drupal\webpatches\PatchesCollector
 *
 * @group webpatches
 */
class PatchesCollectorTest extends UnitTestCase {

  /**
   * The fixture project root.
   *
   * @var string
   */
  protected $fixtureRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fixtureRoot = sys_get_temp_dir() . '/webpatches-' . uniqid();
    mkdir($this->fixtureRoot);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach (glob($this->fixtureRoot . '/*') ?: [] as $file) {
      unlink($file);
    }
    if (is_dir($this->fixtureRoot)) {
      rmdir($this->fixtureRoot);
    }
    parent::tearDown();
  }

  /**
   * Writes a fixture file into the project root.
   *
   * @param string $name
   *   The file name.
   * @param array $data
   *   The data to encode.
   */
  protected function writeJson(string $name, array $data): void {
    file_put_contents($this->fixtureRoot . '/' . $name, json_encode($data));
  }

  /**
   * Builds a collector pointed at the fixture project root.
   *
   * @param array $settings
   *   The webpatches.settings values to override.
   *
   * @return \Drupal\webpatches\PatchesCollectorInterface
   *   The collector under test.
   */
  protected function collector(array $settings = []): PatchesCollectorInterface {
    $settings += [
      'sources' => [
        PatchesCollectorInterface::SOURCE_ROOT => TRUE,
        PatchesCollectorInterface::SOURCE_PATCHES_FILE => TRUE,
        PatchesCollectorInterface::SOURCE_CUSTOM => FALSE,
        PatchesCollectorInterface::SOURCE_DEPENDENCIES => TRUE,
      ],
      'custom_file_path' => '',
      'only_installed_packages' => FALSE,
    ];
    $config_factory = $this->getConfigFactoryStub(['webpatches.settings' => $settings]);
    return new TestPatchesCollector($this->fixtureRoot, $config_factory, $this->getStringTranslationStub());
  }

  /**
   * Tests that root and patches file declarations are both listed.
   *
   * @covers ::getPatches
   */
  public function testReadsRootAndPatchesFile(): void {
    $this->writeJson('composer.json', [
      'extra' => [
        'patches' => [
          'drupal/redirect' => ['Issue #1: A root patch' => 'https://example.com/root.patch'],
        ],
      ],
    ]);
    $this->writeJson('patches.composer.json', [
      'patches' => [
        'drupal/coffee' => ['Issue #2: A patches file patch' => 'https://example.com/file.patch'],
      ],
    ]);

    $patches = $this->collector()->getPatches();
    $this->assertCount(2, $patches);
    $this->assertSame('drupal/coffee', $patches[0]['package']);
    $this->assertSame(PatchesCollectorInterface::SOURCE_PATCHES_FILE, $patches[0]['source']);
    $this->assertSame('drupal/redirect', $patches[1]['package']);
    $this->assertSame(PatchesCollectorInterface::SOURCE_ROOT, $patches[1]['source']);
  }

  /**
   * Tests that only allowlisted dependency packages contribute patches.
   *
   * @covers ::getPatches
   * @covers ::getIgnoredPatches
   */
  public function testDependencyAllowlist(): void {
    $this->writeJson('composer.json', []);
    $this->writeJson('composer.lock', [
      'packages' => [
        [
          'name' => 'webship/patches',
          'version' => '11.0.29',
          'extra' => [
            'patches' => [
              'drupal/redirect' => ['Issue #3: An allowed patch' => 'https://example.com/allowed.patch'],
            ],
          ],
        ],
        [
          'name' => 'drupal/ai_context',
          'version' => '1.0.0',
          'extra' => [
            'patches' => [
              'drupal/canvas' => ['Issue #3567225: A patch AI Context declares itself' => 'https://example.com/canvas.patch'],
            ],
          ],
        ],
      ],
    ]);

    $collector = $this->collector();
    $patches = $collector->getPatches();
    $this->assertCount(1, $patches);
    $this->assertSame('drupal/redirect', $patches[0]['package']);
    $this->assertSame('webship/patches', $patches[0]['provider']);

    $ignored = $collector->getIgnoredPatches();
    $this->assertCount(1, $ignored);
    $this->assertSame('drupal/ai_context', $ignored[0]['provider']);
    $this->assertNull($ignored[0]['package']);
  }

  /**
   * Tests that extra.patches-ignore moves a single patch to the ignored list.
   *
   * @covers ::getIgnoredPatches
   */
  public function testPatchesIgnoreDropsASinglePatch(): void {
    $this->writeJson('composer.json', [
      'extra' => [
        'patches-ignore' => [
          'webship/patches' => [
            'drupal/redirect' => ['https://example.com/dropped.patch'],
          ],
        ],
      ],
    ]);
    $this->writeJson('composer.lock', [
      'packages' => [
        [
          'name' => 'webship/patches',
          'version' => '11.0.29',
          'extra' => [
            'patches' => [
              'drupal/redirect' => [
                'Issue #5: A kept patch' => 'https://example.com/kept.patch',
                'Issue #6: A dropped patch' => 'https://example.com/dropped.patch',
              ],
            ],
          ],
        ],
      ],
    ]);

    $collector = $this->collector();
    $patches = $collector->getPatches();
    $this->assertCount(1, $patches);
    $this->assertSame('https://example.com/kept.patch', $patches[0]['url']);

    $ignored = $collector->getIgnoredPatches();
    $this->assertCount(1, $ignored);
    $this->assertSame('https://example.com/dropped.patch', $ignored[0]['url']);
    $this->assertSame('drupal/redirect', $ignored[0]['package']);
  }

  /**
   * Tests that the report can be limited to installed packages.
   *
   * @covers ::getPatches
   */
  public function testOnlyInstalledPackages(): void {
    $this->writeJson('composer.json', [
      'extra' => [
        'patches' => [
          'drupal/redirect' => ['Issue #7: An installed package' => 'https://example.com/installed.patch'],
          'drupal/not_installed' => ['Issue #8: A missing package' => 'https://example.com/missing.patch'],
        ],
      ],
    ]);
    $this->writeJson('composer.lock', [
      'packages' => [
        ['name' => 'drupal/redirect', 'version' => '1.11.0'],
      ],
    ]);

    $all = $this->collector(['only_installed_packages' => FALSE])->getPatches();
    $this->assertCount(2, $all);

    $installed_only = $this->collector(['only_installed_packages' => TRUE])->getPatches();
    $this->assertCount(1, $installed_only);
    $this->assertSame('drupal/redirect', $installed_only[0]['package']);
  }

  /**
   * Tests that a custom patches file is read when it is enabled.
   *
   * @covers ::getPatches
   */
  public function testCustomPatchesFile(): void {
    $this->writeJson('composer.json', []);
    $this->writeJson('custom.patches.json', [
      'extra' => [
        'patches' => [
          'drupal/coffee' => ['Issue #9: A custom file patch' => 'https://example.com/custom.patch'],
        ],
      ],
    ]);

    $patches = $this->collector([
      'sources' => [
        PatchesCollectorInterface::SOURCE_ROOT => TRUE,
        PatchesCollectorInterface::SOURCE_PATCHES_FILE => TRUE,
        PatchesCollectorInterface::SOURCE_CUSTOM => TRUE,
        PatchesCollectorInterface::SOURCE_DEPENDENCIES => TRUE,
      ],
      'custom_file_path' => 'custom.patches.json',
    ])->getPatches();

    $this->assertCount(1, $patches);
    $this->assertSame(PatchesCollectorInterface::SOURCE_CUSTOM, $patches[0]['source']);
  }

  /**
   * Tests that the module's own package is never reported.
   *
   * Published releases of the module used to carry a leftover extra.patches
   * block; the reporter must not present its own stale metadata as an
   * ignored patching source.
   *
   * @covers ::getPatchProviders
   * @covers ::getIgnoredPatches
   */
  public function testOwnPackageIsNotReported(): void {
    $this->writeJson('composer.json', []);
    $this->writeJson('composer.lock', [
      'packages' => [
        [
          'name' => 'drupal/webpatches',
          'version' => '1.0.0',
          'extra' => [
            'patches' => [
              'drupal/core' => ['Issue #3272720: A stale patch' => 'https://example.com/stale.patch'],
            ],
          ],
        ],
        [
          'name' => 'drupal/ai_context',
          'version' => '1.0.0',
          'extra' => [
            'patches' => [
              'drupal/canvas' => ['Issue #3567225: A patch AI Context declares itself' => 'https://example.com/canvas.patch'],
            ],
          ],
        ],
      ],
    ]);

    $collector = $this->collector();
    $providers = array_column($collector->getPatchProviders(), 'package');
    $this->assertSame(['drupal/ai_context'], $providers);

    $ignored_providers = array_column($collector->getIgnoredPatches(), 'provider');
    $this->assertSame(['drupal/ai_context'], $ignored_providers);
    $this->assertNotContains('drupal/webpatches', $ignored_providers);
  }

  /**
   * Tests the allowed / not allowed verdict for each patching package.
   *
   * @covers ::getPatchProviders
   */
  public function testPatchProviders(): void {
    $this->writeJson('composer.json', [
      'extra' => [
        'composer-patches' => [
          // blocked/* is allowlisted, so the ignore rule is what excludes it.
          'allowed-dependency-patches' => ['webship/patches', 'blocked/*'],
          'ignore-dependency-patches' => ['blocked/*'],
        ],
      ],
    ]);
    $this->writeJson('composer.lock', [
      'packages' => [
        [
          'name' => 'webship/patches',
          'version' => '11.0.x-dev',
          'extra' => [
            'patches' => [
              'drupal/redirect' => ['Issue #1234567: One' => 'https://example.com/a.patch'],
              'drupal/coffee' => ['Issue #1234568: Two' => 'https://example.com/b.patch'],
            ],
          ],
        ],
        [
          'name' => 'blocked/package',
          'version' => '1.0.0',
          'extra' => ['patches' => ['drupal/foo' => ['Issue #1234569: Three' => 'https://example.com/c.patch']]],
        ],
        [
          'name' => 'some/other',
          'version' => '2.0.0',
          'extra' => ['patches' => ['drupal/bar' => ['Issue #1234570: Four' => 'https://example.com/d.patch']]],
        ],
        ['name' => 'no/patches', 'version' => '1.0.0'],
      ],
    ]);

    $providers = $this->collector()->getPatchProviders();
    $this->assertCount(3, $providers, 'A package with no patches is not a patching source.');

    $by_name = array_column($providers, NULL, 'package');

    $this->assertTrue($by_name['webship/patches']['allowed']);
    $this->assertSame(2, $by_name['webship/patches']['count']);
    $this->assertSame('11.0.x-dev', $by_name['webship/patches']['version']);

    $this->assertFalse($by_name['blocked/package']['allowed']);
    $this->assertStringContainsString('ignore-dependency-patches', (string) $by_name['blocked/package']['reason']);

    $this->assertFalse($by_name['some/other']['allowed']);
    $this->assertStringContainsString('allowed-dependency-patches', (string) $by_name['some/other']['reason']);

    // Allowed packages sort first.
    $this->assertSame('webship/patches', $providers[0]['package']);
  }

  /**
   * Tests which patches file Composer Patches would read.
   *
   * @covers ::getSources
   */
  public function testPatchesFileResolution(): void {
    // Composer Patches v2 reads extra.composer-patches.patches-file.
    $this->writeJson('composer.json', [
      'extra' => ['composer-patches' => ['patches-file' => 'custom-v2.json']],
    ]);
    $sources = $this->collector()->getSources();
    $this->assertStringEndsWith('/custom-v2.json', $sources[PatchesCollectorInterface::SOURCE_PATCHES_FILE]['path']);

    // The v1 top level key is honoured too.
    $this->writeJson('composer.json', ['extra' => ['patches-file' => 'custom-v1.json']]);
    $sources = $this->collector()->getSources();
    $this->assertStringEndsWith('/custom-v1.json', $sources[PatchesCollectorInterface::SOURCE_PATCHES_FILE]['path']);

    // With nothing declared, an existing conventional file is found.
    $this->writeJson('composer.json', []);
    $this->writeJson('patches.composer.json', ['patches' => []]);
    $sources = $this->collector()->getSources();
    $this->assertStringEndsWith('/patches.composer.json', $sources[PatchesCollectorInterface::SOURCE_PATCHES_FILE]['path']);

    // patches.json is the v2 default and wins over the other convention.
    $this->writeJson('patches.json', ['patches' => []]);
    $sources = $this->collector()->getSources();
    $this->assertStringEndsWith('/patches.json', $sources[PatchesCollectorInterface::SOURCE_PATCHES_FILE]['path']);
  }

  /**
   * Tests the patches.lock.json sync check.
   *
   * @covers ::getLockStatus
   */
  public function testLockStatus(): void {
    $this->writeJson('composer.json', []);
    $this->writeJson('composer.lock', [
      'packages' => [
        [
          'name' => 'webship/patches',
          'version' => '11.0.29',
          'extra' => [
            'patches' => [
              'drupal/redirect' => ['Issue #2879648: One' => 'https://example.com/a.patch'],
              'drupal/coffee' => ['Issue #3535874: Two' => 'https://example.com/b.patch'],
            ],
          ],
        ],
      ],
    ]);

    // No lock file at all.
    $status = $this->collector()->getLockStatus();
    $this->assertFalse($status['found']);
    $this->assertNull($status['in_sync']);
    $this->assertSame(2, $status['declared_count']);

    // A lock in sync with the declarations.
    $this->writeJson('patches.lock.json', [
      'patches' => [
        'drupal/redirect' => [
          ['package' => 'drupal/redirect', 'description' => 'Issue #2879648: One', 'url' => 'https://example.com/a.patch'],
        ],
        'drupal/coffee' => [
          ['package' => 'drupal/coffee', 'description' => 'Issue #3535874: Two', 'url' => 'https://example.com/b.patch'],
        ],
      ],
    ]);
    $status = $this->collector()->getLockStatus();
    $this->assertTrue($status['found']);
    $this->assertTrue($status['in_sync']);
    $this->assertSame(2, $status['lock_count']);

    // A stale lock: one declared patch missing, one leftover locked patch.
    $this->writeJson('patches.lock.json', [
      'patches' => [
        'drupal/redirect' => [
          ['package' => 'drupal/redirect', 'description' => 'Issue #2879648: One', 'url' => 'https://example.com/a.patch'],
        ],
        'drupal/removed' => [
          ['package' => 'drupal/removed', 'description' => 'Issue #1111111: Gone', 'url' => 'https://example.com/gone.patch'],
        ],
      ],
    ]);
    $status = $this->collector()->getLockStatus();
    $this->assertFalse($status['in_sync']);
    $this->assertCount(1, $status['missing']);
    $this->assertSame('drupal/coffee', $status['missing'][0]['package']);
    $this->assertCount(1, $status['stale']);
    $this->assertSame('drupal/removed', $status['stale'][0]['package']);
  }

  /**
   * Tests that the display filter does not affect the lock comparison.
   *
   * @covers ::getLockStatus
   */
  public function testLockStatusIgnoresDisplayFilter(): void {
    $this->writeJson('composer.json', [
      'extra' => [
        'patches' => [
          'drupal/not_installed' => ['Issue #2222222: Hidden by the filter' => 'https://example.com/h.patch'],
        ],
      ],
    ]);
    $this->writeJson('composer.lock', ['packages' => []]);
    $this->writeJson('patches.lock.json', [
      'patches' => [
        'drupal/not_installed' => [
          ['package' => 'drupal/not_installed', 'description' => 'Issue #2222222: Hidden by the filter', 'url' => 'https://example.com/h.patch'],
        ],
      ],
    ]);

    $collector = $this->collector(['only_installed_packages' => TRUE]);
    $this->assertSame([], $collector->getPatches(), 'The display filter hides the patch.');
    $this->assertTrue($collector->getLockStatus()['in_sync'], 'The lock comparison still sees it.');
  }

  /**
   * Tests that disabling the dependency source empties the provider list.
   *
   * @covers ::getPatchProviders
   */
  public function testProvidersRespectSourceToggle(): void {
    $this->writeJson('composer.json', []);
    $this->writeJson('composer.lock', [
      'packages' => [
        [
          'name' => 'webship/patches',
          'version' => '11.0.29',
          'extra' => [
            'patches' => [
              'drupal/redirect' => ['Issue #2879648: One' => 'https://example.com/a.patch'],
            ],
          ],
        ],
      ],
    ]);

    $this->assertCount(1, $this->collector()->getPatchProviders());
    $off = $this->collector([
      'sources' => [
        PatchesCollectorInterface::SOURCE_ROOT => TRUE,
        PatchesCollectorInterface::SOURCE_PATCHES_FILE => TRUE,
        PatchesCollectorInterface::SOURCE_CUSTOM => FALSE,
        PatchesCollectorInterface::SOURCE_DEPENDENCIES => FALSE,
      ],
    ]);
    $this->assertSame([], $off->getPatchProviders());
  }

}

/**
 * A collector whose project root is the fixture directory.
 */
class TestPatchesCollector extends PatchesCollector {

  /**
   * {@inheritdoc}
   */
  public function getProjectRoot(): ?string {
    return $this->appRoot;
  }

}
