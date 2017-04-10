<?php

namespace Drupal\sorted_configuration\plugin\ConfigFilter;

use Drupal\config_filter\Plugin\ConfigFilterBase;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Workaround core bug https://www.drupal.org/node/2852557.
 *
 * Will become deprecated in Drupal 8.4.x.
 *
 * @ConfigFilter(
 *   id = "sorted_config_filter",
 *   label = "Sorted configuration filter",
 *   weight = 0
 * )
 */
class SortedConfigFilter extends ConfigFilterBase implements ContainerFactoryPluginInterface {

  /**
   * Configurations to target.
   *
   * Technically this doubles activeStore read operations during import, so only
   * apply when you need it! Values that start and end with '/' are read as
   * regex patterns.
   *
   * @var array
   */
  protected $targetConfigs = [];

  /**
   * Set TargetConfigs.
   *
   * @param array $targetConfigs
   *   New Target Configs value.
   */
  public function setTargetConfigs(array $targetConfigs) {
    $this->targetConfigs = $targetConfigs;
  }

  /**
   * Storage for the active configuration.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

  /**
   * @inheritdoc
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StorageInterface $storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->activeStorage = $storage;
    $this->setTargetConfigs(Settings::get('sorted_configuration_targets', []));
  }

  /**
   * @inheritdoc
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.storage')
    );
  }

  /**
   * Filter Read.
   *
   * If SORTED active matches SORTED staged configuration, return the active
   * version. This way we don't get false diffs.
   */
  public function filterRead($name, $data) {
    if ($this->isTargetConfig($name)) {
      if ($this->activeStorage->exists($name)) {
        $active_data = $this->activeStorage->read($name);
        if (self::recursiveKsort($active_data) == self::recursiveKsort($data)) {
          return $active_data;
        }
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function filterReadMultiple(array $names, array $data) {
    $intersections = array_filter($names, [$this, 'isTargetConfig']);
    if ($intersections) {
      foreach ($intersections as $intersection) {
        $data[$intersection] = $this->filterRead($intersection, $data[$intersection]);
      }
    }
    return $data;
  }

  /**
   * Is Target Config.
   *
   * Test if the given $name string matches any of our target names or regexes.
   *
   * @param string $name
   *   The configuration name to test.
   *
   * @return bool
   *   Returns TRUE on match, FALSE otherwise.
   */
  protected function isTargetConfig($name) {
    foreach ($this->targetConfigs as $target) {
      if (mb_substr($target, 0, 1) == '/' && mb_substr($target, -1, 1) == '/' && preg_match($target, $name)) {
        return TRUE;
      }
      elseif ($target == $name) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Recursive ksort.
   *
   * Recursively sort an array.
   *
   * @param array $array
   *   The array to be sorted.
   *
   * @return array
   *   The input array, sorted.
   */
  protected function recursiveKsort(array $array) {
    foreach ($array as $key => &$value) {
      if (is_array($value)) {
        $value = self::recursiveKsort($value);
      }
    }
    ksort($array);
    return $array;
  }

}
