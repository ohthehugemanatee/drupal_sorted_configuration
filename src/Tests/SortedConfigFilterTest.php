<?php

namespace Drupal\ras_config\Tests;

use Drupal\config_filter\Config\FilteredStorage;
use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\Core\Config\Storage\CachedStorageTest;
use Drupal\ras_config\Plugin\ConfigFilter\SortedConfigFilter;

/**
 * Tests Config sorting
 *
 * @group config
 */
class SortedConfigFilterTest extends CachedStorageTest {

  protected static $modules = ['sorted_configuration', 'config_filter'];

  /**
   * Our simulated active config store.
   *
   * @var FileStorage
   */
  protected $activeStorage;

  protected $testData = [
    'one' => [
      'one one' => 'one one',
      'one two' => 'one two',
      'one three' => 'one three'
    ],
    'two' => 'two',
    'three' => 'three',
    'four' => [
      'four one' => 'four one',
      'four two' => 'four two',
      'four three' => 'four three',
      'four four' => 'four four',
    ],
  ];

  protected $shuffledTestData = [];


  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->activeStorage = $this->container->get('config.storage');
    // Replace storage with a filtered storage using our plugin.
    $plugin_manager = $this->container->get('plugin.manager.config_filter');
    $definitions = $plugin_manager->getDefinitions();
    // Most inherited tests should still pass.
    $sorted_config_filter = new SortedConfigFilter([], 'sorted_config_filter', $definitions['sorted_config_filter'], $this->storage);
    $sorted_config_filter->setTargetConfigs(['test.matching', '/reg.x/']);

    /** @var FilteredStorage storage */
    $this->storage = new FilteredStorage($this->storage, [$sorted_config_filter]);

    // Set up test data.
    $this->shuffledTestData = $this->multidimensionalShuffle($this->testData);

    // Write under a name that will be compared UNshuffled.
    $this->activeStorage->write('test.not_matching', $this->testData);
    $this->storage->write('test.not_matching', $this->shuffledTestData);

    // Write under a name that will be compared shuffled.
    $this->activeStorage->write('test.matching', $this->testData);
    $this->storage->write('test.matching', $this->shuffledTestData);

    // Write under a name that will be compared with shuffled based on a regex.
    $this->activeStorage->write('regex', $this->testData);
    $this->storage->write('regex', $this->shuffledTestData);

  }

  /**
   * @covers \Drupal\ras_config\Plugin\ConfigFilter\SortedConfigFilter::filterRead
   */
  public function testFilterRead() {
    $this->assertNotEquals(serialize($this->testData), serialize($this->storage->read('test.not_matching')), 'Untargeted config is compared unsorted.');
    $this->assertEquals($this->testData, $this->storage->read('test.matching'), 'Targeted config is compared sorted.');
    $this->assertEquals($this->testData, $this->storage->read('regex'), 'Targeted config is compared sorted.');
  }


  /**
   * @covers \Drupal\ras_config\Plugin\ConfigFilter\SortedConfigFilter::filterReadMultiple
   */
  public function testFilterReadMultiple() {
    $expected_result = [
      'test.not_matching' => $this->testData,
      'test.matching' => $this->shuffledTestData,
    ];
    $this->assertEquals($expected_result, $this->storage->readMultiple(['test.not_matching', 'test.matching']), 'Multiple configs have individual comparison behaviors.');
  }

  /**
   * Shuffles all levels of a multidimensional array, preserving keys.
   *
   * @param array $array
   *
   * @return array
   *   The modified array.
   */
  protected function multidimensionalShuffle(array $array) {
    foreach ($array as $key => &$value) {
      if (is_array($value)) {
        $value = self::multidimensionalShuffle($value);
      }
    }
    $keys = array_keys($array);
    shuffle($keys);
    $new = [];
    foreach($keys as $key) {
      $new[$key] = $array[$key];
    }
    return $new;
  }
}
