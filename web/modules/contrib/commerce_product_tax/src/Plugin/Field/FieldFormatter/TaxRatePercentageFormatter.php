<?php

namespace Drupal\commerce_product_tax\Plugin\Field\FieldFormatter;

use Drupal\commerce_price\Calculator;
use Drupal\commerce_tax\Plugin\Commerce\TaxType\LocalTaxTypeInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\commerce_tax\Entity\TaxType;

/**
 * Plugin implementation of the 'commerce_product_tax_rate_percentage' formatter.
 *
 * @FieldFormatter(
 *   id = "commerce_product_tax_rate_percentage",
 *   label = @Translation("Tax rate percentage"),
 *   field_types = {
 *     "commerce_tax_rate"
 *   }
 * )
 */
class TaxRatePercentageFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t("Displays the tax rate as it's percentage value instead of the default back-end value.");
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $tax_type = $item->getFieldDefinition()->getSetting('tax_type');
      $tax_type_entity = TaxType::load($tax_type);
      if ($tax_type_entity === NULL) {
        continue;
      }

      $tax_type_plugin = $tax_type_entity->getPlugin();
      if (!($tax_type_plugin instanceof LocalTaxTypeInterface)) {
        continue;
      }

      $zones = $tax_type_plugin->getZones();
      [$zone_id, $rate_id] = explode('|', $item->value);
      if (!isset($zones[$zone_id])) {
        continue;
      }

      /** @var \Drupal\commerce_tax\TaxZone $zone */
      $zone = $zones[$zone_id];
      $rate = $zone->getRate($rate_id);
      if ($rate === NULL) {
        continue;
      }

      $percentage = $rate->getPercentage();
      if ($percentage === NULL) {
        continue;
      }

      $element[$delta] = [
        '#markup' => (Calculator::multiply($percentage->getNumber(), 100)) . '%',
      ];
    }

    return $element;
  }

}
