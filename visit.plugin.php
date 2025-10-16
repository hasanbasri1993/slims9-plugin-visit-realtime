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

// Load environment variables
$env = loadVisitPluginEnv();

// Load Pusher classes safely to avoid autoloader conflicts
if (!class_exists('Pusher\Pusher')) {
  // Check if any Composer autoloader is already loaded
  $autoloaderExists = false;
  foreach (get_declared_classes() as $class) {
    if (strpos($class, 'ComposerAutoloaderInit') === 0) {
      $autoloaderExists = true;
      break;
    }
  }

  if (!$autoloaderExists) {
    // Safe to load autoloader
    require __DIR__ . '/vendor/autoload.php';
  } else {
    // Autoloader conflict - manually include Pusher classes in correct order
    require_once __DIR__ . '/vendor/pusher/pusher-php-server/src/PusherInterface.php';
    require_once __DIR__ . '/vendor/pusher/pusher-php-server/src/PusherException.php';
    require_once __DIR__ . '/vendor/pusher/pusher-php-server/src/ApiErrorException.php';
    require_once __DIR__ . '/vendor/pusher/pusher-php-server/src/PusherInstance.php';
    require_once __DIR__ . '/vendor/pusher/pusher-php-server/src/PusherCrypto.php';
    require_once __DIR__ . '/vendor/pusher/pusher-php-server/src/Webhook.php';
    require_once __DIR__ . '/vendor/pusher/pusher-php-server/src/Pusher.php';
  }
}


Plugins::opac('visit', __DIR__ . '/visit.inc.php');

$plugins = Plugins::getInstance();
$plugins->hook(Plugins::MEMBER_ON_VISIT, function ($data) use ($env) {
  // Check if plugin is enabled
  if (!isset($env['VISIT_PLUGIN_ENABLED']) || $env['VISIT_PLUGIN_ENABLED'] !== 'true') {
    return;
  }
  
  // Get Pusher configuration from environment variables
  $pusherKey = $env['PUSHER_KEY'] ?? '';
  $pusherSecret = $env['PUSHER_SECRET'] ?? '';
  $pusherAppId = $env['PUSHER_APP_ID'] ?? '';
  $pusherCluster = $env['PUSHER_CLUSTER'] ?? 'ap1';
  $pusherUseTls = isset($env['PUSHER_USE_TLS']) ? $env['PUSHER_USE_TLS'] === 'true' : true;
  $pusherChannel = $env['PUSHER_CHANNEL'] ?? 'my-channel';
  $pusherEvent = $env['PUSHER_EVENT'] ?? 'my-event';
  
  $options = array(
    'cluster' => $pusherCluster,
    'useTLS' => $pusherUseTls
  );
  
  $pusher = new Pusher\Pusher(
    $pusherKey,
    $pusherSecret,
    $pusherAppId,
    $options
  );
  
  $data['message'] =  $data['member_name'] . __(', thank you for inserting your data to our visitor log');
  if (isset($data['visit_purpose_text'])) {
    $data['message'] .= ' (' . $data['visit_purpose_text'] . ')';
  }
  $pusher->trigger($pusherChannel, $pusherEvent, $data);
});
