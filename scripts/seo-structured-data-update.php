<?php

/**
 * @file
 * Configure Open Graph, Twitter Cards, Schema.org defaults, clean up duplicate aliases & GTM, and regenerate sitemap.
 */

use Drupal\redirect\Entity\Redirect;

// 1. Resolve Bruce's portrait image URL from node 558 for front-page og:image fallback.
$author_image_url = '';
if ($bruce = \Drupal\node\Entity\Node::load(558)) {
  if ($bruce->hasField('field_person_featured_image') && !$bruce->get('field_person_featured_image')->isEmpty() && ($file = $bruce->get('field_person_featured_image')->entity)) {
    $author_image_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
  }
}
echo "Author portrait URL: $author_image_url\n";

// 2. Update metatag_defaults: global, front, node, node__article.
$storage = \Drupal::entityTypeManager()->getStorage('metatag_defaults');

$global = $storage->load('global');
$global->set('tags', [
  'canonical_url' => '[current-page:url]',
  'title' => '[current-page:title] | [site:name]',
  'og_site_name' => '[site:name]',
  'og_url' => '[current-page:url]',
  'og_title' => '[current-page:title]',
  'og_type' => 'website',
  'twitter_cards_type' => 'summary_large_image',
  'twitter_cards_title' => '[current-page:title]',
]);
$global->save();
echo "Updated metatag_defaults.global\n";

$front_tags = [
  'title' => 'Creative Writing By Bruce Whealton | Memoirs, Poetry & Psychology',
  'description' => 'Books, memoirs, poetry collections, and psychology articles by Bruce M. Whealton Jr., author and former clinical social worker.',
  'canonical_url' => '[site:url]',
  'shortlink' => '[site:url]',
  'bing' => 'F8120FF758A15942F8A89026760FBFC2',
  'og_site_name' => '[site:name]',
  'og_type' => 'website',
  'og_url' => '[site:url]',
  'og_title' => 'Creative Writing By Bruce Whealton | Memoirs, Poetry & Psychology',
  'og_description' => 'Books, memoirs, poetry collections, and psychology articles by Bruce M. Whealton Jr., author and former clinical social worker.',
  'twitter_cards_type' => 'summary_large_image',
  'twitter_cards_title' => 'Creative Writing By Bruce Whealton | Memoirs, Poetry & Psychology',
  'twitter_cards_description' => 'Books, memoirs, poetry collections, and psychology articles by Bruce M. Whealton Jr., author and former clinical social worker.',
];
if ($author_image_url !== '') {
  $front_tags['og_image'] = $author_image_url;
  $front_tags['twitter_cards_image'] = $author_image_url;
}
$front = $storage->load('front');
$front->set('tags', $front_tags);
$front->save();
echo "Updated metatag_defaults.front\n";

$node_defaults = $storage->load('node');
$node_defaults->set('tags', [
  'title' => '[node:title] | [site:name]',
  'description' => '[node:summary]',
  'canonical_url' => '[node:url]',
  'og_site_name' => '[site:name]',
  'og_type' => 'article',
  'og_url' => '[node:url]',
  'og_title' => '[node:title]',
  'og_description' => '[node:summary]',
  'og_updated_time' => '[node:changed:custom:c]',
  'twitter_cards_type' => 'summary_large_image',
  'twitter_cards_title' => '[node:title]',
  'twitter_cards_description' => '[node:summary]',
]);
$node_defaults->save();
echo "Updated metatag_defaults.node\n";

if ($node_article = $storage->load('node__article')) {
  $node_article->set('tags', [
    'og_type' => 'article',
  ]);
  $node_article->save();
  echo "Updated metatag_defaults.node__article (removed conflicting empty schema_book_type)\n";
}

// 3. Set pathauto update_action to 2 (Delete old alias and let redirect auto_redirect create 301).
\Drupal::configFactory()->getEditable('pathauto.settings')->set('update_action', 2)->save();
echo "Updated pathauto.settings update_action = 2\n";

// 4. Clean up duplicate active node path_alias rows & create 301 redirects for superseded aliases.
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$dups = \Drupal::database()->query("SELECT path FROM {path_alias} WHERE status = 1 AND path LIKE '/node/%' GROUP BY path HAVING COUNT(*) > 1")->fetchCol();
foreach ($dups as $sys_path) {
  $rows = \Drupal::database()->query("SELECT id, path, alias FROM {path_alias} WHERE status = 1 AND path = :p ORDER BY id ASC", [':p' => $sys_path])->fetchAll();
  if (count($rows) < 2) {
    continue;
  }
  // For /node/562 (The Origin of Love in The Labyrinth of the Mind), keep /book/labyrinth-mind/origin-love (id=920).
  $keep_row = end($rows);
  if ($sys_path === '/node/562') {
    foreach ($rows as $r) {
      if ($r->alias === '/book/labyrinth-mind/origin-love') {
        $keep_row = $r;
        break;
      }
    }
  }
  foreach ($rows as $r) {
    if ((int) $r->id === (int) $keep_row->id) {
      continue;
    }
    // Delete the duplicate/superseded path_alias entity.
    if ($entity = $alias_storage->load($r->id)) {
      $entity->delete();
      echo "Deleted duplicate path_alias id={$r->id} ({$r->alias}) for $sys_path (kept {$keep_row->alias})\n";
    }
    // If the old alias is distinct from the kept alias, ensure a 301 redirect exists.
    if ($r->alias !== $keep_row->alias && $sys_path !== '/node/433') {
      $source_path = ltrim($r->alias, '/');
      $existing = \Drupal::database()->query("SELECT rid FROM {redirect} WHERE redirect_source__path = :s", [':s' => $source_path])->fetchField();
      if (!$existing) {
        $redirect = Redirect::create();
        $redirect->setSource($source_path);
        $redirect->setRedirect($sys_path);
        $redirect->setStatusCode(301);
        $redirect->save();
        echo "Created 301 redirect: /{$source_path} -> internal:{$sys_path}\n";
      }
    }
  }
}

// Also remove /person/Bruce_Whealton from path_alias if still present on /node/557 since redirect rid=197 already redirects it.
$extra_557 = \Drupal::database()->query("SELECT id FROM {path_alias} WHERE path = '/node/557' AND alias = '/person/Bruce_Whealton'")->fetchCol();
foreach ($extra_557 as $id557) {
  if ($e = $alias_storage->load($id557)) {
    $e->delete();
    echo "Deleted superseded path_alias id=$id557 (/person/Bruce_Whealton) on /node/557\n";
  }
}

// 5. Uninstall duplicate/unused analytics modules ('gtm' and 'google_analytics'), keeping 'google_tag'.
$installer = \Drupal::service('module_installer');
foreach (['gtm', 'google_analytics'] as $mod) {
  if (\Drupal::moduleHandler()->moduleExists($mod)) {
    $installer->uninstall([$mod]);
    echo "Uninstalled redundant module: $mod\n";
  }
}

// 6. Remove duplicate subdirectory /home/brucewhealton/public_html/creative-writing/robots.txt
$subdir_robots = '/home/brucewhealton/public_html/creative-writing/robots.txt';
if (file_exists($subdir_robots)) {
  unlink($subdir_robots);
  echo "Removed duplicate subdirectory robots.txt ($subdir_robots)\n";
}
