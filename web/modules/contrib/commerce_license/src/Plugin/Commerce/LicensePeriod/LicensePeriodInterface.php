<?php

namespace Drupal\commerce_license\Plugin\Commerce\LicensePeriod;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines an interface for license renewal plugins.
 */
interface LicensePeriodInterface extends ConfigurableInterface, DependentPluginInterface, PluginFormInterface {

  /**
   * Represents an unlimited end time.
   *
   * @var integer
   */
  public const UNLIMITED = 0;

  /**
   * Gets the plugin label.
   *
   * @return string
   *   The plugin label.
   */
  public function getLabel(): string;

  /**
   * Gets the plugin description.
   *
   * @return string
   *   The plugin description.
   */
  public function getDescription(): string;

  /**
   * Calculates the end of the previous period.
   *
   * @param \DateTimeImmutable $date
   *   The date and time to begin the period from.
   *
   * @return \DateTimeImmutable|int
   *   The expiry date and time, or LicensePeriodInterface::UNLIMITED.
   */
  public function calculateStart(\DateTimeImmutable $date);

  /**
   * Calculates the end date and time for the period.
   *
   * @param \DateTimeImmutable $start
   *   The date and time to begin the period from.
   *
   * @return \DateTimeImmutable|int
   *   The expiry date and time, or LicensePeriodInterface::UNLIMITED.
   */
  public function calculateEnd(\DateTimeImmutable $start);

}
