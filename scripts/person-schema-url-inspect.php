<?php

$client = \Drupal::httpClient();
foreach (['/person/Bruce_Whealton_Jr_(1966-)', '/person/Bruce_Whealton_Jr_%281966-%29', '/person/Bruce_Whealton_(1940-2015)', '/'] as $path) {
  $response = $client->get('https://brucewhealton.com/creative-writing' . $path, ['http_errors' => FALSE, 'allow_redirects' => FALSE]);
  $html = (string) $response->getBody();
  $dom = new \DOMDocument();
  @$dom->loadHTML($html);
  $xpath = new \DOMXPath($dom);
  $graphs = [];
  foreach ($xpath->query('//head/script[@type="application/ld+json"]') as $script) {
    $graphs[] = json_decode($script->textContent, TRUE);
  }
  echo json_encode(['requested' => $path, 'status' => $response->getStatusCode(), 'canonical' => $xpath->evaluate('string(//head/link[@rel="canonical"]/@href)'), 'jsonld_in_head' => $graphs], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
}
