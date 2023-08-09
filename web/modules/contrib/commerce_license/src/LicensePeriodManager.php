<?php

namespace Drupal\commerce_license;

use Drupal\commerce_license\Annotation\CommerceLicensePeriod;
use Drupal\commerce_license\Plugin\Commerce\LicensePeriod\LicensePeriodInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Manages discovery and instantiation of license period plugins.
 *
 * @see \Drupal\commerce_license\Annotation\CommerceLicensePeriod
 * @see plugin_api
 */
class LicensePeriodManager extends DefaultPluginManager implements FallbackPluginManagerInterface {

  /**
   * Constructs a new LicensePeriodManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Commerce/LicensePeriod',
      $namespaces,
      $module_handler,
      LicensePeriodInterface::class,
      CommerceLicensePeriod::class
    );

    $this->alterInfo('commerce_license_period_info');
    $this->setCacheBackend($cache_backend, 'commerce_license_period_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    foreach (['id', 'label'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new PluginException(sprintf('The license period %s must define the %s property.', $plugin_id, $required_property));
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) {
    throw new NotFoundHttpException();
  }

}
