# Sorted Configuration

A [Drupal 8](https://drupal.org/project/drupal) module to force [Config Management](https://www.drupal.org/docs/8/api/configuration-api/configuration-api-overview) to compare sorted, rather than unsorted, configs for diffs.

Drupal 8 Configuration Management can be [unreliable](https://www.drupal.org/node/2361539) [about](https://www.drupal.org/node/2350821) [the](https://www.drupal.org/node/2350537) [order](https://www.drupal.org/node/2256679) of configuration keys. The [official fix is coming in 8.4](https://www.drupal.org/node/2852557), which allows config schemas to specify a field to be used for sorting.

In the meantime, the export that generated your .yml configuration files, and the export used to test for changes, may have keys in a different order. That means false diffs. For some reason, some modules are particularly prone to this problem. Image styles are always a problem for me in particular. 

This module lets you specify your problem modules in settings.php, and it will force CM to compare a deep-sorted version of the configs. Voila, no more false diffs!

## INSTALL

Install it like any other Drupal module. Add an array of target configurations to sort in your settings.php. You can either specify specific config entities, or use a regular expression to select multiple. Any entry which starts and ends with a forward slash ('/') is read as a regex. 

Technically this doubles activeStore read operations during import, so only apply when you need it!

``` php
// Workaround for configurations which cause diffs due to different sort order.
$settings['sorted_configuration_targets'] =  [
  '/image\.style\.(.*)/', // A regex to target all image styles.
  'system.settings', // An explicitly named config entity.
];
```
Note that these are configuration entity names - you cannot specify just a single key inside an entity. We're sorting the whole config entity, or nothing at all.

Clear caches, and your targeted configs will only show real diffs. :)
