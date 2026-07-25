<?php

namespace Drupal\webpatches\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for Web Patches.
 */
class WebpatchesHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.webpatches':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Web Patches shows the patches and the ignored patches declared for this site. Patches are applied by Composer, through cweagans/composer-patches and the webship/patches Composer plugin. This module reads the same declarations and applies the same allowlist and ignore rules, so you can see what is declared and what is filtered out.') . '</p>';
        $output .= '<h3>' . $this->t('Uses') . '</h3>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Reviewing the patches of a site') . '</dt>';
        $output .= '<dd>' . $this->t('The report at Reports &gt; Web Patches lists the patching sources (declaration files and the installed packages that declare patches, with the allowlist verdict for each), every declared patch with links to its drupal.org project, issue and merge request when those can be derived, and the patches that are declared but not applied, with the reason.') . '</dd>';
        $output .= '<dt>' . $this->t('Choosing the sources') . '</dt>';
        $output .= '<dd>' . $this->t('Configuration &gt; Development &gt; Web Patches selects which declarations are read: the root composer.json, the patches file referenced by extra.patches-file, a custom patches file, and the patches contributed by installed dependency packages.') . '</dd>';
        $output .= '</dl>';
        return $output;
    }
  }

}
