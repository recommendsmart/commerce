<?php

namespace Drupal\commerce_product_tax\Plugin\Validation\Constraint;

use Drupal\commerce_tax\Entity\TaxType;
use Drupal\commerce_tax\Resolver\TaxRateResolverInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the tax rate constraint.
 */
class TaxRateConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\Core\Field\FieldItemListInterface $value */
    if (!isset($value)) {
      return;
    }
    $field_definition = $value->getFieldDefinition();
    $is_multiple = $field_definition->getFieldStorageDefinition()->isMultiple();
    $allowed_zones = $field_definition->getSetting('allowed_zones');
    $tax_type = TaxType::load($field_definition->getSetting('tax_type'));

    if (empty($tax_type)) {
      return;
    }
    /** @var \Drupal\commerce_tax\Plugin\Commerce\TaxType\LocalTaxTypeInterface $tax_type_plugin */
    $tax_type_plugin = $tax_type->getPlugin();
    $zones = $tax_type_plugin->getZones();
    $seen_zones = [];
    foreach ($value as $delta => $item) {
      [$zone_id, $rate_id] = explode('|', $item->value);

      // Ensure the selected zone is "allowed".
      if (!empty($allowed_zones) && !in_array($zone_id, $allowed_zones)) {
        $value = isset($zones[$zone_id]) ? $zones[$zone_id]->getLabel() : $zone_id;
        $this->context->buildViolation($constraint->invalidZoneMessage)
          ->atPath((string) $delta)
          ->setParameter('%name', $field_definition->getLabel())
          ->setParameter('%value', $value)
          ->addViolation();
        continue;
      }

      // Ensure a single rate is selected per zone, in case of a multiple field.
      if ($is_multiple && in_array($zone_id, $seen_zones)) {
        $this->context->buildViolation($constraint->singleTaxRatePerZoneMessage)
          ->atPath((string) $delta)
          ->addViolation();
        continue;
      }

      $allowed_rates = [0 => TaxRateResolverInterface::NO_APPLICABLE_TAX_RATE];
      foreach ($zones[$zone_id]->getRates() as $rate) {
        $allowed_rates[] = $rate->getId();
      }

      // Ensure the selected rate exists in the zone.
      if (!in_array($rate_id, $allowed_rates)) {
        $this->context->buildViolation($constraint->invalidRateMessage)
          ->atPath((string) $delta)
          ->setParameter('%name', $field_definition->getLabel())
          ->setParameter('%value', $rate_id)
          ->addViolation();
      }

      $seen_zones[] = $zone_id;
    }
  }

}
