<?php

namespace Drupal\commerce_avatax_test;

use Drupal\commerce_avatax\AvataxLib;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\ExtensionPathResolver;

/**
 * Decorates `commerce_avatax.avatax_lib` for testing.
 */
class AvataxLibTest extends AvataxLib {

  /**
   * The extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * Sets the extension path resolver.
   *
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extension_path_resolver
   *   The extension path resolver.
   */
  public function setExtensionPathResolver(ExtensionPathResolver $extension_path_resolver) {
    $this->extensionPathResolver = $extension_path_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveAddress(array $address) {
    // Irvine.
    if ($address['locality'] === 'Irvine') {
      $file = $this->extensionPathResolver->getPath('module', 'commerce_avatax') . '/tests/fixtures/irvine.json';
      if ($address['administrative_area'] === 'C0' || $address['address_line1'] === '2000 Main Stree') {
        $file = $this->extensionPathResolver->getPath('module', 'commerce_avatax') . '/tests/fixtures/irvine_suggestion.json';
      }

      if ($address['address_line1'] === '20000 Main Street') {
        $file = $this->extensionPathResolver->getPath('module', 'commerce_avatax') . '/tests/fixtures/irvine_error.json';
      }
    }
    else {
      $file = $this->extensionPathResolver->getPath('module', 'commerce_avatax') . '/tests/fixtures/durham.json';
      if ($address['address_line1'] === '512 S Mangu' || $address['postal_code'] === '27001') {
        $file = $this->extensionPathResolver->getPath('module', 'commerce_avatax') . '/tests/fixtures/durham_suggestion.json';
      }
    }

    $response_body = Json::decode(file_get_contents($file));

    // In fixtures we have address which are fixed. We need to replace
    // address array which represents what we are sending, so that
    // mockup response could be valid.
    $response_body['address'] = [
      'line1' => $address['address_line1'],
      'line2' => $address['address_line2'],
      'city' => $address['locality'],
      'region' => $address['administrative_area'],
      'country' => $address['country_code'],
      'postalCode' => $address['postal_code'],
    ];

    return $response_body;
  }

}
