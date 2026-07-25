<?php

namespace Drupal\webpatches\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\webpatches\PatchesCollectorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the patches and the ignored patches declared for this site.
 */
class PatchesController extends ControllerBase {

  /**
   * The patches collector.
   *
   * @var \Drupal\webpatches\PatchesCollectorInterface
   */
  protected $patchesCollector;

  /**
   * Constructs a PatchesController object.
   *
   * @param \Drupal\webpatches\PatchesCollectorInterface $patches_collector
   *   The patches collector.
   */
  public function __construct(PatchesCollectorInterface $patches_collector) {
    $this->patchesCollector = $patches_collector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('webpatches.collector'));
  }

  /**
   * Builds the list of patches and ignored patches.
   *
   * @return array
   *   A render array.
   */
  public function listPatches(): array {
    $sources = $this->patchesCollector->getSources();
    $patches = $this->patchesCollector->getPatches();
    $ignored = $this->patchesCollector->getIgnoredPatches();
    $only_installed = (bool) $this->config('webpatches.settings')->get('only_installed_packages');

    $build = [];
    $build['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Patches are applied by Composer, not by this module. This page reads the same declarations Composer reads, and applies the same allowlist and ignore rules, so you can see what is declared for this site and what is filtered out.'),
    ];

    if (!$this->patchesCollector->getProjectRoot()) {
      $build['no_root'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [$this->t('The Composer project root could not be located, so no patch declaration could be read.')],
        ],
      ];
      return $build;
    }

    $build['sources'] = $this->buildSourcesTable($sources);
    $build['patches'] = $this->buildPatchesTable($patches);
    $build['ignored'] = $this->buildIgnoredTable($ignored);

    if ($only_installed) {
      $build['filter_note'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Only patches for packages installed on this site are listed. Change this on the @settings page.', [
          '@settings' => Link::fromTextAndUrl(
            $this->t('Web Patches settings'),
            Url::fromRoute('webpatches.settings')
          )->toString(),
        ]),
        '#weight' => 100,
      ];
    }

    // The declarations live on disk, outside of Drupal's cache invalidation.
    $build['#cache']['max-age'] = 0;
    return $build;
  }

  /**
   * Builds the table of patch declaration sources.
   *
   * @param array[] $sources
   *   The sources as returned by the collector.
   *
   * @return array
   *   A render array.
   */
  protected function buildSourcesTable(array $sources): array {
    $rows = [];
    foreach ($sources as $source) {
      if (!$source['enabled']) {
        $status = $this->t('Disabled');
      }
      elseif ($source['found']) {
        $status = $this->t('Read');
      }
      else {
        $status = $this->t('Not found');
      }
      $rows[] = [
        ['data' => $source['label']],
        ['data' => $source['path'] ?: $this->t('n/a')],
        ['data' => $status],
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('Sources'),
      '#open' => FALSE,
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Source'), $this->t('File'), $this->t('Status')],
        '#rows' => $rows,
        '#empty' => $this->t('No sources are enabled.'),
      ],
    ];
  }

  /**
   * Builds the table of declared patches.
   *
   * @param array[] $patches
   *   The patches as returned by the collector.
   *
   * @return array
   *   A render array.
   */
  protected function buildPatchesTable(array $patches): array {
    $rows = [];
    foreach ($patches as $patch) {
      $rows[] = [
        ['data' => $patch['package']],
        ['data' => $patch['description']],
        ['data' => $this->buildPatchLink($patch['url'])],
        ['data' => $this->sourceLabel($patch)],
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('Patches (@count)', ['@count' => count($patches)]),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Package'),
          $this->t('Patch'),
          $this->t('File'),
          $this->t('Declared in'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No patches are declared for this site.'),
      ],
    ];
  }

  /**
   * Builds the table of ignored patches.
   *
   * @param array[] $ignored
   *   The ignored patches as returned by the collector.
   *
   * @return array
   *   A render array.
   */
  protected function buildIgnoredTable(array $ignored): array {
    $rows = [];
    foreach ($ignored as $patch) {
      $rows[] = [
        ['data' => $patch['package'] ?: $this->t('All patches')],
        ['data' => $patch['description'] ?: $this->t('n/a')],
        ['data' => $patch['provider'] ?: $this->t('n/a')],
        ['data' => $patch['reason']],
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('Ignored patches (@count)', ['@count' => count($ignored)]),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Package'),
          $this->t('Patch'),
          $this->t('Declared by'),
          $this->t('Reason'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No declared patch is ignored on this site.'),
      ],
    ];
  }

  /**
   * Builds the cell that points at a patch file.
   *
   * @param string $url
   *   The patch URL or relative path.
   *
   * @return array|string
   *   A render array for remote patches, the raw path otherwise.
   */
  protected function buildPatchLink(string $url) {
    if (!preg_match('#^https?://#', $url)) {
      return $url;
    }
    return [
      '#type' => 'link',
      '#title' => basename(parse_url($url, PHP_URL_PATH) ?: $url),
      '#url' => Url::fromUri($url),
      '#attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer'],
    ];
  }

  /**
   * Returns the human readable origin of a patch.
   *
   * @param array $patch
   *   A patch as returned by the collector.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The label of the source the patch was declared in.
   */
  protected function sourceLabel(array $patch) {
    if (!empty($patch['provider'])) {
      return $patch['provider'];
    }
    $sources = $this->patchesCollector->getSources();
    return $sources[$patch['source']]['label'] ?? $patch['source'];
  }

}
