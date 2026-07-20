<?php
/**
 * @file
 * Diagnostic tool to check filesystem config vs database config.
 */

$file_path = __DIR__ . '/config/sync/core.entity_view_display.node.page.default.yml';
if (file_exists($file_path)) {
  print "STATUS: File exists on remote server at $file_path.\n";
  print "STATUS: File contents (first 25 lines):\n";
  $lines = file($file_path);
  for ($i = 0; $i < min(25, count($lines)); $i++) {
    print " | " . $lines[$i];
  }
} else {
  print "STATUS: File NOT found on remote server at $file_path.\n";
}

// Print database active config
$active_config = \Drupal::config('core.entity_view_display.node.page.default')->getRawData();
print "\nSTATUS: Database active configuration (third_party_settings):\n";
print " | " . json_encode($active_config['third_party_settings'] ?? []) . "\n";
