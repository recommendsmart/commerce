<?php

namespace Drupal\block_visibility_conditions_commerce\Plugin\Condition;

use Drupal\block_visibility_conditions\Plugin\Condition\NotConditionPluginBase;

/**
 * Provides a 'Not Product Type' condition.
 *
 * The block will still be shown on all other pages, including non-product
 * pages. This differs from the negated condition "Product type", which will
 * only be evaluated on product pages, which means the block won't be shown on
 * other pages like views.
 *
 * @Condition(
 *   id = "not_product_type",
 *   label = @Translation("Not Product Type")
 * )
 */
class NotProductType extends NotConditionPluginBase {

  protected const CONTENT_ENTITY_TYPE = 'commerce_product_type';

}
