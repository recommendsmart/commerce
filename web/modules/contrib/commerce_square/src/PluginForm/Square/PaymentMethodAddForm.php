<?php

namespace Drupal\commerce_square\PluginForm\Square;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;
use Square\Environment;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Payment method add form for Square.
 */
class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * The 'commerce_square.settings' config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->config = $container->get('config.factory')->get('commerce_square.settings');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildCreditCardForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_square\Plugin\Commerce\PaymentGateway\Square $plugin */
    $plugin = $this->plugin;
    $configuration = $plugin->getConfiguration();
    $api_mode = ($configuration['mode'] == 'test') ? Environment::SANDBOX : Environment::PRODUCTION;

    $element['#attached']['library'][] = 'commerce_square/form';
    $element['#attached']['drupalSettings']['commerceSquare'] = [
      'applicationId' => $this->config->get($api_mode . '_app_id'),
      'locationId' => $configuration[$configuration['mode'] . '_location_id'],
      'apiMode' => $api_mode,
      'drupalSelector' => 'edit-' . str_replace('_', '-', implode('-', $element['#parents'])),
    ];
    $element['#attributes']['class'][] = 'square-form';
    // Populated by the JS library.
    $element['payment_token'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['square-payment-token']],
    ];
    $element['card_type'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['square-card-type']],
    ];
    $element['last4'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['square-last4']],
    ];
    $element['exp_month'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['square-exp-month']],
    ];
    $element['exp_year'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['square-exp-year']],
    ];

    // Display credit card logos in checkout form.
    if ($plugin->getConfiguration()['enable_credit_card_icons']) {
      $element['#attached']['library'][] = 'commerce_square/credit_card_icons';
      $element['#attached']['library'][] = 'commerce_payment/payment_method_icons';

      $supported_credit_cards = [];
      foreach ($plugin->getCreditCardTypes() as $credit_card) {
        $supported_credit_cards[] = $credit_card->getId();
      }

      $element['credit_card_logos'] = [
        '#theme' => 'commerce_square_credit_card_logos',
        '#credit_cards' => $supported_credit_cards,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateCreditCardForm(array &$element, FormStateInterface $form_state) {
    // The JS library performs its own validation.
  }

  /**
   * {@inheritdoc}
   */
  public function submitCreditCardForm(array $element, FormStateInterface $form_state) {
    // The payment gateway plugin will process the submitted payment details.
  }

}
