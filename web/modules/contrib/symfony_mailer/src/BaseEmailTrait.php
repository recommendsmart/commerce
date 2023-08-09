<?php

namespace Drupal\symfony_mailer;

use Symfony\Component\Mime\Header\Headers;

/**
 * Trait that implements BaseEmailInterface, writing to a Symfony Email object.
 */
trait BaseEmailTrait {

  /**
   * The inner Symfony Email object.
   *
   * @var \Symfony\Component\Mime\Email
   */
  protected $inner;

  /**
   * The addresses.
   *
   * @var array
   */
  protected $addresses = [
    'From' => [],
    'Reply-To' => [],
    'To' => [],
    'Cc' => [],
    'Bcc' => [],
  ];

  /**
   * The sender.
   *
   * @var \Drupal\symfony_mailer\AddressInterface
   */
  protected $sender;

  /**
   * {@inheritdoc}
   */
  public function setSender($address) {
    $this->sender = Address::create($address);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSender(): ?AddressInterface {
    return $this->sender;
  }

  /**
   * {@inheritdoc}
   */
  public function setAddress(string $name, $addresses) {
    assert(isset($this->addresses[$name]));
    if ($name == 'To') {
      $this->valid(self::PHASE_BUILD);
    }
    $this->addresses[$name] = Address::convert($addresses);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setFrom($addresses) {
    return $this->setAddress('From', $addresses);
  }

  /**
   * {@inheritdoc}
   */
  public function getFrom(): array {
    return $this->addresses['From'];
  }

  /**
   * {@inheritdoc}
   */
  public function setReplyTo($addresses) {
    return $this->setAddress('Reply-To', $addresses);
  }

  /**
   * {@inheritdoc}
   */
  public function getReplyTo(): array {
    return $this->addresses['Reply-To'];
  }

  /**
   * {@inheritdoc}
   */
  public function setTo($addresses) {
    return $this->setAddress('To', $addresses);
  }

  /**
   * {@inheritdoc}
   */
  public function getTo(): array {
    return $this->addresses['To'];
  }

  /**
   * {@inheritdoc}
   */
  public function setCc($addresses) {
    return $this->setAddress('Cc', $addresses);
  }

  /**
   * {@inheritdoc}
   */
  public function getCc(): array {
    return $this->addresses['Cc'];
  }

  /**
   * {@inheritdoc}
   */
  public function setBcc($addresses) {
    return $this->setAddress('Bcc', $addresses);
  }

  /**
   * {@inheritdoc}
   */
  public function getBcc(): array {
    return $this->addresses['Bcc'];
  }

  /**
   * {@inheritdoc}
   */
  public function setPriority(int $priority) {
    $this->inner->priority($priority);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPriority(): int {
    return $this->inner->getPriority();
  }

  /**
   * {@inheritdoc}
   */
  public function setTextBody(string $body) {
    $this->inner->text($body);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTextBody(): ?string {
    return $this->inner->getTextBody();
  }

  /**
   * {@inheritdoc}
   */
  public function setHtmlBody(?string $body) {
    $this->valid(self::PHASE_POST_RENDER, self::PHASE_POST_RENDER);
    $this->inner->html($body);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHtmlBody(): ?string {
    $this->valid(self::PHASE_POST_SEND, self::PHASE_POST_RENDER);
    return $this->inner->getHtmlBody();
  }

  /**
   * {@inheritdoc}
   */
  public function attachFromPath(string $path, string $name = NULL, string $mimeType = NULL) {
    $this->inner->attachFromPath($path, $name, $mimeType);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function attachNoPath(string $body, string $name = NULL, string $mimeType = NULL) {
    $this->inner->attach($body, $name, $mimeType);
    return $this;
  }

  // @codingStandardsIgnoreStart
  // public function embedFromPath(string $path, string $name = null, string $contentType = null);
  // @codingStandardsIgnoreEnd

  /**
   * {@inheritdoc}
   */
  public function getHeaders(): Headers {
    return $this->inner->getHeaders();
  }

  /**
   * {@inheritdoc}
   */
  public function addTextHeader(string $name, string $value) {
    $this->getHeaders()->addTextHeader($name, $value);
    return $this;
  }

}
