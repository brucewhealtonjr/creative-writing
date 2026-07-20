<?php
/**
 * @file
 * Diagnostic tool to check config sync directory setting.
 */

print "STATUS: Config Sync Directory in Settings: " . \Drupal\Core\Site\Settings::get('config_sync_directory') . "\n";

$file_path = __DIR__ . '/config/sync/core.entity_view_display.node.page.default.yml';
if (file_exists($file_path)) {
  print "STATUS: File exists at project root config/sync.\n";
} else {
  print "STATUS: File NOT found at project root config/sync.\n";
}
