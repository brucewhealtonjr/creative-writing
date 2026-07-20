<?php
/**
 * @file
 * Programmatically configures the homepage Layout Builder layout safely.
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\block_content\Entity\BlockContent;
use Drupal\node\Entity\Node;

// 1. Get the current front page path
$front_uri = \Drupal::config('system.site')->get('page.front');
$node = NULL;

if (preg_match('/\/node\/(\d+)/', $front_uri, $matches)) {
  $nid = $matches[1];
  $temp_node = Node::load($nid);
  // Verify the loaded node is a Basic Page ('page')
  if ($temp_node && $temp_node->bundle() === 'page') {
    $node = $temp_node;
    print "Found existing homepage Basic Page (node ID $nid).\n";
  }
}

// 2. If no valid Basic Page is set as the homepage, create a new one
if (!$node) {
  print "No Basic Page homepage node found. Creating a new Basic Page for the homepage...\n";
  $node = Node::create([
    'type' => 'page',
    'title' => 'Home',
    'status' => 1,
    'uid' => 1,
  ]);
  $node->save();
  $nid = $node->id();
  print "Created new homepage node (ID $nid).\n";
  
  // Set this new node as the front page
  \Drupal::configFactory()->getEditable('system.site')
    ->set('page.front', "/node/$nid")
    ->save();
  print "Updated front page path to /node/$nid.\n";
}

print "Configuring Layout Builder on homepage node ID " . $node->id() . "...\n";

// 3. Clear old layout builder sections to start fresh
$node->get('layout_builder__layout')->setValue([]);

// 4. Create or update the Hero Banner block content entity
$hero_body = <<<'HTML'
<div class="hero-banner" style="background-image: url('/sites/default/files/hero_background.png'); background-size: cover; background-position: center; min-height: 480px; display: flex; align-items: center; justify-content: center; text-align: center; color: #ffffff; padding: 60px 20px; position: relative;">
  <div class="hero-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.65); z-index: 1;"></div>
  <div class="hero-content" style="position: relative; z-index: 2; max-width: 800px;">
    <!-- Large prominent header with solid white and high-contrast text shadow -->
    <h1 style="font-size: 3.2rem; margin-bottom: 20px; font-weight: 800; line-height: 1.25; color: #ffffff; text-shadow: 0px 4px 12px rgba(0, 0, 0, 0.95), 0px 1px 2px rgba(0, 0, 0, 0.85); font-family: 'Outfit', 'Inter', sans-serif; letter-spacing: -0.5px;">Exploring the Depths of the Human Condition</h1>
    <!-- Tagline styled with light warm tint for elegant styling and excellent readability -->
    <p style="font-size: 1.6rem; margin-bottom: 40px; font-weight: 400; font-style: italic; color: #e6dfc3; text-shadow: 0px 3px 8px rgba(0, 0, 0, 0.95); font-family: 'Inter', sans-serif;">through Stories, Essays, and Poetry</p>
    <!-- CTA Buttons with Gold & White Outline styling -->
    <div class="hero-actions" style="display: flex; gap: 18px; justify-content: center; flex-wrap: wrap;">
      <a href="#books-section" class="btn-hero-primary" style="display: inline-block; background-color: #d4af37; color: #111111; padding: 15px 35px; font-size: 1.15rem; font-weight: 700; text-decoration: none; border-radius: 4px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4); text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.3s ease;">Explore My Books</a>
      <a href="#writings-section" class="btn-hero-secondary" style="display: inline-block; background-color: transparent; color: #ffffff; border: 2px solid #ffffff; padding: 13px 33px; font-size: 1.15rem; font-weight: 700; text-decoration: none; border-radius: 4px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.3s ease;">Updates & Articles</a>
    </div>
  </div>
</div>
HTML;

$block_storage = \Drupal::entityTypeManager()->getStorage('block_content');
$existing_blocks = $block_storage->loadByProperties(['info' => 'Homepage Hero Banner']);
if (!empty($existing_blocks)) {
  $block = reset($existing_blocks);
  $block->body->value = $hero_body;
  $block->body->format = 'full_html';
  $block->save();
  print "Updated existing Hero Banner block.\n";
} else {
  $block = BlockContent::create([
    'type' => 'basic',
    'info' => 'Homepage Hero Banner',
    'body' => [
      'value' => $hero_body,
      'format' => 'full_html',
    ],
  ]);
  $block->save();
  print "Created new Hero Banner block.\n";
}

// 5. Create Section 1 (Hero Banner inline block component)
$section1 = new Section('layout_onecol');
$component1 = new SectionComponent(
  \Drupal::service('uuid')->generate(),
  'content',
  [
    'id' => 'inline_block:basic',
    'label' => 'Hero Banner',
    'label_display' => '0',
    'provider' => 'layout_builder',
    'view_mode' => 'full',
    'block_id' => $block->id(),
    'block_revision_id' => $block->getRevisionId(),
    'context_mapping' => [],
  ]
);
$section1->appendComponent($component1);
$node->get('layout_builder__layout')->appendItem($section1);

// 6. Create Section 2 (Books by Bruce views block component)
$section2 = new Section('layout_onecol');
$component2 = new SectionComponent(
  \Drupal::service('uuid')->generate(),
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
$section2->appendComponent($component2);
$node->get('layout_builder__layout')->appendItem($section2);

// 7. Create Section 3 (Recent Writing views block component)
$section3 = new Section('layout_onecol');
$component3 = new SectionComponent(
  \Drupal::service('uuid')->generate(),
  'content',
  [
    'id' => 'views_block:recent_writing-block_1',
    'label' => 'Recent Articles, Blogs & Updates',
    'label_display' => 'visible',
    'provider' => 'views',
    'views_label' => 'Recent Articles, Blogs & Updates',
    'items_per_page' => 'none',
    'context_mapping' => [],
  ]
);
$section3->appendComponent($component3);
$node->get('layout_builder__layout')->appendItem($section3);

// 8. Save the node
$node->save();
print "Homepage Layout Builder configuration successfully updated!\n";
