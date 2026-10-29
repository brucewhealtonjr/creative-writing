<?php

function book_schema(int $id): array {
  $node = \Drupal\node\Entity\Node::load($id);
  $response = \Drupal::httpClient()->get($node->toUrl('canonical', ['absolute' => TRUE])->toString());
  $dom = new \DOMDocument();
  @$dom->loadHTML((string) $response->getBody());
  $xpath = new \DOMXPath($dom);
  foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
    $data = json_decode($script->textContent, TRUE, 512, JSON_THROW_ON_ERROR);
    foreach ($data['@graph'] ?? [$data] as $object) {
      if (($object['@type'] ?? '') === 'Book') {
        return [$object, $xpath];
      }
    }
  }
  throw new \RuntimeException('Missing Book object on node ' . $id);
}
$bruce = \Drupal\node\Entity\Node::load(558);
foreach ([403 => TRUE, 64 => FALSE, 306 => FALSE] as $id => $about_bruce) {
  [$object, $xpath] = book_schema($id);
  if (($object['author'][0]['@id'] ?? '') !== 'urn:uuid:' . $bruce->uuid() || !str_contains(rawurldecode($object['author'][0]['url'] ?? ''), '/person/Bruce_Whealton_Jr_(1966-)')) {
    throw new \RuntimeException('Author Person link missing on node ' . $id);
  }
  if (isset($object['about']) !== $about_bruce) {
    throw new \RuntimeException('Authorship and subject matter confused on node ' . $id);
  }
  if (!$xpath->query('//a[contains(@href,"/person/Bruce_Whealton_Jr_")]')->length) {
    throw new \RuntimeException('Visible author link missing on node ' . $id);
  }
  echo "PASS public book $id: author schema and clickable Person link; subject choice preserved.\n";
}
$response = \Drupal::httpClient()->get($bruce->toUrl('canonical', ['absolute' => TRUE])->toString());
$html = (string) $response->getBody();
$dom = new \DOMDocument();
@$dom->loadHTML($html);
$xpath = new \DOMXPath($dom);
$subjects = [];
foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
  $json = json_decode($script->textContent, TRUE, 512, JSON_THROW_ON_ERROR);
  foreach ($json['@graph'] ?? [$json] as $object) {
    if (($object['@type'] ?? '') === 'Person') {
      $subjects = array_column($object['subjectOf'] ?? [], '@id');
    }
  }
}
foreach ([46, 131, 198, 403] as $id) {
  if (!in_array('urn:uuid:' . \Drupal\node\Entity\Node::load($id)->uuid(), $subjects, TRUE)) {
    throw new \RuntimeException('Memoir missing from Person subjectOf: ' . $id);
  }
}
if (in_array('urn:uuid:' . \Drupal\node\Entity\Node::load(64)->uuid(), $subjects, TRUE)) {
  throw new \RuntimeException('Poetry collection incorrectly used as Person subjectOf.');
}
echo "PASS Person subjectOf contains the selected memoirs and excludes unselected poetry.\n";
\Drupal::service('account_switcher')->switchTo(\Drupal\user\Entity\User::load(1));
try {
  $controller = \Drupal::classResolver()->getInstanceFromDefinition(\Drupal\person_identity\Controller\ContentSearchController::class);
  $result = $controller->search(\Symfony\Component\HttpFoundation\Request::create('/creative-writing/editor/search', 'GET', ['search' => 'Bruce', 'type' => 'post']));
  $items = json_decode($result->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
  $people = array_column($items, NULL, 'id');
  if (!isset($people[558], $people[557]) || !str_starts_with($people[558]['url'], '/creative-writing/person/') || $people[558]['type'] !== 'Person') {
    throw new \RuntimeException('Gutenberg search must return namesakes with valid base-path aliases.');
  }
  echo "PASS link picker finds Person names/dates and returns /creative-writing/ aliases.\n";
}
finally {
  \Drupal::service('account_switcher')->switchBack();
}
$response = \Drupal::httpClient()->get('https://brucewhealton.com/creative-writing/editor/search?type=post&search=Bruce&_format=json', ['http_errors' => FALSE]);
if ($response->getStatusCode() !== 403) {
  throw new \RuntimeException('Editor search should remain permission-protected.');
}
echo "PASS editor search retains existing permission protection.\n";
