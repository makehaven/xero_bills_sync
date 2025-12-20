<?php

namespace Drupal\xero_bills_sync\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\eck\Entity\EckEntityInterface;
use Drupal\file\Entity\File;
use Drupal\user\UserInterface;

/**
 * Handles synchronization of payment requests to Xero.
 */
class SyncManager {

  /**
   * The Xero Client service.
   *
   * @var mixed
   */
  protected $xeroClient;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new SyncManager object.
   */
  public function __construct(
    $xero_client,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
    FileSystemInterface $file_system
  ) {
    $this->xeroClient = $xero_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('xero_bills_sync');
    $this->config = $config_factory->get('xero_bills_sync.settings');
    $this->fileSystem = $file_system;
  }

  /**
   * Syncs an ECK payment request entity to Xero.
   *
   * @param \Drupal\eck\Entity\EckEntityInterface $entity
   *   The payment request entity.
   */
  public function syncPaymentRequest(EckEntityInterface $entity) {
    // Ensure we are dealing with the correct entity type.
    if ($entity->getEntityTypeId() !== 'payment_request') {
      return;
    }

    // Check if already synced.
    if ($entity->hasField('field_xero_invoice_id') && !$entity->get('field_xero_invoice_id')->isEmpty()) {
      return;
    }

    // Check status.
    if ($entity->hasField('field_status') && $entity->get('field_status')->value !== 'submitted') {
      return;
    }

    // Get Xero Contact ID from user.
    $owner = $entity->getOwner();
    if (!$owner instanceof UserInterface) {
      $this->logger->error('Payment request @id has no valid owner.', ['@id' => $entity->id()]);
      return;
    }

    $xero_contact_id = $this->getXeroContactId($owner);

    if (!$xero_contact_id) {
      $this->logger->error('Could not find Xero Contact ID for user @user (UID: @uid)', [
        '@user' => $owner->getDisplayName(),
        '@uid' => $owner->id(),
      ]);
      return;
    }

    $bundle = $entity->bundle();
    $mappings = $this->config->get('mappings') ?: [];
    $account_code = $mappings[$bundle] ?? '600';

    $amount = 0;
    if ($entity->hasField('field_amount')) {
      $amount = $entity->get('field_amount')->value;
    }

    // Prepare Invoice (Bill) payload.
    // Note: Xero requires "Invoices" root key when posting JSON in some contexts, 
    // but typically raw body is the invoice(s).
    $invoice_data = [
      'Type' => 'ACCPAY',
      'InvoiceNumber' => 'PAYREQ-' . $entity->id(),
      'Contact' => [
        'ContactID' => $xero_contact_id,
      ],
      'Date' => date('Y-m-d'),
      'DueDate' => date('Y-m-d', strtotime('+30 days')),
      'LineAmountTypes' => 'Exclusive',
      'Status' => 'SUBMITTED',
      'LineItems' => [
        [
          'Description' => $entity->label() ?: 'Payment Request #' . $entity->id(),
          'Quantity' => 1,
          'UnitAmount' => $amount,
          'AccountCode' => $account_code,
        ],
      ],
    ];

    try {
      // Create Invoice in Xero.
      // We pass the invoice wrapped in an array or not? Xero often accepts a list.
      // Usually POST /Invoices expects { "Invoices": [ ... ] } or just the array if handled by library.
      // Let's assume standard Xero API JSON structure.
      
      $payload = [
        'Invoices' => [$invoice_data]
      ];

      $response = $this->xeroClient->request('POST', 'Invoices', [
        'json' => $payload,
        'headers' => ['Accept' => 'application/json']
      ]);
      
      $response_body = json_decode($response->getBody()->getContents(), TRUE);

      if ($response_body && isset($response_body['Invoices'][0]['InvoiceID'])) {
        $invoice_id = $response_body['Invoices'][0]['InvoiceID'];
        if ($entity->hasField('field_xero_invoice_id')) {
          $entity->set('field_xero_invoice_id', $invoice_id);
          $entity->save();
        }

        $this->logger->notice('Successfully created Xero Bill @invoice_id for payment request @id', [
          '@invoice_id' => $invoice_id,
          '@id' => $entity->id(),
        ]);

        // Handle Attachments.
        $this->uploadAttachments($entity, $invoice_id);
      }
      else {
        $this->logger->error('Failed to create Xero Bill for payment request @id. Response: @response', [
          '@id' => $entity->id(),
          '@response' => print_r($response_body, TRUE)
        ]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error syncing to Xero: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Retrieves the Xero Contact ID for a Drupal user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The Drupal user.
   *
   * @return string|null
   *   The Xero Contact ID (GUID) or NULL if not found.
   */
  protected function getXeroContactId(UserInterface $user) {
    // 1. Check local field on User entity.
    if ($user->hasField('field_xero_contact_id') && !$user->get('field_xero_contact_id')->isEmpty()) {
      return $user->get('field_xero_contact_id')->value;
    }

    // 2. Fallback: Search Xero by Email.
    $email = $user->getEmail();
    if (!$email) {
      return NULL;
    }

    try {
      $response = $this->xeroClient->request('GET', 'Contacts', [
        'query' => ['where' => 'EmailAddress=="' . $email . '"'],
        'headers' => ['Accept' => 'application/json']
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (!empty($data['Contacts']) && isset($data['Contacts'][0]['ContactID'])) {
        $contact_id = $data['Contacts'][0]['ContactID'];
        
        // Save back to user for future use.
        if ($user->hasField('field_xero_contact_id')) {
          $user->set('field_xero_contact_id', $contact_id);
          $user->save();
          $this->logger->info('Matched Xero Contact @cid to User @uid by email.', ['@cid' => $contact_id, '@uid' => $user->id()]);
        }
        
        return $contact_id;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to search Xero contacts by email: @message', ['@message' => $e->getMessage()]);
    }

    return NULL;
  }

  /**
   * Uploads attachments from the entity to the Xero Invoice.
   *
   * @param \Drupal\eck\Entity\EckEntityInterface $entity
   *   The ECK entity.
   * @param string $invoice_id
   *   The Xero Invoice GUID.
   */
  protected function uploadAttachments(EckEntityInterface $entity, $invoice_id) {
    $attachment_field = $this->config->get('attachment_field') ?: 'field_attachment';
    
    if (!$entity->hasField($attachment_field) || $entity->get($attachment_field)->isEmpty()) {
      return;
    }

    foreach ($entity->get($attachment_field) as $item) {
      $file = $item->entity;
      if ($file instanceof File) {
        try {
          $file_uri = $file->getFileUri();
          $file_path = $this->fileSystem->realpath($file_uri);
          $file_content = file_get_contents($file_path);
          $filename = $file->getFilename();
          $mime_type = $file->getMimeType();
          
          // Xero Attachment Endpoint: POST /Invoices/{Guid}/Attachments/{Filename}
          // The filename should be URL encoded if it contains special characters, 
          // but typically simple filenames are best.
          $endpoint = "Invoices/$invoice_id/Attachments/" . rawurlencode($filename);

          $this->xeroClient->request('POST', $endpoint, [
            'body' => $file_content,
            'headers' => [
              'Content-Type' => $mime_type,
              'Accept' => 'application/json'
            ]
          ]);

          $this->logger->notice('Uploaded attachment @filename to Xero Bill @invoice_id', [
            '@filename' => $filename,
            '@invoice_id' => $invoice_id,
          ]);
        }
        catch (\Exception $e) {
          $this->logger->error('Failed to upload attachment @filename: @message', [
            '@filename' => $file->getFilename(),
            '@message' => $e->getMessage(),
          ]);
        }
      }
    }
  }
}
