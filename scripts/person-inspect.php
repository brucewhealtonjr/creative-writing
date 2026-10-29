<?php

use Drupal\node\Entity\Node;

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'person')->execute();
foreach (Node::loadMultiple($ids) as $node) {
  echo json_encode(['id' => $node->id(), 'title' => $node->label(), 'status' => $node->isPublished(), 'alias' => \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id()), 'body' => $node->get('body')->value], JSON_UNESCAPED_SLASHES) . "\n";
}
$about = Node::load(433);
echo 'ABOUT:' . json_encode($about->toArray(), JSON_UNESCAPED_SLASHES) . "\n";
echo 'MODULES:' . json_encode(array_keys(\Drupal::moduleHandler()->getModuleList())) . "\n";
foreach (['field_mother', 'field_father', 'field_motherof'] as $field) {
  $config = \Drupal\field\Entity\FieldConfig::load('node.person.' . $field);
  echo 'FIELD:' . json_encode($config?->toArray(), JSON_UNESCAPED_SLASHES) . "\n";
}
