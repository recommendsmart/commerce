<?php

/**
 * @file
 * Post update functions for Pricelist.
 */

/**
 * Create the 'commerce_product_variation_prices' view.
 */
function commerce_pricelist_post_update_1() {
  /** @var \Drupal\commerce\Config\ConfigUpdaterInterface $config_updater */
  $config_updater = \Drupal::service('commerce.config_updater');

  $config_names = [
    'views.view.commerce_product_variation_prices',
  ];
  $result = $config_updater->import($config_names);

  $success_results = $result->getSucceeded();
  $failure_results = $result->getFailed();
  $message = '';
  if ($success_results) {
    $message = t('Succeeded:') . '<br>';
    foreach ($success_results as $success_message) {
      $message .= $success_message . '<br>';
    }
    $message .= '<br>';
  }
  if ($failure_results) {
    $message .= t('Failed:') . '<br>';
    foreach ($failure_results as $failure_message) {
      $message .= $failure_message . '<br>';
    }
  }

  return $message;
}

/**
 * Allows price list start and end dates to have a time component.
 */
function commerce_pricelist_post_update_2(array &$sandbox = NULL) {
  $storage = \Drupal::entityTypeManager()->getStorage('commerce_pricelist');
  if (!isset($sandbox['current_count'])) {
    $query = $storage->getQuery();
    $query->accessCheck(FALSE);
    $sandbox['total_count'] = $query->count()->execute();
    $sandbox['current_count'] = 0;

    if (empty($sandbox['total_count'])) {
      $sandbox['#finished'] = 1;
      return;
    }
  }

  $query = $storage->getQuery();
  $query->accessCheck(FALSE);
  $query->range($sandbox['current_count'], 25);
  $result = $query->execute();
  if (empty($result)) {
    $sandbox['#finished'] = 1;
    return;
  }

  /** @var \Drupal\commerce_pricelist\Entity\PriceList[] $price_lists */
  $price_lists = $storage->loadMultiple($result);
  foreach ($price_lists as $price_list) {
    // Re-set each date to ensure it is stored in the updated format.
    // Increase the end date by a day to match old inclusive loading
    // (where an end date was valid until 23:59:59 of that day).
    $start_date = $price_list->getStartDate();
    $end_date = $price_list->getEndDate();
    if ($end_date) {
      $end_date = $end_date->modify('+1 day');
    }
    $price_list->setStartDate($start_date);
    $price_list->setEndDate($end_date);

    $price_list->save();
  }

  $sandbox['current_count'] += 25;
  if ($sandbox['current_count'] >= $sandbox['total_count']) {
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['total_count'] - $sandbox['current_count']) / $sandbox['total_count'];
  }
}

/**
 * Import the "commerce_pricelist_prices" view.
 */
function commerce_pricelist_post_update_3() {
  /** @var \Drupal\commerce\Config\ConfigUpdaterInterface $config_updater */
  $config_updater = \Drupal::service('commerce.config_updater');
  $result = $config_updater->import([
    'views.view.commerce_pricelist_prices',
  ]);
  return implode('<br>', $result->getFailed());
}

/**
 * Import the "commerce_pricelist_product_prices" view.
 */
function commerce_pricelist_post_update_4() {
  if (!\Drupal::moduleHandler()->moduleExists('commerce_product')) {
    return;
  }
  /** @var \Drupal\commerce\Config\ConfigUpdaterInterface $config_updater */
  $config_updater = \Drupal::service('commerce.config_updater');
  $result = $config_updater->import([
    'views.view.commerce_pricelist_product_prices',
  ]);

  return implode('<br>', $result->getFailed());
}

/**
 * Import the "commerce_pricelist_product_prices" view if missing.
 */
function commerce_pricelist_post_update_5() {
  /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $view_storage */
  $view_storage = \Drupal::entityTypeManager()->getStorage('view');
  /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $view */
  $view = $view_storage->load('commerce_pricelist_product_prices');
  if (!$view) {
    return commerce_pricelist_post_update_4();
  }
}
