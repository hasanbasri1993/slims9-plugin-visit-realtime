<?php

/**
 * Plugin Name: Visit Notification
 * Plugin URI: -
 * Description: Belajar membuat plugin sederhana
 * Version: 1.0.0
 * Author: Hasan Basri
 * Author URI: https://foo.who
 */

use SLiMS\Plugins;

/**
 * Load environment variables from config.env file
 */
function loadVisitPluginEnv() {
  $envFile = __DIR__ . '/config.env';
  if (!file_exists($envFile)) {
    return [];
  }
  
  $env = [];
  $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  
  foreach ($lines as $line) {
    // Skip comments
    if (strpos(trim($line), '#') === 0) {
      continue;
    }
    
    // Parse key=value pairs
    if (strpos($line, '=') !== false) {
      list($key, $value) = explode('=', $line, 2);
      $env[trim($key)] = trim($value);
    }
  }
  
  return $env;
}

require_once __DIR__ . '/SimplePusher.php';

Plugins::opac('visit', __DIR__ . '/visit.inc.php');
