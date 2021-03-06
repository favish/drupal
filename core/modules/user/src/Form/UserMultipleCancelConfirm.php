<?php

namespace Drupal\user\Form;

use Drupal\User\RoleStorageInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for cancelling multiple user accounts.
 */
class UserMultipleCancelConfirm extends ConfirmFormBase {

  /**
   * The temp store factory.
   *
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The role storage used when changing the admin role.
   *
   * @var \Drupal\user\RoleStorageInterface
   */
  protected $roleStorage;

  /**
   * Constructs a new UserMultipleCancelConfirm.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   The temp store factory.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\user\RoleStorageInterface $role_storage
   *   The role storage.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, UserStorageInterface $user_storage, EntityManagerInterface $entity_manager, RoleStorageInterface $role_storage) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->userStorage = $user_storage;
    $this->entityManager = $entity_manager;
    $this->roleStorage = $role_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('entity.manager')->getStorage('user'),
      $container->get('entity.manager'),
      $container->get('entity.manager')->getStorage('user_role')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_multiple_cancel_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to cancel these user accounts?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.user.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Cancel accounts');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Retrieve the accounts to be canceled from the temp store.
    /* @var \Drupal\user\Entity\User[] $accounts */
    $accounts = $this->tempStoreFactory
      ->get('user_user_operations_cancel')
      ->get($this->currentUser()->id());
    if (!$accounts) {
      return $this->redirect('entity.user.collection');
    }

    // Get the admin role.
    $admin_roles = $this->roleStorage->getQuery()
      ->condition('is_admin', TRUE)
      ->execute();
    $admin_role = reset($admin_roles);

    // Count the admin users in this batch.
    $deleted_admin_user_count = 0;
    foreach ($accounts as $account) {
      if ($account->hasRole($admin_role)) {
        $deleted_admin_user_count++;
      }
    }

    // Get count of all users with admin role.
    $ids = $this->userStorage->getQuery()
      ->condition('roles', $admin_role)
      ->execute();
    $admin_user_count = count($ids);

    // Prevent all/last user(s) with administrator role from being deleted.
    if ($admin_user_count == $deleted_admin_user_count) {
      $message = $this->t('This action cannot be completed, because it would cancel all administrator accounts.');
      drupal_set_message($message, 'error');
      // If only user 1 was selected, redirect to the overview.
      return $this->redirect('entity.user.collection');
    }

    $form['operation'] = ['#type' => 'hidden', '#value' => 'cancel'];

    $form['user_cancel_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('When cancelling these accounts'),
    ];

    $form['user_cancel_method'] += user_cancel_methods();

    // Allow to send the account cancellation confirmation mail.
    $form['user_cancel_confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require email confirmation to cancel account'),
      '#default_value' => FALSE,
      '#description' => $this->t('When enabled, the user must confirm the account cancellation via email.'),
    ];
    // Also allow to send account canceled notification mail, if enabled.
    $form['user_cancel_notify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify user when account is canceled'),
      '#default_value' => FALSE,
      '#access' => $this->config('user.settings')->get('notify.status_canceled'),
      '#description' => $this->t('When enabled, the user will receive an email notification after the account has been canceled.'),
    ];

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $current_user_id = $this->currentUser()->id();

    // Clear out the accounts from the temp store.
    $this->tempStoreFactory->get('user_user_operations_cancel')->delete($current_user_id);
    if ($form_state->getValue('confirm')) {
      foreach ($form_state->getValue('accounts') as $uid => $value) {
        // Prevent programmatic form submissions from cancelling user 1.
        if ($uid <= 1) {
          continue;
        }
        // Prevent user administrators from deleting themselves without confirmation.
        if ($uid == $current_user_id) {
          $admin_form_mock = [];
          $admin_form_state = $form_state;
          $admin_form_state->unsetValue('user_cancel_confirm');
          // The $user global is not a complete user entity, so load the full
          // entity.
          $account = $this->userStorage->load($uid);
          $admin_form = $this->entityManager->getFormObject('user', 'cancel');
          $admin_form->setEntity($account);
          // Calling this directly required to init form object with $account.
          $admin_form->buildForm($admin_form_mock, $admin_form_state);
          $admin_form->submitForm($admin_form_mock, $admin_form_state);
        }
        else {
          user_cancel($form_state->getValues(), $uid, $form_state->getValue('user_cancel_method'));
        }
      }
    }
    $form_state->setRedirect('entity.user.collection');
  }

}
