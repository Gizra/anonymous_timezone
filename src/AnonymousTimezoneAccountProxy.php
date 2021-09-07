<?php

namespace Drupal\anonymous_timezone;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxy;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\GeoIp2Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Timezone logic for anonymous users.
 */
class AnonymousTimezoneAccountProxy extends AccountProxy {

  /**
   * The kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected KillSwitch $killSwitch;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * AccountProxy constructor.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   *   The kill switch.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(EventDispatcherInterface $eventDispatcher, KillSwitch $killSwitch, RequestStack $requestStack, ConfigFactoryInterface $configFactory) {
    $this->eventDispatcher = $eventDispatcher;
    $this->killSwitch = $killSwitch;
    $this->requestStack = $requestStack;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount() {
    $account = parent::getAccount();
    if ($account->isAnonymous()) {
      $tz = $this->getGeoTimeZone();
      if (!empty($tz)) {
        date_default_timezone_set($tz);
      }
    }
    return $account;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeZone() {
    if ($this->isAnonymous()) {
      $tz = $this->getGeoTimeZone();
      if (!empty($tz)) {
        return $tz;
      }
    }

    return parent::getTimeZone();
  }

  /**
   * Returns the timezone based on the IP address of the visitor.
   *
   * @return string|null
   *   Timezone ID or nothing.
   *
   * @throws \MaxMind\Db\Reader\InvalidDatabaseException
   */
  protected function getGeoTimeZone() {
    $cache = &drupal_static(__METHOD__, []);
    $ip = $this->requestStack->getCurrentRequest()->getClientIp();
    if (isset($cache[$ip])) {
      return $cache[$ip];
    }
    try {
      $this->killSwitch->trigger();
      $geodb_file = $this->configFactory->get('anonymous_timezone.settings')->get('geodb');
      if (empty($geodb_file) || !file_exists($geodb_file)) {
        throw new GeoIp2Exception('Missing GeoDB file');
      }
      $reader = new Reader($geodb_file);
      $record = $reader->city($ip);
      $tz = $record->location->timeZone;
      if (!empty($tz)) {
        $cache[$ip] = $tz;
      }
    }
    catch (GeoIp2Exception $e) {
      // No idea of the timezone at this point.
    }
    if (empty($cache[$ip])) {
      $cache[$ip] = NULL;
    }
    return $cache[$ip];
  }

}
