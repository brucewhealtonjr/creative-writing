<?php

$node = \Drupal\node\Entity\Node::load(558);
$profiles = array_column($node->get('field_identity_profiles')->getValue(), 'uri');
foreach (['linkedin.com/in/brucewhealton', 'facebook.com/brucewhealton', 'instagram.com/brucewhealton', 'linktr.ee/brucewhealton'] as $fragment) {
  if (!str_contains(implode(' ', $profiles), $fragment)) {
    throw new \RuntimeException('Missing identity profile: ' . $fragment);
  }
}
if (in_array('https://bwlinks.me/nia', $profiles, TRUE) || !in_array('https://bwlinks.me/nia', array_column($node->get('field_related_links')->getValue(), 'uri'), TRUE)) {
  throw new \RuntimeException('Audiobook must be a Related link, not sameAs.');
}
$response = \Drupal::httpClient()->get('https://brucewhealton.com/creative-writing/person/Bruce_Whealton_Jr_(1966-)');
$html = (string) $response->getBody();
foreach (array_merge($profiles, ['https://bwlinks.me/nia']) as $uri) {
  if (!str_contains($html, $uri)) {
    throw new \RuntimeException('Link missing from public page: ' . $uri);
  }
}
$dom = new \DOMDocument();
@$dom->loadHTML($html);
$xpath = new \DOMXPath($dom);
$same_as = [];
foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
  $json = json_decode($script->textContent, TRUE, 512, JSON_THROW_ON_ERROR);
  foreach ($json['@graph'] ?? [$json] as $entity) {
    if (($entity['@type'] ?? '') === 'Person') {
      $same_as = $entity['sameAs'] ?? [];
    }
  }
}
sort($profiles);
sort($same_as);
if ($profiles !== $same_as) {
  throw new \RuntimeException('Served sameAs does not match identity profiles.');
}
echo 'PASS: Public page and JSON-LD contain ' . count($profiles) . " identity profiles; audiobook appears only under Related links.\n";
echo 'PASS: Gutenberg enabled for Person: ' . (\Drupal::config('gutenberg.settings')->get('person_enable_full') ? 'yes' : 'NO') . "\n";
