<?php
foreach (['book_cover_and_description', 'external_published_books', 'book', 'book_part'] as $bundle) {
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', $bundle)->execute();
  echo 'BUNDLE ' . $bundle . ' COUNT ' . count($ids) . "\n";
  if (in_array($bundle, ['book_cover_and_description', 'external_published_books'])) {
    foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
      echo json_encode(['id' => $node->id(), 'title' => $node->label(), 'url' => $node->toUrl()->toString(), 'published' => $node->isPublished(), 'summary' => $node->hasField('body') ? ($node->get('body')->summary ?: mb_substr(strip_tags((string) $node->get('body')->value), 0, 380)) : ''], JSON_UNESCAPED_SLASHES) . "\n";
    }
  }
  foreach (\Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle) as $name => $field) {
    if (str_contains($name, 'author') || str_contains($name, 'person')) {
      echo 'FIELD ' . $name . ' ' . $field->getType() . "\n";
    }
  }
}
