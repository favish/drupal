<?php

namespace Drupal\KernelTests\Core\Render;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simpletest\UserCreationTrait;

/**
 * Tests the caching of render items via functional tests.
 *
 * @group Render
 */
class RenderCacheTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['user']);
    $this->installSchema('system', ['sequences']);
  }

  /**
   * Check the render cache for the user.permissions context.
   */
  public function testPermissionContext() {
    $this->doTestWithContexts(['user.permissions']);
  }

  /**
   * Check the render cache for the user.roles context.
   */
  public function testRolesContext() {
    $this->doTestWithContexts(['user.roles']);
  }

  /**
   * Checks the functionality of the render cache for the given context.
   *
   * @param string[] $contexts
   *   List of cache contexts to use.
   */
  protected function doTestWithContexts($contexts) {
    // Set up two authenticated users and an admin user so we can test the
    // output of the render cache for them.
    $first_authenticated_user = $this->createUser();
    $second_authenticated_user = $this->createUser();
    $admin_user = $this->createUser([], NULL, TRUE);

    // Set up a test element we will reuse below.
    $test_element = [
      '#cache' => [
        'keys' => ['test'],
        'contexts' => $contexts,
      ],
    ];

    // Render content that only authenticated users should see.
    \Drupal::service('account_switcher')->switchTo($first_authenticated_user);
    $element = $test_element;
    $element['#markup'] = 'content for authenticated users';
    $output = \Drupal::service('renderer')->renderRoot($element);
    $this->assertEqual($output, 'content for authenticated users');

    // Verify the cache is working by rendering the same element but with
    // different markup passed in; the result should be the same.
    $element = $test_element;
    $element['#markup'] = 'should not be used';
    $output = \Drupal::service('renderer')->renderRoot($element);
    $this->assertEqual($output, 'content for authenticated users');
    \Drupal::service('account_switcher')->switchBack();

    // Verify that the second authenticated user shares the cache with the
    // first authenticated user.
    \Drupal::service('account_switcher')->switchTo($second_authenticated_user);
    $element = $test_element;
    $element['#markup'] = 'should not be used';
    $output = \Drupal::service('renderer')->renderRoot($element);
    $this->assertEqual($output, 'content for authenticated users');
    \Drupal::service('account_switcher')->switchBack();

    // Verify that the admin user (who has an admin role without explicit
    // permissions) does not share the same cache.
    \Drupal::service('account_switcher')->switchTo($admin_user);
    $element = $test_element;
    $element['#markup'] = 'content for admin user';
    $output = \Drupal::service('renderer')->renderRoot($element);
    $this->assertEqual($output, 'content for admin user');
  }

}
