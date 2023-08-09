<?php

namespace Drupal\commerce_email;

use Drupal\commerce\MailHandlerInterface;
use Drupal\commerce_email\Entity\EmailInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Utility\Token;

class EmailSender implements EmailSenderInterface {

  /**
   * The mail handler.
   *
   * @var \Drupal\commerce\MailHandlerInterface
   */
  protected $mailHandler;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new EmailSender object.
   *
   * @param \Drupal\commerce\MailHandlerInterface $mail_handler
   *   The mail handler.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(MailHandlerInterface $mail_handler, Token $token, Connection $database) {
    $this->mailHandler = $mail_handler;
    $this->token = $token;
    $this->database = $database;
  }

  /**
   * {@inheritDoc}
   */
  public function send(EmailInterface $email, ContentEntityInterface $entity, array $related_entities = []) {
    $entity_type_id = $entity->getEntityTypeId();
    $event_entity_type_id = $email->getEvent()->getEntityTypeId();
    if ($entity_type_id != $event_entity_type_id) {
      throw new \InvalidArgumentException(sprintf('The email requires a "%s" entity, but a "%s" entity was given.', $event_entity_type_id, $entity_type_id));
    }

    // An array of keyed entities used to replace tokens.
    $replacements = [$entity_type_id => $entity];
    if (!empty($related_entities)) {
      $related_entity_type_ids = $email->getEvent()->getRelatedEntityTypeIds();
      $replacements = array_merge($replacements, array_combine($related_entity_type_ids, $related_entities));
    }

    $short_entity_type_id = str_replace('commerce_', '', $entity_type_id);
    $to = $this->prepareToString($email, $replacements);
    if (empty($to)) {
      return FALSE;
    }
    $subject = $this->replaceTokens($email->getSubject(), $replacements);
    $body = [
      '#type' => 'inline_template',
      '#template' => $this->replaceTokens($email->getBody(), $replacements),
      '#context' => [
        $short_entity_type_id => $entity,
      ],
    ];
    // @todo Figure out how to get the langcode generically.
    $params = [
      'id' => 'commerce_email_' . $email->id(),
      'from' => $this->replaceTokens($email->getFrom(), $replacements),
      'cc' => $this->replaceTokens($email->getCc(), $replacements),
      'bcc' => $this->replaceTokens($email->getBcc(), $replacements),
    ];

    return $this->mailHandler->sendMail($to, $subject, $body, $params);
  }

  /**
   * Replaces tokens in the given value.
   *
   * @param string $value
   *   The value.
   * @param array $replacements
   *   An array of keyed entities.
   *
   * @return string
   *   The value with tokens replaced.
   */
  protected function replaceTokens(string $value, array $replacements) {
    if (!empty($value)) {
      $value = $this->token->replace($value, $replacements, ['clear' => TRUE]);
    }

    return $value;
  }
  /**
   * Prepares value for the 'to' address.
   *
   * @param \Drupal\commerce_email\Entity\EmailInterface $email
   *   The email.
   * @param array $replacements
   *   An array of keyed entities.
   *
   * @return string
   *   Value for the 'to' address.
   */
  protected function prepareToString(EmailInterface $email, array $replacements) {
    if ($email->getToType() === 'role' && !empty($email->getToRole())) {
      /** @var \Drupal\Core\Database\Driver\mysql\Select $select */
      $select = $this->database->select('users_field_data', 'd');
      $select->join('user__roles', 'r', 'r.entity_id = d.uid');
      $select->condition('d.status', 1);
      $select->condition('r.roles_target_id', $email->getToRole());
      $select->fields('d', ['mail']);
      $select->groupBy('d.mail');
      $result = $select->execute()->fetchCol();
      $to = implode(', ', $result);
    }
    else {
      $to = $this->replaceTokens($email->getTo(), $replacements);
    }
    return $to;
  }

}
