<?php

namespace Drupal\persistent_login\EventSubscriber;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\persistent_login\CookieHelperInterface;
use Drupal\persistent_login\PersistentToken;
use Drupal\persistent_login\TokenException;
use Drupal\persistent_login\TokenManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event Subscriber to handle loading and setting tokens.
 */
class TokenHandler implements AuthenticationProviderInterface, EventSubscriberInterface {

  /**
   * The token manager service.
   *
   * @var \Drupal\persistent_login\TokenManager
   */
  protected $tokenManager;

  /**
   * The cookie helper service.
   *
   * @var \Drupal\persistent_login\CookieHelper
   */
  protected $cookieHelper;

  /**
   * The session configuration.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Config Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Logger Channel Factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $loggerChannelFactory;

  /**
   * The persistent token of the current request.
   *
   * @var \Drupal\persistent_login\PersistentToken|null
   */
  protected $token;

  /**
   * Construct a token manager object.
   *
   * @param \Drupal\persistent_login\TokenManager $token_manager
   *   The token manager service.
   * @param \Drupal\persistent_login\CookieHelperInterface $cookie_helper
   *   The cookie helper service.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Config Factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface|null $logger_channel_factory
   *   The Logger Channel Factory service.
   *
   * @phpcs:disable Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
   */
  public function __construct(
    TokenManager $token_manager,
    CookieHelperInterface $cookie_helper,
    SessionConfigurationInterface $session_configuration,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    $logger_channel_factory = NULL
  ) {
    $this->tokenManager = $token_manager;
    $this->cookieHelper = $cookie_helper;
    $this->sessionConfiguration = $session_configuration;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;

    if (empty($logger_channel_factory) || !($logger_channel_factory instanceof LoggerChannelFactoryInterface)) {
      @trigger_error('logger_channel_factory will be a required parameter in persistent_login 3.x', E_USER_DEPRECATED);
      $logger_channel_factory = \Drupal::service('logger.factory');
    }
    $this->loggerChannelFactory = $logger_channel_factory;
  }

  /**
   * Specify subscribed events.
   *
   * @return array
   *   The subscribed events.
   */
  public static function getSubscribedEvents() {
    $events = [];

    $events[KernelEvents::RESPONSE][] = ['setTokenOnResponseEvent'];

    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request): bool {
    // Ignore Persistent Login token if a valid session is already initialized
    // via the \Drupal\user\Authentication\Provider\Cookie provider.
    if (
      // A session cookie was provided.
      // @see \Drupal\user\Authentication\Provider\Cookie::applies()
      $request->hasSession() && $this->sessionConfiguration->hasSession($request)
      &&
      // The session is valid and for an authenticated user.
      // @see \Drupal\user\Authentication\Provider\Cookie::authenticate()
      $request->getSession()->get('uid')
    ) {
      return FALSE;
    }

    return $this->cookieHelper->hasCookie($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request): ?AccountInterface {
    $token = $this->getTokenFromCookie($request);

    if (empty($token)) {
      return NULL;
    }

    $this->token = $this->tokenManager->validateToken($token);

    if ($this->token->getStatus() === PersistentToken::STATUS_VALID) {
      try {
        /** @var \Drupal\User\UserInterface $user */
        $user = $this->entityTypeManager->getStorage('user')
          ->load($this->token->getUid());

        // @see user_login_finalize().
        $session = $request->getSession();
        $session->migrate();
        $session->set('uid', $user->id());
        // @todo tag session as opened by PL.

        $this->loggerChannelFactory->get('user')
          ->notice('Session opened for %name via Persistent Login token.', [
            '%name' => $user->getAccountName(),
          ]);

        return $user;
      }
      catch (PluginException $e) {
      }
    }

    return NULL;
  }

  /**
   * Set or clear a token cookie on this response, if required.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function setTokenOnResponseEvent(ResponseEvent $event) {

    if (!$event->isMainRequest()) {
      return;
    }

    if (empty($this->token)) {
      return;
    }

    $request = $event->getRequest();
    $response = $event->getResponse();
    $sessionOptions = $this->sessionConfiguration->getOptions($request);

    // New or updated token.
    if ($this->token->getStatus() === PersistentToken::STATUS_VALID) {
      $config = $this->configFactory->get('persistent_login.settings');
      if ($config->get('extend_lifetime') && $config->get('lifetime') > 0) {
        $this->token = $this->token->setExpiry(new \DateTime("now +" . $config->get('lifetime') . " day"));
      }

      $this->token = $this->tokenManager->updateToken($this->token);

      $response->headers->setCookie(
        Cookie::create(
          $this->cookieHelper->getCookieName($request),
          $this->token,
          $this->token->getExpiry(),
          $sessionOptions['cookie_path'] ?? '/',
          $sessionOptions['cookie_domain'],
          $sessionOptions['cookie_secure'],
          $sessionOptions['cookie_httponly'] ?? TRUE,
          FALSE,
          $sessionOptions['cookie_samesite'] ?? NULL
        )
      );
      $response->setPrivate();
    }
    elseif ($this->token->getStatus() === PersistentToken::STATUS_INVALID) {
      // Invalid token, or manually cleared token (e.g. user logged out).
      $this->tokenManager->deleteToken($this->token);
      $response->headers->clearCookie(
        $this->cookieHelper->getCookieName($request),
        $sessionOptions['cookie_path'] ?? '/',
        $sessionOptions['cookie_domain'],
        $sessionOptions['cookie_secure'],
        $sessionOptions['cookie_httponly'] ?? TRUE,
        $sessionOptions['cookie_samesite'] ?? NULL
      );
      $response->setPrivate();
    }
    // Ignore token if status is STATUS_NOT_VALIDATED.
  }

  /**
   * Create a token object from the cookie provided in the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request that contains a persistent login cookie.
   *
   * @return \Drupal\persistent_login\PersistentToken|null
   *   A new PersistentToken object, or NULL if the cookie value was not valid.
   */
  public function getTokenFromCookie(Request $request) {
    $cookieValue = $this->cookieHelper->getCookieValue($request);
    // Token values are 43-character base-64 encoded, URL-safe strings.
    // @see \Drupal\Component\Utility\Crypt::hmacBase64()
    if (empty($cookieValue) || !preg_match('<[a-z0-9_-]+:[a-z0-9+_-]+>i', $cookieValue)) {
      return NULL;
    }
    return PersistentToken::createFromString($cookieValue);
  }

  /**
   * Create and store a new token for the specified user.
   *
   * @param int $uid
   *   The user id to associate the token to.
   */
  public function setNewSessionToken($uid) {
    try {
      $this->token = $this->tokenManager->createNewTokenForUser($uid);
    }
    catch (TokenException $e) {
      // Ignore error creating new token.
    }
  }

  /**
   * Mark the user's current token as invalid.
   *
   * This will cause the token to be removed from the database at the end of the
   * request.
   */
  public function clearSessionToken(Request $request = NULL) {
    if (!$this->token) {
      if (!$request) {
        $request = \Drupal::request();
      }
      $this->token = $this->getTokenFromCookie($request);
    }

    if ($this->token) {
      $this->token = $this->token->setInvalid();
    }
  }

}
