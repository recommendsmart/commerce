<?php

namespace Drupal\commerce_avatax\Plugin\Commerce\TaxType;

use Drupal\commerce_avatax\AvataxLibInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_tax\Plugin\Commerce\TaxType\RemoteTaxTypeBase;
use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the AvaTax remote tax type.
 *
 * @CommerceTaxType(
 *   id = "avatax",
 *   label = "AvaTax",
 * )
 */
class Avatax extends RemoteTaxTypeBase {

  /**
   * The AvaTax library.
   *
   * @var \Drupal\commerce_avatax\AvataxLibInterface
   */
  protected $avataxLib;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Transliteration library.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * Constructs a new AvaTax object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\commerce_avatax\AvataxLibInterface $avatax_lib
   *   The AvaTax library.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   The transliteration library.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, AvataxLibInterface $avatax_lib, ConfigFactoryInterface $config_factory, TransliterationInterface $transliteration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $event_dispatcher);

    $this->avataxLib = $avatax_lib;
    $this->configFactory = $config_factory;
    $this->transliteration = $transliteration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('commerce_avatax.avatax_lib'),
      $container->get('config.factory'),
      $container->get('transliteration'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'display_inclusive' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function applies(OrderInterface $order) {
    $config = $this->configFactory->get('commerce_avatax.settings');
    return !$config->get('disable_tax_calculation');
  }

  /**
   * {@inheritdoc}
   */
  public function apply(OrderInterface $order) {
    $response_body = $this->avataxLib->transactionsCreate($order);

    // Do not go further unless there have been lines added.
    if (empty($response_body['lines'])) {
      return;
    }
    $currency_code = $order->getTotalPrice() ? $order->getTotalPrice()->getCurrencyCode() : $order->getStore()->getDefaultCurrencyCode();
    $adjustments = [];
    $applied_adjustments = [];
    foreach ($response_body['lines'] as $line) {
      $line_number = $line['lineNumber'];
      if (array_key_exists('details', $line)) {
        // Use details, if present. Per Avalara:
        // Tax details represent taxes being charged by various tax authorities.
        // Taxes that appear in the details collection are intended to be
        // displayed to the customer and charged as a 'tax' on the invoice.
        foreach ($line['details'] as $detail) {
          $label = isset($detail['taxName']) ? Html::escape($detail['taxName']) : $this->t('Sales tax');
          $values = [
            'amount' => $detail['tax'],
            'label' => $label,
          ];
          if (array_key_exists('rate', $detail)) {
            $values['rate'] = (string) $detail['rate'];
          }
          $adjustments[$line_number][] = $values;
        }
      }
      else {
        // If there are no details, fall back to the tax on the line.
        $adjustments[$line_number][] = [
          'amount' => $line['tax'],
          'label' => $this->t('Sales tax'),
        ];
      }
    }

    // Add tax adjustments to order items.
    foreach ($order->getItems() as $order_item) {
      $uuid = $order_item->uuid();
      if (!isset($adjustments[$uuid])) {
        continue;
      }
      $adjustment = $adjustments[$uuid];
      foreach ($adjustment as $adjustment_detail) {
        $label = $adjustment_detail['label'];
        $values = [
          'type' => 'tax',
          'label' => $label,
          'amount' => new Price((string) $adjustment_detail['amount'], $currency_code),
          'source_id' => $this->pluginId . '|' . $this->parentEntity->id() . '|' . $this->sanitizeString($label),
        ];
        if (array_key_exists('rate', $adjustment_detail)) {
          $values['percentage'] = $adjustment_detail['rate'];
        }
        $order_item->addAdjustment(new Adjustment($values));
      }
      $applied_adjustments[$order_item->uuid()] = $uuid;
    }

    // If we still have Tax adjustments to apply, add them to the order.
    $remaining_adjustments = array_diff_key($adjustments, $applied_adjustments);
    if (!$remaining_adjustments) {
      return;
    }
    // Add adjustments not associated with a specific order item.
    // e.g. taxes on shipping, handling, freight, etc...
    foreach ($remaining_adjustments as $remaining_adjustment) {
      foreach ($remaining_adjustment as $adjustment_detail) {
        $label = $adjustment_detail['label'];
        $values = [
          'type' => 'tax',
          'label' => $label,
          'amount' => new Price((string) $adjustment_detail['amount'], $currency_code),
          'source_id' => $this->pluginId . '|' . $this->parentEntity->id() . '|' . $this->sanitizeString($label),
        ];
        if (array_key_exists('rate', $adjustment_detail)) {
          $values['percentage'] = $adjustment_detail['rate'];
        }
        $order->addAdjustment(new Adjustment($values));
      }
    }
  }

  /**
   * Sanitizes a string, cloned from machine name.
   *
   * @param string $value
   *   The string to sanitize.
   *
   * @return string|null
   *   The sanitized string.
   */
  protected function sanitizeString(string $value): ?string {
    $new_value = $this->transliteration->transliterate($value, LanguageInterface::LANGCODE_DEFAULT, '_');
    $new_value = strtolower($new_value);
    $new_value = preg_replace('/[^a-z0-9_]+/', '_', $new_value);
    return preg_replace('/_+/', '_', $new_value);
  }

}
