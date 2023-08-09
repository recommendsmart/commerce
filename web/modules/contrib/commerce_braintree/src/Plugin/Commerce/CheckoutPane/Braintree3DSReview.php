<?php

namespace Drupal\commerce_braintree\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_braintree\ErrorHelper;
use Drupal\commerce_braintree\Plugin\Commerce\PaymentGateway\HostedFieldsInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_price\Calculator;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds 3DS authentication for Braintree vaulted/stored payment methods.
 *
 * This checkout pane is required for 3DS functionality. It ensures that the
 * last step in the checkout performs authentication. If the
 * customer's card is not enrolled in 3DS then the form will submit as normal.
 * Otherwise a modal will appear for the customer to authenticate.
 *
 * @CommerceCheckoutPane(
 *   id = "braintree_3ds_review",
 *   label = @Translation("Braintree 3DS review"),
 *   default_step = "review",
 *   wrapper_element = "container",
 * )
 */
class Braintree3DSReview extends CheckoutPaneBase {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->setLogger($container->get('logger.channel.commerce_payment'));
    return $instance;
  }

  /**
   * Sets the logger.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The new logger.
   *
   * @return $this
   */
  public function setLogger(LoggerInterface $logger) {
    $this->logger = $logger;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    // Only display for reusable/vaulted 3DS Braintree payment methods.
    if ($this->order->get('payment_method')->isEmpty() ||
      $this->order->get('payment_gateway')->isEmpty() ||
      !$this->order->get('payment_gateway')->entity) {
      return FALSE;
    }
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $this->order->get('payment_gateway')->entity;
    if (!$payment_gateway->getPlugin() instanceof HostedFieldsInterface) {
      return FALSE;
    }
    $configuration = $payment_gateway->getPlugin()->getConfiguration();
    if (empty($configuration['3d_secure'])) {
      return FALSE;
    }
    $payment_method = $this->order->get('payment_method')->entity;
    if (!$payment_method || !$payment_method->isReusable() || $payment_method->getType()->getPluginId() !== 'credit_card') {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->order->get('payment_method')->entity;
    /** @var \Drupal\commerce_braintree\Plugin\Commerce\PaymentGateway\HostedFieldsInterface $braintree_plugin */
    $braintree_plugin = $this->order->get('payment_gateway')->entity->getPlugin();

    // 3DS nonces are single-use, so a new nonce must be generated from the
    // stored payment method and authenticated for use in payment transaction.
    try {
      $result = $braintree_plugin->createPaymentMethodNonce($payment_method->getRemoteId());

      $pane_form['#attached']['library'][] = 'commerce_braintree/checkout-review';
      $amount = Calculator::trim($this->order->getBalance()->getNumber());
      $pane_form['#attached']['drupalSettings']['commerceBraintree'] = [
        'clientToken' => $braintree_plugin->generateClientToken(),
        'formId' => $complete_form['#id'],
        'amount' => $amount,
        'nonce' => $result->paymentMethodNonce->nonce,
        'bin' => $result->paymentMethodNonce->details['bin'],
        'email' => $this->order->getEmail(),
      ];
      // Unused non-hidden element included to ensure pane is built.
      $pane_form['payment_errors'] = [
        '#type' => 'markup',
        '#markup' => '<div id="payment-errors"></div>',
        '#weight' => -200,
      ];
      // Populated by the JS library.
      $pane_form['payment_method_nonce'] = [
        '#type' => 'hidden',
        '#attributes' => [
          'class' => ['braintree-nonce'],
        ],
      ];
    }
    catch (\Braintree\Exception $e) {
      ErrorHelper::handleException($e);
    }
    catch (PaymentGatewayException $e) {
      $this->logger->error($e->getMessage());
      $message = $this->t('We encountered an unexpected error processing your payment method. Please try again later.');
      $this->messenger()->addError($message);
      $this->checkoutFlow->redirectToStep($this->getErrorStepId());
    }

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($this->order);
    $cacheability->setCacheMaxAge(0);
    $cacheability->applyTo($pane_form);

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $error_step_id = $this->getErrorStepId();
    $values = $form_state->getValue($pane_form['#parents']);

    if (empty($values['payment_method_nonce'])) {
      $this->logger->error('Missing payment method nonce.');
      $message = $this->t('We encountered an unexpected error processing your payment method. Please try again later.');
      $this->messenger()->addError($message);
      $this->checkoutFlow->redirectToStep($error_step_id);
    }
    $braintree_plugin = $this->order->get('payment_gateway')->entity->getPlugin();
    $configuration = $braintree_plugin->configuration;
    try {
      $paymentMethodNonce = $braintree_plugin->findPaymentMethodNonce($values['payment_method_nonce']);
      $result = $paymentMethodNonce->threeDSecureInfo;
      $required = isset($configuration['3d_secure']) && ($configuration['3d_secure'] == 'required');
      ErrorHelper::handleErrors3ds($result, $required);
    }
    catch (\Braintree\Exception $e) {
      ErrorHelper::handleException($e);
    }
    catch (PaymentGatewayException $e) {
      $this->logger->error($e->getMessage());
      $message = $this->t('We encountered an unexpected error processing your payment method. Please try again later.');
      $this->messenger()->addError($message);
      $this->checkoutFlow->redirectToStep($error_step_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->order->get('payment_method')->entity;
    // The payment method nonce should be used for this one time purchase and
    // the previous tokenized payment method should be kept for future
    // purchases.
    $three_d_payment_method = $payment_method->createDuplicate();
    $three_d_payment_method->setRemoteId($values['payment_method_nonce']);
    $three_d_payment_method->setReusable(FALSE);
    $three_d_payment_method->save();
    $this->order->set('payment_method', $payment_method);
  }

  /**
   * Gets the step ID that the customer should be sent to on error.
   *
   * @return string
   *   The error step ID.
   */
  protected function getErrorStepId() {
    // Default to the step that contains the PaymentInformation pane.
    $step_id = $this->checkoutFlow->getPane('payment_information')->getStepId();
    if ($step_id == '_disabled') {
      // Can't redirect to the _disabled step. This could mean that isVisible()
      // was overridden to allow Braintree3DSReview to be used without a
      // payment_information pane, but this method was not modified.
      throw new \RuntimeException('Cannot get the step ID for the payment_information pane. The pane is disabled.');
    }

    return $step_id;
  }

}
