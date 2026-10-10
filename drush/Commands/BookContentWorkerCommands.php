<?php

declare(strict_types=1);

namespace Drush\Commands;

use Drupal\Component\Utility\Xss;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;

/**
 * Restricted, idempotent manuscript-to-Book-Part synchronization.
 */
#[CLI\Bootstrap(DrupalBootLevels::FULL)]
final class BookContentWorkerCommands extends DrushCommands {

  private const ALLOWED_BOOK_NID = 403;

  private const ALLOWED_HTML_TAGS = [
    'p', 'br', 'h2', 'h3', 'em', 'strong', 'blockquote', 'ul', 'ol', 'li', 'a',
  ];

  /**
   * Validates or applies a structured Book Part manifest.
   */
  #[CLI\Command(name: 'book-content:sync', aliases: ['book-worker:sync'])]
  #[CLI\Argument(name: 'manifest', description: 'Absolute path to a JSON manifest on the server.')]
  #[CLI\Option(name: 'apply', description: 'Create or update drafts. Without this option, no content is changed.')]
  #[CLI\Option(name: 'author', description: 'Drupal user ID that owns the drafts; the user must have the content_creator role.')]
  #[CLI\Usage(name: 'drush book-content:sync /secure/path/book.json --author=123', description: 'Validate without writing.')]
  #[CLI\Usage(name: 'drush book-content:sync /secure/path/book.json --author=123 --apply', description: 'Create or update unpublished Book Parts.')]
  public function sync(string $manifest, array $options = ['apply' => FALSE, 'author' => 0]): void {
    if (!is_file($manifest) || !is_readable($manifest)) {
      throw new \InvalidArgumentException("Manifest is not readable: $manifest");
    }

    $payload = json_decode((string) file_get_contents($manifest), TRUE, 512, JSON_THROW_ON_ERROR);
    if (($payload['book_nid'] ?? NULL) !== self::ALLOWED_BOOK_NID || !is_array($payload['items'] ?? NULL)) {
      throw new \InvalidArgumentException('Manifest must target book_nid 403 and contain an items array.');
    }

    $author_id = (int) $options['author'];
    $author = User::load($author_id);
    if (!$author || !in_array('content_creator', $author->getRoles(), TRUE)) {
      throw new \InvalidArgumentException('The --author user must exist and have the content_creator role.');
    }

    $apply = (bool) $options['apply'];
    $state = \Drupal::state();
    $mapping = $state->get('book_content_worker.manuscript_ids', []);
    $results = [];
    $transaction = $apply ? \Drupal::database()->startTransaction() : NULL;

    try {
      foreach ($payload['items'] as $index => $item) {
      $item = $this->validateItem($item, $index);
      $nid = isset($item['nid']) ? (int) $item['nid'] : (int) ($mapping[$item['external_id']] ?? 0);
      $node = $nid ? Node::load($nid) : NULL;

      if ($node) {
        $this->validateExistingNode($node);
      }
      elseif ($nid) {
        throw new \RuntimeException("Mapped node $nid does not exist for {$item['external_id']}.");
      }

      $action = $node ? 'update' : 'create';
      if ($apply) {
        $node = $node ?: Node::create([
          'type' => 'book_part',
          'uid' => $author_id,
          'status' => 0,
        ]);
        $node->setTitle($item['title']);
        $node->set('body', [
          'value' => $this->toGutenberg($item['body']),
          'format' => 'gutenberg',
          'summary' => '',
        ]);
        $node->set('field_part', $item['part'] ?: NULL);
        $node->set('field_section', $item['section'] ?: NULL);
        $node->setOwnerId($author_id);
        $node->setUnpublished();
        $node->book = ($node->book ?? []) + ['bid' => self::ALLOWED_BOOK_NID];
        $node->book['bid'] = self::ALLOWED_BOOK_NID;
        $node->book['pid'] = self::ALLOWED_BOOK_NID;
        $node->book['weight'] = $item['weight'];
        $node->setNewRevision(TRUE);
        $node->setRevisionUserId($author_id);
        $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $node->setRevisionLogMessage("Book content worker sync: {$item['external_id']}");
        $node->save();
        \Drupal::service('book.manager')->updateOutline($node);
        \Drupal::service('pathauto.generator')->updateEntityAlias($node, 'update', ['force' => TRUE]);
        $mapping[$item['external_id']] = (int) $node->id();
      }

        $results[] = [
          'external_id' => $item['external_id'],
          'action' => $action,
          'nid' => $node ? (int) $node->id() : NULL,
          'status' => 'unpublished',
          'applied' => $apply,
        ];
      }

      if ($apply) {
        $state->set('book_content_worker.manuscript_ids', $mapping);
      }
    }
    catch (\Throwable $exception) {
      if ($transaction) {
        $transaction->rollBack();
      }
      throw $exception;
    }
    $this->output()->writeln(json_encode(['book_nid' => self::ALLOWED_BOOK_NID, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  /**
   * Validates and normalizes one manifest item.
   */
  private function validateItem(mixed $item, int $index): array {
    if (!is_array($item)) {
      throw new \InvalidArgumentException("Item $index must be an object.");
    }
    foreach (['external_id', 'title', 'body', 'weight'] as $key) {
      if (!array_key_exists($key, $item)) {
        throw new \InvalidArgumentException("Item $index is missing $key.");
      }
    }
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,127}$/', (string) $item['external_id'])) {
      throw new \InvalidArgumentException("Item $index has an invalid external_id.");
    }
    $title = trim((string) $item['title']);
    $body = trim((string) $item['body']);
    $part = trim((string) ($item['part'] ?? ''));
    $section = trim((string) ($item['section'] ?? ''));
    $weight = filter_var($item['weight'], FILTER_VALIDATE_INT);
    if ($title === '' || mb_strlen($title) > 255 || $body === '' || strlen($body) > 2_000_000) {
      throw new \InvalidArgumentException("Item $index has an invalid title or body.");
    }
    if (mb_strlen($part) > 255 || mb_strlen($section) > 255 || $weight === FALSE || $weight < -100 || $weight > 100) {
      throw new \InvalidArgumentException("Item $index has an invalid part, section, or weight.");
    }
    return [
      'external_id' => (string) $item['external_id'],
      'nid' => isset($item['nid']) ? (int) $item['nid'] : NULL,
      'title' => $title,
      'body' => $body,
      'part' => $part,
      'section' => $section,
      'weight' => (int) $weight,
    ];
  }

  /**
   * Refuses cross-book, cross-bundle, or published updates.
   */
  private function validateExistingNode(NodeInterface $node): void {
    if ($node->bundle() !== 'book_part' || $node->isPublished()) {
      throw new \RuntimeException('Existing targets must be unpublished Book Part nodes.');
    }
    $book = \Drupal::service('book.manager')->loadBookLink((int) $node->id());
    if (!$book || (int) $book['bid'] !== self::ALLOWED_BOOK_NID) {
      throw new \RuntimeException('Existing targets must belong to the approved Book (node 403).');
    }
  }

  /**
   * Converts sanitized semantic HTML into native Gutenberg blocks.
   */
  private function toGutenberg(string $html): string {
    $safe = Xss::filter($html, self::ALLOWED_HTML_TAGS);
    $document = new \DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(TRUE);
    $document->loadHTML(
      '<?xml encoding="UTF-8"><body>' . $safe . '</body>',
      LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $body = $document->getElementsByTagName('body')->item(0);
    if (!$body) {
      throw new \InvalidArgumentException('Unable to parse the item body.');
    }

    $blocks = [];
    foreach ($body->childNodes as $node) {
      if ($node instanceof \DOMText && trim($node->textContent) === '') {
        continue;
      }
      if (!$node instanceof \DOMElement) {
        throw new \InvalidArgumentException('The body contains unsupported top-level content.');
      }
      $tag = strtolower($node->tagName);
      $serialized = trim((string) $document->saveHTML($node));
      [$block, $attributes] = match ($tag) {
        'p' => ['paragraph', ''],
        'h2' => ['heading', ''],
        'h3' => ['heading', ' {"level":3}'],
        'blockquote' => ['quote', ''],
        'ul' => ['list', ''],
        'ol' => ['list', ' {"ordered":true}'],
        default => throw new \InvalidArgumentException("Unsupported top-level HTML element: $tag"),
      };
      $blocks[] = "<!-- wp:$block$attributes -->\n$serialized\n<!-- /wp:$block -->";
    }
    if (!$blocks) {
      throw new \InvalidArgumentException('The body did not contain any importable blocks.');
    }
    return implode("\n\n", $blocks);
  }

}
