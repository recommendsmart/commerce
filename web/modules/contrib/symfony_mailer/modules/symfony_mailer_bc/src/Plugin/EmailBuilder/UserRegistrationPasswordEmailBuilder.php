<?php

namespace Drupal\symfony_mailer_bc\Plugin\EmailBuilder;

use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the Email Builder plug-in for user registration password module.
 *
 * @EmailBuilder(
 *   id = "user_registrationpassword",
 *   sub_types = {
 *     "register_confirmation_with_pass" = @Translation("Welcome (no approval required, password is set)"),
 *   },
 *   proxy = TRUE,
 * )
 */
class UserRegistrationPasswordEmailBuilder extends UserEmailBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    parent::build($email);
    $this->tokenOptions([
      'callback' => 'user_registrationpassword_mail_tokens',
      'clear' => TRUE,
    ]);
  }

}
