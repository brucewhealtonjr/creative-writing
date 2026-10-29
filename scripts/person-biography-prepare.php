<?php

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\pathauto\PathautoState;

$state = \Drupal::state();
if ($state->get('person_identity.bruce_node')) {
  throw new \RuntimeException('Biography already prepared; inspect the saved node instead of creating a duplicate.');
}
$about = Node::load(433);
if (!$about || $about->bundle() !== 'page') {
  throw new \RuntimeException('Expected source About page is missing.');
}
$summary = 'Bruce M. Whealton Jr. (born 1966) is a living author, poet, family historian, and former clinical social worker from Wilmington, North Carolina. He is distinct from his late father, Bruce Whealton.';
$body = $about->get('body')->value;
$files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uuid' => '2fdbcb0e-e076-4200-9530-5eceefc883ea']);
$photo = $files ? reset($files) : NULL;
if ($photo) {
  $body = preg_replace('#^<img[^>]*>#', '', $body, 1);
}
$body = str_replace('<p><em>Bruce M. Whealton Jr.</em></p><p><strong>About Bruce M. Whealton Jr.</strong></p>', '', $body);
$intro = '<p>I am Bruce M. Whealton Jr. (born 1966), a living author, poet, family historian, and former clinical social worker from Wilmington, North Carolina. This is my biography. My late father, Bruce Whealton, is a different person.</p>';
$body = str_replace('<p><em>Author, poet, mental health professional and former therapist/licensed clinical social worker, and family historian from Wilmington, North Carolina.</em></p>', $intro, $body);
$body = str_replace('This memorial site is part of my broader work as a writer and former therapist.', 'This website brings together my writing and memorials for people I have loved.', $body);
$body = str_replace('href="../family-tree/"', 'href="/creative-writing/family-tree/"', $body);
$body = str_replace('<p>Cite as: <a href="/creative-writing/about-bruce">brucewhealton.com/creative-writing/about-bruce</a></p>', '<p>Cite as: <a href="/creative-writing/person/Bruce_Whealton_Jr_(1966-)">Bruce M. Whealton Jr. (1966-)</a></p>', $body);

$father = Node::create([
  'type' => 'person', 'uid' => 1, 'status' => 0, 'promote' => 0,
  'title' => 'Bruce Whealton', 'field_person_name' => 'Bruce Whealton', 'field_person_life_status' => 'deceased',
  'body' => ['value' => '<p>Bruce Whealton was the father of author Bruce M. Whealton Jr. (born 1966). He is deceased. His birth and death dates have not yet been added to this profile.</p>', 'summary' => 'Bruce Whealton, the late father of living author Bruce M. Whealton Jr. (born 1966).', 'format' => 'gutenberg'],
]);
$father->setRevisionLogMessage('Create a minimal family profile from the site owner’s confirmed relationship; dates remain unknown.');
$father->save();
$person = Node::create([
  'type' => 'person', 'uid' => 1, 'status' => 0, 'promote' => 0,
  'title' => 'Bruce M. Whealton Jr. (1966-)', 'field_person_name' => 'Bruce M. Whealton Jr.',
  'field_person_given_name' => 'Bruce', 'field_person_additional_name' => 'M.', 'field_person_family_name' => 'Whealton',
  'field_person_birth' => '1966', 'field_person_life_status' => 'living', 'field_father' => ['target_id' => $father->id()],
  'body' => ['value' => $body, 'summary' => $summary, 'format' => 'gutenberg'],
  'field_identity_profiles' => [['uri' => 'https://familypedia.fandom.com/wiki/Bruce_Whealton_Jr_(1966-)', 'title' => 'Bruce Whealton Jr. on Familypedia']],
  'path' => ['alias' => '/person/Bruce_Whealton_Jr_(1966-)', 'pathauto' => PathautoState::SKIP],
]);
if ($photo) {
  $person->set('field_person_featured_image', ['target_id' => $photo->id(), 'alt' => 'Bruce M. Whealton Jr.', 'width' => 480, 'height' => 793]);
}
$person->setRevisionLogMessage('Create Person biography from About page 433, preserve biography and photo, and distinguish the living author from his late father.');
$person->save();
$state->set('person_identity.bruce_node', (int) $person->id());
$state->set('person_identity.father_node', (int) $father->id());
echo json_encode(['person' => $person->id(), 'father' => $father->id(), 'title' => $person->label(), 'alias' => $person->get('path')->alias, 'photo_preserved' => (bool) $photo, 'status' => 'draft'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
