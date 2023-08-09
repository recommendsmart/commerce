<?php

namespace Drupal\commerce_license\Entity;

use Drupal\commerce_license\Plugin\Commerce\LicenseType\LicenseTypeInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for License entities.
 *
 * @ingroup commerce_license
 */
interface LicenseInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the License creation timestamp.
   *
   * @return int
   *   Creation timestamp of the License.
   */
  public function getCreatedTime();

  /**
   * Sets the License creation timestamp.
   *
   * @param int $timestamp
   *   The License creation timestamp.
   *
   * @return \Drupal\commerce_license\Entity\LicenseInterface
   *   The called License entity.
   */
  public function setCreatedTime(int $timestamp);

  /**
   * Gets the License expiration timestamp.
   *
   * @return int
   *   Expiration timestamp of the License.
   */
  public function getExpiresTime();

  /**
   * Sets the License expiration timestamp.
   *
   * @param int $timestamp
   *   The License expiration timestamp.
   *
   * @return \Drupal\commerce_license\Entity\LicenseInterface
   *   The called License entity.
   */
  public function setExpiresTime(int $timestamp);

  /**
   * Gets the granted timestamp.
   *
   * @return int
   *   The granted timestamp.
   */
  public function getGrantedTime();

  /**
   * Sets the granted timestamp.
   *
   * @param int $timestamp
   *   The granted timestamp.
   *
   * @return $this
   */
  public function setGrantedTime(int $timestamp);

  /**
   * Gets the renewal timestamp.
   *
   * @return int
   *   The renewal timestamp.
   */
  public function getRenewedTime();

  /**
   * Sets the renewal timestamp.
   *
   * @param int $timestamp
   *   The renewal timestamp.
   *
   * @return $this
   */
  public function setRenewedTime(int $timestamp);

  /**
   * The renewal window start time.
   *
   * Calculated in the case of a renewable license.
   *
   * @return int|null
   *   The renewal window start time.
   */
  public function getRenewalWindowStartTime();

  /**
   * Get an un-configured instance of the associated license type plugin.
   *
   * @return \Drupal\commerce_license\Plugin\Commerce\LicenseType\LicenseTypeInterface
   *   An un-configured instance of the associated license type plugin.
   */
  public function getTypePlugin();

  /**
   * Gets the type of expiration this license uses.
   *
   * @return string
   *   The ID of the plugin.
   */
  public function getExpirationPluginType();

  /**
   * Gets the expiration plugin for this license.
   *
   * @return \Drupal\commerce_license\Plugin\Commerce\LicensePeriod\LicensePeriodInterface
   *   The plugin configured for this license.
   */
  public function getExpirationPlugin();

  /**
   * Gets the license state.
   *
   * @return \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface
   *   The shipment state.
   */
  public function getState();

  /**
   * Gets the licensed entity that was purchased.
   *
   * @return \Drupal\commerce\PurchasableEntityInterface
   *   The licensed entity.
   */
  public function getPurchasedEntity();

  /**
   * Set values on the license from a configured license type plugin.
   *
   * This should be called when a license is created for an order, using the
   * configured license type plugin on the product variation that is being
   * purchased.
   *
   * @param \Drupal\commerce_license\Plugin\Commerce\LicenseType\LicenseTypeInterface $license_plugin
   *   The configured license type plugin.
   */
  public function setValuesFromPlugin(LicenseTypeInterface $license_plugin);

  /**
   * Implements the workflow_callback for the state field.
   *
   * @param \Drupal\commerce_license\Entity\LicenseInterface $license
   *   The license.
   *
   * @return string
   *   The workflow ID.
   *
   * @see \Drupal\state_machine\Plugin\Field\FieldType\StateItem
   */
  public static function getWorkflowId(LicenseInterface $license);

  /**
   * Checks if the license can be renewed at this time.
   *
   * @return bool
   *   TRUE if the license can be renewed. FALSE otherwise.
   */
  public function canRenew();

  /**
   * Gets the originating order.
   *
   * The order that originated the license creation.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *   The originated order, or NULL if not known.
   */
  public function getOriginatingOrder();

  /**
   * Sets the originating order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $originating_order
   *   The originating order.
   *
   * @return $this
   */
  public function setOriginatingOrder(OrderInterface $originating_order);

  /**
   * Gets the originating order ID.
   *
   * @return int|null
   *   The originating order ID, or NULL if not known.
   */
  public function getOriginatingOrderId();

}
