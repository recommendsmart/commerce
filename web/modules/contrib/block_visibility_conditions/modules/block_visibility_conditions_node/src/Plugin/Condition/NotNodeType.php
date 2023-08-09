<?php

namespace Drupal\block_visibility_conditions_node\Plugin\Condition;

use Drupal\block_visibility_conditions\Plugin\Condition\NotConditionPluginBase;

/**
 * Provides a 'Not Node Type' condition.
 *
 * The block will still be shown on all other pages, including non-node pages.
 * This differs from the negated condition "Content type", which will only be
 * evaluated on node pages, which means the block won't be shown on other pages
 * like views.
 *
 * @Condition(
 *   id = "not_node_type",
 *   label = @Translation("Not Node Type")
 * )
 */
class NotNodeType extends NotConditionPluginBase {

  protected const CONTENT_ENTITY_TYPE = 'node_type';

}
