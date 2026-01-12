<?php

namespace Drupal\xero_bills_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\eck\Entity\EckEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\xero_bills_sync\Service\SyncManager;

/**
 * Controller to manually sync payment requests to Xero.
 */
class ManualSyncController extends ControllerBase {

  /**
   * The Sync Manager service.
   *
   * @var \Drupal\xero_bills_sync\Service\SyncManager
   */
  protected $syncManager;

  /**
   * Constructs a new ManualSyncController.
   *
   * @param \Drupal\xero_bills_sync\Service\SyncManager $sync_manager
   *   The sync manager.
   */
  public function __construct(SyncManager $sync_manager) {
    $this->syncManager = $sync_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('xero_bills_sync.sync_manager')
    );
  }

  /**
   * Syncs the payment request to Xero.
   *
   * @param \Drupal\Core\Entity\EntityInterface $payment_request
   *   The payment request entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function sync(EntityInterface $payment_request) {
    // Check if it is a payment request entity.
    if ($payment_request->getEntityTypeId() !== 'payment_request') {
      $this->messenger()->addError($this->t('Invalid entity type.'));
      return $this->redirect('<front>');
    }

    $result = $this->syncManager->syncPaymentRequest($payment_request);

    if ($result) {
      $this->messenger()->addStatus($this->t('Successfully synced payment request to Xero.'));
    }
    else {
      $this->messenger()->addError($this->t('Failed to sync to Xero. Check logs for details or ensure the request is "Submitted", not a duplicate, and not already synced.'));
    }

    // Redirect back to the payment requests list.
    return $this->redirect('view.payment_requests_staff.page_1');
  }
}
