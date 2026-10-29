<?php

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\node\Entity\Node;
use Drupal\person_identity\Identity;

function verify_person($condition, string $message): void {
  if (!$condition) {
    throw new \RuntimeException($message);
  }
  echo "PASS: $message\n";
}

verify_person(Identity::label('Bruce Whealton Jr.', '1966') === 'Bruce Whealton Jr. (1966-)', 'Living/year-only label');
verify_person(Identity::alias('Bruce Whealton Jr.', '1966') === '/person/Bruce_Whealton_Jr_(1966-)', 'Case and lifespan punctuation preserved');
verify_person(Identity::label('Jane Doe', 'c. 1800', '1870') === 'Jane Doe (c. 1800-1870)', 'Approximate year remains explicit');
verify_person(Identity::label('Jane Doe') === 'Jane Doe', 'Unknown dates are not invented');
verify_person(Identity::label('Jane Doe', '1800-02-03', '1870-05-06') === 'Jane Doe (1800-1870)', 'Full dates retain compact year labels');

$settings = \Drupal::configFactory()->getEditable('simple_sitemap_engines.settings');
$notify = $settings->get('index_now_on_entity_save');
$settings->set('index_now_on_entity_save', FALSE)->save();
$transaction = \Drupal::database()->startTransaction();
try {
  $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionHandler(\Drupal\field\Entity\FieldConfig::load('node.person.field_person_siblings'));
  $relative = $handler->createNewEntity('node', 'person', 'Identity Verification Namesake (c. 1800-1870)', 1);
  $relative->save();
  verify_person($relative->get('field_person_name')->value === 'Identity Verification Namesake', 'Missing relative gets a separate name field');
  verify_person($relative->get('field_person_birth')->value === 'c. 1800', 'Missing relative preserves approximate birth');
  verify_person($relative->get('field_person_death')->value === '1870', 'Missing relative preserves death year');
  $other = Node::create(['type' => 'person', 'title' => 'Identity Verification Namesake', 'field_person_name' => 'Identity Verification Namesake', 'field_person_birth' => '1900', 'status' => 1, 'uid' => 1]);
  $other->save();
  $matches = $handler->getReferenceableEntities('Identity Verification Namesake', 'CONTAINS', 10);
  $labels = array_merge(...array_values($matches));
  verify_person(count($labels) === 2, 'Autocomplete returns both namesakes');
  verify_person(str_contains(implode(' ', $labels), 'c. 1800-1870') && str_contains(implode(' ', $labels), '1900-'), 'Autocomplete exposes distinguishing life dates');
  $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $relative->id());
  verify_person($alias === '/person/Identity_Verification_Namesake_(c_1800-1870)', 'Generated approximate-date alias');
  $other->set('field_person_siblings', [['target_id' => $relative->id()]]);
  $other->set('field_identity_profiles', [['uri' => 'https://example.com/profile-one', 'title' => 'One'], ['uri' => 'https://example.org/profile-two', 'title' => 'Two']]);
  $other->save();
  $build = ['#cache' => ['contexts' => ['theme', 'languages:language_interface', 'route.name.is_layout_builder_ui'], 'tags' => ['existing_tag'], 'max-age' => 3600]];
  person_identity_node_view($build, $other, EntityViewDisplay::load('node.person.default'), 'full');
  verify_person(count(array_intersect(['theme', 'languages:language_interface', 'route.name.is_layout_builder_ui'], $build['#cache']['contexts'])) === 3 && in_array('existing_tag', $build['#cache']['tags']), 'Person schema preserves existing render cache metadata');
  $json = json_decode($build['#attached']['html_head'][0][0]['#value'], TRUE, 512, JSON_THROW_ON_ERROR);
  $person = $json['@graph'][1];
  verify_person(count($person['sameAs']) === 2 && $person['sameAs'][0] === 'https://example.com/profile-one', 'sameAs includes every profile URL, without link labels');
  verify_person(!isset($person['birthDate']), 'Year-only birth does not fabricate a full date');
  verify_person($person['sibling'][0]['@id'] === 'urn:uuid:' . $relative->uuid(), 'Siblings use stable Person identities');
  verify_person($person['name'] === 'Identity Verification Namesake', 'Schema name excludes lifespan');
}
finally {
  $transaction->rollBack();
  \Drupal::entityTypeManager()->getStorage('node')->resetCache();
  \Drupal::service('cache_tags.invalidator')->invalidateTags(['node_list', 'path_alias_list']);
  $settings->set('index_now_on_entity_save', $notify)->save();
  echo "Rolled back verification records.\n";
}
