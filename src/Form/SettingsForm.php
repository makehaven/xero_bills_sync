<?php

namespace Drupal\xero_bills_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
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
   * Constructs a new SettingsForm.
   */
  public function __construct($config_factory, EntityTypeBundleInfoInterface $bundle_info) {
    parent::__construct($config_factory);
    $this->bundleInfo = $bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.bundle.info')
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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('xero_bills_sync.settings')
      ->set('default_hourly_rate', $form_state->getValue('default_hourly_rate'))
      ->set('attachment_field', $form_state->getValue('attachment_field'))
      ->set('mappings', $form_state->getValue('mappings'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}