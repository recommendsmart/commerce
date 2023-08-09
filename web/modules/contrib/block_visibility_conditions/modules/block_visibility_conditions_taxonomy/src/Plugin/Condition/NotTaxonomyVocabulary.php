<?php

namespace Drupal\block_visibility_conditions_taxonomy\Plugin\Condition;

use Drupal\block_visibility_conditions\Plugin\Condition\NotConditionPluginBase;

/**
 * Provides a 'Not Taxonomy Vocabulary' condition.
 *
 * The block will still be shown on all other pages, including non-taxonomy term
 * pages. This differs from the negated condition "Taxonomy vocabulary", which
 * will only be evaluated on taxonomy term pages, which means the block won't
 * be shown on other pages like views.
 *
 * @Condition(
 *   id = "not_taxonomy_vocabulary",
 *   label = @Translation("Not Taxonomy Vocabulary")
 * )
 */
class NotTaxonomyVocabulary extends NotConditionPluginBase {

  protected const CONTENT_ENTITY_TYPE = 'taxonomy_vocabulary';

}
