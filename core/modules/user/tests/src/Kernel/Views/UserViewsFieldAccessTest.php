<?php

namespace Drupal\Tests\user\Kernel\Views;

use Drupal\user\Entity\User;
use Drupal\Tests\views\Kernel\Handler\FieldFieldAccessTestBase;

/**
 * Tests base field access in Views for the user entity.
 *
 * @group user
 */
class UserViewsFieldAccessTest extends FieldFieldAccessTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['user', 'entity_test', 'language'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    $this->installEntitySchema('user');
  }

  public function testUserFields() {
    $user = User::create([
      'name' => 'test user',
      'status' => 1,
      'created' => 123456,
    ]);

    $user->save();

    // @todo Expand the test coverage in https://www.drupal.org/node/2464635

    $this->assertFieldAccess('user', 'uid', $user->id());
    $this->assertFieldAccess('user', 'uuid', $user->uuid());
    $this->assertFieldAccess('user', 'langcode', $user->language()->getName());
    $this->assertFieldAccess('user', 'name', 'test user');
    // $this->assertFieldAccess('user', 'created', \Drupal::service('date.formatter')->format(123456));
    // $this->assertFieldAccess('user', 'changed', \Drupal::service('date.formatter')->format(REQUEST_TIME));
  }

}
