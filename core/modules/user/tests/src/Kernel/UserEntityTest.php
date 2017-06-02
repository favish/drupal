<?php

namespace Drupal\Tests\user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * Tests the user entity class.
 *
 * @group user
 * @see \Drupal\user\Entity\User
 */
class UserEntityTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['system', 'user', 'field'];

  /**
   * Tests some of the methods.
   *
   * @see \Drupal\user\Entity\User::getRoles()
   * @see \Drupal\user\Entity\User::addRole()
   * @see \Drupal\user\Entity\User::removeRole()
   */
  public function testUserMethods() {
    $role_storage = $this->container->get('entity.manager')->getStorage('user_role');
    $role_storage->create(['id' => 'test_role_one'])->save();
    $role_storage->create(['id' => 'test_role_two'])->save();
    $role_storage->create(['id' => 'test_role_three'])->save();

    // Test that user 1 is no different than any regular authenticated user.
    $user = User::create(['uid' => 1]);
    $this->assertEqual([RoleInterface::AUTHENTICATED_ID], $user->getRoles());

    // Test that a regular authenticated user gets the authenticated role.
    $user = User::create(['uid' => 2]);
    $this->assertEqual([RoleInterface::AUTHENTICATED_ID], $user->getRoles());

    // Test role manipulation for an authenticated user.
    $values = ['uid' => 3, 'roles' => ['test_role_one']];
    $user = User::create($values);

    $this->assertTrue($user->hasRole('test_role_one'));
    $this->assertFalse($user->hasRole('test_role_two'));
    $this->assertEqual([RoleInterface::AUTHENTICATED_ID, 'test_role_one'], $user->getRoles());

    $user->addRole('test_role_one');
    $this->assertTrue($user->hasRole('test_role_one'));
    $this->assertFalse($user->hasRole('test_role_two'));
    $this->assertEqual([RoleInterface::AUTHENTICATED_ID, 'test_role_one'], $user->getRoles());

    $user->addRole('test_role_two');
    $this->assertTrue($user->hasRole('test_role_one'));
    $this->assertTrue($user->hasRole('test_role_two'));
    $this->assertEqual([RoleInterface::AUTHENTICATED_ID, 'test_role_one', 'test_role_two'], $user->getRoles());

    $user->removeRole('test_role_three');
    $this->assertTrue($user->hasRole('test_role_one'));
    $this->assertTrue($user->hasRole('test_role_two'));
    $this->assertEqual([RoleInterface::AUTHENTICATED_ID, 'test_role_one', 'test_role_two'], $user->getRoles());

    $user->removeRole('test_role_one');
    $this->assertFalse($user->hasRole('test_role_one'));
    $this->assertTrue($user->hasRole('test_role_two'));
    $this->assertEqual([RoleInterface::AUTHENTICATED_ID, 'test_role_two'], $user->getRoles());
  }

}
