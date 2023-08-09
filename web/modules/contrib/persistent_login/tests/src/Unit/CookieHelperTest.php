<?php

namespace Drupal\Tests\persistent_login\Unit;

use Drupal\Core\Session\SessionConfiguration;
use Drupal\persistent_login\CookieHelper;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cookie Helper Tests.
 */
class CookieHelperTest extends UnitTestCase {

  /**
   * Data provider for protocol security of request.
   */
  public static function cookieSecureProvider() {
    return [
      'secure' => [TRUE],
      'insecure' => [FALSE],
    ];
  }

  /**
   * Test default cookie prefix.
   *
   * @dataProvider cookieSecureProvider
   */
  public function testCookieName($secure): void {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject $configFactory */
    $configFactory = $this->getConfigFactoryStub([
      'persistent_login.settings' => [
        'cookie_prefix' => 'PL',
      ],
    ]);
    /** @var \Drupal\Core\Session\SessionConfigurationInterface|\PHPUnit\Framework\MockObject\MockObject $sessionConfiguration */
    $sessionConfiguration = $this->createStub(SessionConfiguration::class);
    $cookieHelper = new CookieHelper(
      $sessionConfiguration,
      $configFactory
    );
    /** @var \Symfony\Component\HttpFoundation\Request|\PHPUnit\Framework\MockObject\MockObject $request */
    $request = $this->createStub(Request::class);

    $sessionConfiguration->method('getOptions')->willReturn([
      'name' => ($secure ? 'SSESS' : 'SESS') . '669af8b697a5f362dffd3f58410ac59e',
    ]);
    $request->method('isSecure')->willReturn($secure);
    $this->assertEquals(($secure ? 'SPL' : 'PL') . '669af8b697a5f362dffd3f58410ac59e', $cookieHelper->getCookieName($request));
  }

  /**
   * Provider for Cookie Prefixes.
   */
  public static function prefixPrefixProvider() {
    return [
      'insecure_secure' => ['Secure', FALSE],
      'secure_secure'   => ['Secure', TRUE],
      'insecure_host'   => ['Host', FALSE],
      'secure_host'     => ['Host', TRUE],
    ];
  }

  /**
   * Test prefix with __Secure or __Host prefix.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#cookie_prefixes
   *
   * @dataProvider prefixPrefixProvider
   */
  public function testPrefixedCookieName($prefix, $secure): void {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject $configFactory */
    $configFactory = $this->getConfigFactoryStub([
      'persistent_login.settings' => [
        'cookie_prefix' => '__' . $prefix . '-PL',
      ],
    ]);
    /** @var \Drupal\Core\Session\SessionConfigurationInterface|\PHPUnit\Framework\MockObject\MockObject $sessionConfiguration */
    $sessionConfiguration = $this->createStub(SessionConfiguration::class);
    $cookieHelper = new CookieHelper(
      $sessionConfiguration,
      $configFactory
    );
    /** @var \Symfony\Component\HttpFoundation\Request|\PHPUnit\Framework\MockObject\MockObject $request */
    $request = $this->createStub(Request::class);

    $sessionConfiguration->method('getOptions')->willReturn([
      'name' => ($secure ? 'SSESS' : 'SESS') . '669af8b697a5f362dffd3f58410ac59e',
    ]);
    $request->method('isSecure')->willReturn($secure);
    $this->assertEquals('__' . $prefix . '-' . ($secure ? 'SPL' : 'PL') . '669af8b697a5f362dffd3f58410ac59e', $cookieHelper->getCookieName($request));
  }

}
