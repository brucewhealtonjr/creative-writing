<?php

use Drupal\node\Entity\Node;
use Drupal\redirect\Entity\Redirect;

require_once DRUPAL_ROOT . '/modules/custom/person_identity/person_identity.post_update.php';
person_identity_post_update_add_other_names();
\Drupal::service('update.post_update_registry')->registerInvokedUpdates(['person_identity_post_update_add_other_names']);
$person = Node::load(558);
$father = Node::load(557);
if (!$person || !$father || trim($person->get('field_person_name')->value) !== 'Bruce M. Whealton Jr.' || trim($father->get('field_person_name')->value) !== 'Bruce Whealton') {
  throw new \RuntimeException('Unexpected Person records; no date updates made.');
}
file_put_contents('/home/brucewhealton/backups/person-layout-20261027/people-before.json', json_encode([$person->toArray(), $father->toArray()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$person->setNewRevision(TRUE);
$person->set('field_person_name', trim($person->get('field_person_name')->value));
$person->set('field_person_birth', '1966-04-22');
$person->setRevisionLogMessage('Record the full birth date supplied by the site owner.');
$person->save();
$father->setNewRevision(TRUE);
$father->set('field_person_birth', '1940-09-27');
$father->set('field_person_death', '2015-03-27');
$father->get('path')->pathauto = \Drupal\pathauto\PathautoState::CREATE;
$father->get('body')->value = str_replace([' He is deceased.', ' His birth and death dates have not yet been added to this profile.'], '', (string) $father->get('body')->value);
if ($father->get('body')->summary === 'Bruce Whealton, the late father of living author Bruce M. Whealton Jr. (born 1966).') {
  $father->get('body')->summary = 'Bruce Whealton (1940-2015), father of living author Bruce M. Whealton Jr. (born 1966).';
}
$father->setRevisionLogMessage('Record confirmed birth and death dates and update the name-and-dates URL.');
$father->save();
$ids = \Drupal::entityQuery('redirect')->accessCheck(FALSE)->condition('redirect_source.path', 'person/Bruce_Whealton')->execute();
$redirect = $ids ? Redirect::load(reset($ids)) : Redirect::create(['language' => 'en']);
$redirect->setSource('person/Bruce_Whealton');
$redirect->set('redirect_redirect', [['uri' => 'entity:node/557']]);
$redirect->setStatusCode(301);
$redirect->save();
foreach ([$person, $father] as $node) {
  echo $node->label() . ': ' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . "\n";
}
