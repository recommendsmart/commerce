<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides the mailer helper service.
 */
interface MailerHelperInterface {

  /**
   * Parses an address string into Address structures.
   *
   * This function should only be used for back-compatibility and migration,
   * when old code has already encoded the addresses to a string. This function
   * converts back to human-readable format, ready for the symfony mailer
   * library to encode once more during sending! New code should store
   * configuration in human-readable format with a list of addresses with
   * display names.
   *
   * @todo This function is limited. It cannot handle display names, or emails
   * with characters that require special encoding.
   *
   * @param string $encoded
   *   Encoded address string.
   * @param string $langcode
   *   (Optional) Language code to add to the address.
   *
   * @return \Drupal\symfony_mailer\Address[]
   *   The parsed address structures.
   */
  public function parseAddress(string $encoded, string $langcode = NULL);

  /**
   * Converts an address array into Policy configuration.
   *
   * This function should only be used for migration.
   *
   * @param \Drupal\symfony_mailer\Address[] $addresses
   *   Array of address structures.
   *
   * @return array
   *   The equivalent policy configuration.
   */
  public function policyFromAddresses(array $addresses);

  /**
   * Transforms an HTML string into plain text.
   *
   * @param string $html
   *   The string to be transformed.
   *
   * @return string
   *   The transformed string.
   */
  public function htmlToText(string $html);

  /**
   * Returns the configuration factory.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  public function config();

  /**
   * Renders an element that lists policy related to a config entity.
   *
   * The element is designed for insertion into a config edit page for an
   * entity that has a related EmailBuilder.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   Config entity being edited.
   * @param string $sub_type
   *   Sub-type of the policies to show.
   *
   * @return array
   *   The render array.
   */
  public function renderEntityPolicy(ConfigEntityInterface $entity, string $sub_type);

  /**
   * Renders an element that lists policy for a specific type.
   *
   * The element is designed for insertion into a settings page for a module.
   *
   * @param string $type
   *   Type of the policies to show.
   *
   * @return array
   *   The render array.
   */
  public function renderTypePolicy(string $type);

}
