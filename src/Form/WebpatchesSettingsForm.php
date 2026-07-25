<?php

namespace Drupal\webpatches\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webpatches\PatchesCollectorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures which patch declarations the Web Patches report reads.
 */
class WebpatchesSettingsForm extends ConfigFormBase {

  /**
   * The patches collector.
   *
   * @var \Drupal\webpatches\PatchesCollectorInterface
   */
  protected $patchesCollector;

  /**
   * Constructs a WebpatchesSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   *   The typed config manager.
   * @param \Drupal\webpatches\PatchesCollectorInterface $patches_collector
   *   The patches collector.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config, PatchesCollectorInterface $patches_collector) {
    parent::__construct($config_factory, $typed_config);
    $this->patchesCollector = $patches_collector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('webpatches.collector')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webpatches_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['webpatches.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('webpatches.settings');
    $sources = (array) $config->get('sources');

    $form['sources'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Patch declaration sources'),
      '#description' => $this->t('Choose which declarations the Web Patches report reads.'),
      '#tree' => TRUE,
    ];
    $form['sources'][PatchesCollectorInterface::SOURCE_ROOT] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Root composer.json'),
      '#description' => $this->t('The extra.patches section of the composer.json of this project.'),
      '#default_value' => !empty($sources[PatchesCollectorInterface::SOURCE_ROOT]),
    ];
    $form['sources'][PatchesCollectorInterface::SOURCE_PATCHES_FILE] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Patches file (patches.composer.json)'),
      '#description' => $this->t('The file referenced by extra.patches-file in the root composer.json, which defaults to patches.composer.json.'),
      '#default_value' => !empty($sources[PatchesCollectorInterface::SOURCE_PATCHES_FILE]),
    ];
    $form['sources'][PatchesCollectorInterface::SOURCE_CUSTOM] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Custom patches file'),
      '#description' => $this->t('An extra patches file at the path configured below.'),
      '#default_value' => !empty($sources[PatchesCollectorInterface::SOURCE_CUSTOM]),
    ];
    $form['sources'][PatchesCollectorInterface::SOURCE_DEPENDENCIES] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Installed dependency packages'),
      '#description' => $this->t('The patches contributed by installed packages such as webship/patches and webship/drupal-patches, filtered by the allowlist and the ignore rules of the root composer.json.'),
      '#default_value' => !empty($sources[PatchesCollectorInterface::SOURCE_DEPENDENCIES]),
    ];

    $form['custom_file_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom patches file path'),
      '#description' => $this->t('Absolute, or relative to the Composer project root (@root). The file may be a bare {"patches": {…}} file or a composer.json shaped file that carries the list under extra.patches.', [
        '@root' => $this->patchesCollector->getProjectRoot() ?: $this->t('unknown'),
      ]),
      '#default_value' => $config->get('custom_file_path'),
      '#states' => [
        'visible' => [
          ':input[name="sources[' . PatchesCollectorInterface::SOURCE_CUSTOM . ']"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['only_installed_packages'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only list patches for packages installed on this site'),
      '#description' => $this->t('Hides patches that target packages this site does not install, so the report shows only the patches that matter here.'),
      '#default_value' => (bool) $config->get('only_installed_packages'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $path = trim((string) $form_state->getValue('custom_file_path'));
    if ($path !== '' && $form_state->getValue(['sources', PatchesCollectorInterface::SOURCE_CUSTOM])) {
      $absolute = $path[0] === '/'
        ? $path
        : rtrim((string) $this->patchesCollector->getProjectRoot(), '/') . '/' . $path;
      if (!is_file($absolute)) {
        $form_state->setErrorByName('custom_file_path', $this->t('The file @path does not exist.', ['@path' => $absolute]));
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $sources = [];
    foreach (array_keys($form['sources']) as $key) {
      if (str_starts_with($key, '#')) {
        continue;
      }
      $sources[$key] = (bool) $form_state->getValue(['sources', $key]);
    }

    $this->config('webpatches.settings')
      ->set('sources', $sources)
      ->set('custom_file_path', trim((string) $form_state->getValue('custom_file_path')))
      ->set('only_installed_packages', (bool) $form_state->getValue('only_installed_packages'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
