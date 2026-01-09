<?php

namespace Drupal\xero_bills_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Payment Request routes.
 */
class PaymentRequestController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * Constructs a new PaymentRequestController object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFormBuilderInterface $entity_form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder')
    );
  }

  /**
   * Provides the payment request add form.
   *
   * @param string $bundle
   *   The bundle machine name.
   * @param \Drupal\user\UserInterface|string|null $user
   *   The user entity or username/ID from the route.
   *
   * @return array
   *   The render array.
   */
  public function add($bundle, $user = NULL) {
    // Default to current user.
    $uid = $this->currentUser()->id();

    // If a valid user entity is provided via the route, use that.
    if ($user instanceof UserInterface) {
      $uid = $user->id();
    }
    // Note: If $user is a string (e.g. username from URL where validation failed),
    // we default to current user to prevent errors, effectively handling the "username in URL" bug
    // by falling back to a safe default while the page loads.

    $entity = $this->entityTypeManager->getStorage('payment_request')->create([
      'type' => $bundle,
      'uid' => $uid,
    ]);

    $form = $this->entityFormBuilder->getForm($entity, 'default');
    
    // Attach our custom styling.
    $form['#attached']['library'][] = 'xero_bills_sync/payment_form_style';
    
    // Wrap in a container for targeting.
    $form['#prefix'] = '<div class="payment-request-compact-form">';
    $form['#suffix'] = '</div>';

    return [
      '#type' => 'container',
      'content' => $form,
      '#title' => $this->t('Request @type', ['@type' => ucfirst(str_replace('_', ' ', $bundle))]),
    ];
  }

  /**
   * Redirects /payback to the reimbursement form for the current user.
   */
  public function paybackRedirect() {
    return $this->redirect('xero_bills_sync.add_request', [
      'user' => $this->currentUser()->id(),
      'bundle' => 'reimbursement',
    ]);
  }

  /**
   * Redirects /timesheet to the contractor payment form for the current user.
   */
  public function timesheetRedirect() {
    return $this->redirect('xero_bills_sync.add_request', [
      'user' => $this->currentUser()->id(),
      'bundle' => 'payment',
    ]);
  }

}
