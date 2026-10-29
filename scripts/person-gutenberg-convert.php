<?php

use Drupal\node\Entity\Node;

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'person')->execute();
foreach (Node::loadMultiple($ids) as $node) {
  $body = (string) $node->get('body')->value;
  if ($body === '' || str_contains($body, '<!-- wp:')) {
    continue;
  }
  file_put_contents('/home/brucewhealton/backups/person-editor-20261027/node-' . $node->id() . '-before-blocks.json', json_encode($node->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  $dom = new \DOMDocument('1.0', 'UTF-8');
  @$dom->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>' . $body . '</body></html>');
  $root = $dom->getElementsByTagName('body')->item(0);
  $blocks = [];
  foreach ($root->childNodes as $element) {
    if ($element instanceof \DOMText && trim($element->textContent) === '') {
      continue;
    }
    $tag = strtolower($element->nodeName);
    if ($tag === 'p') {
      $blocks[] = "<!-- wp:paragraph -->\n" . $dom->saveHTML($element) . "\n<!-- /wp:paragraph -->";
    }
    elseif (preg_match('/^h([1-6])$/', $tag, $match)) {
      $element->setAttribute('class', trim($element->getAttribute('class') . ' wp-block-heading'));
      $attrs = $match[1] === '2' ? '' : ' ' . json_encode(['level' => (int) $match[1]]);
      $blocks[] = "<!-- wp:heading$attrs -->\n" . $dom->saveHTML($element) . "\n<!-- /wp:heading -->";
    }
    elseif (in_array($tag, ['ul', 'ol'], TRUE)) {
      $items = [];
      foreach ($element->childNodes as $item) {
        if ($item instanceof \DOMElement && $item->tagName === 'li') {
          $item->removeAttribute('data-list-item-id');
          if ($item->getAttribute('class') === 'ck-list-marker-italic') {
            $item->removeAttribute('class');
          }
          $items[] = "<!-- wp:list-item -->\n" . $dom->saveHTML($item) . "\n<!-- /wp:list-item -->";
        }
      }
      $attrs = $tag === 'ol' ? ' {"ordered":true}' : '';
      $blocks[] = "<!-- wp:list$attrs -->\n<$tag class=\"wp-block-list\">" . implode("\n", $items) . "</$tag>\n<!-- /wp:list -->";
    }
    else {
      $blocks[] = "<!-- wp:html -->\n" . $dom->saveHTML($element) . "\n<!-- /wp:html -->";
    }
  }
  $converted = implode("\n\n", $blocks);
  $text = static function(string $html): string {
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    return preg_replace('/\s+/u', '', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  };
  if ($text($body) !== $text($converted)) {
    throw new \RuntimeException('Conversion changed text for node ' . $node->id());
  }
  $node->setNewRevision(TRUE);
  $node->get('body')->value = $converted;
  $node->setRevisionLogMessage('Convert existing biography HTML to editable Gutenberg blocks; preserve all text.');
  $node->save();
  echo 'Converted ' . $node->id() . ' into ' . count($blocks) . " top-level Gutenberg blocks; text comparison passed.\n";
}
