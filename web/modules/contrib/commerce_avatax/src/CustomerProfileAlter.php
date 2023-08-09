<?php

namespace Drupal\commerce_avatax;

use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\commerce_order\Plugin\Commerce\InlineForm\CustomerProfile;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * The customer profile alter for address validation.
 */
class CustomerProfileAlter implements CustomerProfileAlterInterface {

  use DependencySerializationTrait;

  /**
   * AvaTax settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The AvaTax library.
   *
   * @var \Drupal\commerce_avatax\AvataxLib
   */
  protected $avataxLib;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrf;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new CustomerProfileAlter object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\commerce_avatax\AvataxLibInterface $avatax_lib
   *   The AvaTax library.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf
   *   The CSRF token generator.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AvataxLibInterface $avatax_lib, CsrfTokenGenerator $csrf, TimeInterface $time) {
    $this->config = $config_factory->get('commerce_avatax.settings');
    $this->avataxLib = $avatax_lib;
    $this->csrf = $csrf;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(array &$inline_form, FormStateInterface $form_state) {
    return $inline_form['#profile_scope'] === 'shipping' && (bool) $this->config->get('address_validation.enable') === TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function alter(array &$inline_form, FormStateInterface $form_state) {
    assert($inline_form['#inline_form'] instanceof CustomerProfile);
    $inline_form['#attached']['library'][] = 'commerce_avatax/address';
    $inline_form['#attributes']['class'][] = 'avatax-form';

    // Determine if we have existing profile or we adding new / editing
    // existing one.
    $rendered = isset($inline_form['rendered']);

    // Used to hold the proposed address suggestion.
    $inline_form['address_suggestion'] = ['#type' => 'hidden'];

    $endpoint = Url::fromRoute(
      'commerce_avatax.address_validator',
      [],
      ['query' => ['token' => $this->csrf->get('commerce-avatax/address-validator')]]
    )->setAbsolute();

    // If no countries were checked in the admin, validate US & CA addresses.
    $countries = $this->config->get('address_validation.countries');
    $countries = !empty($countries) ? $countries : ['US' => 'US', 'CA' => 'CA'];
    // Set ID for JS more precise targeting.
    $js_data = [
      'inline_id' => $inline_form['#id'],
      'countries' => $countries,
      'rendered' => $rendered,
      'endpoint' => $endpoint->toString(),
    ];

    $profile = $inline_form['#inline_form']->getEntity();
    if ($profile !== NULL) {
      $address = $profile->get('address')->first();
      if ($address instanceof AddressItem) {
        $js_data['address'] = $address->toArray();
        $js_data['country'] = !empty($address->getCountryCode()) ? $address->getCountryCode() : NULL;
      }
      if (!isset($js_data['country']) && isset($inline_form['address']['widget'][0]['address']['#default_value']['country_code'])) {
        $js_data['country'] = $inline_form['address']['widget'][0]['address']['#default_value']['country_code'];
      }
      $js_data['fields'] = [
        'address_line1',
        'address_line2',
        'locality',
        'administrative_area',
        'country_code',
        'postal_code',
      ];
    }
    $inline_form['#attached']['drupalSettings']['commerceAvatax'] = $js_data;

    // Add ours validation and submit handlers.
    $inline_form['#element_validate'][] = [$this, 'submitForm'];
  }

  /**
   * Submits the inline form.
   *
   * @param array $inline_form
   *   The inline form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$inline_form, FormStateInterface $form_state) {
    // Do not perform additional saves to the profile unless the element was
    // completed submitted. This submit handler executes on validation and
    // therefore runs during AJAX processing.
    if (!$form_state->isSubmitted()) {
      return;
    }

    assert($inline_form['#inline_form'] instanceof CustomerProfile);
    $inline_form_values = $form_state->getValue($inline_form['#parents']);
    $address_suggestion = $inline_form_values['address_suggestion'] ?? NULL;
    if ($address_suggestion && $address_suggestion !== 'original') {
      $profile = $inline_form['#inline_form']->getEntity();

      $address = $profile->get('address')->first();
      assert($address instanceof AddressItem);
      $suggestion = Json::decode(base64_decode($address_suggestion));
      $values = array_merge($address->toArray(), (array) $suggestion);
      $address->setValue($values);

      $profile->save();
    }
  }

}
