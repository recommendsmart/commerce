<?php

namespace Drupal\commerce_license;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_license\Plugin\Commerce\LicenseType\ExistingRightsFromConfigurationCheckingInterface;
use Drupal\commerce_order\AvailabilityCheckerInterface;
use Drupal\commerce_order\AvailabilityResult;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Prevents purchase of a license that grants rights the user already has.
 *
 * This does not check existing licenses, but checks the granted features
 * directly. For example, for a role license, this checks whether the user has
 * the role the license grants, rather than whether they have a license for
 * that role.
 *
 * Using an availability checker rather than an order processor, even though
 * they currently ultimately do the same thing (as availability checkers are
 * processed by AvailabilityOrderProcessor, which is itself an order processor),
 * because eventually availability checkers should deal with hiding the 'add to
 * cart' form -- see https://www.drupal.org/node/2710107.
 *
 * @see \Drupal\commerce_license\LicenseOrderProcessorMultiples
 */
class LicenseAvailabilityCheckerExistingRights implements AvailabilityCheckerInterface {

  use StringTranslationTrait;

  /**
   * The current active user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new LicenseAvailabilityCheckerExistingRights object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current active user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   */
  public function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(OrderItemInterface $order_item) {
    $purchased_entity = $order_item->getPurchasedEntity();

    if ($purchased_entity === NULL) {
      return FALSE;
    }

    // This applies only to product variations which have our license trait on
    // them. Check for the field the trait provides, as checking for the trait
    // on the bundle is expensive -- see https://www.drupal.org/node/2894805.
    if (!$purchased_entity->hasField('license_type') || $purchased_entity->get('license_type')->isEmpty()) {
      return FALSE;
    }

    // Don't do an availability check on recurring orders.
    if ($order_item->getOrder() && $order_item->getOrder()->bundle() === 'recurring') {
      return FALSE;
    }

    // This applies only to license types that implement the interface.
    $license_type_plugin = $purchased_entity->get('license_type')->first()->getTargetInstance();
    return $license_type_plugin instanceof ExistingRightsFromConfigurationCheckingInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function check(OrderItemInterface $order_item, Context $context) {
    // Hand over to the license type plugin configured in the product variation,
    // to let it determine whether the user already has what the license would
    // grant.
    $customer = $context->getCustomer();
    $purchased_entity = $order_item->getPurchasedEntity();

    // Load the full user entity for the plugin.
    $user = $this->entityTypeManager->getStorage('user')->load($customer->id());

    if (!$user || !$purchased_entity) {
      return AvailabilityResult::neutral();
    }

    // Handle licence renewal.
    /** @var \Drupal\commerce_license\Entity\LicenseInterface $existing_license */
    $existing_license = $this->entityTypeManager
      ->getStorage('commerce_license')
      ->getExistingLicense($purchased_entity, $user->id());

    if ($existing_license && $existing_license->canRenew()) {
      return AvailabilityResult::neutral();
    }

    // Shows a message to indicate window start time,
    // in case license is renewable but we're out of its renewable window.
    if ($existing_license && !is_null($existing_license->getRenewalWindowStartTime())) {
      $message = $this->getRenewalStartTimeMessage($existing_license->getRenewalWindowStartTime());
      return AvailabilityResult::unavailable($message);
    }

    return $this->checkPurchasable($purchased_entity, $user);
  }

  /**
   * Adds a renewalStartTimeMessage status message to queue.
   *
   * @param int|null $renewal_window_start_time
   *   The renewal window start time.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The renewal start time message.
   */
  private function getRenewalStartTimeMessage(?int $renewal_window_start_time): TranslatableMarkup {
    return $this->t('You have an existing license. You will be able to renew your license after @date.', [
      '@date' => $this->dateFormatter->format($renewal_window_start_time),
    ]);
  }

  /**
   * Checks if new license is eligible for purchase.
   *
   * Hand over to the license type plugin configured in the product variation,
   * to let it determine whether the user already has what the license would
   * grant. Adds a notPurchasableMessage status message to queue.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $entity
   *   The purchased entity.
   * @param \Drupal\Core\Entity\EntityInterface $user
   *   The user the license would be granted to.
   *
   * @return \Drupal\commerce_order\AvailabilityResult
   *   The availability of an order item.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function checkPurchasable(PurchasableEntityInterface $entity, EntityInterface $user): AvailabilityResult {
    $license_type_plugin = $entity->get('license_type')->first()->getTargetInstance();
    $existing_rights_result = $license_type_plugin->checkUserHasExistingRights($user);

    if (!$existing_rights_result->hasExistingRights()) {
      return AvailabilityResult::neutral();
    }

    // Show a message that includes the reason from the rights check.
    if ($user->id() == $this->currentUser->id()) {
      $rights_check_message = $existing_rights_result->getOwnerUserMessage();
    }
    else {
      $rights_check_message = $existing_rights_result->getOtherUserMessage();
    }
    $message = $rights_check_message . ' ' . $this->t('You may not purchase the @product-label product.', [
      '@product-label' => $entity->label(),
    ]);

    return AvailabilityResult::unavailable($message);
  }

}
