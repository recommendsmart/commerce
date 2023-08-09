<?php

namespace Drupal\commerce_license\Plugin\EntityReferenceSelection;

use Drupal\commerce_product\Plugin\EntityReferenceSelection\ProductVariationSelection as CommerceProductVariationSelection;

/**
 * Provides specific access control for the commerce product variation.
 *
 * @EntityReferenceSelection(
 *   id = "commerce_license:commerce_product_variation",
 *   label = @Translation("Commerce license product variation selection"),
 *   entity_types = {"commerce_product_variation"},
 *   group = "commerce_license",
 *   weight = 5
 * )
 */
class ProductVariationSelection extends CommerceProductVariationSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $query->exists('license_type');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities) {
    $entities = parent::validateReferenceableNewEntities($entities);
    $entities = array_filter($entities, static function ($license) {
      /** @var \Drupal\commerce_license\Entity\LicenseInterface $license */
      return $license->hasField('license_type');
    });
    return $entities;
  }

}
