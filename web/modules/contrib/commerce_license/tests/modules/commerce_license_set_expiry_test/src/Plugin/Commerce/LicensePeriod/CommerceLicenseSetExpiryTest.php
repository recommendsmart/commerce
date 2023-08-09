<?php

namespace Drupal\commerce_license_set_expiry_test\Plugin\Commerce\LicensePeriod;

use Drupal\commerce_license\Plugin\Commerce\LicensePeriod\LicensePeriodBase;

/**
 * Provides the expiry test license period.
 *
 * @CommerceLicensePeriod(
 *   id = "commerce_license_set_expiry_test",
 *   label = @Translation("Set expiry test"),
 *   description = @Translation("Set expiry test"),
 * )
 */
class CommerceLicenseSetExpiryTest extends LicensePeriodBase {

  /**
   * {@inheritdoc}
   */
  public function calculateEnd(\DateTimeImmutable $start) {
    // Return a fixed date & time that we can test.
    return new \DateTimeImmutable('@12345', new \DateTimeZone('UTC'));
  }

}
