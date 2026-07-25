<?php

namespace Drupal\webpatches\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\webpatches\PatchLinks;
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
    $build['#attached']['library'][] = 'webpatches/admin';
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
      // The dependency source is the resolved lock file rather than a patch
      // declaration file, and every patch it contributes already names its
      // declaring package in the Patches table.
      if ($source['id'] === PatchesCollectorInterface::SOURCE_DEPENDENCIES) {
        continue;
      }
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
      '#title' => $this->t('Patching sources'),
      '#open' => FALSE,
      'files_title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Declaration files'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Source'), $this->t('File'), $this->t('Status')],
        '#rows' => $rows,
        '#empty' => $this->t('No sources are enabled.'),
      ],
      'providers' => $this->buildProvidersTable(),
    ];
  }

  /**
   * Builds the table of installed packages that declare patches.
   *
   * @return array
   *   A render array.
   */
  protected function buildProvidersTable(): array {
    $rows = [];
    foreach ($this->patchesCollector->getPatchProviders() as $provider) {
      $rows[] = [
        'data' => [
          ['data' => $this->buildPackageCell($provider['package'])],
          ['data' => ['#plain_text' => $provider['version']]],
          ['data' => ['#plain_text' => (string) $provider['count']]],
          [
            'data' => $provider['allowed']
              ? ['#markup' => $this->t('Allowed')]
              : ['#markup' => $this->t('Not allowed')],
          ],
          ['data' => $provider['reason']],
        ],
        'class' => [$provider['allowed'] ? 'color-success' : 'color-warning'],
      ];
    }

    return [
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Packages declaring patches'),
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('An installed package only contributes its patches when it is in the allowlist and is not matched by the ignore rules.'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Package'),
          $this->t('Version'),
          $this->t('Patches'),
          $this->t('Status'),
          $this->t('Reason'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No installed package declares patches.'),
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
        ['data' => $this->buildPackageCell($patch['package'])],
        ['data' => $this->buildPatchCell($patch)],
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
          $this->t('Declared in'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No patches are declared for this site.'),
      ],
    ];
  }

  /**
   * Builds the cell naming the patched package.
   *
   * @param string $package
   *   The Composer package name.
   *
   * @return array
   *   A render array linking to the project page where one can be derived.
   */
  protected function buildPackageCell(string $package): array {
    $url = PatchLinks::packageUrl($package);
    if ($url === NULL) {
      return ['#plain_text' => $package];
    }
    return [
      '#type' => 'link',
      '#title' => $package,
      '#url' => Url::fromUri($url),
      '#attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer'],
    ];
  }

  /**
   * Builds the cell describing a patch.
   *
   * The description comes first, with any drupal.org issue reference turned
   * into a link, and the patch file underneath it alongside a link to the
   * merge request when the file name carries its id.
   *
   * @param array $patch
   *   A patch as returned by the collector.
   *
   * @return array
   *   A render array.
   */
  protected function buildPatchCell(array $patch): array {
    $build = [];
    $build['description'] = $this->buildDescription($patch['description']);

    $meta = [];
    $meta['file'] = $this->buildPatchLink($patch['url']);

    $merge_request = PatchLinks::mergeRequestUrl($patch['package'], $patch['url']);
    if ($merge_request !== NULL) {
      $meta['separator'] = ['#plain_text' => ' · '];
      $meta['merge_request'] = [
        '#type' => 'link',
        '#title' => $this->t('MR !@id', ['@id' => PatchLinks::mergeRequestId($patch['url'])]),
        '#url' => Url::fromUri($merge_request),
        '#attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer'],
      ];
    }

    $build['meta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['webpatches-patch-meta']],
    ] + $meta;

    return $build;
  }

  /**
   * Builds a patch description with its issue reference linked.
   *
   * @param string $description
   *   The patch description.
   *
   * @return array
   *   A render array. The description is split around the issue reference so
   *   every part is rendered as plain text and never as markup.
   */
  protected function buildDescription(string $description): array {
    $issue = PatchLinks::issueId($description);
    if ($issue === NULL) {
      return ['#plain_text' => $description];
    }

    $token = '#' . $issue;
    $position = strpos($description, $token);
    return [
      'before' => ['#plain_text' => substr($description, 0, $position)],
      'issue' => [
        '#type' => 'link',
        '#title' => $token,
        '#url' => Url::fromUri('https://www.drupal.org/node/' . $issue),
        '#attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer'],
      ],
      'after' => ['#plain_text' => substr($description, $position + strlen($token))],
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
        [
          'data' => $patch['package']
            ? $this->buildPackageCell($patch['package'])
            : ['#markup' => $this->t('All patches')],
        ],
        [
          'data' => $patch['description']
            ? $this->buildPatchCell($patch)
            : ['#markup' => $this->t('n/a')],
        ],
        [
          'data' => $patch['provider']
            ? $this->buildPackageCell($patch['provider'])
            : ['#markup' => $this->t('n/a')],
        ],
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
   * @return array
   *   A render array: a link for a remote patch, the plain path for a local
   *   one.
   */
  protected function buildPatchLink(string $url): array {
    // Patch URLs come out of composer.json files on disk. Only well formed
    // http(s) URLs become links; anything else is shown as plain text.
    if (!preg_match('#^https?://#', $url) || !UrlHelper::isValid($url, TRUE)) {
      return ['#plain_text' => $url];
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
