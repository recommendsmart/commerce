<?php

namespace Drupal\commerce_email\Plugin\Commerce\EmailEvent;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Plugin\PluginBase;

/**
 * Provides a base class for email event plugins.
 */
abstract class EmailEventBase extends PluginBase implements EmailEventInterface {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEventName() {
    return $this->pluginDefinition['event_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return $this->pluginDefinition['entity_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRelatedEntityTypeIds() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function extractRelatedEntitiesFromEvent(Event $event) {
    return [];
  }

}
