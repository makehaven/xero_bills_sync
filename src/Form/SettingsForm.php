<?php

namespace Drupal\xero_bills_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\xero_bills_sync\Service\SyncManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Configure Xero Bills Sync settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The sync manager.
   *
   * @var \Drupal\xero_bills_sync\Service\SyncManager
   */
  protected $syncManager;

  /**
   * Constructs a new SettingsForm.
   */
  public function __construct($config_factory, TypedConfigManagerInterface $typed_config, EntityTypeBundleInfoInterface $bundle_info, SyncManager $sync_manager) {
    parent::__construct($config_factory, $typed_config);
    $this->bundleInfo = $bundle_info;
    $this->syncManager = $sync_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.bundle.info'),
      $container->get('xero_bills_sync.sync_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['xero_bills_sync.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xero_bills_sync_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('xero_bills_sync.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General Settings'),
      '#open' => TRUE,
    ];

    $form['general']['default_hourly_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('System Default Hourly Rate'),
      '#description' => $this->t('The default hourly rate to use if a specific user does not have one set.'),
      '#default_value' => $config->get('default_hourly_rate') ?: 25.00,
      '#min' => 0,
      '#step' => 0.01,
    ];

    $form['general']['sync_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Xero synchronization'),
      '#description' => $this->t('When disabled, payment requests are stored locally without attempting to reach Xero.'),
      '#default_value' => (bool) $config->get('sync_enabled'),
    ];

    $form['general']['sync_backlog'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Backfill submitted requests when sync is enabled'),
      '#description' => $this->t('When enabled, cron will sync submitted requests that do not yet have a Xero Invoice ID.'),
      '#default_value' => (bool) $config->get('sync_backlog'),
      '#states' => [
        'visible' => [
          ':input[name="sync_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['eck'] = [
      '#type' => 'details',
      '#title' => $this->t('ECK Settings'),
      '#open' => TRUE,
    ];

    $form['eck']['attachment_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Attachment Field Name'),
      '#description' => $this->t('The field name on the payment_request entity that contains the file/image attachments.'),
      '#default_value' => $config->get('attachment_field') ?: 'field_attachment',
    ];

    $form['mappings'] = [
      '#type' => 'details',
      '#title' => $this->t('Bundle to Account Code Mappings'),
      '#description' => $this->t('Map each payment_request bundle to its corresponding Xero Account Code.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $bundles = $this->bundleInfo->getBundleInfo('payment_request');
    foreach ($bundles as $bundle_id => $bundle_info) {
      $form['mappings'][$bundle_id] = [
        '#type' => 'textfield',
        '#title' => $bundle_info['label'],
        '#default_value' => $config->get('mappings.' . $bundle_id) ?: '600',
        '#size' => 10,
      ];
    }

    if (empty($bundles)) {
      $form['mappings']['info'] = [
        '#markup' => $this->t('No bundles found for entity type "payment_request". Please ensure it is created.'),
      ];
    }

    $form['instructions'] = [
      '#type' => 'details',
      '#title' => $this->t('Form Instructions'),
      '#open' => TRUE,
    ];

    $form['instructions']['reimbursement_instructions'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Reimbursement instructions'),
      '#description' => $this->t('Shown above the reimbursement request form.'),
      '#default_value' => $config->get('reimbursement_instructions.value') ?: '',
      '#format' => $config->get('reimbursement_instructions.format') ?: 'basic_html',
    ];

    $form['instructions']['hours_instructions'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Hours-based payment instructions'),
      '#description' => $this->t('Shown above forms that include hours and hourly rate fields.'),
      '#default_value' => $config->get('hours_instructions.value') ?: '',
      '#format' => $config->get('hours_instructions.format') ?: 'basic_html',
    ];

    $form = parent::buildForm($form, $form_state);

    $form['actions']['backfill_now'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run backfill now'),
      '#submit' => ['::submitForm', '::submitBackfill'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('xero_bills_sync.settings')
      ->set('default_hourly_rate', $form_state->getValue('default_hourly_rate'))
      ->set('attachment_field', $form_state->getValue('attachment_field'))
      ->set('sync_enabled', (bool) $form_state->getValue('sync_enabled'))
      ->set('sync_backlog', (bool) $form_state->getValue('sync_backlog'))
      ->set('mappings', $form_state->getValue('mappings'))
      ->set('reimbursement_instructions', $form_state->getValue('reimbursement_instructions'))
      ->set('hours_instructions', $form_state->getValue('hours_instructions'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Runs a one-time backfill of submitted requests.
   */
  public function submitBackfill(array &$form, FormStateInterface $form_state) {
    $sync_enabled = (bool) $this->config('xero_bills_sync.settings')->get('sync_enabled');
    if (!$sync_enabled) {
      $this->messenger()->addWarning($this->t('Enable Xero synchronization before running a backfill.'));
      return;
    }

    $count = $this->syncManager->syncBacklog(50);
    if ($count === 0) {
      $this->messenger()->addStatus($this->t('No submitted requests were found to backfill.'));
      return;
    }

    $this->messenger()->addStatus($this->t('Backfill started for @count submitted request(s).', ['@count' => $count]));
  }

}
