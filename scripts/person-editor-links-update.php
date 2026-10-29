<?php

use Drupal\node\Entity\Node;

$directory = '/home/brucewhealton/backups/person-editor-20261027';
$config = \Drupal::configFactory()->getEditable('gutenberg.settings');
file_put_contents($directory . '/gutenberg-before.json', json_encode($config->getRawData(), JSON_PRETTY_PRINT));
$node = Node::load(558);
if (!$node || trim($node->get('field_person_name')->value) !== 'Bruce M. Whealton Jr.') {
  throw new \RuntimeException('Expected biography not found.');
}
file_put_contents($directory . '/biography-before.json', json_encode($node->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$blocks = array_unique(array_merge($config->get('people_allowed_blocks') ?: [], ['core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/freeform']));
$config->set('person_enable_full', TRUE)
  ->set('person_allowed_blocks', array_values($blocks))
  ->set('person_allowed_drupal_blocks', [])
  ->set('person_allowed_content_block_types', [])
  ->set('person_allowed_image_styles', $config->get('people_allowed_image_styles') ?: ['full', 'large', 'thumbnail'])
  ->set('person_template', '')
  ->set('person_template_lock', 'none')->save();
$identities = $node->get('field_identity_profiles')->getValue();
foreach ([
  'https://www.linkedin.com/in/brucewhealton' => 'Bruce Whealton on LinkedIn',
  'https://www.facebook.com/brucewhealton' => 'Bruce Whealton on Facebook',
  'https://www.instagram.com/brucewhealton/' => 'Bruce Whealton on Instagram',
  'https://linktr.ee/brucewhealton' => 'Bruce Whealton on Linktree',
] as $url => $title) {
  $found = FALSE;
  foreach ($identities as $item) {
    $normalize = static fn($uri) => rtrim(str_replace('://www.', '://', $uri), '/');
    if ($normalize($item['uri']) === $normalize($url)) {
      $found = TRUE;
      break;
    }
  }
  if (!$found) {
    $identities[] = ['uri' => $url, 'title' => $title];
  }
}
$node->set('field_identity_profiles', $identities);
$related = $node->get('field_related_links')->getValue();
if (!in_array('https://bwlinks.me/nia', array_column($related, 'uri'), TRUE)) {
  $related[] = ['uri' => 'https://bwlinks.me/nia', 'title' => "Tell Me I'm Not Invisible — audiobook"];
}
$node->set('field_related_links', $related);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Add the site owner’s social identity profiles and the explicitly identified audiobook link.');
$node->save();
echo "Enabled Gutenberg for Person. Updated identity profiles and audiobook related link; biography text preserved.\n";
