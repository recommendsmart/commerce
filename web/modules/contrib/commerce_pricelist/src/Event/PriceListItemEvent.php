<?php

namespace Drupal\commerce_pricelist\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_pricelist\Entity\PriceListItemInterface;

/**
 * Defines the price list item event.
 *
 * @see \Drupal\commerce_pricelist\Event\PriceListEvents
 */
class PriceListItemEvent extends EventBase {

  /**
   * The price list item.
   *
   * @var \Drupal\commerce_pricelist\Entity\PriceListItemInterface
   */
  protected $priceListItem;

  /**
   * Constructs a new PriceListItemEvent object.
   *
   * @param \Drupal\commerce_pricelist\Entity\PriceListItemInterface $price_list_item
   *   The price list item.
   */
  public function __construct(PriceListItemInterface $price_list_item) {
    $this->priceListItem = $price_list_item;
  }

  /**
   * Gets the price list item.
   *
   * @return \Drupal\commerce_pricelist\Entity\PriceListItemInterface
   *   Gets the price list item.
   */
  public function getPriceListItem() : PriceListItemInterface {
    return $this->priceListItem;
  }

}
