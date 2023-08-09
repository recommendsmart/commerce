<?php

namespace Drupal\commerce_license\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles license renewal.
 *
 * Set the already existing license in the order item.
 */
class LicenseRenewalCartEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The license storage.
   *
   * @var \Drupal\commerce_license\LicenseStorage
   */
  protected $licenseStorage;

  /**
   * Constructs a new LicenseRenewalCartEventSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
      EntityTypeManagerInterface $entity_type_manager,
      MessengerInterface $messenger,
      DateFormatterInterface $date_formatter
  ) {
    $this->licenseStorage = $entity_type_manager->getStorage('commerce_license');
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[CartEvents::CART_ENTITY_ADD] = ['onCartEntityAdd', 100];
    return $events;
  }

  /**
   * Sets the already existing license in the order item.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The cart event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onCartEntityAdd(CartEntityAddEvent $event): void {
    $order_item = $event->getOrderItem();
    // Only act if the order item has a license reference field.
    if (!$order_item->hasField('license')) {
      return;
    }
    // We can't renew license types that don't allow us to find a license
    // given only a product variation and a user.
    $variation = $order_item->getPurchasedEntity();
    if ($variation === NULL || !$variation->hasField('license_type')) {
      return;
    }

    $cart = $event->getCart();
    $existing_license = $this->licenseStorage->getExistingLicense($variation, $cart->getCustomerId());
    if ($existing_license && $existing_license->canRenew()) {
      $order_item->set('license', $existing_license->id());
      $order_item->save();

      if ($existing_license->getState()->getId() !== 'renewal_in_progress') {
        $transition = $existing_license->getState()->getWorkflow()->getTransition('renewal_in_progress');
        $existing_license->getState()->applyTransition($transition);
        $existing_license->save();
      }

      // Shows a message with existing and extended dates when order completed.
      $expiresTime = $existing_license->getExpiresTime();
      $datetime = (new \DateTimeImmutable())->setTimestamp($expiresTime);
      $extendedDatetime = $existing_license->getExpirationPlugin()->calculateEnd($datetime);

      // @todo link here once there is user admin UI for licenses!
      $this->messenger->addStatus($this->t("You have an existing license, until @expires-time.\nThis will be extended until @extended-date when you complete this order.", [
        '@expires-time' => $this->dateFormatter->format($expiresTime),
        '@extended-date' => $this->dateFormatter->format($extendedDatetime->getTimestamp()),
      ]));
    }
    elseif ($existing_license) {
      // This will never be fired when expected,
      // since the CART_ENTITY_ADD is not fired at this point ?
      $renewal_window_start_time = $existing_license->getRenewalWindowStartTime();

      if (!is_null($renewal_window_start_time)) {
        $this->messenger->addStatus($this->t('You have an existing license. You will be able to renew your license after %date.', [
          '%date' => $this->dateFormatter->format($renewal_window_start_time),
        ]));
      }
    }
  }

}
