<?php

namespace Drupal\Tests\commerce_product_tax\Functional;

use Drupal\commerce_price\Price;
use Drupal\commerce_tax\Resolver\TaxRateResolverInterface;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Tests the tax rate default widget.
 *
 * @group commerce_product_tax
 */
class TaxRateDefaultWidgetTest extends CommerceBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_product',
    'commerce_tax',
    'commerce_product_tax',
  ];

  /**
   * A field to use in this test class.
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
   * The test variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $variation;

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_product',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createEntity('field_storage_config', [
      'field_name' => 'field_tax_rate',
      'entity_type' => 'commerce_product_variation',
      'type' => 'commerce_tax_rate',
      'cardinality' => -1,
    ]);

    $this->taxType = $this->createEntity('commerce_tax_type', [
      'id' => 'eu_vat',
      'label' => 'EU VAT',
      'plugin' => 'european_union_vat',
    ]);
    $this->field = $this->createEntity('field_config', [
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
    $display = commerce_get_entity_display('commerce_product_variation', 'default', 'form');
    $display->setComponent('field_tax_rate')->save();
    $this->variation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => 'foo',
      'title' => $this->randomString(),
      'price' => new Price('12.00', 'USD'),
    ]);
    $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => 'My product',
      'variations' => [$this->variation],
      'stores' => [$this->store],
    ]);
  }

  /**
   * Tests the widget.
   */
  public function testWidget() {
    $this->drupalGet($this->variation->toUrl('edit-form'));
    $select = $this->getSession()->getPage()->findField('field_tax_rate[]');
    $this->assertNotEmpty($select);
    $this->assertSession()->optionExists('field_tax_rate[]', 'fr|reduced');
    $this->assertSession()->optionExists('field_tax_rate[]', 'fr|' . TaxRateResolverInterface::NO_APPLICABLE_TAX_RATE);
    $this->assertSession()->optionExists('field_tax_rate[]', 'fr|standard');
    $this->assertSession()->optionExists('field_tax_rate[]', 'de|' . TaxRateResolverInterface::NO_APPLICABLE_TAX_RATE);
    $this->assertSession()->optionExists('field_tax_rate[]', 'de|reduced');
    $this->assertSession()->optionNotExists('field_tax_rate[]', 'it|reduced');
    $select->selectOption('fr|reduced');
    $select->selectOption('de|reduced', TRUE);
    $this->submitForm([], t('Save'));
    $this->variation = $this->reloadEntity($this->variation);
    $tax_rates = [];
    foreach ($this->variation->get('field_tax_rate') as $tax_rate_item) {
      $tax_rates[] = $tax_rate_item->value;
    }
    $this->assertFieldValues($tax_rates, ['fr|reduced', 'de|reduced']);
    $this->field->set('settings', [
      'tax_type' => 'eu_vat',
      'allowed_zones' => [],
    ])->save();

    $this->drupalGet($this->variation->toUrl('edit-form'));
    $option_field = $this->assertSession()->optionExists('field_tax_rate[]', 'fr|reduced');
    $this->assertTrue($option_field->hasAttribute('selected'));
    $option_field = $this->assertSession()->optionExists('field_tax_rate[]', 'de|reduced');
    $this->assertTrue($option_field->hasAttribute('selected'));

    /** @var \Drupal\commerce_tax\Plugin\Commerce\TaxType\LocalTaxTypeInterface $tax_type_plugin */
    $tax_type_plugin = $this->taxType->getPlugin();
    foreach ($tax_type_plugin->getZones() as $zone) {
      if ($zone->getId() == 'ic') {
        continue;
      }
      foreach ($zone->getRates() as $rate) {
        $this->assertSession()->optionExists('field_tax_rate[]', $zone->getId() . '|' . $rate->getId());
      }
    }
  }

}
