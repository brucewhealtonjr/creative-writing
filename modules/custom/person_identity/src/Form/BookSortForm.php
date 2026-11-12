<?php

namespace Drupal\person_identity\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Provides a drag-and-drop form to manually sort Book Cover and Description nodes.
 */
final class BookSortForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'person_identity_book_sort_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Drag the books up or down using the crosshair handles on the left (or click "Show row weights" to enter numbers directly), then click <strong>Save Book Order</strong>. This controls the order of books in the homepage carousel and on the <a href=":url">My Books</a> page.', [
        ':url' => Url::fromUserInput('/books-by-bruce-whealton')->toString(),
      ]) . '</p>',
    ];

    $form['books'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Book Title'),
        $this->t('Status'),
        $this->t('Operations'),
        $this->t('Display Order'),
      ],
      '#empty' => $this->t('No books found.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'book-order-weight',
        ],
      ],
    ];

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'book_cover_and_description')
      ->execute();

    $books = $nids ? Node::loadMultiple($nids) : [];

    // Sort by field_display_order ASC, then sticky DESC, then created DESC.
    uasort($books, static function (Node $a, Node $b): int {
      $wa = ($a->hasField('field_display_order') && !$a->get('field_display_order')->isEmpty())
        ? (int) $a->get('field_display_order')->value
        : 99;
      $wb = ($b->hasField('field_display_order') && !$b->get('field_display_order')->isEmpty())
        ? (int) $b->get('field_display_order')->value
        : 99;
      if ($wa !== $wb) {
        return $wa <=> $wb;
      }
      if ($a->isSticky() !== $b->isSticky()) {
        return $b->isSticky() <=> $a->isSticky();
      }
      return $b->getCreatedTime() <=> $a->getCreatedTime();
    });

    $position = 1;
    foreach ($books as $nid => $book) {
      $weight = ($book->hasField('field_display_order') && !$book->get('field_display_order')->isEmpty())
        ? (int) $book->get('field_display_order')->value
        : $position;

      $form['books'][$nid]['#attributes']['class'][] = 'draggable';
      $form['books'][$nid]['#weight'] = $weight;

      $form['books'][$nid]['title'] = [
        '#type' => 'link',
        '#title' => $book->label(),
        '#url' => $book->toUrl(),
      ];

      $form['books'][$nid]['status'] = [
        '#plain_text' => $book->isPublished() ? (string) $this->t('Published') : (string) $this->t('Unpublished'),
      ];

      $form['books'][$nid]['operations'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit book'),
        '#url' => $book->toUrl('edit-form'),
      ];

      $form['books'][$nid]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Display Order for @title', ['@title' => $book->label()]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#delta' => 50,
        '#attributes' => ['class' => ['book-order-weight']],
      ];

      $position++;
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Book Order'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValue('books') ?? [];
    if (!is_array($values)) {
      return;
    }

    // Sort submitted items by their weight so we save clean sequential numbers 1, 2, 3...
    uasort($values, static function (array $a, array $b): int {
      return ((int) ($a['weight'] ?? 0)) <=> ((int) ($b['weight'] ?? 0));
    });

    $order = 1;
    foreach ($values as $nid => $row) {
      $book = Node::load((int) $nid);
      if ($book && $book->bundle() === 'book_cover_and_description' && $book->hasField('field_display_order')) {
        $book->set('field_display_order', $order);
        $book->save();
        $order++;
      }
    }

    Cache::invalidateTags(['node_list', 'node_list:book_cover_and_description', 'config:views.view.books_by_bruce']);
    $this->messenger()->addStatus($this->t('The book display order has been saved.'));
  }

}
