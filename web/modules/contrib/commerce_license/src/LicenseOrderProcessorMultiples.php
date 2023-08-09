<?php

namespace Drupal\commerce_license;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Order processor that ensures only 1 of each license may be added to the cart.
 *
 * This is an order processor rather than an availability checker, as
 * \Drupal\commerce_order\AvailabilityOrderProcessor::check() removes the
 * entire order item if availability fails, whereas we only want to keep the
 * quantity at 1.
 *
 * @todo Figure out if the cart event subscriber covers all cases already.
 *
 * @see \Drupal\commerce_license\EventSubscriber\LicenseMultiplesCartEventSubscriber
 */
class LicenseOrderProcessorMultiples implements OrderProcessorInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * The license storage service.
   *
   * @var \Drupal\commerce_license\LicenseStorageInterface
   */
  protected $licenseStorage;

  /**
   * Constructs a new LicenseOrderProcessorMultiples object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager, UuidInterface $uuid) {
    $this->setMessenger($messenger);
    $this->entityTypeManager = $entity_type_manager;
    $this->licenseStorage = $entity_type_manager->getStorage('commerce_license');
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    // Collect licenses by purchased entity.
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface[] $purchased_entities */
    $purchased_entities = [];

    foreach ($order->getItems() as $order_item) {
      // Skip order items that do not have a license reference field.
      if (!$order_item->hasField('license')) {
        continue;
      }

      $purchased_entity = $order_item->getPurchasedEntity();

      if ($purchased_entity && $purchased_entity->hasField('license_type') && !$purchased_entity->get('license_type')->isEmpty()) {
        // Force the quantity to 1.
        if ($order_item->getQuantity() > 1) {
          $order_item->setQuantity(1);
          $this->messenger()->addWarning($this->t('You may only have a single %product-label in your cart.', [
            '%product-label' => $purchased_entity->label(),
          ]));
        }

        $product_variation_type_id = $purchased_entity->bundle();
        $product_variation_type = $this->entityTypeManager->getStorage('commerce_product_variation_type')->load($product_variation_type_id);
        $allow_renewal = $product_variation_type->getThirdPartySetting(
          'commerce_license',
          'allow_renewal',
          FALSE
        );

        $existing_license = $this->licenseStorage->getExistingLicense($purchased_entity, $order->getCustomerId());
        if ($existing_license) {
          $license_uuid = $existing_license->uuid();
        }
        else {
          $license_uuid = $this->uuid->generate();
        }

        // Check if this $purchased_entity is already in the cart.
        if (in_array($purchased_entity->uuid(), array_map(static function ($pe) {
          return $pe->uuid();
        }, $purchased_entities), TRUE)) {
          $order->removeItem($order_item);
          // Remove success message from user facing messages.
          $this->messenger()->deleteByType($this->messenger()::TYPE_STATUS);
          $this->messenger()->addError($this->t('You may only have one of %product-label in your cart.', [
            '%product-label' => $purchased_entity->label(),
          ]));
        }
        // If another $order_item resolves to the same product variation
        // and the variation allows renewal.
        elseif ($allow_renewal && array_key_exists($license_uuid, $purchased_entities)) {
          $order->removeItem($order_item);
          // Remove success message from user facing messages.
          $this->messenger()->deleteByType($this->messenger()::TYPE_STATUS);
          $this->messenger()->addError($this->t('Removed %removed-product-label as %product-label in your cart already grants the same license.', [
            '%product-label' => $purchased_entities[$license_uuid]->label(),
            '%removed-product-label' => $purchased_entity->label(),
          ]));
        }
        // Add this to the array to check against.
        else {
          $purchased_entities[$license_uuid] = $purchased_entity;
        }
      }
    }
  }

}
