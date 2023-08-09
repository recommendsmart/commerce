<?php

namespace Drupal\commerce_email\Plugin\Commerce\EmailEvent;

use Drupal\commerce_recurring\Event\PaymentDeclinedEvent;
use Drupal\Component\EventDispatcher\Event;

/**
 * Provides the RecurringPaymentDeclined email event.
 *
 * @CommerceEmailEvent(
 *   id = "commerce_recurring_payment_declined",
 *   label = @Translation("Commerce Recurring - Payment declined"),
 *   event_name = "commerce_recurring.payment_declined",
 *   entity_type = "commerce_order",
 *   provider = "commerce_recurring",
 * )
 */
class RecurringPaymentDeclined extends EmailEventBase {

  /**
   * {@inheritdoc}
   */
  public function extractEntityFromEvent(Event $event) {
    assert($event instanceof PaymentDeclinedEvent);
    return $event->getOrder();
  }

}
