<?php

namespace Drupal\Tests\commerce_product_tax\Kernel;

use Drupal\commerce_tax\Entity\TaxType;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Provides a base class for Commerce product tax kernel tests.
 */
abstract class ProductTaxKernelTestBase extends CommerceKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_tax',
    'commerce_product',
    'commerce_product_tax',
  ];

  /**
   * Tax rate field instance.
   *
   * @var \Drupal\field\FieldConfigInterface
   */
  protected $field;

  /**
   * A sample tax type.
   *
   * @var \Drupal\commerce_tax\Entity\TaxTypeInterface
   */
  protected $taxType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    $this->installConfig(['commerce_product']);
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_tax_rate',
      'entity_type' => 'commerce_product_variation',
      'type' => 'commerce_tax_rate',
      'cardinality' => -1,
    ]);
    $field_storage->save();

    $tax_type = TaxType::create([
      'id' => 'eu_vat',
      'label' => 'EU VAT',
      'plugin' => 'european_union_vat',
    ]);
    $tax_type->save();
    $this->taxType = $this->reloadEntity($tax_type);
    $field = FieldConfig::create([
      'field_name' => 'field_tax_rate',
      'entity_type' => 'commerce_product_variation',
      'label' => 'Tax rate',
      'bundle' => 'default',
      'required' => TRUE,
      'settings' => [
        'tax_type' => 'eu_vat',
        'allowed_zones' => ['fr', 'de'],
      ],
    ]);
    $field->save();
    $this->field = $this->reloadEntity($field);

    EntityFormDisplay::load('commerce_product_variation.default.default')
      ->setComponent('field_tax_rate')
      ->save();
  }

}
