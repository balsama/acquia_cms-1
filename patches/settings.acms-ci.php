<?php

/**
 * @file
 * This file contains db information for CI.
 */

$databases['default']['default'] = [
  'database' => 'drupal',
  'username' => 'drupal',
  'password' => 'drupal',
  'prefix' => '',
  'host' => '127.0.0.1',
  'port' => '3306',
  'namespace' => 'Drupal\\Driver\\Database\\mysql',
  'driver' => 'mysql',
];

$settings['hash_salt'] = '3c79ef1e1cbed7d1f62f203e118e2843';