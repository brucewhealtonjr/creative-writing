<?php
/**
 * @file
 * Diagnostic tool and homepage Layout Builder configuration.
 */

use Drupal\node\Entity\Node;

// 1. Check if layout_builder module is enabled
$module_handler = \Drupal::moduleHandler();
if ($module_handler->moduleExists('layout_builder')) {
  print "STATUS: Layout Builder module is ENABLED.\n";
} else {
  print "STATUS: Layout Builder module is DISABLED.\n";
}

// 2. Check Page view display settings
$entity_field_manager = \Drupal::service('entity_field.manager');
$fields = $entity_field_manager->getFieldDefinitions('node', 'page');
print "STATUS: Available fields on 'page' bundle:\n";
foreach (array_keys($fields) as $field_name) {
  print " - $field_name\n";
}

// 3. Check display settings
$display = \Drupal::entityTypeManager()
  ->getStorage('entity_view_display')
  ->load('node.page.default');

if ($display) {
  $layout_settings = $display->getThirdPartySettings('layout_builder');
  print "STATUS: Layout Builder third party settings: " . json_encode($layout_settings) . "\n";
} else {
  print "STATUS: core.entity_view_display.node.page.default not found.\n";
}
