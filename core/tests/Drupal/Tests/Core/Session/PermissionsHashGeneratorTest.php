<?php

namespace Drupal\Tests\Core\Session;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Session\PermissionsHashGenerator;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Session\PermissionsHashGenerator
 * @group Session
 */
class PermissionsHashGeneratorTest extends UnitTestCase {

  /**
   * The mocked admin account.
   *
   * @var \Drupal\user\UserInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $account1;

  /**
   * An "updated" admin account.
   *
   * @var \Drupal\user\UserInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $account1Updated;

  /**
   * A mocked account.
   *
   * @var \Drupal\user\UserInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $account2;

  /**
   * An "updated" mocked account.
   *
   * @var \Drupal\user\UserInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $account2Updated;

  /**
   * A different account.
   *
   * @var \Drupal\user\UserInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $account3;

  /**
   * The mocked private key service.
   *
   * @var \Drupal\Core\PrivateKey|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $privateKey;

  /**
   * The mocked cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $cache;

  /**
   * The mocked cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $staticCache;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityTypeManager;

  /**
   * The permission hash class being tested.
   *
   * @var \Drupal\Core\Session\PermissionsHashGeneratorInterface
   */
  protected $permissionsHash;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    new Settings(['hash_salt' => 'test']);

    // Mock an admin role.
    $admin_role = $this->getMockBuilder('Drupal\user\Entity\Role')
      ->disableOriginalConstructor()
      ->setMethods(['isAdmin'])
      ->getMock();
    $admin_role->expects($this->any())
      ->method('isAdmin')
      ->will($this->returnValue(TRUE));

    // Mock a regular role.
    $regular_role = $this->getMockBuilder('Drupal\user\Entity\Role')
      ->disableOriginalConstructor()
      ->setMethods(['isAdmin'])
      ->getMock();
    $regular_role->expects($this->any())
      ->method('isAdmin')
      ->will($this->returnValue(FALSE));

    // Account 1: 'authenticated' and 'administrator' role.
    $roles_1 = [
      'authenticated' => $regular_role,
      'administrator' => $admin_role,
    ];
    $this->account1 = $this->getMockBuilder('Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->setMethods(['getRoles', 'id'])
      ->getMock();
    $this->account1->expects($this->any())
      ->method('getRoles')
      ->will($this->returnValue(array_keys($roles_1)));
    $this->account1->expects($this->any())
      ->method('id')
      ->willReturn(1);

    // Account 2: 'editor' and 'authenticated' roles.
    $roles_2 = [
      'editor' => $regular_role,
      'authenticated' => $regular_role,
    ];
    $this->account2 = $this->getMockBuilder('Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->setMethods(['getRoles', 'id'])
      ->getMock();
    $this->account2->expects($this->any())
      ->method('getRoles')
      ->will($this->returnValue(array_keys($roles_2)));
    $this->account2->expects($this->any())
      ->method('id')
      ->willReturn(2);

    // Account 3: 'authenticated' and 'editor' roles (different order).
    $roles_3 = [
      'authenticated' => $regular_role,
      'editor' => $regular_role,
    ];
    $this->account3 = $this->getMockBuilder('Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->setMethods(['getRoles', 'id'])
      ->getMock();
    $this->account3->expects($this->any())
      ->method('getRoles')
      ->will($this->returnValue(array_keys($roles_3)));
    $this->account3->expects($this->any())
      ->method('id')
      ->willReturn(3);

    // Updated account 1: now also 'publisher' role.
    $roles_1_updated = [
      'authenticated' => $regular_role,
      'administrator' => $admin_role,
      'publisher' => $regular_role,
    ];
    $this->account1Updated = $this->getMockBuilder('Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->setMethods(['getRoles', 'id'])
      ->getMock();
    $this->account1Updated->expects($this->any())
      ->method('getRoles')
      ->will($this->returnValue(array_keys($roles_1_updated)));
    $this->account1Updated->expects($this->any())
      ->method('id')
      ->willReturn(1);

    // Updated account 2: now also 'publisher' role.
    $roles_2_updated = [
      'editor' => $regular_role,
      'authenticated' => $regular_role,
      'publisher' => $regular_role,
    ];
    $this->account2Updated = $this->getMockBuilder('Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->setMethods(['getRoles', 'id'])
      ->getMock();
    $this->account2Updated->expects($this->any())
      ->method('getRoles')
      ->will($this->returnValue(array_keys($roles_2_updated)));
    $this->account2Updated->expects($this->any())
      ->method('id')
      ->willReturn(2);

    // Mocked private key, cache, static cache and entity type manager services.
    $random = Crypt::randomBytesBase64(55);
    $this->privateKey = $this->getMockBuilder('Drupal\Core\PrivateKey')
      ->disableOriginalConstructor()
      ->setMethods(['get'])
      ->getMock();
    $this->privateKey->expects($this->any())
      ->method('get')
      ->will($this->returnValue($random));
    $this->cache = $this->getMockBuilder('Drupal\Core\Cache\CacheBackendInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $this->staticCache = $this->getMockBuilder('Drupal\Core\Cache\CacheBackendInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $this->entityTypeManager = $this->getMockBuilder('Drupal\Core\Entity\EntityTypeManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    // Mock the role storage and the return values we care about.
    $role_storage = $this->getMockBuilder('Drupal\Core\Entity\EntityStorageInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $role_storage->expects($this->any())
      ->method('loadMultiple')
      ->willReturnMap([
        [$this->account1->getRoles(), $roles_1],
        [$this->account2->getRoles(), $roles_2],
        [$this->account3->getRoles(), $roles_3],
        [$this->account1Updated->getRoles(), $roles_1_updated],
        [$this->account2Updated->getRoles(), $roles_2_updated],
      ]);

    // Set the role storage on the entity type manager.
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('user_role')
      ->will($this->returnValue($role_storage));

    $this->permissionsHash = new PermissionsHashGenerator($this->privateKey, $this->cache, $this->staticCache, $this->entityTypeManager);
  }

  /**
   * @covers ::generate
   */
  public function testGenerate() {
    // Ensure that admin accounts always gets the same hash.
    $admin_hash = $this->permissionsHash->generate($this->account1);
    $updated_admin_hash = $this->permissionsHash->generate($this->account1Updated);
    $this->assertSame($admin_hash, $updated_admin_hash, 'Admin user with updated roles generates same permissions hash.');

    // Ensure that two user accounts with the same roles generate the same hash.
    $hash_2 = $this->permissionsHash->generate($this->account2);
    $hash_3 = $this->permissionsHash->generate($this->account3);
    $this->assertSame($hash_2, $hash_3, 'Different users with the same roles generate the same permissions hash.');

    // Compare with hash for user account 2 with an additional role.
    $updated_hash_2 = $this->permissionsHash->generate($this->account2Updated);
    $this->assertNotSame($hash_2, $updated_hash_2, 'Same user with updated roles generates different permissions hash.');
  }

  /**
   * @covers ::generate
   */
  public function testGeneratePersistentCache() {
    // Set expectations for the mocked cache backend.
    $expected_cid = 'user_permissions_hash:authenticated,editor';

    $mock_cache = new \stdClass();
    $mock_cache->data = 'test_hash_here';

    $this->staticCache->expects($this->once())
      ->method('get')
      ->with($expected_cid)
      ->will($this->returnValue(FALSE));
    $this->staticCache->expects($this->once())
      ->method('set')
      ->with($expected_cid, $this->isType('string'));

    $this->cache->expects($this->once())
      ->method('get')
      ->with($expected_cid)
      ->will($this->returnValue($mock_cache));
    $this->cache->expects($this->never())
      ->method('set');

    $this->permissionsHash->generate($this->account2);
  }

  /**
   * @covers ::generate
   */
  public function testGenerateStaticCache() {
    // Set expectations for the mocked cache backend.
    $expected_cid = 'user_permissions_hash:authenticated,editor';

    $mock_cache = new \stdClass();
    $mock_cache->data = 'test_hash_here';

    $this->staticCache->expects($this->once())
      ->method('get')
      ->with($expected_cid)
      ->will($this->returnValue($mock_cache));
    $this->staticCache->expects($this->never())
      ->method('set');

    $this->cache->expects($this->never())
      ->method('get');
    $this->cache->expects($this->never())
      ->method('set');

    $this->permissionsHash->generate($this->account2);
  }

  /**
   * Tests the generate method with no cache returned.
   */
  public function testGenerateNoCache() {
    // Set expectations for the mocked cache backend.
    $expected_cid = 'user_permissions_hash:authenticated,editor';

    $this->staticCache->expects($this->once())
      ->method('get')
      ->with($expected_cid)
      ->will($this->returnValue(FALSE));
    $this->staticCache->expects($this->once())
      ->method('set')
      ->with($expected_cid, $this->isType('string'));

    $this->cache->expects($this->once())
      ->method('get')
      ->with($expected_cid)
      ->will($this->returnValue(FALSE));
    $this->cache->expects($this->once())
      ->method('set')
      ->with($expected_cid, $this->isType('string'));

    $this->permissionsHash->generate($this->account2);
  }

}

namespace Drupal\Core\Session;

// @todo remove once user_role_permissions() can be injected.
if (!function_exists('user_role_permissions')) {
  function user_role_permissions(array $roles) {
    $role_permissions = [];
    foreach ($roles as $rid) {
      $role_permissions[$rid] = [];
    }
    return $role_permissions;
  }
}
