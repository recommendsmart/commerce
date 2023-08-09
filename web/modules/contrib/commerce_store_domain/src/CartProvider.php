<?php

namespace Drupal\commerce_store_domain;

use Drupal\commerce_cart\CartProvider as BaseCartProvider;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Ensure only carts of the current store are returned by the cart provider.
 */
class CartProvider extends BaseCartProvider {

  /**
   * {@inheritdoc}
   */
  public function getCartIds(AccountInterface $account = NULL, StoreInterface $store = NULL) {
    // If no store is passed, defaults to the current store.
    $store = $store ?: $this->currentStore->getStore();
    return parent::getCartIds($account, $store);
  }

}
