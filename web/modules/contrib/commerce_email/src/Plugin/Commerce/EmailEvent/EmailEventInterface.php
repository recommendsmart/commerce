<?php

namespace Drupal\commerce_email\Plugin\Commerce\EmailEvent;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Defines the interface for email events.
 */
interface EmailEventInterface extends PluginInspectionInterface {

  /**
   * Gets the email event label.
   *
   * @return string
   *   The email event label.
   */
  public function getLabel();

  /**
   * Gets the Symfony event name.
   *
   * @return string
   *   The Symfony event name.
   */
  public function getEventName();

  /**
   * Gets the email event entity type ID.
   *
   * This is the entity type ID of the entity the event is fired for.
   *
   * @return string
   *   The email event entity type ID.
   */
  public function getEntityTypeId();

  /**
   * Extracts the entity from the given event.
   *
   * @param \Drupal\Component\EventDispatcher\Event $event
   *   The event.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The extracted entity.
   */
  public function extractEntityFromEvent(Event $event);

  /**
   * Gets the related entity type IDs.
   *
   * Until Drupal core supports more than one entity of the same type, it is up
   * to the plugin to ensure the related entity types are different from the
   * primary entity type and from each other.
   *
   * @see https://www.drupal.org/project/drupal/issues/1920688
   *
   * @return array
   *   The related entity type IDs.
   */
  public function getRelatedEntityTypeIds();

  /**
   * Extracts the related entities from the given event.
   *
   * Related entities should be positioned in the array in the same order as
   * the return value of getRelatedEntityTypeIds(). If a related entity cannot
   * be loaded for some reason, a NULL value should be inserted in the array in
   * its place.
   *
   * @param \Drupal\Component\EventDispatcher\Event $event
   *   The event.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   The extracted related entities.
   */
  public function extractRelatedEntitiesFromEvent(Event $event);

}
