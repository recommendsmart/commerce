<?php

namespace Drupal\commerce_avatax\Form;

use Drupal\commerce_avatax\ClientFactory;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for AvaTax settings.
 */
class ConfigSettingsForm extends ConfigFormBase {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Avatax client factory.
   *
   * @var \Drupal\commerce_avatax\ClientFactory
   */
  protected $clientFactory;

  /**
   * Constructs a ConfigSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Drupal\commerce_avatax\ClientFactory $client_factory
   *   The Avatax client factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger, ModuleHandlerInterface $module_handler, TranslationInterface $string_translation, ClientFactory $client_factory) {
    parent::__construct($config_factory);

    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
    $this->stringTranslation = $string_translation;
    $this->clientFactory = $client_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('module_handler'),
      $container->get('string_translation'),
      $container->get('commerce_avatax.client_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_avatax_config_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_avatax.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_avatax.settings');

    $form['configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuration'),
      '#open' => TRUE,
      '#id' => 'configuration-wrapper',
    ];
    $form['configuration']['api_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('API mode:'),
      '#default_value' => $config->get('api_mode'),
      '#options' => [
        'development' => $this->t('Development'),
        'production' => $this->t('Production'),
      ],
      '#required' => TRUE,
      '#description' => $this->t('The mode to use when calculating taxes.'),
    ];
    $form['configuration']['account_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account ID:'),
      '#default_value' => $config->get('account_id'),
      '#required' => TRUE,
      '#description' => $this->t('The account ID to use when calculating taxes.'),
    ];
    $form['configuration']['license_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('License key:'),
      '#default_value' => $config->get('license_key'),
      '#required' => TRUE,
      '#description' => $this->t('The license key to send to AvaTax when calculating taxes.'),
    ];
    $form['configuration']['company_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company code:'),
      '#default_value' => $config->get('company_code'),
      '#required' => TRUE,
      '#description' => $this->t('The default company code to send to AvaTax when calculating taxes, if company code is not set on the store of a given order.'),
    ];

    $form['configuration']['validate'] = [
      '#type' => 'button',
      '#value' => $this->t('Validate credentials'),
      '#validate' => [
        [$this, 'validateForm'],
      ],
      '#limit_validation_errors' => [
        ['account_id'],
        ['license_key'],
        ['company_code'],
      ],
      '#ajax' => [
        'callback' => [$this, 'validateCredentials'],
        'wrapper' => 'configuration-wrapper',
      ],
    ];
    $form['configuration']['address_validation'] = [
      '#type' => 'details',
      '#title' => $this->t('Address validation'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $address_validation_settings = $config->get('address_validation');
    $form['configuration']['address_validation']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate addresses in checkout'),
      '#description' => $this->t('If checked, addresses entered in checkout will be validated.'),
      '#default_value' => $address_validation_settings['enable'],
    ];

    $form['configuration']['address_validation']['countries'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Countries'),
      '#default_value' => $address_validation_settings['countries'],
      '#options' => [
        'CA' => $this->t('Canada'),
        'US' => $this->t('United States'),
      ],
      '#description' => $this->t('Restricts the address validation to the selected countries. If unchecked, US/CA addresses will be validated by default.'),
      '#states' => [
        'visible' => [
          ':input[name="address_validation[enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['configuration']['address_validation']['postal_code_match'] = [
      '#type' => 'checkbox',
      '#title' => t('Match on postal code'),
      '#description' => t('Postal codes are 9 digits, but most people enter the first 5 digits, do you want AvaTax to match all 9 digits?'),
      '#default_value' => $address_validation_settings['postal_code_match'],
      '#states' => [
        'visible' => [
          ':input[name="address_validation[enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['configuration']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
      '#open' => TRUE,
    ];
    $form['configuration']['advanced']['disable_tax_calculation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable tax calculation'),
      '#description' => $this->t("Enable this option if you don't want to use AvaTax for the tax calculation."),
      '#default_value' => $config->get('disable_tax_calculation'),
    ];
    $form['configuration']['advanced']['disable_commit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable document committing.'),
      '#description' => $this->t('Enable this option if you are only using the AvaTax service to display taxes and a backend system is performing the final commit of the tax document.'),
      '#default_value' => $config->get('disable_commit'),
      '#states' => [
        'invisible' => [
          ':input[name="disable_tax_calculation"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['configuration']['advanced']['logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable logging'),
      '#description' => $this->t('Enables detailed AvaTax transaction logging.'),
      '#default_value' => $config->get('logging'),
      '#states' => [
        'invisible' => [
          ':input[name="disable_tax_calculation"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['configuration']['advanced']['shipping_tax_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Shipping Tax Code'),
      '#default_value' => $config->get('shipping_tax_code'),
      '#required' => TRUE,
      '#description' => $this->t('Browse shipping codes in Avalara\'s <a href="@tax-code-finder" target="_blank">tax code finder</a>.', [
        '@tax-code-finder' => 'https://taxcode.avatax.avalara.com/search?category=Freight&tab=decision_tree',
      ]),

      '#access' => $this->moduleHandler->moduleExists('commerce_shipping'),
      '#states' => [
        'invisible' => [
          ':input[name="disable_tax_calculation"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['configuration']['advanced']['customer_code_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Customer code field'),
      '#default_value' => $config->get('customer_code_field'),
      '#options' => [
        'mail' => $this->t('Email'),
        'uid' => $this->t('Customer ID'),
      ],
      '#required' => TRUE,
      '#description' => $this->t('The "customerCode" field to use when the actual customer code field is empty (this setting affects authenticated users only).'),
      '#states' => [
        'invisible' => [
          ':input[name="disable_tax_calculation"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback for validation.
   */
  public function validateCredentials(array &$form, FormStateInterface $form_state) {
    return $form['configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();

    try {
      $client = $this->clientFactory->createInstance($values);
      $headers = [
        'headers' => [
          'Authorization' => 'Basic ' . base64_encode($values['account_id'] . ':' . $values['license_key']),
        ],
      ];
      $ping_request = $client->get('/api/v2/utilities/ping', $headers);
      $ping_request = Json::decode($ping_request->getBody()->getContents());
      if (!empty($ping_request['authenticated']) && $ping_request['authenticated'] === TRUE) {
        $companies_request = $client->get('/api/v2/companies', $headers);
        $companies = $companies_request->getBody()->getContents();
        $companies = Json::decode($companies);
        if (empty($companies['value']) || !in_array($values['company_code'], array_column($companies['value'], 'companyCode'))) {
          $form_state->setError($form['configuration']['company_code'], $this->t('Could not confirm the provided company code.'));
        }
        else {
          $this->messenger->addMessage($this->t('AvaTax response confirmed using the account and license key above.'));
        }
      }
      else {
        $form_state->setError($form['configuration']['account_id'], $this->t('Could not confirm the provided credentials.'));
        $form_state->setError($form['configuration']['license_key'], $this->t('Could not confirm the provided credentials.'));
      }
    }
    catch (\Exception $e) {
      $form_state->setError($form['configuration'], $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $address_validation = $form_state->getValue('address_validation');
    $address_validation['countries'] = array_filter($address_validation['countries']);

    $this->config('commerce_avatax.settings')
      ->set('api_mode', $form_state->getValue('api_mode'))
      ->set('account_id', $form_state->getValue('account_id'))
      ->set('company_code', $form_state->getValue('company_code'))
      ->set('address_validation', $address_validation)
      ->set('customer_code_field', $form_state->getValue('customer_code_field'))
      ->set('disable_commit', $form_state->getValue('disable_commit'))
      ->set('disable_tax_calculation', $form_state->getValue('disable_tax_calculation'))
      ->set('license_key', $form_state->getValue('license_key'))
      ->set('logging', $form_state->getValue('logging'))
      ->set('shipping_tax_code', $form_state->getValue('shipping_tax_code'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
