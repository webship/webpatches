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
          'name' => 'webship/webship-patches',
          'version' => '11.0.29',
          'extra' => [
            'patches' => [
              'drupal/redirect' => ['Issue #3: An allowed patch' => 'https://example.com/allowed.patch'],
            ],
          ],
        ],
        [
          'name' => 'some/other-package',
          'version' => '1.0.0',
          'extra' => [
            'patches' => [
              'drupal/coffee' => ['Issue #4: A patch from an unlisted package' => 'https://example.com/other.patch'],
            ],
          ],
        ],
      ],
    ]);

    $collector = $this->collector();
    $patches = $collector->getPatches();
    $this->assertCount(1, $patches);
    $this->assertSame('drupal/redirect', $patches[0]['package']);
    $this->assertSame('webship/webship-patches', $patches[0]['provider']);

    $ignored = $collector->getIgnoredPatches();
    $this->assertCount(1, $ignored);
    $this->assertSame('some/other-package', $ignored[0]['provider']);
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
          'webship/webship-patches' => [
            'drupal/redirect' => ['https://example.com/dropped.patch'],
          ],
        ],
      ],
    ]);
    $this->writeJson('composer.lock', [
      'packages' => [
        [
          'name' => 'webship/webship-patches',
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
