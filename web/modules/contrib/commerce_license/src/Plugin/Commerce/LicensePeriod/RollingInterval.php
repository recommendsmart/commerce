<?php

namespace Drupal\commerce_license\Plugin\Commerce\LicensePeriod;

use Drupal\commerce\Interval;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a period based on a rolling interval from the start date.
 *
 * @CommerceLicensePeriod(
 *   id = "rolling_interval",
 *   label = @Translation("Rolling interval"),
 *   description = @Translation("Provide a period based on a rolling interval"),
 * )
 */
class RollingInterval extends LicensePeriodBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      // The interval configuration.
      'interval' => [
        // The interval period. This is the ID of an interval plugin, for
        // example 'month'.
        'period' => '',
        // The interval. This is a value which multiplies the period.
        'interval' => '',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['interval'] = [
      '#type' => 'interval',
      '#title' => $this->t('Interval'),
      '#default_value' => $config['interval'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);

    $this->configuration['interval'] = $values['interval'];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateStart(\DateTimeImmutable $date) {
    // For a rolling interval, the start date is the same as the given date.
    return $date;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateEnd(\DateTimeImmutable $start) {
    // Get our interval values from our configuration.
    $config = $this->getConfiguration();
    $interval_configuration = $config['interval'];
    // The interval plugin ID is the 'period' value.
    $period = $interval_configuration['period'];
    $interval = $interval_configuration['interval'];
    switch ($period) {
      case 'fortnight':
        $period = 'day';
        $interval = 14 * $interval;
        break;

      case 'quarter':
        $period = 'month';
        $interval = 3 * $interval;
        break;

      case 'second':
        $period = 'minute';
        $interval = (int) round($interval / 60);
        if ($interval === 0) {
          $interval = 1;
        }
        break;
    }
    $interval_object = new Interval($interval, $period);
    $end = $interval_object->add(new DrupalDateTime('@' . $start->getTimestamp()));
    return \DateTimeImmutable::createFromMutable($end->getPhpDateTime());
  }

}
