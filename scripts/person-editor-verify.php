<?php

\Drupal::service('account_switcher')->switchTo(\Drupal\user\Entity\User::load(1));
try {
  foreach ([557, 558] as $id) {
    $node = \Drupal\node\Entity\Node::load($id);
    if ($node->get('body')->format !== 'gutenberg') {
      $node->setNewRevision(TRUE);
      $node->get('body')->format = 'gutenberg';
      $node->setRevisionLogMessage('Use the existing allowed Person biography text format.');
      $node->save();
    }
    $violations = $node->validate();
    if (count($violations)) {
      foreach ($violations as $violation) {
        echo $violation->getPropertyPath() . ': ' . $violation->getMessage() . "\n";
      }
      throw new \RuntimeException('Person entity validation failed.');
    }
    echo 'PASS entity validation: ' . $node->label() . "\n";
  }
}
finally {
  \Drupal::service('account_switcher')->switchBack();
}
