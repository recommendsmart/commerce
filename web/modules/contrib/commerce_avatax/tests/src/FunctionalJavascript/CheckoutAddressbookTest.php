<?php

namespace Drupal\Tests\commerce_avatax\FunctionalJavascript;

use Drupal\commerce_checkout\Entity\CheckoutFlow;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;

/**
 * Tests the address.
 *
 * @group commerce_avatax
 */
class CheckoutAddressbookTest extends CommerceWebDriverTestBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $product;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_avatax',
    'commerce_avatax_test',
    'commerce_product',
    'commerce_order',
    'commerce_cart',
    'commerce_checkout',
    'commerce_checkout_test',
    'commerce_shipping_test',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_checkout_flow',
      'administer views',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->placeBlock('commerce_cart');
    $this->placeBlock('commerce_checkout_progress');

    $variation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => 9.99,
        'currency_code' => 'USD',
      ],
    ]);

    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $this->product = $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => 'My product',
      'variations' => [$variation],
      'stores' => [$this->store],
    ]);

    $this->config('commerce_avatax.settings')->setData([
      'api_mode' => 'development',
      'account_id' => 'test',
      'company_code' => 'DEFAULT',
      'customer_code_field' => 'mail',
      'disable_commit' => FALSE,
      'disable_tax_calculation' => FALSE,
      'license_key' => 'test',
      'logging' => FALSE,
      'shipping_tax_code' => 'FR020100',
      'address_validation' => [
        'enable' => TRUE,
        'countries' => [
          'US' => 'US',
          'CA' => 'CA',
        ],
        'postal_code_match' => TRUE,
      ],
    ])->save();

    /** @var \Drupal\commerce_payment\Entity\PaymentGateway $gateway */
    $gateway = PaymentGateway::create([
      'id' => 'manual',
      'label' => 'Manual',
      'plugin' => 'manual',
    ]);
    $gateway->save();

    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_checkout', 'checkout_flow', 'shipping');
    $order_type->setThirdPartySetting('commerce_shipping', 'shipment_type', 'default');
    $order_type->save();

    // Create the order field.
    $field_definition = commerce_shipping_build_shipment_field_definition($order_type->id());
    $this->container->get('commerce.configurable_field_manager')
      ->createField($field_definition);

    // Install the variation trait.
    $trait_manager = $this->container->get('plugin.manager.commerce_entity_trait');
    $trait = $trait_manager->createInstance('purchasable_entity_shippable');
    $trait_manager->installTrait($trait, 'commerce_product_variation', 'default');

    /** @var \Drupal\commerce_shipping\Entity\PackageType $package_type */
    $package_type = $this->createEntity('commerce_package_type', [
      'id' => 'package_type_a',
      'label' => 'Package Type A',
      'dimensions' => [
        'length' => 20,
        'width' => 20,
        'height' => 20,
        'unit' => 'mm',

      ],
      'weight' => [
        'number' => 20,
        'unit' => 'g',
      ],
    ]);
    $this->container->get('plugin.manager.commerce_package_type')
      ->clearCachedDefinitions();

    $this->createEntity('commerce_shipping_method', [
      'name' => 'Standard shipping',
      'stores' => [$this->store->id()],
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Standard shipping',
          'rate_amount' => [
            'number' => '9.99',
            'currency_code' => 'USD',
          ],
        ],
      ],
    ]);
    $checkout_flow = CheckoutFlow::load('shipping');
    $checkout_flow_configuration = $checkout_flow->get('configuration');
    $checkout_flow_configuration['panes']['shipping_information']['auto_recalculate'] = FALSE;
    $checkout_flow->set('configuration', $checkout_flow_configuration);
    $checkout_flow->save();
  }

  /**
   * Tests anonymous checkout and inline form expanded.
   *
   * @covers \Drupal\commerce_avatax\CustomerProfileAlter
   */
  public function testCheckout() {
    $this->drupalLogout();
    $this->drupalGet($this->product->toUrl());
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->pageTextContains('1 item');
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->pageTextNotContains('Order Summary');

    $this->submitForm([], 'Continue as Guest');

    $address = [
      'given_name' => 'John',
      'family_name' => 'Smith',
      'address_line1' => '2000 Main Stree',
      'locality' => 'Irvine',
      'administrative_area' => 'CA',
      'postal_code' => '92610',
    ];
    $address_prefix = 'shipping_information[shipping_profile][address][0][address]';
    $page = $this->getSession()->getPage();
    $page->fillField('contact_information[email]', 'guest@example.com');
    $page->fillField($address_prefix . '[country_code]', 'US');
    $this->assertSession()->assertWaitOnAjaxRequest();
    foreach ($address as $property => $value) {
      $page->fillField($address_prefix . '[' . $property . ']', $value);
    }

    $page->findButton('Recalculate shipping')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Shipping method');
    $this->submitForm([], 'Continue to review');

    $this->assertSession()->waitForText('Confirm your shipping address');

    $this->assertSession()->pageTextContains('Use recommended');
    $this->assertSession()
      ->pageTextContains('Your shipping address is different from the post office records. We suggest you accept the recommended address to avoid shipping delays.');
    $this->getSession()->getPage()->findButton('Use recommended')->click();
    $this->assertSession()->waitForText('United States');

    // Validate that new profile is there.
    $this->assertSession()->pageTextNotContains($address['address_line1']);
    $this->assertSession()->pageTextNotContains($address['postal_code']);
    $this->assertSession()->pageTextContains('2000 Main St');
    $this->assertSession()->pageTextContains('92614-7202');

    $this->assertSession()->pageTextContains('Contact information');
    $this->assertSession()->pageTextContains('Payment information');
    $this->assertSession()->pageTextContains('Order Summary');
    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()
      ->pageTextContains('Your order number is 1. You can view your order on your account page when logged in.');
    $this->assertSession()->pageTextContains('0 items');

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = Order::load(1);
    $profiles = $order->collectProfiles();

    $shipping = $profiles['shipping'];
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $shipping->get('address')->first();

    $this->assertEquals('2000 Main St', $address->getAddressLine1());
    $this->assertEquals('92614-7202', $address->getPostalCode());
  }

  /**
   * Tests authenticated checkout and without existing profiles.
   *
   * @covers \Drupal\commerce_avatax\CustomerProfileAlter
   */
  public function testMultipleAddress() {
    // Create a default profile for the current user.
    $profile_1 = Profile::create([
      'type' => 'customer',
      'uid' => $this->adminUser->id(),
      'address' => [
        'country_code' => 'US',
        'administrative_area' => 'CA',
        'locality' => 'Irvine',
        'postal_code' => '92614',
        'address_line1' => '2000 Main Street',
        'organization' => 'Centarro',
        'given_name' => 'John',
        'family_name' => 'Smith',
      ],
      'data' => [
        'copy_to_address_book' => TRUE,
      ],
    ]);
    $profile_1->save();

    $profile_2 = Profile::create([
      'type' => 'customer',
      'uid' => $this->adminUser->id(),
      'default' => 1,
      'address' => [
        'country_code' => 'US',
        'administrative_area' => 'NC',
        'locality' => 'Durham',
        'postal_code' => '27001',
        'address_line1' => '512 S Mangu',
        'organization' => 'Centarro',
        'given_name' => 'John',
        'family_name' => 'Smith',
      ],
      'data' => [
        'copy_to_address_book' => TRUE,
      ],
    ]);
    $profile_2->save();

    $this->reloadEntity($profile_1);
    $this->reloadEntity($profile_2);

    $this->drupalGet($this->product->toUrl());
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->pageTextContains('1 item');
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->pageTextContains('Order Summary');

    $this->assertSession()->pageTextContains('2000 Main Street');
    $this->assertSession()->pageTextContains('Irvine, CA 92614');

    $this->submitForm([], 'Continue to review');

    $this->assertSession()->waitForText('Confirm your shipping address');
    $this->assertSession()->pageTextContains('Use recommended');

    $this->assertSession()
      ->pageTextContains('Your shipping address is different from the post office records. We suggest you accept the recommended address to avoid shipping delays.');
    $this->getSession()->getPage()->findButton('Use recommended')->click();

    $this->assertSession()->waitForText('United States');

    $this->assertSession()->pageTextContains('Contact information');
    $this->assertSession()->pageTextContains('Payment information');
    $this->assertSession()->pageTextContains('Order Summary');

    $this->assertSession()->pageTextContains('2000 Main St');
    $this->assertSession()->pageTextContains('Irvine, CA 92614-7202');

    $this->assertSession()->buttonExists('Pay and complete purchase');

    $this->drupalGet('checkout/1/order_information');
    $this->getSession()
      ->getPage()
      ->fillField('shipping_information[shipping_profile][select_address]', $profile_2->id());
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->submitForm([], 'Continue to review');

    $this->assertSession()->waitForText('Confirm your shipping address');
    $this->assertSession()->pageTextContains('Use as entered');
    $this->assertSession()->pageTextContains('512 S Mangum St');
    $this->assertSession()->pageTextContains('27701-3973');
    $this->assertSession()
      ->pageTextContains('Your shipping address is different from the post office records. We suggest you accept the recommended address to avoid shipping delays.');
    $this->getSession()->getPage()->pressButton('Use as entered');

    $this->assertSession()->waitForText('United States');

    $this->assertSession()->pageTextContains('Contact information');
    $this->assertSession()->pageTextContains('Payment information');
    $this->assertSession()->pageTextContains('Order Summary');

    $this->assertSession()->pageTextContains('512 S Mangu');
    $this->assertSession()->pageTextContains('Durham, NC 27001');

    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()
      ->pageTextContains('Your order number is 1. You can view your order on your account page when logged in.');
    $this->assertSession()->pageTextContains('0 items');
  }

  /**
   * Tests anonymous checkout shipping to a non-Avatax country.
   */
  public function testNonAvataxCheckout() {
    $this->drupalLogout();
    $this->drupalGet($this->product->toUrl());
    $this->submitForm([], 'Add to cart');
    $this->assertSession()->pageTextContains('1 item');
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->pageTextNotContains('Order Summary');

    $this->submitForm([], 'Continue as Guest');

    $address = [
      'given_name' => 'Jean',
      'family_name' => 'Dupont',
      'address_line1' => 'Rue de Sébastopol',
      'locality' => 'Toulouse',
      'postal_code' => '31000',
      'sorting_code' => '1',
    ];
    $address_prefix = 'shipping_information[shipping_profile][address][0][address]';
    $page = $this->getSession()->getPage();
    $page->fillField('contact_information[email]', 'guest@example.com');
    $page->fillField($address_prefix . '[country_code]', 'FR');

    $this->assertSession()->waitForField($address_prefix . '[sorting_code]');
    foreach ($address as $property => $value) {
      $page->fillField($address_prefix . '[' . $property . ']', $value);
    }
    $page->findButton('Recalculate shipping')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Shipping method');

    $this->submitForm([], 'Continue to review');

    $this->assertSession()->pageTextContains('Contact information');
    $this->assertSession()->pageTextContains('Payment information');
    $this->assertSession()->pageTextContains('Order Summary');
    $this->submitForm([], 'Pay and complete purchase');

    $this->assertSession()
      ->pageTextContains('Your order number is 1. You can view your order on your account page when logged in.');
    $this->assertSession()->pageTextContains('0 items');
  }

}
