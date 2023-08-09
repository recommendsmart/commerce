<?php

namespace Drupal\Tests\commerce_avatax\Unit;

use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\commerce_avatax\AvataxLibInterface;
use Drupal\commerce_avatax\CustomerProfileAlter;
use Drupal\commerce_order\Plugin\Commerce\InlineForm\CustomerProfile;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\UrlGenerator;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @group commerce_avatax
 * @coversDefaultClass \Drupal\commerce_avatax\CustomerProfileAlter
 */
class CustomerProfileAlterTest extends UnitTestCase {

  /**
   * @covers ::applies
   * @dataProvider appliesDataProvider
   */
  public function testApplies(bool $enabled, string $profile_scope, bool $expected_applies) {
    $sut = $this->getCustomerProfileAlter([
      'address_validation' => [
        'enable' => $enabled,
      ],
    ]);

    $inline_form = [
      '#profile_scope' => $profile_scope,
    ];
    $this->assertEquals($expected_applies, $sut->applies($inline_form, new FormState()));
  }

  /**
   * @covers ::alter
   * @dataProvider alterDataProvider
   */
  public function testAlter(bool $rendered, array $mocked_address) {
    $url_generator = $this->prophesize(UrlGenerator::class);
    $url_generator->generateFromRoute(
      'commerce_avatax.address_validator',
      [],
      ['query' => ['token' => NULL], 'absolute' => TRUE],
      FALSE)
      ->willReturn('http://example.com/commerce-avatax/address-validator');
    $container = new ContainerBuilder();
    $container->set('url_generator', $url_generator->reveal());
    \Drupal::setContainer($container);

    $sut = $this->getCustomerProfileAlter([
      'address_validation' => [
        'enable' => TRUE,
        'countries' => ['US', 'RS'],
      ],
    ]);

    $customer_profile = $this->prophesize(CustomerProfile::class);
    $shipping_profile = $this->prophesize(ProfileInterface::class);
    $address_field_item = $this->prophesize(AddressItem::class);
    $address_field_item->getCountryCode()->willReturn($mocked_address['country_code']);
    $address_field_item->toArray()->willReturn($mocked_address);
    $address_field_list = $this->prophesize(FieldItemListInterface::class);
    $address_field_list->first()->willReturn($address_field_item->reveal());
    $address_field_list->isEmpty()->willReturn(FALSE);
    $shipping_profile->get('address')->willReturn($address_field_list->reveal());
    $customer_profile->getEntity()->willReturn($shipping_profile->reveal());

    $inline_form = [
      '#inline_form' => $customer_profile->reveal(),
      '#profile_scope' => 'shipping',
      '#id' => 'foobar-inline',
      'address' => [
        'widget' => [
          [
            'address' => [
              '#default_value' => [
                'country_code' => 'US',
              ],
            ],
          ],
        ],
      ],
    ];
    if ($rendered) {
      $inline_form['rendered'] = [];
    }
    $form_state = new FormState();
    $sut->alter($inline_form, $form_state);

    $this->assertEquals([
      'library' => [
        'commerce_avatax/address',
      ],
      'drupalSettings' => [
        'commerceAvatax' => [
          'inline_id' => 'foobar-inline',
          'countries' => ['US', 'RS'],
          'rendered' => $rendered,
          'endpoint' => 'http://example.com/commerce-avatax/address-validator',
          'address' => $mocked_address,
          'country' => 'US',
          'fields' => [
            'address_line1',
            'address_line2',
            'locality',
            'administrative_area',
            'country_code',
            'postal_code',
          ],
        ],
      ],
    ], $inline_form['#attached']);
  }

  /**
   * Data provider for `applies`.
   *
   * @return \Generator
   *   The test data.
   */
  public function appliesDataProvider(): \Generator {
    yield [TRUE, 'billing', FALSE];
    yield [FALSE, 'billing', FALSE];
    yield [TRUE, 'shipping', TRUE];
    yield [FALSE, 'shipping', FALSE];
  }

  /**
   * Data provider for `alter`.
   *
   * @return \Generator
   *   The test data.
   */
  public function alterDataProvider(): \Generator {
    yield [FALSE, ['country_code' => 'US'], 0, FALSE];
    yield [
      TRUE,
      ['country_code' => 'US', 'administrative_area' => 'WI'],
      FALSE,
    ];
    yield [
      TRUE,
      ['country_code' => 'US', 'administrative_area' => 'WI'],
      TRUE,
    ];
  }

  /**
   * Gets a mocked CustomerProfileAlter object for testing.
   *
   * @param array $settings
   *   The settings to use.
   *
   * @return \Drupal\commerce_avatax\CustomerProfileAlter
   *   The customer profile alter.
   */
  private function getCustomerProfileAlter(array $settings): CustomerProfileAlter {
    $config = new ImmutableConfig(
      'commerce_avatax.settings',
      $this->prophesize(StorageInterface::class)->reveal(),
      $this->prophesize(EventDispatcherInterface::class)->reveal(),
      $this->prophesize(TypedConfigManagerInterface::class)->reveal()
    );
    $config->initWithData($settings);
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('commerce_avatax.settings')->willReturn($config);
    $time = $this->prophesize(TimeInterface::class);
    $time->getCurrentTime()->willReturn(time());
    return new CustomerProfileAlter(
      $config_factory->reveal(),
      $this->prophesize(AvataxLibInterface::class)->reveal(),
      $this->prophesize(CsrfTokenGenerator::class)->reveal(),
      $time->reveal()
    );

  }

}
