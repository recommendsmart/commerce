<?php

namespace Drupal\commerce_email\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * @todo Add CC and BCC (and support multiple).
 * @todo Support a plain-text version of the body?
 */
interface EmailInterface extends ConfigEntityInterface {

  /**
   * Gets the email event.
   *
   * @return \Drupal\commerce_email\Plugin\Commerce\EmailEvent\EmailEventInterface
   *   The email event.
   */
  public function getEvent();

  /**
   * Gets the email event ID.
   *
   * @return string
   *   The email event ID.
   */
  public function getEventId();

  /**
   * Sets the email event ID.
   *
   * @param string $event_id
   *   The email event ID.
   *
   * @return $this
   */
  public function setEventId($event_id);

  /**
   * Gets the target entity type ID.
   *
   * This is the entity type for which the email will be built.
   * For example, "commerce_order".
   *
   * @return string
   *   The target entity type ID.
   */
  public function getTargetEntityTypeId();

  /**
   * Sets the target entity type ID.
   *
   * @param string $entity_type_id
   *   The target entity type ID.
   *
   * @return $this
   */
  public function setTargetEntityTypeId($entity_type_id);

  /**
   * Gets the "from" address.
   *
   * @return string
   *   The "from" address.
   */
  public function getFrom();

  /**
   * Sets the "from" address.
   *
   * @param string $from
   *   The "from" address.
   *
   * @return $this
   */
  public function setFrom($from);

  /**
   * Gets the "toType" option.
   *
   * @return string
   *   The "toType" option.
   */
  public function getToType();

  /**
   * Sets the "toType" option.
   *
   * @param string $to_type
   *   The "toType" value.
   *
   * @return $this
   */
  public function setToType($to_type);

  /**
   * Gets the "toRole" value.
   *
   * @return string
   *   The "toRole" value.
   */
  public function getToRole();

  /**
   * Sets the "toRole" value.
   *
   * @param string $to_role
   *   The "toRole" value.
   *
   * @return $this
   */
  public function setToRole($to_role);

  /**
   * Gets the "to" address.
   *
   * @return string
   *   The "to" address.
   */
  public function getTo();

  /**
   * Sets the "to" address.
   *
   * @param string $to
   *   The "to" address.
   *
   * @return $this
   */
  public function setTo($to);

  /**
   * Gets the "CC" address.
   *
   * @return string
   *   The "CC" address.
   */
  public function getCc();

  /**
   * Sets the "CC" address.
   *
   * @param string $cc
   *   The "CC" address.
   *
   * @return $this
   */
  public function setCc($cc);

  /**
   * Gets the "BCC" address.
   *
   * @return string
   *   The "BCC" address.
   */
  public function getBcc();

  /**
   * Sets the "BCC" address.
   *
   * @param string $bcc
   *   The "BCC" address.
   *
   * @return $this
   */
  public function setBcc($bcc);

  /**
   * Gets the subject.
   *
   * @return string
   *   The subject.
   */
  public function getSubject();

  /**
   * Sets the subject.
   *
   * @param string $subject
   *   The subject.
   *
   * @return $this
   */
  public function setSubject($subject);

  /**
   * Gets the body.
   *
   * @return string
   *   The body.
   */
  public function getBody();

  /**
   * Sets the body.
   *
   * @param string $body
   *   The body.
   *
   * @return $this
   */
  public function setBody($body);

  /**
   * Gets whether to email the email should be queued.
   *
   * @return bool
   *   TRUE if the email should be queued, FALSE otherwise.
   */
  public function shouldQueue();

  /**
   * Sets whether to email the email should be queued.
   *
   * @param bool $queue
   *   TRUE if the email should be queued, FALSE otherwise.
   *
   * @return $this
   */
  public function setQueue(bool $queue);

  /**
   * Gets the email conditions.
   *
   * @return \Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface[]
   *   The email conditions.
   */
  public function getConditions();

  /**
   * Gets the email condition operator.
   *
   * @return string
   *   The condition operator. Possible values: AND, OR.
   */
  public function getConditionOperator();

  /**
   * Sets the email condition operator.
   *
   * @param string $condition_operator
   *   The condition operator.
   *
   * @return $this
   */
  public function setConditionOperator($condition_operator);

  /**
   * Checks whether the email applies to the given entity.
   *
   * Ensures that the conditions pass.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   TRUE if email applies, FALSE otherwise.
   */
  public function applies(ContentEntityInterface $entity);

}
