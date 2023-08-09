<?php

namespace Drupal\commerce_avatax;

use Drupal\address\AddressInterface;
use Drupal\commerce_avatax\Resolver\ChainTaxCodeResolverInterface;
use Drupal\commerce_order\AdjustmentTypeManager;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_tax\Event\CustomerProfileEvent;
use Drupal\commerce_tax\Event\TaxEvents;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Variable;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\profile\Entity\ProfileInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The AvaTax integration library.
 */
class AvataxLib implements AvataxLibInterface {

  /**
   * The adjustment type manager.
   *
   * @var \Drupal\commerce_order\AdjustmentTypeManager
   */
  protected $adjustmentTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The chain tax code resolver.
   *
   * @var \Drupal\commerce_avatax\Resolver\ChainTaxCodeResolverInterface
   */
  protected $chainTaxCodeResolver;

  /**
   * The client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * The AvaTax configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * A cache of prepared customer profiles, keyed by order ID.
   *
   * @var \Drupal\profile\Entity\ProfileInterface
   */
  protected $profiles = [];

  /**
   * Constructs a new AvataxLib object.
   *
   * @param \Drupal\commerce_order\AdjustmentTypeManager $adjustment_type_manager
   *   The adjustment type manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_avatax\Resolver\ChainTaxCodeResolverInterface $chain_tax_code_resolver
   *   The chain tax code resolver.
   * @param \Drupal\commerce_avatax\ClientFactory $client_factory
   *   The client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(AdjustmentTypeManager $adjustment_type_manager, EntityTypeManagerInterface $entity_type_manager, ChainTaxCodeResolverInterface $chain_tax_code_resolver, ClientFactory $client_factory, ConfigFactoryInterface $config_factory, EventDispatcherInterface $event_dispatcher, LoggerInterface $logger, ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend) {
    $this->adjustmentTypeManager = $adjustment_type_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->chainTaxCodeResolver = $chain_tax_code_resolver;
    $this->config = $config_factory->get('commerce_avatax.settings');
    $this->client = $client_factory->createInstance($this->config->get());
    $this->eventDispatcher = $event_dispatcher;
    $this->logger = $logger;
    $this->moduleHandler = $module_handler;
    $this->cache = $cache_backend;
  }

  /**
   * {@inheritdoc}
   */
  public function transactionsCreate(OrderInterface $order, $type = 'SalesOrder') {
    $request_body = $this->prepareTransactionsCreate($order, $type);

    // Do not go further unless there have been lines added.
    if (empty($request_body['lines'])) {
      return [];
    }
    $cid = 'transactions_create:' . $order->id();
    // Check if the response was cached, and return it in case the request
    // about to be performed is different than the one in cache.
    if ($cached = $this->cache->get($cid)) {
      $cached_data = $cached->data;

      if (!empty($cached_data['response']) && isset($cached_data['request'])) {
        // The comparison would always fail if we wouldn't artificially override
        // the date here.
        $cached_data['request']['date'] = $request_body['date'];

        if ($cached_data['request'] == $request_body) {
          return $cached_data['response'];
        }
      }
    }

    $response_body = $this->doRequest('POST', 'api/v2/transactions/create', ['json' => $request_body]);
    if (!empty($response_body)) {
      $this->moduleHandler->alter('commerce_avatax_order_response', $response_body, $order);
      // Cache the request + the response for 24 hours.
      $expire = time() + (60 * 60 * 24);
      $this->cache->set($cid, [
        'request' => $request_body,
        'response' => $response_body,
      ], $expire);
    }
    return $response_body;
  }

  /**
   * {@inheritdoc}
   */
  public function transactionsVoid(OrderInterface $order) {
    $store = $order->getStore();
    // Attempt to get company code for specific store, otherwise, fallback to
    // the company code configured in the settings.
    if ($store->get('avatax_company_code')->isEmpty()) {
      $company_code = $this->config->get('company_code');
    }
    else {
      $company_code = $store->get('avatax_company_code')->value;
    }
    $transaction_code = 'DC-' . $order->uuid();

    return $this->doRequest('POST', "api/v2/companies/$company_code/transactions/$transaction_code/void", [
      'json' => [
        'code' => 'DocVoided',
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareTransactionsCreate(OrderInterface $order, $type = 'SalesOrder') {
    $store = $order->getStore();
    // Attempt to get company code for specific store, otherwise, fallback to
    // the company code configured in the settings.
    if ($store->get('avatax_company_code')->isEmpty()) {
      $company_code = $this->config->get('company_code');
    }
    else {
      $company_code = $store->get('avatax_company_code')->value;
    }
    $date = new DrupalDateTime();
    // Gather all the adjustment types.
    $adjustment_types = array_keys($this->adjustmentTypeManager->getDefinitions());
    $customer = $order->getCustomer();

    $currency_code = $order->getTotalPrice() ? $order->getTotalPrice()->getCurrencyCode() : $store->getDefaultCurrencyCode();
    $request_body = [
      'type' => $type,
      'companyCode' => $company_code,
      'date' => $date->format('c'),
      'code' => 'DC-' . $order->uuid(),
      'currencyCode' => $currency_code,
      'lines' => [],
    ];
    // Pass the tax exemption number|type if not empty.
    if (!$customer->isAnonymous()) {
      if ($customer->hasField('avatax_tax_exemption_number') && !$customer->get('avatax_tax_exemption_number')->isEmpty()) {
        $request_body['ExemptionNo'] = $customer->get('avatax_tax_exemption_number')->value;
      }
      if ($customer->hasField('avatax_tax_exemption_type') && !$customer->get('avatax_tax_exemption_type')->isEmpty()) {
        $request_body['CustomerUsageType'] = $customer->get('avatax_tax_exemption_type')->value;
      }
      if ($customer->hasField('avatax_customer_code') && !$customer->get('avatax_customer_code')->isEmpty()) {
        $request_body['customerCode'] = $customer->get('avatax_customer_code')->value;
      }
      else {
        $customer_code_field = $this->config->get('customer_code_field');
        // For authenticated users, if the avatax_customer_code field is empty,
        // use the field configured in config (mail|uid).
        if ($order->hasField($customer_code_field) && !$order->get($customer_code_field)->isEmpty()) {
          $customer_code = $customer_code_field === 'mail' ? $order->getEmail() : $order->getCustomerId();
          $request_body['customerCode'] = $customer_code;
        }
      }
    }

    // If the customer code could not be determined (either because the customer
    // is anonymous or the mail is empty, fallback to the logic below).
    if (!isset($request_body['customerCode'])) {
      $request_body['customerCode'] = $order->getEmail() ?: 'anonymous-' . $order->id();
    }

    $has_shipments = $order->hasField('shipments') && !$order->get('shipments')->isEmpty();
    foreach ($order->getItems() as $order_item) {
      $profile = $this->resolveCustomerProfile($order_item);

      // If we could not resolve a profile for the order item, do not add it
      // to the API request. There may not be an address available yet, or the
      // item may not be shippable and not attached to a shipment.
      if (!$profile) {
        continue;
      }
      $purchased_entity = $order_item->getPurchasedEntity();

      /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
      $address = $profile->get('address')->first();
      $line_item = [
        'number' => $order_item->uuid(),
        'quantity' => $order_item->getQuantity(),
        // When the transaction request is performed when an order is placed,
        // the order item already has a tax adjustment that we shouldn't send
        // to AvaTax.
        'amount' => $order_item->getAdjustedTotalPrice(array_diff($adjustment_types, ['tax']))->getNumber(),
      ];

      // Send the "SKU" as the "itemCode".
      if ($purchased_entity instanceof ProductVariationInterface) {
        $item_code = $purchased_entity->getSku();
        // Avalara has a max length of 50 for the itemCode.
        // If the sku is longer than 50, then we will pass the uuid instead.
        if (strlen($item_code) > 50) {
          $item_code = $purchased_entity->uuid();
        }
        $line_item['itemCode'] = $item_code;
      }
      $line_item['addresses'] = [
        'shipFrom' => self::formatAddress($store->getAddress()),
        'shipTo' => self::formatAddress($address),
      ];

      $line_item['taxCode'] = $this->chainTaxCodeResolver->resolve($order_item);
      $request_body['lines'][] = $line_item;
    }

    if ($has_shipments) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      foreach ($order->get('shipments')->referencedEntities() as $shipment) {
        if (is_null($shipment->getAmount())) {
          continue;
        }
        $request_body['lines'][] = [
          'taxCode' => $this->config->get('shipping_tax_code'),
          'number' => $shipment->uuid(),
          'description' => $shipment->label(),
          'amount' => $shipment->getAdjustedAmount(array_diff($adjustment_types, ['tax']))->getNumber(),
          'quantity' => 1,
          'addresses' => [
            'shipFrom' => self::formatAddress($store->getAddress()),
            'shipTo' => self::formatAddress($shipment->getShippingProfile()->get('address')->first()),
          ],
        ];
      }
    }

    // Send additional order adjustments as separate lines.
    foreach ($order->getAdjustments() as $adjustment) {
      // Skip shipping, fees and tax adjustments.
      if (in_array($adjustment->getType(), ['shipping', 'fee', 'tax'])) {
        continue;
      }
      $line_item = [
        // @todo Figure out which taxCode to use here.
        'taxCode' => 'P0000000',
        'description' => $adjustment->getLabel(),
        'amount' => $adjustment->getAmount()->getNumber(),
        'quantity' => 1,
        'addresses' => [
          'shipFrom' => self::formatAddress($store->getAddress()),
        ],
      ];
      // Take the "shipTo" from the first line if present, otherwise just ignore
      // the adjustment, because sending lines without an "addresses" key
      // is only possible when a global "addresses" is specified at the
      // document level, which isn't the case here.
      if (isset($request_body['lines'][0]['addresses']['shipTo'])) {
        $line_item['addresses']['shipTo'] = $request_body['lines'][0]['addresses']['shipTo'];
        $request_body['lines'][] = $line_item;
      }
    }

    if ($request_body['type'] === 'SalesInvoice') {
      $request_body['commit'] = TRUE;
    }
    $this->moduleHandler->alter('commerce_avatax_order_request', $request_body, $order);

    return $request_body;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveAddress(array $address) {
    $request_data = [
      'line1' => $address['address_line1'],
      'line2' => $address['address_line2'],
      'city' => $address['locality'],
      'region' => $address['administrative_area'],
      'country' => $address['country_code'],
      'postalCode' => $address['postal_code'],
    ];

    // Generate cid for cache.
    $cid = base64_encode(trim(serialize($request_data)));

    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    // Set to mixed check.
    $request_data['textCase'] = 'Mixed';

    $response_body = $this->doRequest('POST', 'api/v2/addresses/resolve', [
      'json' => $request_data,
    ]);

    // Cache the request + the response for 24 hours.
    $this->cache->set($cid, $response_body, time() + 86400);

    return $response_body;
  }

  /**
   * {@inheritdoc}
   */
  public function validateAddress(array $address) {
    $validation = [
      'valid' => FALSE,
      'errors' => [],
      'suggestion' => [],
      'fields' => [],
      'original' => [],
    ];

    $avatax_response = $this->resolveAddress($address);
    if (empty($avatax_response['address'])) {
      // If we don't get validated address or error message,
      // mark response as invalid.
      return $validation;
    }

    $original_address = $avatax_response['address'];
    $validation['original'] = self::formatDrupalAddress($original_address);

    // We have some errors. We don't need the validated address object while
    // it is going to be partial anyways.
    if (!empty($avatax_response['messages'])) {
      $error_fields_mapping = [
        'Address.City' => 'locality',
        'Address.Line0' => 'address_line1',
        'Address.Line1' => 'address_line2',
        'Address.PostalCode' => 'postal_code',
        'Address.Region' => 'administrative_area',
      ];
      $validation['errors'] = array_map(static function (array $message) use ($error_fields_mapping) {
        return $error_fields_mapping[$message['refersTo']];
      }, $avatax_response['messages']);
    }

    elseif (!empty($avatax_response['validatedAddresses'])) {
      $validation['valid'] = TRUE;
      // AvaTax always return one valid address.
      $validatedAddress = end($avatax_response['validatedAddresses']);

      unset($validatedAddress['addressType'], $validatedAddress['latitude'], $validatedAddress['longitude'], $validatedAddress['line_3']);
      $suggestion = array_filter(array_diff($validatedAddress, $avatax_response['address']));

      // Check if we required the full postal code in our suggestion. If the
      // provided address had a full postal code, always return the suggestion.
      if (!empty($suggestion['postalCode']) && strlen($original_address['postalCode']) === 5) {
        $postal_code_match = $this->config->get('address_validation.postal_code_match');
        // If we do not need full match, remove suggestion for postal code.
        if (!$postal_code_match && strpos($validatedAddress['postalCode'], $original_address['postalCode']) === 0) {
          unset($suggestion['postalCode']);
        }
      }

      if (!empty($suggestion)) {
        $validation['fields'] = array_filter(self::formatDrupalAddress($suggestion));
        $validation['suggestion'] = self::formatDrupalAddress($validatedAddress);
      }

    }

    return $validation;
  }

  /**
   * Format an address with AvaTax array data.
   *
   * @param array $address
   *   The AvaTax address to format.
   *
   * @return array
   *   Return a Drupal keyed formatted address.
   */
  public static function formatDrupalAddress(array $address): array {
    return [
      'address_line1' => $address['line1'] ?? '',
      'address_line2' => $address['line2'] ?? '',
      'locality' => $address['city'] ?? '',
      'administrative_area' => $address['region'] ?? '',
      'country_code' => $address['country'] ?? '',
      'postal_code' => $address['postalCode'] ?? '',
    ];
  }

  /**
   * Formats an address for use in the order request.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The address to format.
   *
   * @return array
   *   Return a formatted address for use in the order request.
   */
  protected static function formatAddress(AddressInterface $address): array {
    return [
      'line1' => $address->getAddressLine1(),
      'line2' => $address->getAddressLine2(),
      'city' => $address->getLocality(),
      'region' => $address->getAdministrativeArea(),
      'country' => $address->getCountryCode(),
      'postalCode' => $address->getPostalCode(),
    ];
  }

  /**
   * Resolves the customer profile for the given order item.
   *
   * Stolen from TaxTypeBase::resolveCustomerProfile().
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The customer profile, or NULL if not yet known.
   */
  protected function resolveCustomerProfile(OrderItemInterface $order_item): ?ProfileInterface {
    $order = $order_item->getOrder();
    if (!$order) {
      return NULL;
    }
    $customer_profile = $this->buildCustomerProfile($order);
    // Allow the customer profile to be altered, per order item.
    $event = new CustomerProfileEvent($customer_profile, $order_item);
    $this->eventDispatcher->dispatch($event, TaxEvents::CUSTOMER_PROFILE);
    $customer_profile = $event->getCustomerProfile();

    return $customer_profile;
  }

  /**
   * Builds a customer profile for the given order.
   *
   * Constructed only for the purposes of tax calculation, never saved.
   * The address comes one of the saved profiles, with the following priority:
   * - Shipping profile
   * - Billing profile
   * - Store profile (if the tax type is display inclusive)
   * The tax number comes from the billing profile, if present.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The customer profile, or NULL if not available yet.
   */
  protected function buildCustomerProfile(OrderInterface $order): ?ProfileInterface {
    $order_uuid = $order->uuid();
    if (!isset($this->profiles[$order_uuid])) {
      $order_profiles = $order->collectProfiles();
      $address = NULL;
      foreach (['shipping', 'billing'] as $scope) {
        if (isset($order_profiles[$scope])) {
          $address_field = $order_profiles[$scope]->get('address');
          if (!$address_field->isEmpty()) {
            $address = $address_field->getValue();
            break;
          }
        }
      }
      if (!$address) {
        // A customer profile isn't usable without an address. Stop here.
        return NULL;
      }

      $tax_number = NULL;
      if (isset($order_profiles['billing']) && $order_profiles['billing']->hasField('tax_number')) {
        $tax_number = $order_profiles['billing']->get('tax_number')->getValue();
      }
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      $this->profiles[$order_uuid] = $profile_storage->create([
        'type' => 'customer',
        'uid' => 0,
        'address' => $address,
        'tax_number' => $tax_number,
      ]);
    }

    return $this->profiles[$order_uuid];
  }

  /**
   * Performs an HTTP request to AvaTax.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $path
   *   The remote path. The base URL will be automatically appended.
   * @param array $parameters
   *   An array of fields to include with the request. Optional.
   *
   * @return array
   *   The response array.
   */
  protected function doRequest(string $method, string $path, array $parameters = []): array {
    $response_body = [];
    $error = FALSE;
    try {
      $response = $this->client->request($method, $path, $parameters);
      $response_body = Json::decode($response->getBody()->getContents());
    }
    catch (ClientException $e) {
      $error = TRUE;
      $body = $e->getResponse()->getBody()->getContents();
      $response_body = Json::decode($body);
      // Log a formatted error response.
      $this->logger->error('@method error @title <pre>@path</pre>Request: <pre>@request</pre>Response: <pre>@response</pre>', [
        '@method' => $method,
        '@path' => $path,
        '@title' => $response_body['title'] ?? $response_body['error']['message'] ?? 'Exception',
        '@request' => Variable::export($parameters),
        '@response' => Variable::export($response_body),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
    // Log the response/request if logging is enabled.
    if ($this->config->get('logging')) {
      $url = $this->client->getConfig('base_uri') . $path;
      // Log the response if it has not returned an error.
      if (!$error) {
        $this->logger->info('URL: <pre>$method @url</pre>Headers: <pre>@headers</pre>Request: <pre>@request</pre>Response: <pre>@response</pre>', [
          '@url' => $url,
          '@headers' => Variable::export($this->client->getConfig('headers')),
          '@request' => Variable::export($parameters),
          '@response' => Variable::export($response_body),
        ]);
      }
    }

    return $response_body;
  }

  /**
   * Sets the http client.
   *
   * @param \GuzzleHttp\Client $client
   *   The http client.
   *
   * @return $this
   */
  public function setClient(Client $client): self {
    $this->client = $client;
    return $this;
  }

}
