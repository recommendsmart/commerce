<?php

namespace Drupal\commerce_license\Form;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\Login;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for License dashboard form.
 *
 * @ingroup commerce_license
 */
class LicenseDashboardForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The trait manager.
   *
   * @var \Drupal\commerce\EntityTraitManagerInterface
   */
  protected $traitManager;

  /**
   * The checkout pane manager.
   *
   * @var \Drupal\commerce_checkout\CheckoutPaneManager
   */
  protected $paneManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The requirements.
   *
   * @var array
   */
  protected $requirements = [];

  /**
   * The valid checkout flows.
   *
   * @var array
   */
  protected $validCheckoutFlows = [];

  /**
   * The valid order types.
   *
   * @var array
   */
  protected $validOrderTypes = [];

  /**
   * The valid order item types.
   *
   * @var array
   */
  protected $validOrderItemTypes = [];

  /**
   * The valid product variation types.
   *
   * @var array
   */
  protected $validProductVariationTypes = [];

  /**
   * The valid product types.
   *
   * @var array
   */
  protected $validProductTypes = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): LicenseDashboardForm {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->traitManager = $container->get('plugin.manager.commerce_entity_trait');
    $instance->paneManager = $container->get('plugin.manager.commerce_checkout_pane');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Get implementations in the .install files as well.
    include_once './core/includes/install.inc';

    $this->analyzeCheckoutFlows();
    $this->analyzeOrderTypes();
    $this->analyzeOrderItemTypes();
    $this->analyzeProductVariationTypes();
    $this->analyzeProductTypes();
    $this->analyzeProducts();
    $this->analyzeVersion();

    $form['status_report'] = [
      '#type' => 'commerce_license_status_report',
      '#requirements' => $this->requirements,
    ];

    return $form;
  }

  /**
   * Analyze checkout flows.
   */
  protected function analyzeCheckoutFlows(): void {
    try {
      /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface[] $checkout_flows */
      $checkout_flows = $this->entityTypeManager->getStorage('commerce_checkout_flow')->loadMultiple();
      $this->validCheckoutFlows = [];
      foreach ($checkout_flows as $checkout_flow) {
        $checkout_flow_plugin = $checkout_flow->getPlugin();
        $configuration = $checkout_flow_plugin->getConfiguration();
        $panes = $configuration['panes'] ?? [];
        foreach ($panes as $pane_key => $pane) {
          if ($pane['step'] === '_disabled') {
            continue;
          }
          if (!$this->paneManager->hasDefinition($pane_key)) {
            $this->logger('commerce')->warning('The checkout flow <em>@checkout_flow</em> includes an unrecognized checkout pane <em>@checkout_pane</em>. You should review this checkout flow.', [
              '@checkout_flow' => $checkout_flow->label(),
              '@checkout_pane' => $pane_key,
            ]);
            continue;
          }
          $pane_instance = $this->paneManager->createInstance($pane_key, $pane, $checkout_flow_plugin);
          if ($pane_instance instanceof Login) {
            $allow_guest_checkout = $pane['allow_guest_checkout'] ?? TRUE;
            if (!$allow_guest_checkout) {
              $this->validCheckoutFlows[$checkout_flow->id()] = $checkout_flow;
              continue 2;
            }
          }
        }
      }
      if (!empty($this->validCheckoutFlows)) {
        $i = 0;
        foreach ($this->validCheckoutFlows as $valid_checkout_flow) {
          $this->requirements['commerce_license_checkout_flow_' . $i++] = [
            'title' => $this->t('Checkout flow'),
            'value' => $this->t('<a href="@url">@label</a>', [
              '@url' => $valid_checkout_flow->toUrl('edit-form')->toString(),
              '@label' => $valid_checkout_flow->label(),
            ]),
            'description' => $this->t('Checkout flow is defined with a login pane setting of <strong>Guest checkout: Not allowed</strong>.'),
            'severity' => REQUIREMENT_OK,
          ];
        }
      }
      else {
        $this->requirements['commerce_license_checkout_flow'] = [
          'title' => $this->t('Checkout flow'),
          'description' => $this->t('A <a href="@url">checkout flow</a> must be defined with a login pane setting of <strong>Guest checkout: Not allowed</strong>.', [
            '@url' => Url::fromRoute('entity.commerce_checkout_flow.collection')->toString(),
          ]),
          'severity' => REQUIREMENT_ERROR,
        ];
      }
    }
    catch (\Throwable $exception) {
      $this->handleException($exception, 'checkout_flow', $this->t('Checkout flow'));
    }
  }

  /**
   * Analyze order types.
   */
  protected function analyzeOrderTypes(): void {
    try {
      /** @var \Drupal\commerce_order\Entity\OrderTypeInterface[] $order_types */
      $order_types = $this->entityTypeManager->getStorage('commerce_order_type')->loadMultiple();
      $this->validOrderTypes = [];
      foreach ($order_types as $order_type) {
        $order_type_checkout_flow_id = $order_type->getThirdPartySetting('commerce_checkout', 'checkout_flow', 'default');
        if (array_key_exists($order_type_checkout_flow_id, $this->validCheckoutFlows)) {
          $this->validOrderTypes[$order_type->id()] = $order_type;
        }
      }
      if (!empty($this->validOrderTypes)) {
        $i = 0;
        foreach ($this->validOrderTypes as $valid_order_type) {
          $order_type_checkout_flow_id = $valid_order_type->getThirdPartySetting('commerce_checkout', 'checkout_flow', 'default');
          $order_type_checkout_flow = $this->validCheckoutFlows[$order_type_checkout_flow_id];
          $this->requirements['commerce_license_order_type_' . $i++] = [
            'title' => $this->t('Order type'),
            'value' => $this->t('<a href="@url">@label</a><br/>Checkout flow: @checkout_flow', [
              '@url' => $valid_order_type->toUrl('edit-form')->toString(),
              '@label' => $valid_order_type->label(),
              '@checkout_flow' => $order_type_checkout_flow->label(),
            ]),
            'description' => $this->t('Order type is defined using a valid checkout flow.'),
            'severity' => REQUIREMENT_OK,
          ];
        }
      }
      else {
        $this->requirements['commerce_license_order_type'] = [
          'title' => $this->t('Order type'),
          'description' => $this->t('An <a href="@url">order type</a> must be defined using a valid checkout flow.', [
            '@url' => Url::fromRoute('entity.commerce_order_type.collection')->toString(),
          ]),
          'severity' => REQUIREMENT_ERROR,
        ];
      }
    }
    catch (\Throwable $exception) {
      $this->handleException($exception, 'order_type', $this->t('Order type'));
    }
  }

  /**
   * Analyze order item types.
   */
  protected function analyzeOrderItemTypes(): void {
    try {
      /** @var \Drupal\commerce\Plugin\Commerce\EntityTrait\EntityTraitInterface $order_item_type_trait */
      $order_item_type_trait = $this->traitManager->createInstance('commerce_license_order_item_type');
      /** @var \Drupal\commerce_order\Entity\OrderItemTypeInterface[] $order_item_types */
      $order_item_types = $this->entityTypeManager->getStorage('commerce_order_item_type')->loadMultiple();
      $this->validOrderItemTypes = [];
      foreach ($order_item_types as $order_item_type) {
        if ($order_item_type->hasTrait('commerce_license_order_item_type') && array_key_exists($order_item_type->getOrderTypeId(), $this->validOrderTypes)) {
          $this->validOrderItemTypes[$order_item_type->id()] = $order_item_type;
        }
      }
      if (!empty($this->validOrderItemTypes)) {
        $i = 0;
        foreach ($this->validOrderItemTypes as $valid_order_item_type) {
          $order_type = $this->validOrderTypes[$valid_order_item_type->getOrderTypeId()];
          $this->requirements['commerce_license_order_item_type_' . $i++] = [
            'title' => $this->t('Order item type'),
            'value' => $this->t('<a href="@url">@label</a><br/>Order type: @order_type', [
              '@url' => $valid_order_item_type->toUrl('edit-form')->toString(),
              '@label' => $valid_order_item_type->label(),
              '@order_type' => $order_type->label(),
            ]),
            'description' => $this->t('Order item type is defined using a valid order type and has the trait: <strong>"@trait_label"</strong>.', [
              '@trait_label' => $order_item_type_trait->getLabel(),
            ]),
            'severity' => REQUIREMENT_OK,
          ];
        }
      }
      else {
        $this->requirements['commerce_license_order_item_type'] = [
          'title' => $this->t('Order item type'),
          'description' => $this->t('An <a href="@url">order item type</a> must be defined with the trait: <strong>"@trait_label"</strong>.<br/>Note: unless you will only be selling licenses, you should create a new order item type specifically for licenses.', [
            '@url' => Url::fromRoute('entity.commerce_order_item_type.collection')->toString(),
            '@trait_label' => $order_item_type_trait->getLabel(),
          ]),
          'severity' => REQUIREMENT_ERROR,
        ];
      }
    }
    catch (\Throwable $exception) {
      $this->handleException($exception, 'order_item_type', $this->t('Order item type'));
    }
  }

  /**
   * Analyze product variation types.
   */
  protected function analyzeProductVariationTypes(): void {
    try {
      /** @var \Drupal\commerce\Plugin\Commerce\EntityTrait\EntityTraitInterface $product_variation_type_trait */
      $product_variation_type_trait = $this->traitManager->createInstance('commerce_license');
      /** @var \Drupal\commerce_product\Entity\ProductVariationTypeInterface[] $product_variation_types */
      $product_variation_types = $this->entityTypeManager->getStorage('commerce_product_variation_type')->loadMultiple();
      $this->validProductVariationTypes = [];
      foreach ($product_variation_types as $product_variation_type) {
        if ($product_variation_type->hasTrait('commerce_license') && array_key_exists($product_variation_type->getOrderItemTypeId(), $this->validOrderItemTypes)) {
          $this->validProductVariationTypes[$product_variation_type->id()] = $product_variation_type;
        }
      }
      if (!empty($this->validProductVariationTypes)) {
        $i = 0;
        foreach ($this->validProductVariationTypes as $valid_product_variation_type) {
          $order_item_type = $this->validOrderItemTypes[$valid_product_variation_type->getOrderItemTypeId()];
          $this->requirements['commerce_license_product_variation_type_' . $i++] = [
            'title' => $this->t('Product variation type'),
            'value' => $this->t('<a href="@url">@label</a><br/>Order item type: @order_item_type', [
              '@url' => $valid_product_variation_type->toUrl('edit-form')->toString(),
              '@label' => $valid_product_variation_type->label(),
              '@order_item_type' => $order_item_type->label(),
            ]),
            'description' => $this->t('Product variation type is defined using a valid order item type and has the trait: <strong>"@trait_label"</strong>.', [
              '@trait_label' => $product_variation_type_trait->getLabel(),
            ]),
            'severity' => REQUIREMENT_OK,
          ];
        }
      }
      else {
        $this->requirements['commerce_license_product_variation_type'] = [
          'title' => $this->t('Product variation type'),
          'description' => $this->t('A <a href="@url">product variation type</a> must be defined with the trait: <strong>"@trait_label"</strong>.<br/>Note: unless you will only be selling licenses, you should create a new product variation type specifically for licenses.', [
            '@url' => Url::fromRoute('entity.commerce_product_variation_type.collection')->toString(),
            '@trait_label' => $product_variation_type_trait->getLabel(),
          ]),
          'severity' => REQUIREMENT_ERROR,
        ];
      }
    }
    catch (\Throwable $exception) {
      $this->handleException($exception, 'product_variation_type', $this->t('Product variation type'));
    }
  }

  /**
   * Analyze product types.
   */
  protected function analyzeProductTypes(): void {
    try {
      /** @var \Drupal\commerce_product\Entity\ProductTypeInterface[] $product_types */
      $product_types = $this->entityTypeManager->getStorage('commerce_product_type')->loadMultiple();
      $this->validProductTypes = [];
      foreach ($product_types as $product_type) {
        if (array_intersect($product_type->getVariationTypeIds(), array_keys($this->validProductVariationTypes))) {
          $this->validProductTypes[$product_type->id()] = $product_type;
        }
      }
      if (!empty($this->validProductTypes)) {
        $i = 0;
        foreach ($this->validProductTypes as $valid_product_type) {
          $product_variation_type_ids = $valid_product_type->getVariationTypeIds();
          $product_variation_type_labels = [];
          foreach ($this->validProductVariationTypes as $valid_product_variation_type) {
            if (in_array($valid_product_variation_type->id(), $product_variation_type_ids, TRUE)) {
              $product_variation_type_labels[] = $valid_product_variation_type->label();
            }
          }
          $this->requirements['commerce_license_product_type_' . $i++] = [
            'title' => $this->t('Product type'),
            'value' => $this->formatPlural(count($product_variation_type_labels), '<a href="@url">@label</a><br/>Product variation type: @product_variation_types', '<a href="@url">@label</a><br/>Product variation types: @product_variation_types', [
              '@url' => $valid_product_type->toUrl('edit-form')->toString(),
              '@label' => $valid_product_type->label(),
              '@product_variation_types' => implode(', ', $product_variation_type_labels),
            ]),
            'description' => $this->t('Product type is defined using valid product variation type(s).'),
            'severity' => REQUIREMENT_OK,
          ];
        }
      }
      else {
        $this->requirements['commerce_license_product_type'] = [
          'title' => $this->t('Product type'),
          'description' => $this->t('A <a href="@url">product type</a> must be defined with a valid product variation.', [
            '@url' => Url::fromRoute('entity.commerce_product_type.collection')->toString(),
          ]),
          'severity' => REQUIREMENT_ERROR,
        ];
      }
    }
    catch (\Throwable $exception) {
      $this->handleException($exception, 'product_type', $this->t('Product type'));
    }
  }

  /**
   * Analyze products.
   */
  protected function analyzeProducts(): void {
    try {
      $commerce_product_storage = $this->entityTypeManager->getStorage('commerce_product');
      $count = 0;
      $valid_product_ids = [];
      if (!empty($this->validProductTypes)) {
        $count = $commerce_product_storage->getQuery()->accessCheck(TRUE)->condition('type', array_keys($this->validProductTypes), 'IN')->count()->execute();
        $valid_product_ids = $commerce_product_storage->getQuery()->accessCheck(TRUE)->condition('type', array_keys($this->validProductTypes), 'IN')->range(0, 10)->execute();
      }
      /** @var \Drupal\commerce_product\Entity\ProductInterface[] $valid_products */
      $valid_products = $commerce_product_storage->loadMultiple($valid_product_ids);
      if (!empty($valid_products)) {
        $this->requirements['commerce_license_product'] = [
          'title' => $this->t('Product'),
          'value' => $this->formatPlural($count, '@count license product.', '@count license products. @product_count are listed below.', [
            '@product_count' => count($valid_products),
          ]),
          'description' => $this->t('Products are defined using a valid product type.'),
          'severity' => REQUIREMENT_OK,
        ];
        $i = 0;
        foreach ($valid_products as $valid_product) {
          $this->requirements['commerce_license_product_' . $i++] = [
            'title' => $this->t('Product'),
            'value' => $this->t('<a href="@url">@label</a><br/>Product type: @product_type', [
              '@url' => $valid_product->toUrl('canonical')->toString(),
              '@label' => $valid_product->label(),
              '@product_type' => $valid_product->bundle(),
            ]),
            'description' => $this->t('Product is defined using a valid product type.'),
            'severity' => REQUIREMENT_OK,
          ];
        }
      }
      else {
        $this->requirements['commerce_license_product'] = [
          'title' => $this->t('Product'),
          'description' => $this->t('A <a href="@url">product</a> must be defined with a valid product type.', [
            '@url' => Url::fromRoute('entity.commerce_product.collection')->toString(),
          ]),
          'severity' => REQUIREMENT_ERROR,
        ];
      }
    }
    catch (\Throwable $exception) {
      $this->handleException($exception, 'product', $this->t('Product'));
    }
  }

  /**
   * Analyze version.
   */
  protected function analyzeVersion(): void {
    try {
      $rp_module_is_enabled = $this->moduleHandler->moduleExists('recurring_period');
      if ($rp_module_is_enabled) {
        $this->requirements['commerce_license_version_3'] = [
          'title' => $this->t('Version 3'),
          'value' => $this->t('It appears that recurring_period is still configured on your site. Please follow the release notes before upgrading to version 3.'),
          'severity' => REQUIREMENT_ERROR,
        ];
      }
    }
    catch (\Throwable $exception) {
      $this->handleException($exception, 'version_3', $this->t('Version 3'));
    }
  }

  /**
   * Handle an exception.
   *
   * @param \Throwable $exception
   *   The exception.
   * @param string $type
   *   The type.
   * @param string $title
   *   The title.
   */
  protected function handleException(\Throwable $exception, string $type, string $title): void {
    $this->requirements['commerce_license_' . $type] = [
      'title' => $title,
      'description' => $this->t('Exception during analysis. <p>Error: @message</p><p>Trace: @trace</p>', [
        '@message' => $exception->getMessage(),
        '@trace' => $exception->getTraceAsString(),
      ]),
      'severity' => REQUIREMENT_ERROR,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'commerce_license_dashboard_form';
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

  }

}
