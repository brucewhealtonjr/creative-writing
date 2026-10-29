<?php
$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('title', 'family', 'CONTAINS')->execute();
foreach (\Drupal::entityTypeManager()->getStorage('node')->loadMultiple($ids) as $node) {
  echo json_encode(['id' => $node->id(), 'title' => $node->label(), 'url' => $node->toUrl()->toString(), 'published' => $node->isPublished()], JSON_UNESCAPED_SLASHES) . "\n";
}
foreach (\Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'main']) as $link) {
  echo 'MENU ' . json_encode(['id' => $link->id(), 'title' => $link->label(), 'uri' => $link->get('link')->uri], JSON_UNESCAPED_SLASHES) . "\n";
}
echo 'SITEMAPS ' . json_encode(array_keys(\Drupal::entityTypeManager()->getStorage('simple_sitemap')->loadMultiple())) . "\n";
