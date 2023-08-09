<?php

namespace Drupal\commerce_license\Plugin\Commerce\LicensePeriod;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a period that never ends.
 *
 * @CommerceLicensePeriod(
 *   id = "unlimited",
 *   label = @Translation("Unlimited"),
 *   description = @Translation("No end date"),
 * )
 */
class Unlimited extends LicensePeriodBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => $this->t('Unlimited.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateStart(\DateTimeImmutable $date) {
    return self::UNLIMITED;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateEnd(\DateTimeImmutable $start) {
    return self::UNLIMITED;
  }

}
