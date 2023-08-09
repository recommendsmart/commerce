<?php

namespace Drupal\commerce_license\EventSubscriber;

use Drupal\commerce_license\Event\LicenseEvent;
use Drupal\commerce_license\Event\LicenseEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The License Subscriber reacts to license events.
 */
class LicenseSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      LicenseEvents::LICENSE_PREDELETE => 'onDelete',
    ];
  }

  /**
   * Reacts to the deletion of a license.
   *
   * @param \Drupal\commerce_license\Event\LicenseEvent $event
   *   The order event.
   */
  public function onDelete(LicenseEvent $event): void {
    $license = $event->getLicense();
    // Revoke the license if it is active.
    if (in_array($license->getState()->getId(), [
      'active',
      'renewal_in_progress',
    ], TRUE)) {
      $license->getTypePlugin()->revokeLicense($license);
    }
  }

}
