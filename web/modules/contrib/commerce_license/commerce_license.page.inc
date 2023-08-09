<?php

/**
 * @file
 * Contains commerce_license.page.inc.
 *
 * Page callback for License entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for License templates.
 *
 * Default template: commerce_license.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_commerce_license(array &$variables) {
  /** @var \Drupal\commerce_license\Entity\LicenseInterface $license */
  $license = $variables['elements']['#commerce_license'];

  $variables['license_entity'] = $license;
  $variables['license'] = [];
  foreach (Element::children($variables['elements']) as $key) {
    $variables['license'][$key] = $variables['elements'][$key];
  }

  if (\Drupal::moduleHandler()->moduleExists('commerce_log')) {
    $variables['license']['activity'] = [
      '#type' => 'view',
      '#name' => 'commerce_activity',
      '#display_id' => 'default',
      '#arguments' => [$license->id(), 'commerce_license'],
      '#embed' => TRUE,
      '#title' => t('License activity'),
    ];
  }

  // Inject license fields not manually printed in a separate variable for
  // easier rendering.
  $printed_fields = [
    'granted',
    'renewed',
    'expires',
    'created',
    'changed',
    'uid',
    'state',
    'activity',
    '_layout_builder',
  ];
  $variables['additional_license_fields'] = array_diff_key($variables['license'], array_combine($printed_fields, $printed_fields));
}
