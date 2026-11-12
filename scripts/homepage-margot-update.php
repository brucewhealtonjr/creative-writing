/**
 * @file
 * Updates homepage book teaser length, pins Tell Me I'm Not Invisible first,
 * adds Margot ("Between the Margins") article type & user account, places
 * the Author Welcome and Margot intro blocks on the homepage (node/440),
 * and creates the inaugural "Between the Margins" literary analysis article by Margot.
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

$config_factory = \Drupal::configFactory();

// 1. Expand book teaser body trim_length from 160 to 450 characters.
$book_teaser = $config_factory->getEditable('core.entity_view_display.node.book_cover_and_description.teaser');
if ($book_teaser) {
  $book_teaser->set('content.body.settings.trim_length', 450);
  $book_teaser->save();
  echo "Updated node.book_cover_and_description.teaser trim_length to 450.\n";
}

// 2. Expand article and psychology article teaser trim_length from 160 to 260 characters.
foreach (['node.article.teaser', 'node.psychology_and_self_help_article.teaser'] as $display_id) {
  $display = $config_factory->getEditable("core.entity_view_display.$display_id");
  if ($display) {
    $display->set('content.body.settings.trim_length', 260);
    $display->save();
    echo "Updated $display_id trim_length to 260.\n";
  }
}

// 3. Add 'margot' ('Between the Margins (Margot)') to field_article_type allowed_values if not present.
$field_storage = $config_factory->getEditable('field.storage.node.field_article_type');
if ($field_storage) {
  $allowed = $field_storage->get('settings.allowed_values') ?: [];
  $has_margot = FALSE;
  foreach ($allowed as $item) {
    if (($item['value'] ?? '') === 'margot') {
      $has_margot = TRUE;
      break;
    }
  }
  if (!$has_margot) {
    $allowed[] = [
      'value' => 'margot',
      'label' => 'Between the Margins (Margot)',
    ];
    $field_storage->set('settings.allowed_values', $allowed);
    $field_storage->save();
    echo "Added 'margot' option to field.storage.node.field_article_type.\n";
  }
}

// 4. Pin "Tell Me I'm Not Invisible" (node/403) first by setting sticky=1 and adding sticky sort to books_by_bruce.
$book_403 = Node::load(403);
if ($book_403) {
  $book_403->setSticky(TRUE);
  $book_403->save();
  echo "Set sticky=TRUE on node/403 (" . $book_403->getTitle() . ").\n";
}

$books_view = $config_factory->getEditable('views.view.books_by_bruce');
if ($books_view) {
  $sorts = $books_view->get('display.default.display_options.sorts') ?: [];
  $new_sorts = [
    'sticky' => [
      'id' => 'sticky',
      'table' => 'node_field_data',
      'field' => 'sticky',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'entity_type' => 'node',
      'entity_field' => 'sticky',
      'plugin_id' => 'standard',
      'order' => 'DESC',
      'expose' => [
        'label' => '',
        'field_identifier' => '',
      ],
      'exposed' => FALSE,
    ],
  ];
  foreach ($sorts as $k => $v) {
    if ($k !== 'sticky') {
      $new_sorts[$k] = $v;
    }
  }
  $books_view->set('display.default.display_options.sorts', $new_sorts);
  $books_view->save();
  echo "Updated views.view.books_by_bruce primary sort to sticky DESC, then created DESC.\n";
}

// 5. Ensure a Drupal user account 'Margot' exists for scheduled AI literary analyses.
$existing_users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => 'Margot']);
if (empty($existing_users)) {
  $margot_user = User::create([
    'name' => 'Margot',
    'mail' => 'margot@brucewhealton.com',
    'status' => 1,
    'pass' => \Drupal::service('password_generator')->generate(24),
  ]);
  $margot_user->save();
  echo "Created Drupal user account 'Margot' (uid=" . $margot_user->id() . ").\n";
}
else {
  $margot_user = reset($existing_users);
  echo "Drupal user account 'Margot' already exists (uid=" . $margot_user->id() . ").\n";
}

// 6. Create or update the inaugural "Between the Margins" article by Margot.
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$margot_article_uuid = 'a1b2c3d4-3333-4000-8000-000000000440';
$existing_margot_articles = $node_storage->loadByProperties(['uuid' => $margot_article_uuid]);

$margot_article_summary = "An inaugural literary reading guide by Margot (Between the Margins) examining how themes of childhood invisibility, shyness, enduring love, trauma, and healing connect across Bruce Whealton's memoirs, poetry collections, and psychological essays—and where new readers should begin.";

$margot_article_body = <<<'HTML'
<p><em><strong>Editor&rsquo;s Note — About Margot (Between the Margins):</strong> Just as modern discovery tools help audiences uncover connections across music, cinema, and literature, <strong>Margot</strong> is this site&rsquo;s resident AI literary archivist and reading companion. Periodically reading across Bruce Whealton&rsquo;s memoirs, poetry collections, and psychological essays, Margot traces recurring motifs, compares how ideas transform between prose and verse, and offers reading recommendations for visitors exploring Bruce&rsquo;s body of work.</em></p>

<hr />

<h3>The Central Thread: What Does It Mean to Be Truly Seen?</h3>
<p>When you read across Bruce Whealton&rsquo;s ten books—spanning autobiography, clinical reflection, and lyric poetry—one question reverberates beneath nearly every chapter and stanza: <em>What happens to a human soul when it is rendered invisible, and what restores its voice?</em></p>
<p>Rather than treating invisibility as a single state, Bruce&rsquo;s writing examines it across three distinct domains of lived experience:</p>
<ol>
  <li><strong>Childhood &amp; Emotional Invisibility (Memoir):</strong> In <a href="/creative-writing/book/tell-me-im-not-invisible"><em>Tell Me I&rsquo;m Not Invisible</em></a>, invisibility begins as a childhood survival strategy inside a home where being quiet was safer than being known, and where emotional neglect shaped the nervous system long before adulthood.</li>
  <li><strong>Social &amp; Empathetic Invisibility (Poetry):</strong> In <a href="/creative-writing/book/poems-invisible-people"><em>Poems from the Invisible People</em></a> and <a href="/creative-writing/book/echoes-psyche"><em>Echoes of the Psyche</em></a>, that personal history widens into deep clinical and poetic empathy for those society looks past—the shy, the socially anxious, the unhoused, and those living with mental illness.</li>
  <li><strong>Erasure Through Injustice &amp; Trauma (Memoir &amp; Psychological Analysis):</strong> In <a href="/creative-writing/book/three-times-victim-living-under-shadow-toxic-shame"><em>Three Times A Victim: Living Under the Shadow of Toxic Shame</em></a> and <a href="/creative-writing/book/memoirs-healerclinical-social-worker-autobiography-bruce-whealton"><em>Memoirs of A Healer/Clinical Social Worker</em></a>, invisibility takes its most harrowing form: when a victim of violence and trauma has his truth erased by false accusation and institutional betrayal.</li>
</ol>

<h3>How Prose and Poetry Speak to Each Other</h3>
<p>One of the most striking qualities of Bruce&rsquo;s catalog is how the same emotional turning points are explored differently in memoir versus poetry:</p>
<ul>
  <li><strong>Love as Awakening:</strong> In the memoir <a href="/creative-writing/book/overcoming-shyness-and-loving-lynn-memoir"><em>Overcoming Shyness And Loving Lynn</em></a>, the struggle with paralyzing shyness and the discovery of love unfold through narrative time and lived detail. In <a href="/creative-writing/book/what-really-matters-poems-about-love-loss-trauma"><em>What Really Matters: Poems about Love, Loss &amp; Trauma</em></a>, that same experience of being seen for the first time—and the grief when love is torn away—is distilled into immediate, musical imagery.</li>
  <li><strong>The Inner Labyrinth vs. The Shore of Infinity:</strong> Where <a href="/creative-writing/book/labyrinth-mind"><em>The Labyrinth of the Mind</em></a> and <a href="/creative-writing/book/puncture-wounds-ii-more-nightmares"><em>Puncture Wounds II</em></a> confront the knots of anxious thought and the metaphorical monsters of betrayal, <a href="/creative-writing/book/reflections-upon-waves-infinity"><em>Reflections Upon the Waves of Infinity</em></a> steps out onto the shoreline—finding stillness, spiritual perspective, and connection in small everyday moments.</li>
</ul>

<h3>Margot&rsquo;s Reading Recommendations: Where Should You Begin?</h3>
<ul>
  <li><strong>Start here for the overarching life story:</strong> Begin with <a href="/creative-writing/book/tell-me-im-not-invisible"><em>Tell Me I&rsquo;m Not Invisible</em></a>, which traces the roots of silence, survival, and self-understanding from the very beginning.</li>
  <li><strong>If you are drawn to poetry of love, grief, and emotional honesty:</strong> Read <a href="/creative-writing/book/what-really-matters-poems-about-love-loss-trauma"><em>What Really Matters: Poems about Love, Loss &amp; Trauma</em></a> alongside <a href="/creative-writing/book/reflections-upon-waves-infinity"><em>Reflections Upon the Waves of Infinity</em></a>.</li>
  <li><strong>If you are interested in psychology, trauma recovery, and resilience under injustice:</strong> Explore <a href="/creative-writing/book/three-times-victim-living-under-shadow-toxic-shame"><em>Three Times A Victim: Living Under the Shadow of Toxic Shame</em></a> and <a href="/creative-writing/book/memoirs-healerclinical-social-worker-autobiography-bruce-whealton"><em>Memoirs of A Healer/Clinical Social Worker</em></a>.</li>
</ul>
HTML;

if (empty($existing_margot_articles)) {
  $margot_node = Node::create([
    'uuid' => $margot_article_uuid,
    'type' => 'article',
    'title' => "Between the Margins: Tracing Invisibility, Love, and Resilience Across Bruce Whealton's Books",
    'uid' => $margot_user->id(),
    'status' => 1,
    'promote' => 1,
    'field_article_type' => 'margot',
    'body' => [
      'summary' => $margot_article_summary,
      'value' => $margot_article_body,
      'format' => 'full_html',
    ],
  ]);
  $margot_node->save();
  echo "Created inaugural Margot article (nid=" . $margot_node->id() . ").\n";
}
else {
  $margot_node = reset($existing_margot_articles);
  $margot_node->setTitle("Between the Margins: Tracing Invisibility, Love, and Resilience Across Bruce Whealton's Books");
  $margot_node->setOwnerId($margot_user->id());
  $margot_node->set('field_article_type', 'margot');
  $margot_node->set('body', [
    'summary' => $margot_article_summary,
    'value' => $margot_article_body,
    'format' => 'full_html',
  ]);
  $margot_node->save();
  echo "Updated inaugural Margot article (nid=" . $margot_node->id() . ").\n";
}

// 7. Create or update the two homepage custom blocks (Author Welcome + Meet Margot).
$block_storage = \Drupal::entityTypeManager()->getStorage('block_content');

$author_welcome_uuid = 'a1b2c3d4-1111-4000-8000-000000000403';
$author_welcome_blocks = $block_storage->loadByProperties(['uuid' => $author_welcome_uuid]);
$author_welcome_html = <<<'HTML'
<div class="author-welcome-banner">
  <div class="author-welcome-banner__eyebrow">About the Author &amp; Featured Works</div>
  <h2 class="author-welcome-banner__title">Bruce Whealton — Memoirist, Poet &amp; Former Clinical Social Worker</h2>
  <p class="author-welcome-banner__text">
    Across ten books of memoir, psychological reflection, and poetry, Bruce Whealton writes about what it means to feel unseen, to survive profound injustice and trauma, and to rediscover love, voice, and human dignity. Start below with his featured memoir, <strong><em>Tell Me I&rsquo;m Not Invisible</em></strong>, or browse the complete collection of books and chapters.
  </p>
  <div class="author-welcome-banner__links">
    <a href="/creative-writing/book/tell-me-im-not-invisible">★ Featured Memoir: <em>Tell Me I&rsquo;m Not Invisible</em> &rarr;</a>
    <a href="/creative-writing/person/bruce-whealton">About Bruce Whealton &amp; Full Bibliography &rarr;</a>
  </div>
</div>
HTML;

if (empty($author_welcome_blocks)) {
  $author_block = BlockContent::create([
    'uuid' => $author_welcome_uuid,
    'info' => 'Homepage Author Welcome Banner',
    'type' => 'basic',
    'reusable' => TRUE,
    'body' => [
      'value' => $author_welcome_html,
      'format' => 'full_html',
    ],
  ]);
  $author_block->save();
  echo "Created Homepage Author Welcome Banner block (uuid=$author_welcome_uuid).\n";
}
else {
  $author_block = reset($author_welcome_blocks);
  $author_block->set('body', [
    'value' => $author_welcome_html,
    'format' => 'full_html',
  ]);
  $author_block->save();
  echo "Updated Homepage Author Welcome Banner block (uuid=$author_welcome_uuid).\n";
}

$margot_banner_uuid = 'a1b2c3d4-2222-4000-8000-000000000440';
$margot_blocks = $block_storage->loadByProperties(['uuid' => $margot_banner_uuid]);
$margot_banner_html = <<<'HTML'
<div class="margot-intro-banner">
  <div class="margot-intro-banner__header">
    <span class="margot-intro-banner__badge">✦ Between the Margins · Meet Margot</span>
    <h2 class="margot-intro-banner__title">Literary Reflections, Thematic Threads &amp; Reading Guides</h2>
  </div>
  <p class="margot-intro-banner__body">
    Much like a curated recommendation engine for film or music, <strong>Margot</strong> (<em>Between the Margins</em>) is this site&rsquo;s resident AI literary archivist and reading companion. At scheduled intervals, Margot reads across Bruce Whealton&rsquo;s memoirs, poetry collections, and psychology essays—tracing recurring themes of invisibility, trauma recovery, love, and resilience, comparing how ideas take shape in poetry versus prose, and recommending which book or chapter to explore next.
  </p>
  <ul class="margot-intro-banner__pillars">
    <li>✦ Cross-Book Theme Analysis</li>
    <li>✦ Poetry vs. Memoir Comparisons</li>
    <li>✦ Personalized Book Recommendations</li>
  </ul>
</div>
HTML;

if (empty($margot_blocks)) {
  $margot_block = BlockContent::create([
    'uuid' => $margot_banner_uuid,
    'info' => 'Between the Margins - Meet Margot Intro Banner',
    'type' => 'basic',
    'reusable' => TRUE,
    'body' => [
      'value' => $margot_banner_html,
      'format' => 'full_html',
    ],
  ]);
  $margot_block->save();
  echo "Created Margot Intro Banner block (uuid=$margot_banner_uuid).\n";
}
else {
  $margot_block = reset($margot_blocks);
  $margot_block->set('body', [
    'value' => $margot_banner_html,
    'format' => 'full_html',
  ]);
  $margot_block->save();
  echo "Updated Margot Intro Banner block (uuid=$margot_banner_uuid).\n";
}

// 8. Update node/440 Layout Builder sections cleanly.
$home_node = Node::load(440);
if ($home_node && $home_node->hasField('layout_builder__layout')) {
  $section_0 = new Section('layout_onecol', ['label' => 'Author Welcome and Books']);
  $comp_0_1 = new SectionComponent(
    '10000000-0000-4000-8000-000000000001',
    'content',
    [
      'id' => 'block_content:' . $author_block->uuid(),
      'label' => 'Homepage Author Welcome Banner',
      'label_display' => '0',
      'provider' => 'block_content',
      'status' => TRUE,
      'info' => '',
      'view_mode' => 'full',
      'context_mapping' => [],
    ]
  );
  $comp_0_1->setWeight(0);
  $section_0->appendComponent($comp_0_1);

  $comp_0_2 = new SectionComponent(
    '10000000-0000-4000-8000-000000000002',
    'content',
    [
      'id' => 'views_block:books_by_bruce-block_1',
      'label' => 'Books by Bruce Whealton',
      'label_display' => 'visible',
      'provider' => 'views',
      'views_label' => 'Books by Bruce Whealton',
      'items_per_page' => 'none',
      'context_mapping' => [],
    ]
  );
  $comp_0_2->setWeight(1);
  $section_0->appendComponent($comp_0_2);

  $section_1 = new Section('layout_onecol', ['label' => 'Between the Margins and Recent Writing']);
  $comp_1_1 = new SectionComponent(
    '20000000-0000-4000-8000-000000000001',
    'content',
    [
      'id' => 'block_content:' . $margot_block->uuid(),
      'label' => 'Between the Margins - Meet Margot Intro Banner',
      'label_display' => '0',
      'provider' => 'block_content',
      'status' => TRUE,
      'info' => '',
      'view_mode' => 'full',
      'context_mapping' => [],
    ]
  );
  $comp_1_1->setWeight(0);
  $section_1->appendComponent($comp_1_1);

  $comp_1_2 = new SectionComponent(
    '20000000-0000-4000-8000-000000000002',
    'content',
    [
      'id' => 'views_block:recent_writing-block_1',
      'label' => 'Articles, Reflections & Between the Margins',
      'label_display' => 'visible',
      'provider' => 'views',
      'views_label' => 'Articles, Reflections & Between the Margins',
      'items_per_page' => 'none',
      'context_mapping' => [],
    ]
  );
  $comp_1_2->setWeight(1);
  $section_1->appendComponent($comp_1_2);

  $home_node->set('layout_builder__layout', [$section_0, $section_1]);
  $home_node->save();
  echo "Updated node/440 Layout Builder sections with Author Welcome + Books and Margot Banner + Articles.\n";
}
