<?php

namespace Drupal\commerce_license\EventSubscriber;

use Drupal\commerce\Event\CommerceEvents;
use Drupal\commerce\Event\ReferenceablePluginTypesEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registers license types and license periods as referenceable.
 */
class ReferenceablePluginTypesSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CommerceEvents::REFERENCEABLE_PLUGIN_TYPES => 'onPluginTypes',
    ];
  }

  /**
   * Registers our plugin types as referenceable.
   *
   * @param \Drupal\commerce\Event\ReferenceablePluginTypesEvent $event
   *   The event.
   */
  public function onPluginTypes(ReferenceablePluginTypesEvent $event): void {
    $plugin_types = $event->getPluginTypes();
    $plugin_types['commerce_license_type'] = $this->t('License type');
    $plugin_types['commerce_license_period'] = $this->t('License period');
    $event->setPluginTypes($plugin_types);
  }

}
