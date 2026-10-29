<?php

$base = 'https://brucewhealton.com/creative-writing';
$client = \Drupal::httpClient();
$response = $client->get($base . '/person/Bruce_Whealton_Jr_(1966-)', ['http_errors' => FALSE]);
if ($response->getStatusCode() !== 200) {
  throw new \RuntimeException('Anonymous biography status: ' . $response->getStatusCode());
}
$html = (string) $response->getBody();
$dom = new \DOMDocument();
@$dom->loadHTML($html);
$xpath = new \DOMXPath($dom);
$people = [];
foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
  $data = json_decode($script->textContent, TRUE, 512, JSON_THROW_ON_ERROR);
  foreach ($data['@graph'] ?? [$data] as $entry) {
    if (($entry['@type'] ?? '') === 'Person') {
      $people[] = $entry;
    }
  }
}
if (count($people) !== 1 || $people[0]['name'] !== 'Bruce M. Whealton Jr.' || count($people[0]['sameAs'] ?? []) !== 1 || empty($people[0]['parent']) || isset($people[0]['deathDate'])) {
  throw new \RuntimeException('Unexpected Person graph: ' . json_encode($people));
}
echo "PASS anonymous page, one Person object, Familypedia sameAs, father identity, no death date.\n";
echo 'CANONICAL: ' . $xpath->evaluate('string(//link[@rel="canonical"]/@href)') . "\n";
echo 'DESCRIPTION: ' . $xpath->evaluate('string(//meta[@name="description"]/@content)') . "\n";
echo 'SCHEMA: ' . json_encode($people[0], JSON_UNESCAPED_SLASHES) . "\n";
foreach (['/person/Bruce_Whealton', '/family-tree/'] as $path) {
  $result = $client->get($base . $path, ['http_errors' => FALSE]);
  echo 'STATUS ' . $path . ': ' . $result->getStatusCode() . "\n";
}
foreach (['/page/about-bruce-whealton', '/node/433'] as $path) {
  $result = $client->get($base . $path, ['http_errors' => FALSE, 'allow_redirects' => FALSE]);
  echo 'REDIRECT ' . $path . ': ' . $result->getStatusCode() . ' ' . $result->getHeaderLine('Location') . "\n";
  if ($result->getStatusCode() !== 301 || !str_contains(rawurldecode($result->getHeaderLine('Location')), '/person/Bruce_Whealton_Jr_(1966-)')) {
    throw new \RuntimeException('Old About URL must redirect directly to the new Person.');
  }
}
$about_link = $xpath->evaluate('string(//a[normalize-space(.)="About Bruce Whealton"]/@href)');
if (!str_contains(rawurldecode($about_link), '/person/Bruce_Whealton_Jr_(1966-)')) {
  throw new \RuntimeException('About navigation was not updated.');
}
if (str_contains($html, 'href="/creative-writing/family-tree/"')) {
  throw new \RuntimeException('Broken legacy family-tree link remains in biography.');
}
echo "PASS updated navigation and removed broken family-tree link.\n";
$sitemap_response = $client->get($base . '/sitemap.xml');
$sitemap = (string) $sitemap_response->getBody();
if (str_contains($sitemap, '<sitemapindex')) {
  $index = simplexml_load_string($sitemap);
  $sitemap = '';
  foreach ($index->sitemap as $entry) {
    $sitemap .= (string) $client->get((string) $entry->loc)->getBody();
  }
}
if (!str_contains(rawurldecode($sitemap), '/person/Bruce_Whealton_Jr_(1966-)')) {
  throw new \RuntimeException('New biography missing from published sitemap.');
}
if (str_contains($sitemap, '/page/about-bruce-whealton')) {
  throw new \RuntimeException('Redirected About page remains in sitemap.');
}
echo "PASS published sitemap includes new biography and excludes old About page.\n";
