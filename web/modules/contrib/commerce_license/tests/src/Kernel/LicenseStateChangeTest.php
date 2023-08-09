<?php

namespace Drupal\Tests\commerce_license\Kernel;

use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Tests changes to the license state have the correct effects.
 *
 * @group commerce_license
 */
class LicenseStateChangeTest extends OrderKernelTestBase {

  /**
   * The modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_license',
    'commerce_license_test',
  ];

  /**
   * The license storage.
   *
   * @var \Drupal\commerce_license\LicenseStorageInterface
   */
  protected $licenseStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_license');

    $this->licenseStorage = \Drupal::service('entity_type.manager')->getStorage('commerce_license');
  }

  /**
   * Tests that changes to a license's state causes the plugin to react.
   */
  public function testLicenseStateChanges(): void {
    $owner = $this->createUser();

    // Create a license in the 'new' state.
    $license = $this->licenseStorage->create([
      'type' => 'state_change_test',
      'state' => 'new',
      'product_variation' => 1,
      'uid' => $owner->id(),
      // Use the unlimited expiry plugin as it's simple.
      'expiration_type' => [
        'target_plugin_id' => 'unlimited',
        'target_plugin_configuration' => [],
      ],
    ]);

    $license->save();

    // The license is not active: the plugin should not react.
    self::assertEquals(NULL, \Drupal::state()->get('commerce_license_state_change_test'));

    // Activate the license: this puts it into the 'pending' state.
    $transition = $license->getState()->getWorkflow()->getTransition('activate');
    $license->getState()->applyTransition($transition);
    $license->save();

    // The license is not active: the plugin should not react.
    self::assertEquals(NULL, \Drupal::state()->get('commerce_license_state_change_test'));

    // Confirm the license: this puts it into the 'active' state.
    $transition = $license->getState()->getWorkflow()->getTransition('confirm');
    $license->getState()->applyTransition($transition);
    $license->save();

    // The license is now active: the plugin should be called.
    self::assertEquals('grantLicense', \Drupal::state()->get('commerce_license_state_change_test'));

    // Reset the test tracking state.
    \Drupal::state()->set('commerce_license_state_change_test', NULL);

    // Save the license again without changing its state.
    $license->save();

    // The license is unchanged: the plugin should not react.
    self::assertEquals(NULL, \Drupal::state()->get('commerce_license_state_change_test'));

    // Suspend the license.
    $transition = $license->getState()->getWorkflow()->getTransition('suspend');
    $license->getState()->applyTransition($transition);
    $license->save();

    // The license is now inactive: the plugin should be called.
    self::assertEquals('revokeLicense', \Drupal::state()->get('commerce_license_state_change_test'));

    // Reset the test tracking state.
    \Drupal::state()->set('commerce_license_state_change_test', NULL);

    // Revoke the license.
    $transition = $license->getState()->getWorkflow()->getTransition('revoke');
    $license->getState()->applyTransition($transition);
    $license->save();

    // Although the license state changed, it has gone from one inactive state
    // to another: the plugin should not react.
    self::assertEquals(NULL, \Drupal::state()->get('commerce_license_state_change_test'));

    // Reset the test tracking state.
    \Drupal::state()->set('commerce_license_state_change_test', NULL);

    // Test creating a license initially in the 'active' state.
    $license = $this->licenseStorage->create([
      'type' => 'state_change_test',
      'state' => 'active',
      'product_variation' => 1,
      'uid' => 1,
      'expiration_type' => [
        'target_plugin_id' => 'unlimited',
        'target_plugin_configuration' => [],
      ],
    ]);

    $license->save();

    // The license is created active: the plugin should be called.
    self::assertEquals('grantLicense', \Drupal::state()->get('commerce_license_state_change_test'));
  }

}
