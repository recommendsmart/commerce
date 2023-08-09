<?php

namespace Drupal\Tests\persistent_login\Unit;

use Drupal\Component\Datetime\Time;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\persistent_login\PersistentToken;
use Drupal\persistent_login\TokenManager;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Test validation of tokens against database values.
 *
 * @group persistent_login
 */
class TokenManagerValidationTest extends UnitTestCase {

  /**
   * @var \Drupal\Core\DependencyInjection\Container
   */
  protected $container;

  /**
   * @var \Drupal\Core\Database\Connection|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $connectionMock;

  /**
   * @var \Drupal\Core\Access\CsrfTokenGenerator|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $csrfTokenMock;

  /**
   * @var \Drupal\Core\Logger\LoggerChannel|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $loggerMock;

  /**
   * @var \Drupal\Component\Datetime\Time|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $timeMock;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    $this->connectionMock = $this->prophesize(Connection::class);
    $this->csrfTokenMock = $this->prophesize(CsrfTokenGenerator::class);
    $this->loggerMock = $this->prophesize(LoggerChannel::class);
    $this->timeMock = $this->prophesize(Time::class);
  }

  /**
   *
   */
  public function testValidToken() {
    $tokenManager = new TokenManager(
      $this->connectionMock->reveal(),
      $this->getConfigFactoryStub(),
      $this->csrfTokenMock->reveal(),
      $this->loggerMock->reveal(),
      $this->timeMock->reveal()
    );

    $this->timeMock->getRequestTime()
      ->willReturn(1675255867);

    $selectMock = $this->prophesize(Select::class);
    $this->connectionMock->select('persistent_login', 'pl')
      ->willReturn($selectMock);
    $selectMock->fields(Argument::type('string'), Argument::type('array'))
      ->willReturn($selectMock);
    $selectMock->condition('expires', Argument::type('int'), '>')
      ->shouldBeCalled()
      ->willReturn($selectMock);
    $selectMock->condition('series', Crypt::hashBase64('testseries'))
      ->shouldBeCalled()
      ->willReturn($selectMock);

    $selectResultMock = $this->prophesize(StatementInterface::class);
    $selectMock->execute()
      ->shouldBeCalled()
      ->willReturn($selectResultMock);
    $selectResultMock->fetchObject()
      ->willReturn((object) [
        'uid' => 42,
        'instance' => Crypt::hashBase64('testinstance'),
        'created' => 1675169467,
        'refreshed' => 1675169467,
        'expires' => 1682855467,
      ]);

    $inputToken = new PersistentToken('testseries', 'testinstance');

    $validatedToken = $tokenManager->validateToken($inputToken);

    $this->assertEquals($validatedToken->getStatus(), PersistentToken::STATUS_VALID);
    $this->assertEquals($validatedToken->getUid(), 42);
  }

  /**
   * A token with invalid series should be ignored.
   */
  public function testInvalidSeries() {
    $tokenManager = new TokenManager(
      $this->connectionMock->reveal(),
      $this->getConfigFactoryStub(),
      $this->csrfTokenMock->reveal(),
      $this->loggerMock->reveal(),
      $this->timeMock->reveal()
    );

    $this->timeMock->getRequestTime()
      ->willReturn(1675255867);

    $selectMock = $this->prophesize(Select::class);
    $this->connectionMock->select('persistent_login', 'pl')
      ->willReturn($selectMock);
    $selectMock->fields(Argument::type('string'), Argument::type('array'))
      ->willReturn($selectMock);
    $selectMock->condition('expires', Argument::type('int'), '>')
      ->shouldBeCalled()
      ->willReturn($selectMock);
    $selectMock->condition('series', Crypt::hashBase64('testinvalidseries'))
      ->shouldBeCalled()
      ->willReturn($selectMock);

    $selectResultMock = $this->prophesize(StatementInterface::class);
    $selectMock->execute()
      ->shouldBeCalled()
      ->willReturn($selectResultMock);
    $selectResultMock->fetchObject()
      ->willReturn(NULL);

    $inputToken = new PersistentToken('testinvalidseries', 'testinstance');

    $validatedToken = $tokenManager->validateToken($inputToken);

    $this->assertEquals($validatedToken->getStatus(), PersistentToken::STATUS_INVALID);
    $this->assertEquals($validatedToken->getUid(), PersistentToken::STATUS_INVALID);
  }

  /**
   * A valid series but invalid instance should invalidate the series.
   */
  public function testInvalidInstance() {
    $tokenManager = new TokenManager(
      $this->connectionMock->reveal(),
      $this->getConfigFactoryStub(),
      $this->csrfTokenMock->reveal(),
      $this->loggerMock->reveal(),
      $this->timeMock->reveal()
    );

    $this->timeMock->getRequestTime()
      ->willReturn(1675255867);

    $selectMock = $this->prophesize(Select::class);
    $this->connectionMock->select('persistent_login', 'pl')
      ->willReturn($selectMock);
    $selectMock->fields(Argument::type('string'), Argument::type('array'))
      ->willReturn($selectMock);
    $selectMock->condition('expires', Argument::type('int'), '>')
      ->shouldBeCalled()
      ->willReturn($selectMock);
    $selectMock->condition('series', Crypt::hashBase64('testseries'))
      ->shouldBeCalled()
      ->willReturn($selectMock);

    $selectResultMock = $this->prophesize(StatementInterface::class);
    $selectMock->execute()
      ->shouldBeCalled()
      ->willReturn($selectResultMock);
    $selectResultMock->fetchObject()
      ->willReturn((object) [
        'uid' => 42,
        'instance' => 'testinstance',
        'created' => 1675169467,
        'refreshed' => 1675169467,
        'expires' => 1682855467,
      ]);

    $inputToken = new PersistentToken('testseries', 'testinvalidinstance');

    $validatedToken = $tokenManager->validateToken($inputToken);

    $this->assertEquals($validatedToken->getStatus(), PersistentToken::STATUS_INVALID);
    $this->assertEquals($validatedToken->getUid(), PersistentToken::STATUS_INVALID);
  }

}
