<?php

namespace Drupal\symfony_mailer_bc;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Symfony Mailer Back-compatibility configuration override.
 */
class MailerBcConfigOverride implements ConfigFactoryOverrideInterface {

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];

    if (in_array('user.settings', $names)) {
      $overrides['user.settings']['notify'] = [
        'cancel_confirm' => TRUE,
        'password_reset' => TRUE,
        'status_activated' => TRUE,
        'status_blocked' => TRUE,
        'status_canceled' => TRUE,
        'register_admin_created' => TRUE,
        'register_no_approval_required' => TRUE,
        'register_pending_approval' => TRUE,
      ];
    }

    // The notification address is configured using Mailer Policy for
    // UpdateEmailBuilder. Set a dummy value in update.settings to force the
    // update module to send an email. NB UpdateEmailBuilder ignores the passed
    // 'To' address so the dummy value will never be used.
    if (in_array('update.settings', $names)) {
      $overrides['update.settings']['notification']['emails'] = ['dummy'];
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'MailerBcConfigOverride';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}
