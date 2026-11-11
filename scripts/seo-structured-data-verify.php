<?php

/**
 * @file
 * Verify Open Graph, Twitter Cards, Schema.org JSON-LD, sitemap canonicals, 301 redirects, and robots.txt.
 */

function assert_seo(bool $cond, string $msg): void {
  if (!$cond) {
    throw new \RuntimeException("FAIL: $msg");
  }
  echo "PASS: $msg\n";
}

// 1. Check robots.txt files on disk.
assert_seo(file_exists('/home/brucewhealton/public_html/robots.txt'), 'Domain-root robots.txt exists');
assert_seo(!file_exists('/home/brucewhealton/public_html/creative-writing/robots.txt'), 'Duplicate subdirectory robots.txt is removed');

// 2. Check analytics module deduplication.
$mh = \Drupal::moduleHandler();
assert_seo($mh->moduleExists('google_tag') && !$mh->moduleExists('gtm') && !$mh->moduleExists('google_analytics'), 'Only google_tag is enabled; redundant gtm and google_analytics are uninstalled');

// 3. Check sample pages across all content types for Open Graph, Twitter Cards, and Schema.org JSON-LD.
$client = \Drupal::httpClient();
$bruce_uuid = 'urn:uuid:' . \Drupal\node\Entity\Node::load(558)->uuid();

$pages = [
  'front' => ['url' => 'https://brucewhealton.com/creative-writing/', 'schema_type' => 'WebSite', 'og_type' => 'website'],
  'article' => ['url' => 'https://brucewhealton.com/creative-writing/poem-tangled-communication', 'schema_type' => 'Article', 'og_type' => 'article'],
  'psych_article' => ['url' => 'https://brucewhealton.com/creative-writing/why-am-i-always-edge-understanding-hypervigilance-survival-strategy', 'schema_type' => 'Article', 'og_type' => 'article'],
  'book_cover' => ['url' => 'https://brucewhealton.com/creative-writing/book/overcoming-shyness-and-loving-lynn-memoir', 'schema_type' => 'Book', 'og_type' => 'book'],
  'book_part' => ['url' => 'https://brucewhealton.com/creative-writing/book/tell-me-im-not-invisible/preface-best-time-your-life', 'schema_type' => 'Chapter', 'og_type' => 'article'],
  'person' => ['url' => 'https://brucewhealton.com/creative-writing/person/Bruce_Whealton_Jr_%281966-%29', 'schema_type' => 'Person', 'og_type' => 'profile'],
];

foreach ($pages as $key => $spec) {
  $res = $client->get($spec['url'], ['http_errors' => FALSE]);
  assert_seo($res->getStatusCode() === 200, "$key HTTP 200 ({$spec['url']})");
  $dom = new \DOMDocument();
  @$dom->loadHTML((string) $res->getBody());
  $xp = new \DOMXPath($dom);

  $desc = $xp->evaluate('string(//head/meta[@name="description"]/@content)');
  $og_title = $xp->evaluate('string(//head/meta[@property="og:title"]/@content)');
  $og_desc = $xp->evaluate('string(//head/meta[@property="og:description"]/@content)');
  $og_type = $xp->evaluate('string(//head/meta[@property="og:type"]/@content)');
  $og_image = $xp->evaluate('string(//head/meta[@property="og:image"]/@content)');
  $tw_card = $xp->evaluate('string(//head/meta[@name="twitter:card"]/@content)');

  assert_seo($desc !== '' && $og_title !== '' && $og_desc !== '' && $og_image !== '' && $tw_card !== '', "$key has meta description, og:title, og:description, og:image, and twitter:card");
  assert_seo($og_type === $spec['og_type'], "$key has og:type={$spec['og_type']} (got $og_type)");

  $found_types = [];
  $matched_obj = NULL;
  foreach ($xp->query('//script[@type="application/ld+json"]') as $s) {
    $data = json_decode($s->textContent, TRUE, 512, JSON_THROW_ON_ERROR);
    foreach ($data['@graph'] ?? [$data] as $obj) {
      $t = $obj['@type'] ?? '';
      $found_types[] = $t;
      if ($t === $spec['schema_type']) {
        $matched_obj = $obj;
      }
    }
  }
  assert_seo($matched_obj !== NULL, "$key emits Schema.org @type={$spec['schema_type']} (found: " . implode(',', $found_types) . ")");
  if ($key === 'article' || $key === 'psych_article') {
    assert_seo(!in_array('Book', $found_types, TRUE), "$key does not emit conflicting @type=Book");
    assert_seo(($matched_obj['author'][0]['@id'] ?? '') === $bruce_uuid && !empty($matched_obj['headline']) && !empty($matched_obj['datePublished']), "$key Article schema has headline, datePublished, and Bruce Person author @id");
  }
  if ($key === 'book_cover') {
    assert_seo(!empty($matched_obj['description']) && ($matched_obj['author'][0]['@id'] ?? '') === $bruce_uuid, "$key Book schema has description and Bruce Person author @id");
  }
  if ($key === 'book_part') {
    assert_seo(($matched_obj['isPartOf']['@type'] ?? '') === 'Book' && ($matched_obj['author'][0]['@id'] ?? '') === $bruce_uuid, "$key Chapter schema has isPartOf Book and Bruce Person author @id");
    $meta_author = $xp->evaluate('string(//head/meta[@name="author"]/@content)');
    assert_seo($meta_author === 'Bruce M. Whealton Jr.', "$key has <meta name=\"author\" content=\"Bruce M. Whealton Jr.\">");
    assert_seo($xp->query('//div[contains(@class,"book-part-byline")]//a[contains(@href,"/person/Bruce_Whealton_Jr_")]')->length > 0, "$key has visible author byline link to Person page");
    assert_seo($xp->query('//div[contains(@class,"book-part-byline")]//a[contains(@href,"/books-by-bruce-whealton")]')->length > 0, "$key has visible link to All Books by Bruce Whealton");
  }
  if ($key === 'person') {
    assert_seo($xp->query('//div[contains(@class,"person-authored-books")]//a[contains(@href,"/book/")]')->length >= 10, "$key displays organized list of authored books");
  }
}

// 4. Verify 301 redirects on superseded aliases.
foreach ([
  'https://brucewhealton.com/creative-writing/blog/find-me-online' => '/creative-writing/find-me-online',
  'https://brucewhealton.com/creative-writing/psychology-self-help/why-am-i-always-edge-understanding-hypervigilance-survival-strategy' => '/creative-writing/why-am-i-always-edge-understanding-hypervigilance-survival-strategy',
  'https://brucewhealton.com/creative-writing/book/origin-love' => '/creative-writing/book/labyrinth-mind/origin-love',
  'https://brucewhealton.com/creative-writing/book/returning-beach/childlike-imagination' => '/creative-writing/book/reflections-upon-waves-infinity/childlike-imagination',
] as $old_url => $expected_target) {
  $r = $client->get($old_url, ['http_errors' => FALSE, 'allow_redirects' => FALSE]);
  assert_seo($r->getStatusCode() === 301 && str_contains($r->getHeaderLine('Location'), $expected_target), "Old alias $old_url 301-redirects to $expected_target");
}

// 5. Verify ALL 179 sitemap URLs have 200 status, no redirects, and exact canonical URL match.
$res = $client->get('https://brucewhealton.com/creative-writing/sitemap.xml', ['http_errors' => FALSE]);
preg_match_all('#<loc>([^<]+)</loc>#', (string) $res->getBody(), $locs);
$urls = $locs[1];
$mismatches = 0;
foreach ($urls as $u) {
  $r = $client->get($u, ['http_errors' => FALSE, 'allow_redirects' => ['track_redirects' => TRUE]]);
  $html = (string) $r->getBody();
  $canonical = preg_match('#<link\s+rel="canonical"\s+href="([^"]+)"#i', $html, $m) ? $m[1] : '';
  if ($r->getStatusCode() !== 200 || !empty($r->getHeader('X-Guzzle-Redirect-History')) || ($canonical !== $u && rawurldecode($canonical) !== rawurldecode($u))) {
    $mismatches++;
    echo "SITEMAP ISSUE on $u: status=" . $r->getStatusCode() . " canonical=$canonical\n";
  }
}
assert_seo(count($urls) > 150 && $mismatches === 0, "All " . count($urls) . " sitemap.xml URLs return 200 OK with zero redirects and 100% matching canonical URLs");
