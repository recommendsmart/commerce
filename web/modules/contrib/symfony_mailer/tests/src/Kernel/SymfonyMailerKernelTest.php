<?php

namespace Drupal\Tests\symfony_mailer\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\symfony_mailer_test\MailerTestTrait;

/**
 * Tests basic email sending.
 *
 * @group filter
 */
class SymfonyMailerKernelTest extends KernelTestBase {

  use MailerTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['symfony_mailer', 'symfony_mailer_test', 'system', 'user', 'filter'];

  /**
   * The email factory.
   *
   * @var \Drupal\symfony_mailer\EmailFactoryInterface
   */
  protected $emailFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['symfony_mailer']);
    $this->installEntitySchema('user');
    $this->emailFactory = $this->container->get('email_factory');
    $this->config('system.site')
      ->set('name', 'Example')
      ->set('mail', 'sender@example.com')
      ->save();
  }

  /**
   * Basic email sending test.
   */
  public function testEmail() {
    // Test email error.
    $this->emailFactory->sendTypedEmail('symfony_mailer', 'test');
    $this->readMail();
    $this->assertError('An email must have a "To", "Cc", or "Bcc" header.');

    // Test email success.
    $to = 'to@example.com';
    $this->emailFactory->sendTypedEmail('symfony_mailer', 'test', $to);
    $this->readMail();
    $this->assertNoError();
    $this->assertSubject("Test email from Example");
    $this->assertTo($to);
  }

}
