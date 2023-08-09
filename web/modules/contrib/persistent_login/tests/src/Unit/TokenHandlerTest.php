<?php

namespace Drupal\Tests\persistent_login\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionConfiguration;
use Drupal\persistent_login\CookieHelperInterface;
use Drupal\persistent_login\EventSubscriber\TokenHandler;
use Drupal\persistent_login\TokenManager;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test the Token Handler service.
 *
 * @group persistent_login
 */
class TokenHandlerTest extends UnitTestCase {

  /**
   * @var \Drupal\persistent_login\TokenManager|\Prophecy\Prophecy\ObjectProphecy
   */
  private $tokenManagerMock;

  /**
   * @var \Drupal\persistent_login\CookieHelperInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  private $cookieHelperMock;

  /**
   * @var \Drupal\Core\Session\SessionConfiguration|\Prophecy\Prophecy\ObjectProphecy
   */
  private $sessionConfigMock;

  /**
   * @var LoggerChannelFactoryInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  private $loggerChannelFactoryMock;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  private $entityTypeManagerMock;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    $this->tokenManagerMock = $this->prophesize(TokenManager::class);
    $this->cookieHelperMock = $this->prophesize(CookieHelperInterface::class);
    $this->sessionConfigMock = $this->prophesize(SessionConfiguration::class);
    $this->entityTypeManagerMock = $this->prophesize(EntityTypeManagerInterface::class);
    $this->loggerChannelFactoryMock = $this->prophesize(LoggerChannelFactoryInterface::class);
  }

  /**
   * A cookie matching the expected [series:instance] format.
   */
  public function testValidFormatCookie() {
    $tokenHandler = new TokenHandler(
      $this->tokenManagerMock->reveal(),
      $this->cookieHelperMock->reveal(),
      $this->sessionConfigMock->reveal(),
      $this->entityTypeManagerMock->reveal(),
      $this->getConfigFactoryStub(),
      $this->loggerChannelFactoryMock->reveal()
    );

    $this->cookieHelperMock->getCookieValue(Argument::type(Request::class))
      ->willReturn('XcS3SUivdC-MkZadiFEWkhe4tKA8PXb5E7GDiyX_MUs:zfIBCf3abCt728sU_7NZlNvlb2E-ELV9jEzROvJJK28');
    $request = $this->prophesize(Request::class);

    $token = $tokenHandler->getTokenFromCookie($request->reveal());
    $this->assertEquals('XcS3SUivdC-MkZadiFEWkhe4tKA8PXb5E7GDiyX_MUs', $token->getSeries());
    $this->assertEquals('zfIBCf3abCt728sU_7NZlNvlb2E-ELV9jEzROvJJK28', $token->getInstance());
  }

  /**
   * A cookie not matching the [series:instance] format.
   */
  public function testInvalidCookieValue() {
    $tokenHandler = new TokenHandler(
      $this->tokenManagerMock->reveal(),
      $this->cookieHelperMock->reveal(),
      $this->sessionConfigMock->reveal(),
      $this->entityTypeManagerMock->reveal(),
      $this->getConfigFactoryStub(),
      $this->loggerChannelFactoryMock->reveal()
    );

    $this->cookieHelperMock->getCookieValue(Argument::type(Request::class))
      ->willReturn('test');
    $request = $this->prophesize(Request::class);

    $this->assertNull($tokenHandler->getTokenFromCookie($request->reveal()));
  }

  /**
   * A cookie with an empty value.
   *
   * This should not actually occur in practice.
   */
  public function testEmptyCookieValue() {
    $tokenHandler = new TokenHandler(
      $this->tokenManagerMock->reveal(),
      $this->cookieHelperMock->reveal(),
      $this->sessionConfigMock->reveal(),
      $this->entityTypeManagerMock->reveal(),
      $this->getConfigFactoryStub(),
      $this->loggerChannelFactoryMock->reveal()
    );

    $this->cookieHelperMock->getCookieValue(Argument::type(Request::class))
      ->willReturn('');
    $request = $this->prophesize(Request::class);

    $this->assertNull($tokenHandler->getTokenFromCookie($request->reveal()));
  }

}
