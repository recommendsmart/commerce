<?php

namespace Drupal\commerce_license\Plugin\Commerce\LicensePeriod;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a fixed date period.
 *
 * @CommerceLicensePeriod(
 *   id = "fixed_reference_date_interval",
 *   label = @Translation("Interval based on reference date"),
 *   description = @Translation("Provide a period until the next appropriate date based on a fixed reference date and interval."),
 * )
 */
class FixedReferenceDateInterval extends LicensePeriodBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'reference_date' => '',
      'interval' => [
        'period' => '',
        'interval' => '',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['reference_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Reference date'),
      '#default_value' => $config['reference_date'],
      '#required' => TRUE,
    ];
    $form['interval'] = [
      '#type' => 'interval',
      '#title' => 'Interval',
      '#default_value' => $config['interval'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);

    $this->configuration['reference_date'] = $values['reference_date'];
    $this->configuration['interval'] = $values['interval'];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateStart(\DateTimeImmutable $date) {
    $config = $this->getConfiguration();
    $interval = $this->getDateInterval();

    $reference_date = new \DateTimeImmutable($config['reference_date'], $date->getTimezone());
    $start_date = $reference_date;

    $is_reference_date_in_future = $start_date->diff($date)->invert;
    if ($is_reference_date_in_future) {
      // The reference date is in the future, so rewind it until it precedes
      // the start date.
      while ($start_date->diff($date)->invert == TRUE) {
        $start_date = $start_date->sub($interval);
      }
    }
    else {
      // The reference date is in the past, so fast forward it until the next
      // increment beyond the start date, then subtract one interval.
      while ($start_date->diff($date)->invert == FALSE) {
        $start_date = $start_date->add($interval);
      }
      $start_date = $start_date->sub($interval);
    }

    return $start_date;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateEnd(\DateTimeImmutable $start) {
    $config = $this->getConfiguration();
    $date_interval = $this->getDateInterval();

    return $this->findNextAppropriateDate($start, $config['reference_date'], $date_interval);
  }

  /**
   * Gets a \DateInterval object for the plugin's configuration.
   *
   * @return \DateInterval
   *   The date interval object.
   */
  protected function getDateInterval(): \DateInterval {
    $config = $this->getConfiguration();
    $interval_configuration = $config['interval'];
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

    return \DateInterval::createFromDateString($interval . ' ' . $period);
  }

  /**
   * Finds the next appropriate date after the start date.
   *
   * @param \DateTimeImmutable $start_date
   *   The start date.
   * @param string $reference_date_string
   *   A date string using the PHP date format 'Y-m-d'. The timezone will be
   *   assumed to be that of the $start_date.
   * @param \DateInterval $interval
   *   The interval.
   *
   * @return \DateTimeImmutable
   *   The end date.
   *
   * @throws \Exception
   */
  protected function findNextAppropriateDate(\DateTimeImmutable $start_date, string $reference_date_string, \DateInterval $interval): \DateTimeImmutable {
    $reference_date = new \DateTimeImmutable($reference_date_string, $start_date->getTimezone());

    $is_reference_date_in_future = $reference_date->diff($start_date)->invert;
    if ($is_reference_date_in_future) {
      // The reference date is in the future, so rewind it until it precedes
      // the start date, then increase it by one interval unit to find the
      // next appropriate date.
      while ($reference_date->diff($start_date)->invert == TRUE) {
        $reference_date = $reference_date->sub($interval);
      }
      $reference_date = $reference_date->add($interval);
    }
    else {
      // The reference date is in the past, so fast forward it until the next
      // increment beyond the start date to find the next appropriate date.
      while ($reference_date->diff($start_date)->invert == FALSE) {
        $reference_date = $reference_date->add($interval);
      }
    }

    return $reference_date;
  }

}
