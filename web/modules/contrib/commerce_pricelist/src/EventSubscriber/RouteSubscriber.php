<?php

namespace Drupal\commerce_pricelist\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('view.commerce_product_variation_prices.page')) {
      $route->setOption('_admin_route', TRUE);
      $parameters = $route->getOption('parameters') ?: [];
      $parameters['commerce_product']['type'] = 'entity:commerce_product';
      $parameters['commerce_product_variation']['type'] = 'entity:commerce_product_variation';
      $route->setOption('parameters', $parameters);
    }
    if ($route = $collection->get('view.commerce_pricelist_product_prices.page')) {
      $route->setOption('_admin_route', TRUE);
    }
  }

}
