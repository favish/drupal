<?php

/**
 * @file
 * Post update functions for User module.
 */

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * Enforce order of role permissions.
 */
function user_post_update_enforce_order_of_permissions() {
  $entity_save = function (Role $role) {
    $permissions = $role->getPermissions();
    sort($permissions);
    if ($permissions !== $role->getPermissions()) {
      $role->save();
    }
  };
  array_map($entity_save, Role::loadMultiple());
}

/**
 * Grant user 1 the administrator role.
 */
function user_post_update_grant_user_1_admin_role() {
  $account = User::load(1);
  $account->addRole(RoleInterface::ADMINISTRATOR_ID);
  $account->save();
}
