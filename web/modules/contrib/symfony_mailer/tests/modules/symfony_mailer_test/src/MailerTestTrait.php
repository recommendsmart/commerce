<?php

namespace Drupal\symfony_mailer_test;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tracks sent emails for testing.
 */
trait MailerTestTrait {

  /**
   * The test service.
   *
   * @var \Drupal\symfony_mailer_test\MailerTestServiceInterface
   */
  protected $testService;

  /**
   * The emails that have been sent and not yet checked.
   *
   * @var \Drupal\symfony_mailer\EmailInterface[]
   */
  protected $emails;

  /**
   * The most recently sent email.
   *
   * @var \Drupal\symfony_mailer\EmailInterface
   */
  protected $email;

  /**
   * Gets the next email, removing it from the list.
   *
   * @param bool $last
   *   (optional)TRUE if this is the last email.
   *
   * @return \Symfony\Component\Mime\Email
   *   The email.
   */
  public function readMail(bool $last = TRUE) {
    $this->init();
    $this->email = array_shift($this->emails);
    $this->assertNotNull($this->email);
    if ($last) {
      $this->noMail();
    }
    return $this->email;
  }

  /**
   * Checks that the most recently sent email contains text.
   *
   * @param string $value
   *   Text to check for.
   *
   * @return $this
   */
  public function assertBodyContains(string $value) {
    $this->assertStringContainsString($value, $this->email->getHtmlBody());
    return $this;
  }

  /**
   * Checks the subject of the most recently sent email.
   *
   * @param string $value
   *   Text to check for.
   *
   * @return $this
   */
  public function assertSubject($value) {
    $this->assertEquals($value, $this->email->getSubject());
    return $this;
  }

  /**
   * Checks the to address of the most recently sent email.
   *
   * @param string $email
   *   The email address.
   * @param string $display_name
   *   (Optional) The display name.
   *
   * @return $this
   */
  public function assertTo(string $email, string $display_name = '') {
    $to = $this->email->getTo();
    $this->assertCount(1, $to);
    $this->assertEquals($email, $to[0]->getEmail());
    $this->assertEquals($display_name, $to[0]->getDisplayName());
    return $this;
  }

  /**
   * Checks the cc address of the most recently sent email.
   *
   * @param string $email
   *   The email address.
   * @param string $display_name
   *   (Optional) The display name.
   *
   * @return $this
   */
  public function assertCc(string $email, string $display_name = '') {
    $cc = $this->email->getCc();
    $this->assertCount(1, $cc);
    $this->assertEquals($email, $cc[0]->getEmail());
    $this->assertEquals($display_name, $cc[0]->getDisplayName());
    return $this;
  }

  /**
   * Checks the error of the most recently sent email.
   *
   * @param string $error
   *   The error.
   *
   * @return $this
   */
  public function assertError(string $error) {
    $this->assertEquals($error, $this->email->getError());
    return $this;
  }

  /**
   * Checks the most recently sent email was successful.
   *
   * @return $this
   */
  public function assertNoError() {
    $this->assertNull($this->email->getError());
    return $this;
  }

  /**
   * Checks there are no more emails.
   */
  protected function noMail() {
    $this->init();
    $this->assertCount(0, $this->emails, 'All emails have been checked.');
    \Drupal::state()->delete(MailerTestServiceInterface::STATE_KEY);
    $this->emails = NULL;
  }

  /**
   * Initializes the list of emails.
   */
  protected function init() {
    if (is_null($this->emails)) {
      if ($this instanceof KernelTestBase) {
        // Kernel test.
        if (!$this->testService) {
          $this->testService = $this->container->get('symfony_mailer.test');
        }
        $this->emails = $this->testService->getEmails();
      }
      else {
        // Functional test.
        $this->emails = \Drupal::state()->get(MailerTestServiceInterface::STATE_KEY, []);
      }
    }
  }

}
