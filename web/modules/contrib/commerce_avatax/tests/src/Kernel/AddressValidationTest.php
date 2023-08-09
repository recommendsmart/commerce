<?php

namespace Drupal\Tests\commerce_avatax\Kernel;

use Drupal\commerce_avatax\AvataxLib;
use Drupal\commerce_avatax\ClientFactory;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\ClientInterface;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Tests address resolving and validation.
 *
 * @group commerce_avatax
 */
class AddressValidationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'profile',
    'commerce',
    'commerce_order',
    'commerce_price',
    'commerce_tax',
    'commerce_avatax',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('commerce_avatax');
  }

  /**
   * @covers \Drupal\commerce_avatax\AvataxLib::resolveAddress
   * @dataProvider addressesData
   */
  public function testResolveAddress(array $address, string $fixture) {
    $sut = $this->createMockedAvataxLib($fixture);
    $result = $sut->resolveAddress($address);
    $this->assertEquals($address, AvataxLib::formatDrupalAddress($result['address']));
    // Run again to verify ::shouldBeCalledOnce and request caching.
    $sut->resolveAddress($address);
  }

  /**
   * @covers \Drupal\commerce_avatax\AvataxLib::validateAddress
   * @dataProvider addressesData
   */
  public function testvalidateAddress(array $address, string $fixture, bool $postal_code_verification, bool $expected_valid, array $expected_fields, array $expected_errors, array $expected_suggestion) {
    $this->config('commerce_avatax.settings')
      ->set('address_validation.postal_code_match', $postal_code_verification)
      ->save();

    $sut = $this->createMockedAvataxLib($fixture);
    $result = $sut->validateAddress($address);
    $this->assertEquals($expected_valid, $result['valid']);
    $this->assertEquals($expected_fields, $result['fields']);
    $this->assertEquals($expected_errors, $result['errors']);
    $this->assertEquals($expected_suggestion, $result['suggestion']);
    $this->assertEquals($address, $result['original']);
  }

  /**
   * The test address data.
   *
   * @return \Generator
   *   The test data.
   */
  public function addressesData(): \Generator {
    yield [
      [
        'address_line1' => '2000 Main St',
        'address_line2' => '',
        'locality' => 'Irvine',
        'administrative_area' => 'CA',
        'postal_code' => '92614-7202',
        'country_code' => 'US',
      ],
      __DIR__ . '/../../fixtures/irvine.json',
      TRUE,
      TRUE,
      [],
      [],
      [],
    ];
    yield [
      [
        'address_line1' => '2000 Main Stree',
        'address_line2' => '',
        'locality' => 'Irvine',
        'administrative_area' => 'CO',
        'postal_code' => '92610',
        'country_code' => 'US',
      ],
      __DIR__ . '/../../fixtures/irvine_suggestion.json',
      TRUE,
      TRUE,
      [
        'address_line1' => '2000 Main St',
        'administrative_area' => 'CA',
        'postal_code' => '92614-7202',
      ],
      [],
      [
        'address_line1' => '2000 Main St',
        'address_line2' => '',
        'locality' => 'Irvine',
        'administrative_area' => 'CA',
        'postal_code' => '92614-7202',
        'country_code' => 'US',
      ],
    ];
    yield [
      [
        'address_line1' => '2000 Main St',
        'address_line2' => '',
        'locality' => 'Irvine',
        'administrative_area' => 'CA',
        'postal_code' => '92614',
        'country_code' => 'US',
      ],
      __DIR__ . '/../../fixtures/irvine_postal_code_suggestion.json',
      FALSE,
      TRUE,
      [],
      [],
      [],
    ];
    yield [
      [
        'address_line1' => '2000 Main St',
        'address_line2' => '',
        'locality' => 'Irvine',
        'administrative_area' => 'CA',
        'postal_code' => '92614',
        'country_code' => 'US',
      ],
      __DIR__ . '/../../fixtures/irvine_postal_code_suggestion.json',
      TRUE,
      TRUE,
      [
        'postal_code' => '92614-7202',
      ],
      [],
      [
        'address_line1' => '2000 Main St',
        'address_line2' => '',
        'locality' => 'Irvine',
        'administrative_area' => 'CA',
        'postal_code' => '92614-7202',
        'country_code' => 'US',
      ],
    ];
    yield [
      [
        'address_line1' => '20000 Main Street',
        'address_line2' => '',
        'locality' => 'Irvine',
        'administrative_area' => 'CA',
        'postal_code' => '92614',
        'country_code' => 'US',
      ],
      __DIR__ . '/../../fixtures/irvine_error.json',
      TRUE,
      FALSE,
      [],
      [
        0 => 'address_line1',
      ],
      [],
    ];
  }

  /**
   * Creates a mocked AvataxLib.
   *
   * @param string $fixture
   *   The response body fixture.
   *
   * @return \Drupal\commerce_avatax\AvataxLib
   *   The mock.
   */
  private function createMockedAvataxLib(string $fixture): AvataxLib {
    $client_factory = $this->prophesize(ClientFactory::class);

    $mocked_response = $this->prophesize(ResponseInterface::class);
    $mocked_body = $this->prophesize(StreamInterface::class);
    $mocked_body->getContents()->willReturn(file_get_contents($fixture));
    $mocked_response->getBody()->willReturn($mocked_body->reveal());

    $mocked_client = $this->prophesize(ClientInterface::class);
    $mocked_client->request('POST', 'api/v2/addresses/resolve', Argument::any())
      ->willReturn($mocked_response->reveal())
      ->shouldBeCalledOnce();

    $client_factory->createInstance(Argument::type('array'))->willReturn($mocked_client->reveal());

    return new AvataxLib(
      $this->container->get('plugin.manager.commerce_adjustment_type'),
      $this->container->get('entity_type.manager'),
      $this->container->get('commerce_avatax.chain_tax_code_resolver'),
      $client_factory->reveal(),
      $this->container->get('config.factory'),
      $this->container->get('event_dispatcher'),
      $this->container->get('logger.channel.commerce_avatax'),
      $this->container->get('module_handler'),
      $this->container->get('cache.commerce_avatax')
    );
  }

}
