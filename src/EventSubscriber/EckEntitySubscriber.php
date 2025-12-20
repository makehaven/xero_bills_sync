<?php

namespace Drupal\xero_bills_sync\EventSubscriber;

use Drupal\core_event_dispatcher\EntityHookEvents;
use Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent;
use Drupal\xero_bills_sync\Service\SyncManager;
use Drupal\eck\Entity\EckEntityInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to ECK entity events to sync with Xero.
 */
class EckEntitySubscriber implements EventSubscriberInterface {

  /**
   * The Xero Bills Sync manager.
   *
   * @var \Drupal\xero_bills_sync\Service\SyncManager
   */
  protected $syncManager;

  /**
   * Constructs a new EckEntitySubscriber.
   *
   * @param \Drupal\xero_bills_sync\Service\SyncManager $sync_manager
   *   The Xero Bills Sync manager.
   */
  public function __construct(SyncManager $sync_manager) {
    $this->syncManager = $sync_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityHookEvents::ENTITY_INSERT => 'onEntityInsert',
      EntityHookEvents::ENTITY_UPDATE => 'onEntityUpdate',
    ];
  }

  /**
   * Handles entity insert.
   *
   * @param \Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent $event
   *   The entity insert event.
   */
  public function onEntityInsert(EntityInsertEvent $event) {
    $entity = $event->getEntity();
    if ($entity instanceof EckEntityInterface && $entity->getEntityTypeId() === 'payment_request') {
      $this->syncManager->syncPaymentRequest($entity);
    }
  }

  /**
   * Handles entity update.
   *
   * @param \Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent $event
   *   The entity update event.
   */
  public function onEntityUpdate(EntityUpdateEvent $event) {
    $entity = $event->getEntity();
    if ($entity instanceof EckEntityInterface && $entity->getEntityTypeId() === 'payment_request') {
      $this->syncManager->syncPaymentRequest($entity);
    }
  }

}
