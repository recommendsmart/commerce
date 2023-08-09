<?php

namespace Drupal\commerce_braintree\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_payment\Entity\PaymentInterface;

/**
 * Defines the transaction data event.
 *
 * This enables other modules to add transaction data and metadata to the
 * transaction that will be sent to Braintree.
 *
 * @see \Drupal\commerce_braintree\Event\BraintreeEvents
 */
class TransactionDataEvent extends EventBase {

  /**
   * The payment.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentInterface
   */
  protected $payment;

  /**
   * The transaction data.
   *
   * @var array
   */
  protected $transactionData = [];

  /**
   * Constructs a new TransactionDataEvent object.
   *
   * @param array $transactionData
   *   The transaction data to submit to Braintree.
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   */
  public function __construct(array $transactionData, PaymentInterface $payment) {
    $this->payment = $payment;
    $this->transactionData = $transactionData;
  }

  /**
   * Get the transaction data.
   *
   * @return array
   *   The transaction data.
   */
  public function getTransactionData() {
    return $this->transactionData;
  }

  /**
   * Sets the transaction data data array.
   *
   * @param array $transaction_data
   *   The transaction data.
   *
   * @return $this
   */
  public function setTransactionData(array $transaction_data) {
    $this->transactionData = $transaction_data;
    return $this;
  }

  /**
   * Get the payment.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface
   *   The payment.
   */
  public function getPayment() {
    return $this->payment;
  }

}
