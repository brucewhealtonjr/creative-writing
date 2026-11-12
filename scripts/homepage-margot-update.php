<?php

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

// 4. Create field_display_order on book_cover_and_description for manual sorting
//    and set the top 3 books in order:
//    1st: Tell Me I'm Not Invisible (node/403)
//    2nd: The Labyrinth of the Mind (node/64)
//    3rd: Three Times A Victim: Living Under the Shadow of Toxic Shame (node/131)
if (!\Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_display_order')) {
  \Drupal\field\Entity\FieldStorageConfig::create([
    'field_name' => 'field_display_order',
    'entity_type' => 'node',
    'type' => 'integer',
    'cardinality' => 1,
    'settings' => [
      'unsigned' => FALSE,
      'size' => 'normal',
    ],
  ])->save();
  echo "Created field.storage.node.field_display_order.\n";
}

if (!\Drupal\field\Entity\FieldConfig::loadByName('node', 'book_cover_and_description', 'field_display_order')) {
  \Drupal\field\Entity\FieldConfig::create([
    'field_name' => 'field_display_order',
    'entity_type' => 'node',
    'bundle' => 'book_cover_and_description',
    'label' => 'Display Order',
    'description' => 'Manual sort order (1 = First, 2 = Second, 3 = Third, etc.). You can also drag and drop books at /admin/content/sort-books.',
    'required' => FALSE,
    'default_value' => [['value' => 10]],
  ])->save();
  echo "Created field.field.node.book_cover_and_description.field_display_order.\n";
}

$book_form_display = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'book_cover_and_description', 'default');
if ($book_form_display) {
  $book_form_display->setComponent('field_display_order', [
    'type' => 'number',
    'weight' => 0,
    'region' => 'content',
    'settings' => [
      'placeholder' => '1',
    ],
  ])->save();
  echo "Added field_display_order to book_cover_and_description form display.\n";
}

foreach (['default', 'teaser'] as $vm) {
  $book_view_display = \Drupal::service('entity_display.repository')->getViewDisplay('node', 'book_cover_and_description', $vm);
  if ($book_view_display) {
    $book_view_display->removeComponent('field_display_order')->save();
  }
}

$preferred_order = [
  403 => 1, // Tell Me I'm Not Invisible
  64  => 2, // The Labyrinth of the Mind
  131 => 3, // Three Times A Victim: Living Under the Shadow of Toxic Shame
  437 => 4, // From Darkness to Light
  63  => 5, // Reflections Upon the Waves of Infinity
];

$all_books = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'book_cover_and_description']);
$next_order = 6;
foreach ($all_books as $b) {
  $bnid = (int) $b->id();
  $order_val = $preferred_order[$bnid] ?? $next_order++;
  $b->set('field_display_order', $order_val);
  if (!in_array($bnid, [403, 64, 131], TRUE) && $b->isSticky()) {
    $b->setSticky(FALSE);
  }
  $b->save();
  echo "Set field_display_order=$order_val on book nid=$bnid (" . $b->getTitle() . ").\n";
}

$top_three_books = [
  403 => strtotime('2026-07-15 12:00:00'),
  64  => strtotime('2026-07-14 12:00:00'),
  131 => strtotime('2026-07-13 12:00:00'),
];
foreach ($top_three_books as $book_nid => $timestamp) {
  $book_node = Node::load($book_nid);
  if ($book_node) {
    $book_node->setSticky(TRUE);
    $book_node->setCreatedTime($timestamp);
    $book_node->save();
    echo "Pinned book nid=$book_nid (" . $book_node->getTitle() . ") with sticky=TRUE.\n";
  }
}

$books_view = $config_factory->getEditable('views.view.books_by_bruce');
if ($books_view) {
  $sorts = $books_view->get('display.default.display_options.sorts') ?: [];
  $new_sorts = [
    'field_display_order_value' => [
      'id' => 'field_display_order_value',
      'table' => 'node__field_display_order',
      'field' => 'field_display_order_value',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'standard',
      'order' => 'ASC',
      'expose' => [
        'label' => '',
        'field_identifier' => '',
      ],
      'exposed' => FALSE,
    ],
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
    if (!in_array($k, ['field_display_order_value', 'sticky'], TRUE)) {
      $new_sorts[$k] = $v;
    }
  }
  $books_view->set('display.default.display_options.sorts', $new_sorts);
  $books_view->save();
  echo "Updated views.view.books_by_bruce primary sort to field_display_order ASC, then sticky DESC, then created DESC.\n";
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

$margot_article_summary = "An inaugural literary reading guide by Margot (Between the Margins) examining how themes of childhood invisibility, shyness, enduring love, trauma, and healing connect across Bruce Whealton's memoirs, poetry collections, and psychological essays, and where new readers should begin.";

$margot_article_body = <<<'HTML'
<p><em><strong>Editor&rsquo;s Note &mdash; About Margot (Between the Margins):</strong> Just as modern discovery tools help audiences uncover connections across music, cinema, and literature, <strong>Margot</strong> is this site&rsquo;s resident AI literary archivist and reading companion. Periodically reading across Bruce Whealton&rsquo;s memoirs, poetry collections, and psychological essays, Margot traces recurring motifs, compares how ideas transform between prose and verse, and offers reading recommendations for visitors exploring Bruce&rsquo;s body of work.</em></p>

<hr />

<h3>The Central Thread: What Does It Mean to Be Truly Seen?</h3>
<p>When you read across Bruce Whealton&rsquo;s ten books&mdash;spanning autobiography, clinical reflection, and lyric poetry&mdash;one question reverberates beneath nearly every chapter and stanza: <em>What happens to a human soul when it is rendered invisible, and what restores its voice?</em></p>
<p>Rather than treating invisibility as a single state, Bruce&rsquo;s writing examines it across three distinct domains of lived experience:</p>
<ol>
  <li><strong>Childhood &amp; Emotional Invisibility (Memoir):</strong> In <a href="/creative-writing/book/tell-me-im-not-invisible"><em>Tell Me I&rsquo;m Not Invisible</em></a>, invisibility begins as a childhood survival strategy inside a home where being quiet was safer than being known, and where emotional neglect shaped the nervous system long before adulthood.</li>
  <li><strong>Social &amp; Empathetic Invisibility (Poetry):</strong> In <a href="/creative-writing/book/poems-invisible-people"><em>Poems from the Invisible People</em></a> and <a href="/creative-writing/book/echoes-psyche"><em>Echoes of the Psyche</em></a>, that personal history widens into deep clinical and poetic empathy for those society looks past&mdash;the shy, the socially anxious, the unhoused, and those living with mental illness.</li>
  <li><strong>Erasure Through Injustice &amp; Trauma (Memoir &amp; Psychological Analysis):</strong> In <a href="/creative-writing/book/three-times-victim-living-under-shadow-toxic-shame"><em>Three Times A Victim: Living Under the Shadow of Toxic Shame</em></a> and <a href="/creative-writing/book/memoirs-healerclinical-social-worker-autobiography-bruce-whealton"><em>Memoirs of A Healer/Clinical Social Worker</em></a>, invisibility takes its most harrowing form: when a victim of violence and trauma has his truth erased by false accusation and institutional betrayal.</li>
</ol>

<h3>How Prose and Poetry Speak to Each Other</h3>
<p>One of the most striking qualities of Bruce&rsquo;s catalog is how the same emotional turning points are explored differently in memoir versus poetry:</p>
<ul>
  <li><strong>Love as Awakening:</strong> In the memoir <a href="/creative-writing/book/overcoming-shyness-and-loving-lynn-memoir"><em>Overcoming Shyness And Loving Lynn</em></a>, the struggle with paralyzing shyness and the discovery of love unfold through narrative time and lived detail. In <a href="/creative-writing/book/what-really-matters-poems-about-love-loss-trauma"><em>What Really Matters: Poems about Love, Loss &amp; Trauma</em></a>, that same experience of being seen for the first time&mdash;and the grief when love is torn away&mdash;is distilled into immediate, musical imagery.</li>
  <li><strong>The Inner Labyrinth vs. The Shore of Infinity:</strong> Where <a href="/creative-writing/book/labyrinth-mind"><em>The Labyrinth of the Mind</em></a> and <a href="/creative-writing/book/puncture-wounds-ii-more-nightmares"><em>Puncture Wounds II</em></a> confront the knots of anxious thought and the metaphorical monsters of betrayal, <a href="/creative-writing/book/reflections-upon-waves-infinity"><em>Reflections Upon the Waves of Infinity</em></a> steps out onto the shoreline&mdash;finding stillness, spiritual perspective, and connection in small everyday moments.</li>
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
  $margot_node->setCreatedTime(strtotime('2026-11-11 21:00:00'));
  $margot_node->save();
  echo "Updated inaugural Margot article (nid=" . $margot_node->id() . ").\n";
}

// 6b. Create or update Margot's deep-dive article on Identity, Coming Out of the Shell,
//     and the Trauma of a False Permanent Record in "Three Times A Victim".
$margot_identity_uuid = 'a1b2c3d4-4444-4000-8000-000000000131';
$existing_identity_articles = $node_storage->loadByProperties(['uuid' => $margot_identity_uuid]);

$three_times_tid = NULL;
$node_402 = Node::load(402);
if ($node_402 && $node_402->hasField('field_related_to_the_book') && !$node_402->get('field_related_to_the_book')->isEmpty()) {
  $three_times_tid = $node_402->get('field_related_to_the_book')->target_id;
}

$identity_summary = "Margot (Between the Margins) examines why a false criminal conviction is uniquely devastating when you have spent years overcoming childhood silence and shyness to build a life as a gentle healer and clinical social worker--and what psychological research reveals about living against a permanent record that records a lie.";

$identity_body = <<<'HTML'
<p><em><strong>Between the Margins &mdash; Literary &amp; Psychological Analysis by Margot:</strong> In this deep-dive reflection on <a href="/creative-writing/book/three-times-victim-living-under-shadow-toxic-shame"><em>Three Times A Victim: Living Under the Shadow of Toxic Shame</em></a>, Margot examines the psychological violence of wrongful conviction through the lens of identity: why being branded by an institutional lie is uniquely excruciating for a person who spent decades overcoming selective mutism and paralyzing shyness to become known as a gentle healer, clinical social worker, and protector of trauma survivors.</em></p>

<hr />

<h3>1. The Two Childhood Vows and the Courage to Step Out of the Shell</h3>
<p>Early in <a href="/creative-writing/book/three-times-victim-living-under-shadow-toxic-shame"><em>Three Times A Victim</em></a>, Bruce Whealton explains why Section One of his memoir is titled <em>&ldquo;Who I Was Before That Night of My Victimization.&rdquo;</em> The detectives who interrogated him on the night of October 1, 2004&mdash;while he sat in front of them still soaked in his own blood after being violently attacked in his apartment by his landlord&rsquo;s wife, Ana&mdash;never asked who he was. They never asked whether a person&rsquo;s entire life can testify to their character (p. 13).</p>
<p>Growing up in a chaotic, emotionally starving home where his mother&rsquo;s rage was unpredictable and where he was cast as the family scapegoat, young Bruce retreated into what he calls <em>&ldquo;An Invisible Shell: The Complete Silence of Selective Mutism&rdquo;</em> (p. 18). Inside school walls, his voice died completely. Yet inside that silence, he made two defining vows that shaped his core identity for the rest of his life:</p>
<ul>
  <li><strong>The First Vow (p. 17):</strong> <em>&ldquo;I will never become like them. I will never lose my temper. I will never let anyone feel unsafe because of me. I would spend my whole life keeping that promise.&rdquo;</em></li>
  <li><strong>The Second Vow (p. 19):</strong> At seventeen, when he stood up too quickly during a television argument and saw his sister flinch, shock hit him like cold water: <em>&ldquo;That night, the second vow: <strong>No one will ever fear me.</strong> In a home where fear was a weapon, I rejected it.&rdquo;</em></li>
</ul>
<p>Coming out of his proverbial shell required sixteen years of relentless effort (1984&ndash;2000): five years of counseling at Georgia Tech to overcome paralyzing social anxiety (pp. 24&ndash;27); volunteering on the psychiatric intake unit at Georgia Regional Hospital (p. 30); discovering what it felt like to be cherished through love with Celta and his wife Lynn (Chapters 3&ndash;4); forcing himself to read poetry at open mics in Wilmington so he could lead therapy groups (p. 34); earning his Master of Social Work and LCSW license; building a thriving private practice; and becoming <strong>President of the New Hanover County chapter of the North Carolina Society for Clinical Social Workers</strong> (pp. 40&ndash;43).</p>
<p>When Bruce walked with rape and trauma survivors like Karen at Brynn Marr Psychiatric Hospital, his defining words were: <em>&ldquo;My voice will go with you&hellip; You&rsquo;re safe now&rdquo;</em> (p. 41). Being known as a gentle, trustworthy healer was not just a career&mdash;it was the reason he had dared to become visible at all.</p>

<h3>2. &ldquo;The Worst Thing That Could Happen If I Left My Proverbial Shell&rdquo;</h3>
<p>On page 81 of the manuscript, sitting in a dark Durham police interrogation room after his attacker&rsquo;s lies turned the victim into the suspect, Bruce names the exact psychological wound at the center of the book:</p>
<blockquote>
  <p><em>&ldquo;I was aware of how much I did not want to spend another second in jail. Symbolically, these were both the antithesis of all the reasons and events that had led me to experience the courage to be noticed, to gain name recognition in Wilmington. All the countless times I wrote down answers to the question of what was the worst thing that could happen if I left my proverbial shell as a shy person&hellip;, I suddenly was being smacked in the face with the worst possible answer to what was the worst possible thing that could happen &ndash; the most shameful type of event that someone like me could not have dreamed up if I had tried.&rdquo;</em> (pp. 81&ndash;82)</p>
</blockquote>
<p>In Cognitive Behavioral Therapy (CBT) for social anxiety and shyness, a person is taught to test their fears by stepping into visibility: <em>&ldquo;What is the worst that could happen if people notice you?&rdquo;</em> Usually, the answer is mild embarrassment or awkwardness. For Bruce, stepping out of his shell was met with a catastrophic inversion of reality: he was violently assaulted in his own home, ignored by detectives who refused to photograph his bleeding face or examine his blood-soaked clothes, held for seven months in jail, and then ambushed in a courthouse hallway by his own defense lawyer into a coerced plea deal for second-degree kidnapping (Chapter 16, pp. 123&ndash;128).</p>
<p>Notice how the courtroom ambush directly reactivated his childhood selective mutism. When the judge asked whether any mental health condition prevented him from entering the plea, Bruce writes: <em>&ldquo;I couldn&rsquo;t say it because I lacked the capacity to draw in the air and force it across vocal chords that would utter words of truth&rdquo;</em> (p. 126). And when asked, <em>&ldquo;Are you in fact guilty?&rdquo;</em>, he never confessed; he pointed at his lawyer and said: <em>&ldquo;That&rsquo;s what he told me to say for the purpose of this plea deal&rdquo;</em> (p. 127).</p>

<h3>3. Truth vs. Reality: What Happens When a Permanent Record Records a Lie?</h3>
<p>Throughout <em>Three Times A Victim</em>, Bruce draws a razor-sharp philosophical and psychological distinction between <strong>Truth</strong> and <strong>Reality</strong>:</p>
<blockquote>
  <p><em>&ldquo;My life seemed split into two opposing forces&mdash;truth and reality. The truth was the essence of who I truly was and had always been. Reality, however, was a social construct, woven from tales told by others. None of the stories about me were penned by anyone who genuinely knew me.&rdquo;</em> (pp. 131&ndash;132)</p>
</blockquote>
<p>What is expected to happen to a human being&mdash;especially a deeply empathetic, shy clinician whose life was built on the vow <em>&ldquo;No one will ever fear me&rdquo;</em>&mdash;when a permanent state and digital record records a lie? Psychological and criminological research identifies four compounding injuries, every one of which appears in the pages of <em>Three Times A Victim</em>:</p>
<ol>
  <li><strong>The Hijacking of First Impressions (&ldquo;Spoiled Identity&rdquo; &amp; Digital Permanence):</strong> Sociologist Erving Goffman showed that an official stigma acts as a false avatar that enters every room before the person does. For a shy person who worked for years to be known for warmth, integrity, and clinical skill, a background check or Google search (Chapter 24, p. 183) violently overwrites that identity before a single word can be spoken: <em>&ldquo;I felt like no one saw the real me. They only saw the conviction. The label. The lie.&rdquo;</em> (p. 152).</li>
  <li><strong>Fear of One&rsquo;s Own Reflexes and Emotions:</strong> Because his entire moral identity rested on never causing fear, the false accusation made Bruce terrified of ever being misperceived. In Chapter 15 (pp. 119&ndash;120), when he accidentally washed his return train ticket and slapped his hand on the bed in frustration at himself, panic immediately gripped him: <em>&ldquo;What if she thinks I could be dangerous? &hellip; It didn&rsquo;t matter that I knew I was the same person who had those soft gentle hands &ndash; the only hands and arms that could have been there with Lynn or Celta before her.&rdquo;</em> And in Chapter 23 (p. 177), he notes: <em>&ldquo;False accusations had taught me that any strong emotion could be used against me. If I expressed frustration, people might think I was dangerous.&rdquo;</em></li>
  <li><strong>Vocational Exile and Institutional Retraumatization (Brooks &amp; Greenberg, 2021; Hoyle et al., 2016):</strong> Research on wrongful accusations in <em>occupations of trust</em> demonstrates that losing one&rsquo;s calling as a healer inflicts a profound moral and existential injury. Even after Bruce earned his Certified Peer Support Specialist (CPSS) credential in 2021 and proved his gift on the Mobile Crisis Unit at Freedom House&mdash;where his supervisor Sherisse looked at him in astonishment and said, <em>&ldquo;You? You couldn&rsquo;t hurt anyone. I&rsquo;d fight someone before you would&rdquo;</em> (p. 209)&mdash;automated background checks and risk-averse HR departments at other agencies (Chapters 28 and 30, pp. 208, 215&ndash;217) repeatedly slammed doors shut without ever seeing the person behind the file.</li>
  <li><strong>The Slow Erosion of Hope (&ldquo;Enduring Personality Change&rdquo; &mdash; Grounds, 2004):</strong> Forensic psychiatrist Adrian Grounds documented that living under a false conviction produces a chronic, cumulative erosion of agency and belonging. As Bruce writes of December 2019 (Chapter 24, p. 183): <em>&ldquo;It wasn&rsquo;t sudden. It wasn&rsquo;t a breakdown or a psychotic snap. It was more like a slow erosion&mdash;a quiet, daily wearing away of hope, purpose, and identity. It was the accumulation of years spent trying to live in a world where a lie had become the first truth associated with my name.&rdquo;</em></li>
</ol>

<h3>4. Why Witnessing Matters: From &ldquo;Oh, I Believe You. 100%&rdquo; to Claiming Truth</h3>
<p>If trauma is born when a lie is forced into silence, <em>Three Times A Victim</em> also reveals how identity begins to heal: through <strong>believing witnesses</strong> and <strong>reclaiming one&rsquo;s voice</strong>.</p>
<p>Look at the turning points in Section Six (<em>Breaking the Silence: Finding My Voice</em>):</p>
<ul>
  <li>In the hospital hallway in December 2019 (Chapter 25, p. 189), when a fellow patient named Kira listened to his story and said without hesitation, <em>&ldquo;Oh, I believe you. 100%,&rdquo;</em> Bruce writes: <em>&ldquo;Those words were like water in the desert. She didn&rsquo;t ask for proof. She didn&rsquo;t shrink away. She believed me. And something inside me exhaled for the first time in years.&rdquo;</em></li>
  <li>Days later, in an art therapy group (p. 191), when Bruce painted the words <em>misfit, outcast, invisible,</em> and <em>outsider</em>, another patient looked at him and said: <em>&ldquo;You&rsquo;re not invisible. You got me here. You&rsquo;re everywhere. You&rsquo;re like the social butterfly of this place.&rdquo;</em></li>
  <li>At the Community Empowerment Fund (CEF), with his friend Sarah, and at the Orange County Rape Crisis Center (p. 207)&mdash;which wrote an official letter affirming that <em>&ldquo;Sometimes, the person labeled &lsquo;perpetrator&rsquo; is the one who was harmed&rdquo;</em>&mdash;the wall of isolation finally cracked open.</li>
</ul>
<p>When a permanent court database records a lie, writing and publishing <a href="/creative-writing/book/three-times-victim-living-under-shadow-toxic-shame"><em>Three Times A Victim</em></a> creates a permanent counter-record of truth. As Bruce declares in Chapter 31 (<em>Claiming My Truth</em>, pp. 219&ndash;220):</p>
<blockquote>
  <p><em>&ldquo;There comes a point when you stop trying to explain. Not because the pain is gone. Not because the injustice no longer matters. But because you know who you are. I am not what they said I was. I don&rsquo;t have to win back trust&mdash;because I never broke it. I&rsquo;ve lived my life by the highest morals: With gentleness. With integrity. With compassion for those who suffer.&rdquo;</em></p>
</blockquote>
HTML;

$identity_values = [
  'uuid' => $margot_identity_uuid,
  'type' => 'article',
  'title' => 'Between the Margins: When a Permanent Record Records a Lie - Identity, Shyness, and the Wounded Healer in "Three Times A Victim"',
  'uid' => $margot_user->id(),
  'status' => 1,
  'promote' => 1,
  'field_article_type' => 'margot',
  'created' => strtotime('2026-11-11 23:00:00'),
  'body' => [
    'summary' => $identity_summary,
    'value' => $identity_body,
    'format' => 'full_html',
  ],
];
if ($three_times_tid) {
  $identity_values['field_related_to_the_book'] = ['target_id' => $three_times_tid];
}

if (empty($existing_identity_articles)) {
  $identity_node = Node::create($identity_values);
  $identity_node->save();
  echo "Created Margot identity analysis article (nid=" . $identity_node->id() . ").\n";
}
else {
  $identity_node = reset($existing_identity_articles);
  $identity_node->setTitle($identity_values['title']);
  $identity_node->setOwnerId($margot_user->id());
  $identity_node->set('field_article_type', 'margot');
  $identity_node->setCreatedTime($identity_values['created']);
  if ($three_times_tid) {
    $identity_node->set('field_related_to_the_book', ['target_id' => $three_times_tid]);
  }
  $identity_node->set('body', $identity_values['body']);
  $identity_node->save();
  echo "Updated Margot identity analysis article (nid=" . $identity_node->id() . ").\n";
}

// 7. Create or update the two homepage custom blocks (Author Welcome + Meet Margot).
$block_storage = \Drupal::entityTypeManager()->getStorage('block_content');

$author_welcome_uuid = 'a1b2c3d4-1111-4000-8000-000000000403';
$author_welcome_blocks = $block_storage->loadByProperties(['uuid' => $author_welcome_uuid]);
$author_welcome_html = <<<'HTML'
<div class="author-welcome-banner">
  <div class="author-welcome-banner__eyebrow">About the Author &amp; Featured Works</div>
  <h2 class="author-welcome-banner__title">Bruce Whealton &mdash; Memoirist, Poet &amp; Former Clinical Social Worker</h2>
  <p class="author-welcome-banner__text">
    Across ten books of memoir, psychological reflection, and poetry, Bruce Whealton writes about what it means to feel unseen, to survive profound injustice and trauma, and to rediscover love, voice, and human dignity. Start below with his featured memoir, <strong><em>Tell Me I&rsquo;m Not Invisible</em></strong>, or browse the complete collection of books and chapters.
  </p>
  <div class="author-welcome-banner__links">
    <a href="/creative-writing/book/tell-me-im-not-invisible">&#9733; Featured Memoir: <em>Tell Me I&rsquo;m Not Invisible</em> &rarr;</a>
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
    <span class="margot-intro-banner__badge">&#10022; Between the Margins &middot; Meet Margot</span>
    <h2 class="margot-intro-banner__title">Literary Reflections, Thematic Threads &amp; Reading Guides</h2>
  </div>
  <p class="margot-intro-banner__body">
    Much like a curated recommendation engine for film or music, <strong>Margot</strong> (<em>Between the Margins</em>) is this site&rsquo;s resident AI literary archivist and reading companion. At scheduled intervals, Margot reads across Bruce Whealton&rsquo;s memoirs, poetry collections, and psychology essays&mdash;tracing recurring themes of invisibility, trauma recovery, love, and resilience, comparing how ideas take shape in poetry versus prose, and recommending which book or chapter to explore next.
  </p>
  <ul class="margot-intro-banner__pillars">
    <li>&#10022; Exploring Themes Across Books</li>
    <li>&#10022; How Poetry &amp; Memoir Speak to Each Other</li>
    <li>&#10022; Reading Guides &amp; Book Recommendations</li>
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
