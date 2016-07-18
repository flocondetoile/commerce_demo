<?php

namespace Drupal\Tests\commerce_demo\Functional;

use Blackfire\Bridge\PhpUnit\TestCaseTrait as BlackfireTrait;
use Blackfire\Profile\Configuration;
use Drupal\commerce_product\Entity\Product;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;
use Drupal\Tests\migrate\Kernel\MigrateDumpAlterInterface;

/**
 * Tests customer buying experience performance.
 *
 * @group commerce_demo
 */
class CustomerBuyPerformanceTest extends CommerceBrowserTestBase implements MigrateMessageInterface {
  use BlackfireTrait;

  /**
   * The source database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $sourceDatabase;

  /**
   * The primary migration being tested.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  public static $modules = [
    'system', 'field', 'options', 'user', 'path', 'text', 'user', 'views',
    'file', 'image',
    'migrate', 'migrate_plus', 'migrate_tools', 'migrate_source_csv',
    'profile', 'address', 'state_machine', 'inline_entity_form', 'entity',
    'commerce',
    'commerce_price',
    'commerce_store',
    'commerce_order',
    'commerce_product',
    'commerce_cart',
    'commerce_migrate',
    'commerce_demo',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    /** @var \Drupal\migrate_plus\Plugin\MigrationConfigEntityPluginManager $manager */
    $manager = \Drupal::service('plugin.manager.config_entity_migration');
    $plugins = $manager->createInstances([]);
    $migrations = [];

    /** @var \Drupal\migrate_plus\Entity\Migration $migration */
    foreach ($plugins as $id => $migration) {
      $configured_group_id = $migration->get('migration_group');
      if (empty($configured_group_id)) {
        continue;
      }
      if ($configured_group_id == 'commerce_demo') {
        $migrations[] = $migration->id();
      }
    }
    $this->assertEquals(4, count($migrations));
    $this->executeMigrations($migrations);
  }

  /**
   * Tests loading the product page.
   *
   * @group blackfire
   */
  public function testProductPageLoadPerformance() {
    $product = Product::load(1);
    $this->assertNotNull($product);
    $config = new Configuration();
    $config->setTitle('Commerce Demo: Product page load performance');
    $config->assert('main.wall_time < 0.5s', 'Wall time');
    $this->assertBlackfire($config, function () {
      $this->drupalGet('product/1');
    });
  }

  /**
   * Tests add to cart performance.
   *
   * @group blackfire
   */
  public function testAddToCartPerformance() {
    $product = Product::load(1);
    $this->assertNotNull($product);
    $config = new Configuration();
    $config->setTitle('Commerce Demo: Add to cart performance');
    $config->assert('main.wall_time < 0.5s', 'Wall time');
    $this->drupalGet('product/1');
    $this->assertBlackfire($config, function () {
      $this->submitForm([], 'Add to cart');
    });
  }

  /**
   * Executes a set of migrations in dependency order.
   *
   * @param string[] $ids
   *   Array of migration IDs, in any order.
   */
  protected function executeMigrations(array $ids) {
    $manager = $this->container->get('plugin.manager.migration');
    array_walk($ids, function ($id) use ($manager) {
      // This is possibly a base plugin ID and we want to run all derivatives.
      $instances = $manager->createInstances($id);
      array_walk($instances, [$this, 'executeMigration']);
    });
  }

  /**
   * Executes a single migration.
   *
   * @param string|\Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration to execute, or its ID.
   */
  protected function executeMigration($migration) {
    if (is_string($migration)) {
      $this->migration = $this->getMigration($migration);
    }
    else {
      $this->migration = $migration;
    }
    if ($this instanceof MigrateDumpAlterInterface) {
      static::migrateDumpAlter($this);
    }
    $result = (new MigrateExecutable($this->migration, $this))->import();
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
  }

  /**
   * Gets the migration plugin.
   *
   * @param string $plugin_id
   *   The plugin ID of the migration to get.
   *
   * @return \Drupal\migrate\Plugin\Migration
   *   The migration plugin.
   */
  protected function getMigration($plugin_id) {
    return $this->container->get('plugin.manager.migration')->createInstance($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function display($message, $type = 'status') {
    $this->assertTrue($type == 'status');
  }

}
