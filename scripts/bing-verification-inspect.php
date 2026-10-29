<?php

$client = \Drupal::httpClient();
foreach (['https://brucewhealton.com/creative-writing/', 'https://brucewhealton.com/creative-writing/BingSiteAuth.xml', 'https://brucewhealton.com/', 'https://brucewhealton.com/BingSiteAuth.xml'] as $url) {
  $response = $client->get($url, ['http_errors' => FALSE, 'allow_redirects' => ['track_redirects' => TRUE]]);
  $body = (string) $response->getBody();
  $dom = new \DOMDocument();
  @$dom->loadHTML($body);
  $xpath = new \DOMXPath($dom);
  echo json_encode(['url' => $url, 'status' => $response->getStatusCode(), 'type' => $response->getHeaderLine('Content-Type'), 'redirects' => $response->getHeader('X-Guzzle-Redirect-History'), 'tag' => $xpath->evaluate('string(//head/meta[@name="msvalidate.01"]/@content)'), 'xml_token' => preg_match('#<user>\s*([A-F0-9]+)\s*</user>#', $body, $match) ? $match[1] : NULL], JSON_UNESCAPED_SLASHES) . "\n";
}
echo 'VERIFICATION_MODULE: ' . (\Drupal::moduleHandler()->moduleExists('metatag_verification') ? 'enabled' : 'disabled') . "\n";
