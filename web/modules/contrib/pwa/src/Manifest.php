<?php

namespace Drupal\pwa;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Manifest JSON building service.
 */
class Manifest implements ManifestInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private $languageManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The Symfony request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  private $themeManager;

  /**
   * Constructor; saves dependencies.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Symfony request stack.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   */
  public function __construct(
    ConfigFactoryInterface    $configFactory,
    LanguageManagerInterface  $languageManager,
    ModuleHandlerInterface    $moduleHandler,
    RequestStack              $requestStack,
    ThemeManagerInterface     $themeManager
  ) {
    $this->configFactory    = $configFactory;
    $this->languageManager  = $languageManager;
    $this->moduleHandler    = $moduleHandler;
    $this->requestStack     = $requestStack;
    $this->themeManager     = $themeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutput() {
    // Get values.
    $values = $this->getCleanValues();
      $manifest_data['orientation'] = 'portrait';
    if (isset($values['site_name'])) {
      $manifest_data['name'] = $values['site_name'];
    }
    if (isset($values['short_name'])) {
      $manifest_data['short_name'] = $values['short_name'];
    }
    if (isset($values['display'])) {
      $manifest_data['display'] = $values['display'];
    }
    if (isset($values['background_color'])) {
      $manifest_data['background_color'] = $values['background_color'];
    }
    if (isset($values['theme_color'])) {
      $manifest_data['theme_color'] = $values['theme_color'];
    }
    if (isset($values['description'])) {
      $manifest_data['description'] = $values['description'];
    }
    if (isset($values['lang'])) {
      $manifest_data['lang'] = $values['lang'];
    }
    if (isset($values['image'])) {
      $manifest_data['icons'][0]['src'] = $values['image'];
      $manifest_data['icons'][0]['sizes'] = '512x512';
      $manifest_data['icons'][0]['type'] = 'image/png';
      $manifest_data['icons'][0]['purpose'] = 'any maskable';
    }
    if (isset($values['image_small'])) {
      $manifest_data['icons'][1]['src'] = $values['image_small'];
      $manifest_data['icons'][1]['sizes'] = '192x192';
      $manifest_data['icons'][1]['type'] = 'image/png';
      $manifest_data['icons'][1]['purpose'] = 'any maskable';
    }
    if (isset($values['image_very_small'])) {
      $manifest_data['icons'][2]['src'] = $values['image_very_small'];
      $manifest_data['icons'][2]['sizes'] = '144x144';
      $manifest_data['icons'][2]['purpose'] = 'any maskable';

    }
    if (isset($values['start_url'])) {
      $manifest_data['start_url'] = $values['start_url'];
    }
    if (isset($values['scope'])) {
      $manifest_data['scope'] = $values['scope'];
    }

    $this->moduleHandler->alter('pwa_manifest', $manifest_data);
    $this->themeManager->alter('pwa_manifest', $manifest_data);

    return Json::encode($manifest_data);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteImage() {
    $config = $this->configFactory->get('pwa.config');
    $image = $config->get('image');
    // Image exists and is NOT default.
    if (!empty($image) && $image[0] == '/') {
      // Image.
      $path = getcwd() . $image;
      unlink($path);
      // Image_small.
      unlink($path . 'copy.png');
      // Image_very_small.
      unlink($path . 'copy2.png');
    }
  }

  /**
   * Checks the values in config and add default value if necessary.
   *
   * @return array
   *   Values from the configuration.
   *
   * @todo Can we use the injected 'theme.manager' service rather than
   *   theme_get_setting() and then do
   *   $this->themeManager->getActiveTheme()->getLogo()?
   */
  private function getCleanValues() {
    // Set defaults.
    $lang = $this->languageManager->getDefaultLanguage();
    $site_name = $this->configFactory->get('system.site')->get('name');
    $path = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() .
      '/' . $this->moduleHandler->getModule('pwa')->getPath();
    $output = [
      'site_name' => $site_name,
      'short_name' => $site_name,
      'background_color' => '#ffffff',
      'theme_color' => '#ffffff',
      'display' => 'standalone',
      'image' => $path . '/assets/icon-512.png',
      'image_small' => $path . '/assets/icon-192.png',
      'image_very_small' => $path . '/assets/icon-144.png',
    ];

    $config = $this->configFactory->get('pwa.config');
    $config_data = $config->get();
    foreach ($config_data as $key => $value) {
      if ($value !== '') {
        $output[$key] = $value;
      }
    }

    // Image from theme.
    if ($config->get('default_image')) {
      $image = theme_get_setting('logo.path');
      $output['image'] = $image;
      $output['image_small'] = $image;
      $output['image_very_small'] = $image;
    }

    return $output;
  }

}
