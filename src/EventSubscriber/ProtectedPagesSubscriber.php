<?php

namespace Drupal\protected_pages\EventSubscriber;

use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\RedirectDestination;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManager;
use Drupal\protected_pages\ProtectedPagesStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects user to protected page login screen.
 */
class ProtectedPagesSubscriber implements EventSubscriberInterface
{
    const BYPASS_PROTECTION_PERMISSION_ID = 'bypass pages password protection';

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManager
   */
  protected $aliasManager;

  /**
   * The account proxy service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The current path stack service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestination
   */
  protected $destination;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The protected pages storage.
   *
   * @var \Drupal\protected_pages\ProtectedPagesStorage
   */
  protected $protectedPagesStorage;

  /**
   * A policy evaluating to static::DENY when the kill switch was triggered.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $pageCacheKillSwitch;

  /**
   * Constructs a new ProtectedPagesSubscriber.
   *
   * @param \Drupal\path_alias\AliasManager $aliasManager
   *   The path alias manager.
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The account proxy service.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPathStack
   *   The current path stack service.
   * @param \Drupal\Core\Routing\RedirectDestination $destination
   *   The redirect destination service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   * @param \Drupal\protected_pages\ProtectedPagesStorage $protectedPagesStorage
   *   The request stack service.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $pageCacheKillSwitch
   *   The cache kill switch service.
   */
  public function __construct(AliasManager $aliasManager, AccountProxy $currentUser, CurrentPathStack $currentPathStack, RedirectDestination $destination, RequestStack $requestStack, ProtectedPagesStorage $protectedPagesStorage, KillSwitch $pageCacheKillSwitch) {
    $this->aliasManager = $aliasManager;
    $this->currentUser = $currentUser;
    $this->currentPath = $currentPathStack;
    $this->destination = $destination;
    $this->requestStack = $requestStack;
    $this->protectedPagesStorage = $protectedPagesStorage;
    $this->pageCacheKillSwitch = $pageCacheKillSwitch;
  }

  /**
   * Redirects user to protected page login screen.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event to process.
   */
  public function checkProtectedPage(FilterResponseEvent $event)
  {
      if ($this->currentUser->hasPermission(self::BYPASS_PROTECTION_PERMISSION_ID)) {
          return;
      }

      $target = mb_strtolower($this->aliasManager->getAliasByPath($this->currentPath->getPath()));

      $guard = $this->isPageProtected($target);
      if (null === $guard) {
          return;
      }

      if($this->isAuthenticated($guard->pid)) {
          return;
      }

      $this->redirectToLogin($guard->pid)->send();
  }

  public function isPageProtected(string $target)
  {
      $result = $this->protectedPagesStorage->loadProtectedPage(['pid', 'path'], [], FALSE);
      foreach($result as $data) {
          if(true === fnmatch($data->path, $target)) {
              return $data;
          }
          if($data->path === sprintf('%s/*', $target)) {
              return $data;
          }
      }
      return null;
  }

  public function isAuthenticated(int $pid)
  {
      if (isset($_SESSION['_protected_page']['passwords'][$pid])) {
          return true;
      }
      return false;
  }

  public function redirectToLogin(int $pid) {
      $query = \Drupal::destination()->getAsArray();
      $query['protected_page'] = $pid;
      $this->pageCacheKillSwitch->trigger();
      return new RedirectResponse(Url::fromUri('internal:/protected-page', ['query' => $query])->toString());
  }

  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['checkProtectedPage'];
    return $events;
  }
}
