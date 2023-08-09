<?php

namespace Drupal\Tests\commerce_braintree\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\SoftDeclineException;
use Drupal\commerce_price\Price;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Tests the Braintree SDK integration.
 *
 * @group commerce_braintree
 */
class BraintreeApiIntegrationTest extends OrderKernelTestBase {

  public static $modules = [
    'commerce_payment',
    'commerce_braintree',
  ];

  /**
   * The test gateway.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGateway
   */
  protected $gateway;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_payment');
    $this->installEntitySchema('commerce_payment_method');
    $this->installConfig('commerce_payment');

    /** @var \Drupal\commerce_payment\Entity\PaymentGateway $gateway */
    $gateway = PaymentGateway::create([
      'id' => 'braintree',
      'label' => 'Braintree',
      'plugin' => 'braintree_hostedfields',
    ]);
    $gateway->getPlugin()->setConfiguration([
      'merchant_id' => 'hy3tktc463w6g7pw',
      'public_key' => 'fsspfgwhnm6by9gk',
      'private_key' => '671d13c9dee5815425f954df590bfc98',
      'merchant_account_id' => [
        'USD' => 'commerceguys',
      ],
      'display_label' => 'Braintree',
      'payment_method_types' => ['credit_card'],
    ]);
    $gateway->save();
    $this->gateway = $gateway;
  }

  /**
   * Tests creating a payment.
   *
   * @dataProvider dataProviderBillingProfile
   */
  public function testCreatePayment($billing_profile) {
    /** @var \Drupal\commerce_braintree\Plugin\Commerce\PaymentGateway\HostedFieldsInterface $gateway_plugin */
    $gateway_plugin = $this->gateway->getPlugin();
    $gateway_plugin->createPayment($this->generateTestPayment($billing_profile, '10.00'));
  }

  /**
   * Tests creating a payment, with insufficient funds.
   *
   * @dataProvider dataProviderBillingProfile
   */
  public function testCreatePaymentInsufficentFunds($billing_profile) {
    $this->expectException(SoftDeclineException::class);
    $this->expectExceptionMessage('Insufficient Funds (2001 : Insufficient Funds)');
    /** @var \Drupal\commerce_braintree\Plugin\Commerce\PaymentGateway\HostedFieldsInterface $gateway_plugin */
    $gateway_plugin = $this->gateway->getPlugin();
    $gateway_plugin->createPayment($this->generateTestPayment($billing_profile, '2001.00'));
    throw new \Exception('Charge should not have been successful.');
  }

  /**
   * Tests creating a payment, with processor declined.
   *
   * @dataProvider dataProviderBillingProfile
   */
  public function testCreatePaymentProcessorDeclined($billing_profile) {
    $this->expectException(SoftDeclineException::class);
    $this->expectExceptionMessage('Processor Declined (2101 : )');
    /** @var \Drupal\commerce_braintree\Plugin\Commerce\PaymentGateway\HostedFieldsInterface $gateway_plugin */
    $gateway_plugin = $this->gateway->getPlugin();
    $gateway_plugin->createPayment($this->generateTestPayment($billing_profile, '2101.00'));
    throw new \Exception('Charge should not have been successful.');
  }

  /**
   * Tests creating a payment, with application incomplete.
   *
   * @dataProvider dataProviderBillingProfile
   */
  public function testCreatePaymentApplicationIncomplete($billing_profile) {
    $this->expectException(HardDeclineException::class);
    $this->expectExceptionMessage('Rejected by the gateway. Reason: application_incomplete');
    /** @var \Drupal\commerce_braintree\Plugin\Commerce\PaymentGateway\HostedFieldsInterface $gateway_plugin */
    $gateway_plugin = $this->gateway->getPlugin();
    $gateway_plugin->createPayment($this->generateTestPayment($billing_profile, '5001.00'));
    throw new \Exception('Charge should not have been successful.');
  }

  /**
   * Tests a re-used nonce.
   *
   * @dataProvider dataProviderBillingProfile
   */
  public function testCreatePaymentRejectedConsumedNonce($billing_profile) {
    $this->expectException(InvalidRequestException::class);
    $this->expectExceptionMessage('Cannot use a paymentMethodNonce more than once.');
    /** @var \Drupal\commerce_braintree\Plugin\Commerce\PaymentGateway\HostedFieldsInterface $gateway_plugin */
    $gateway_plugin = $this->gateway->getPlugin();
    $gateway_plugin->createPayment($this->generateTestPayment($billing_profile, '10.00', \Braintree\Test\Nonces::$consumed));
  }

  /**
   * Data provider for all test methods.
   */
  public function dataProviderBillingProfile() {
    yield [TRUE];
    yield [FALSE];
  }

  /**
   * Generates a test payment to send over the Braintree gateway.
   *
   * @param bool $billing_profile
   *   Whether a billing profile should be added.
   * @param string $amount
   *   The test amount.
   * @param string $nonce
   *   The test nonce.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface
   *   The test payment.
   *
   * @see https://developers.braintreepayments.com/reference/general/testing/php#test-amounts
   */
  protected function generateTestPayment($billing_profile, $amount, $nonce = NULL) {
    if ($nonce === NULL) {
      $nonce = \Braintree\Test\Nonces::$transactable;
    }

    $user = $this->createUser();
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'state' => 'draft',
      'mail' => 'text@example.com',
      'uid' => $user->id(),
      'ip_address' => '127.0.0.1',
    ]);
    $order->save();

    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = PaymentMethod::create([
      'type' => 'credit_card',
      'payment_gateway' => $this->gateway->id(),
      'uid' => $user->id(),
      'remote_id' => $nonce,
    ]);
    $payment_method->setReusable(FALSE);

    if ($billing_profile) {
      /** @var \Drupal\profile\Entity\ProfileInterface $profile */
      $profile = Profile::create([
        'type' => 'customer',
        'address' => [
          'country_code' => 'US',
          'postal_code' => '53177',
          'locality' => 'Milwaukee',
          'address_line1' => 'Pabst Blue Ribbon Dr',
          'administrative_area' => 'WI',
          'given_name' => 'Frederick',
          'family_name' => 'Pabst',
        ],
        'uid' => $user->id(),
      ]);
      $profile->save();
      $payment_method->setBillingProfile($profile);
    }

    $payment_method->save();

    $payment = Payment::create([
      'state' => 'new',
      'amount' => new Price($amount, 'USD'),
      'payment_gateway' => $this->gateway->id(),
      'order_id' => $order->id(),
    ]);
    $payment->payment_method = $payment_method;
    return $payment;
  }

}
