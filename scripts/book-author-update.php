<?php

use Drupal\node\Entity\Node;

require_once DRUPAL_ROOT . '/modules/custom/person_identity/person_identity.post_update.php';
person_identity_post_update_add_book_people();
\Drupal::service('update.post_update_registry')->registerInvokedUpdates(['person_identity_post_update_add_book_people']);
$bruce = Node::load(558);
if (!$bruce || trim($bruce->get('field_person_name')->value) !== 'Bruce M. Whealton Jr.') {
  throw new \RuntimeException('Confirmed author Person not found.');
}
// These existing titles are confirmed in the site owner's biography/catalogue.
// Exclude node 324: an Amazon author-profile page, not a book.
$books = [46, 64, 79, 92, 102, 131, 198, 285, 403, 426, 306, 307, 308, 309, 310, 323];
$memoirs = [46, 131, 198, 403];
$before = [];
foreach (Node::loadMultiple($books) as $node) {
  if (!in_array($node->bundle(), ['book_cover_and_description', 'external_published_books'], TRUE)) {
    throw new \RuntimeException('Unexpected book record: ' . $node->id());
  }
  $before[$node->id()] = $node->toArray();
  $authors = $node->get('field_book_authors')->getValue();
  if (!in_array(558, array_column($authors, 'target_id'))) {
    $authors[] = ['target_id' => 558];
    $node->set('field_book_authors', $authors);
  }
  if (in_array((int) $node->id(), $memoirs, TRUE)) {
    $subjects = $node->get('field_book_subjects')->getValue();
    if (!in_array(558, array_column($subjects, 'target_id'))) {
      $subjects[] = ['target_id' => 558];
      $node->set('field_book_subjects', $subjects);
    }
  }
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Link the confirmed author to his Person record; identify him as subject only for explicitly autobiographical memoir records. Preserve prose and existing relationships.');
  $node->save();
  echo $node->id() . ': ' . $node->label() . (in_array((int) $node->id(), $memoirs, TRUE) ? ' [author and subject]' : ' [author]') . "\n";
}
file_put_contents('/home/brucewhealton/backups/book-authors-20261028/books-before.json', json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
