<?php

namespace Drupal\user\Plugin\Action;

/**
 * Removes a role from a user.
 *
 * @Action(
 *   id = "user_remove_role_action",
 *   label = @Translation("Remove a role from the selected users"),
 *   type = "user"
 * )
 */
class RemoveRoleUser extends ChangeUserRoleBase {

  /**
   * {@inheritdoc}
   */
  public function execute($account = NULL) {
    $rid = $this->configuration['rid'];
    // Skip removing the role from the user if they already don't have it.
    if ($account !== FALSE && $account->hasRole($rid)) {
      // For efficiency manually save the original account before applying
      // any changes.
      $account->original = clone $account;
      $account->removeRole($rid);
      $account->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $role_storage = \Drupal::getContainer()
      ->get('entity.manager')
      ->getStorage('user_role');

    // Get the admin role.
    $admin_roles = $role_storage->getQuery()
      ->condition('is_admin', TRUE)
      ->execute();
    $admin_role = reset($admin_roles);

    // Are we even removing the admin role?
    if($this->configuration['rid'] == $admin_role) {

      // Get count of all users with admin role.
      $ids = $role_storage->getQuery()
        ->condition('roles', $admin_role)
        ->execute();

      $admin_user_count = count($ids);

      // Count the admin users in this batch.
      $deleted_admin_user_count = 0;
      foreach ($entities as $account) {
        if ($account->hasRole($admin_role)) {
          $deleted_admin_user_count++;
        }
      }
      print_r( $ids); die(); // . ":" . $deleted_admin_user_count);


      // Prevent all/last user(s) with administrator role from being deleted.
      if ($admin_user_count == $deleted_admin_user_count) {
        $message = $this->t('This action cannot be completed, because it would remove all accounts with the Administrator role.');
        drupal_set_message($message, 'error');
        // If only user 1 was selected, redirect to the overview.
        return $this->redirect('entity.user.collection');
      }
    }

    foreach ($entities as $entity) {
      $this->execute($entity);
    }
  }

}
