<?php

namespace Drupal\commerce_product_tax\Resolver;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_tax\Resolver\TaxRateResolverInterface;
use Drupal\commerce_tax\Resolver\TaxTypeAwareInterface;
use Drupal\commerce_tax\Resolver\TaxTypeAwareTrait;
use Drupal\commerce_tax\TaxZone;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Returns the tax rate configured on the product variation.
 */
class TaxRateResolver implements TaxRateResolverInterface, TaxTypeAwareInterface {

  use TaxTypeAwareTrait;

  /**
   * {@inheritdoc}
   */
  public function resolve(TaxZone $zone, OrderItemInterface $order_item, ProfileInterface $customer_profile) {
    $purchased_entity = $order_item->getPurchasedEntity();
    if (!$purchased_entity) {
      return NULL;
    }

    $tax_field_names = $this->getTaxFieldNames($purchased_entity);

    foreach ($tax_field_names as $field_name) {
      $field_items = $purchased_entity->get($field_name);
      if ($field_items->isEmpty()) {
        continue;
      }
      $tax_type = $field_items->getFieldDefinition()->getSetting('tax_type');
      if ($tax_type != $this->taxType->id()) {
        continue;
      }

      foreach ($field_items as $tax_rate) {
        [$zone_id, $rate_id] = explode('|', $tax_rate->value);
        if ($zone->getId() != $zone_id) {
          continue;
        }

        if ($rate_id === static::NO_APPLICABLE_TAX_RATE) {
          return static::NO_APPLICABLE_TAX_RATE;
        }
        foreach ($zone->getRates() as $rate) {
          if ($rate->getId() == $rate_id) {
            return $rate;
          }
        }
      }
    }
  }

  /**
   * Gets the tax field names attached to the purchasable entity.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $entity
   *   The purchasable entity.
   *
   * @return array
   *   An array of tax field names.
   */
  protected function getTaxFieldNames(PurchasableEntityInterface $entity) {
    $field_names = [];
    foreach ($entity->getFieldDefinitions() as $field) {
      if ($field->getType() == 'commerce_tax_rate') {
        $field_names[] = $field->getName();
      }
    }

    return $field_names;
  }

}
