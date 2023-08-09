<?php

namespace Drupal\commerce_pricelist\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_pricelist\Entity\PriceListInterface;

/**
 * Defines the price list event.
 *
 * @see \Drupal\commerce_pricelist\Event\PriceListEvents
 */
class PriceListEvent extends EventBase {

  /**
   * The price list.
   *
   * @var \Drupal\commerce_pricelist\Entity\PriceListInterface
   */
  protected $priceList;

  /**
   * Constructs a new PriceListEvent object.
   *
   * @param \Drupal\commerce_pricelist\Entity\PriceListInterface $price_list
   *   The price list.
   */
  public function __construct(PriceListInterface $price_list) {
    $this->priceList = $price_list;
  }

  /**
   * Gets the price list.
   *
   * @return \Drupal\commerce_pricelist\Entity\PriceListInterface
   *   Gets the price list.
   */
  public function getPriceList() : PriceListInterface {
    return $this->priceList;
  }

}
