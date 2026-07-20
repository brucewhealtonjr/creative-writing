<?php
/**
 * @file
 * Programmatically updates settings.php config sync directory path.
 */

$settings_path = __DIR__ . '/sites/default/settings.php';

if (!file_exists($settings_path)) {
  print "Error: settings.php not found at $settings_path\n";
  exit(1);
}

// 1. Make the file writable
chmod($settings_path, 0644);
print "Making settings.php writable (644)...\n";

$content = file_get_contents($settings_path);

// Regex pattern to match any format of $settings['config_sync_directory'] = ...;
$pattern = '/\$settings\[\'config_sync_directory\'\]\s*=\s*[^;]+;/';
$replacement = "\$settings['config_sync_directory'] = 'config/sync';";

if (preg_match($pattern, $content)) {
  $new_content = preg_replace($pattern, $replacement, $content);
  file_put_contents($settings_path, $new_content);
  print "SUCCESS: settings.php has been updated to point to 'config/sync'!\n";
} else {
  // If the setting wasn't found in the file, append it to the end
  $content .= "\n\$settings['config_sync_directory'] = 'config/sync';\n";
  file_put_contents($settings_path, $content);
  print "SUCCESS: settings.php did not have the setting, so it was appended to point to 'config/sync'!\n";
}

// 2. Lock it back down to read-only
chmod($settings_path, 0444);
print "Locking settings.php back to read-only (444).\n";
