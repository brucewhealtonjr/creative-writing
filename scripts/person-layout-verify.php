<?php

$base = 'https://brucewhealton.com/creative-writing';
$client = \Drupal::httpClient();
foreach ([
  '/person/Bruce_Whealton_Jr_(1966-)' => ['April 22, 1966', '1966-04-22', NULL],
  '/person/Bruce_Whealton_(1940-2015)' => ['September 27, 1940', '1940-09-27', '2015-03-27'],
] as $path => [$display_date, $birth, $death]) {
  $response = $client->get($base . $path, ['http_errors' => FALSE]);
  if ($response->getStatusCode() !== 200) {
    throw new \RuntimeException('Public page failed: ' . $path);
  }
  $dom = new \DOMDocument();
  @$dom->loadHTML((string) $response->getBody());
  $xpath = new \DOMXPath($dom);
  $header = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " person-identity-summary ")]')->item(0);
  if (!$header || !str_contains($header->textContent, $display_date) || !$xpath->query('.//img', $header)->length) {
    throw new \RuntimeException('Portrait and readable date must be together: ' . $path);
  }
  if (str_contains($header->textContent, 'Deceased') || str_contains($header->textContent, 'Life status') || str_contains($header->textContent, 'Full name')) {
    throw new \RuntimeException('Redundant field labels remain.');
  }
  if ($death && (!str_contains($header->textContent, 'March 27, 2015') || str_contains($header->textContent, 'Living'))) {
    throw new \RuntimeException('Father date or living status incorrect.');
  }
  if (!$death && !str_contains($header->textContent, 'Living')) {
    throw new \RuntimeException('Living label is missing.');
  }
  $people = [];
  foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
    $json = json_decode($script->textContent, TRUE, 512, JSON_THROW_ON_ERROR);
    foreach ($json['@graph'] ?? [$json] as $entry) {
      if (($entry['@type'] ?? '') === 'Person') {
        $people[] = $entry;
      }
    }
  }
  if (count($people) !== 1 || $people[0]['birthDate'] !== $birth || ($people[0]['deathDate'] ?? NULL) !== $death) {
    throw new \RuntimeException('Structured dates incorrect.');
  }
  echo "PASS public page, portrait beside facts, readable dates, status visibility and JSON-LD: $path\n";
}
$old = $client->get($base . '/person/Bruce_Whealton', ['http_errors' => FALSE, 'allow_redirects' => FALSE]);
if ($old->getStatusCode() !== 301 || !str_contains(rawurldecode($old->getHeaderLine('Location')), '/person/Bruce_Whealton_(1940-2015)')) {
  throw new \RuntimeException('Father legacy redirect is incorrect.');
}
echo "PASS father old URL redirects permanently to dated profile.\n";
foreach (['field_person_birth_name', 'field_person_other_names'] as $field) {
  if (!\Drupal\field\Entity\FieldConfig::load('node.person.' . $field)) {
    throw new \RuntimeException('Name field missing: ' . $field);
  }
}
echo "PASS optional birth-name and other-name fields exist.\n";
