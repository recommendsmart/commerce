<?php

namespace Drupal\Tests\persistent_login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test a persistent login with a session that has expired from inactivity.
 *
 * @group persistent_login
 */
class InactiveSessionTest extends BrowserTestBase {

  /**
   * A test user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * Set default theme to stark.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['persistent_login'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mimic the required setup of the module by setting the session cookie
    // lifetime to 0.
    $parameters = $this->container->getParameter('session.storage.options');
    $parameters['cookie_lifetime'] = 0;
    $this->setContainerParameter('session.storage.options', $parameters);
    $this->rebuildContainer();
    $this->resetAll();

    // Create a test user.
    $this->user = $this->createUser([], 'Garnett Tyrell');
  }

  /**
   * A user's PL token should start a new session after expired for inactivity.
   */
  public function testInactiveSession(): void {

    $this->assertTrue($this->homepageHasLoginForm(), 'The login form should be present on the page.');

    $this->drupalGet('user');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'name' => $this->user->getAccountName(),
      'pass' => $this->user->passRaw,
      'persistent_login' => TRUE,
    ], t('Log in'));

    $this->assertFalse($this->homepageHasLoginForm(), 'The login form should not be present on the page.');

    // Remove the session from the database.
    $this->getDatabaseConnection()
      ->truncate($this->databasePrefix . 'sessions')
      ->execute();

    $this->assertFalse($this->homepageHasLoginForm(), 'Persistent Login should initiate a new session.');
  }

  /**
   * Returns whether the login form is displayed on the homepage.
   *
   * @return bool
   *   Whether the login form is displayed.
   */
  protected function homepageHasLoginForm(): bool {
    $this->drupalGet('<front>');
    return (bool) $this->getSession()->getPage()->findButton('Log in');
  }

}
