<?php

namespace Drupal\Tests\commerce_product_tax\Kernel;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_store\Entity\Store;
use Drupal\commerce_tax\Resolver\TaxRateResolverInterface;
use Drupal\profile\Entity\Profile;

/**
 * Tests the product tax rate resolver.
 *
 * @group commerce_product_tax
 */
class TaxRateResolverTest extends ProductTaxKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_reference_revisions',
    'profile',
    'state_machine',
    'commerce_order',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('profile');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
  }

  /**
   * Tests the resolver.
   */
  public function testResolver() {
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
    $variation = ProductVariation::create([
      'type' => 'default',
      'status' => 1,
      'sku' => 'foo',
      'title' => $this->randomString(),
      'price' => new Price('12.00', 'USD'),
      'field_tax_rate' => ['fr|reduced'],
    ]);
    $variation->save();
    $order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $variation,
    ]);
    $store = Store::create([
      'type' => 'default',
      'label' => 'My store',
      'address' => [
        'country_code' => 'FR',
      ],
      'prices_include_tax' => TRUE,
    ]);
    $store->save();
    $profile = Profile::create([
      'type' => 'customer',
      'uid' => 0,
      'address' => $store->getAddress(),
    ]);
    /** @var \Drupal\commerce_tax\Resolver\ChainTaxRateResolverInterface $chain_tax_rate_resolver */
    $chain_tax_rate_resolver = $this->container->get('commerce_tax.chain_tax_rate_resolver');
    $chain_tax_rate_resolver->setTaxType($this->taxType);
    $zones = $this->taxType->getPlugin()->getZones();
    $resolved_rate = $chain_tax_rate_resolver->resolve($zones['fr'], $order_item, $profile);
    $this->assertEquals('reduced', $resolved_rate->getId());

    $variation->set('field_tax_rate', ['fr|super_reduced']);
    $variation->save();

    $resolved_rate = $chain_tax_rate_resolver->resolve($zones['fr'], $order_item, $profile);
    $this->assertEquals('super_reduced', $resolved_rate->getId());

    $variation->set('field_tax_rate', ['de|reduced']);
    $variation->save();

    $resolved_rate = $chain_tax_rate_resolver->resolve($zones['de'], $order_item, $profile);
    $this->assertEquals('reduced', $resolved_rate->getId());

    $variation->set('field_tax_rate', ['fr|' . TaxRateResolverInterface::NO_APPLICABLE_TAX_RATE]);
    $variation->save();
    $resolved_rate = $chain_tax_rate_resolver->resolve($zones['fr'], $order_item, $profile);
    $this->assertEquals(TaxRateResolverInterface::NO_APPLICABLE_TAX_RATE, $resolved_rate);

    $variation->set('field_tax_rate', []);
    $variation->save();

    // The default tax rate resolver should resolve the standard rate.
    $resolved_rate = $chain_tax_rate_resolver->resolve($zones['fr'], $order_item, $profile);
    $this->assertEquals('standard', $resolved_rate->getId());
  }

}
