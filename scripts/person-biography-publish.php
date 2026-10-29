<?php

use Drupal\node\Entity\Node;

$person = Node::load(\Drupal::state()->get('person_identity.bruce_node'));
$father = Node::load(\Drupal::state()->get('person_identity.father_node'));
if (!$person || !$father || $person->bundle() !== 'person' || $person->get('field_person_birth')->value !== '1966') {
  throw new \RuntimeException('Prepared biography not found or unexpected data.');
}
$father->set('field_motherof', [['target_id' => $person->id()]]);
foreach ([$father, $person] as $node) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Publish confirmed Person biography after name, relationship, URL and structured-data verification.');
  $node->setPublished();
  $node->save();
}
echo 'Published ' . $person->id() . ' and ' . $father->id() . ".\n";
