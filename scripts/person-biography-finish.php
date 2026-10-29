<?php

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\redirect\Entity\Redirect;

$id = (int) \Drupal::state()->get('person_identity.bruce_node');
$person = Node::load($id);
if (!$person || !$person->isPublished()) {
  throw new \RuntimeException('Published and verified Person required before redirecting.');
}
$body = $person->get('body')->value;
$body = str_replace('<p>For a more comprehensive family tree with historical context, genealogical data, and extended family connections, see the <a href="/creative-writing/family-tree/">Family Tree</a> page.</p>', '<p>For more family history and genealogical connections, see <a href="https://familypedia.fandom.com/wiki/Bruce_Whealton_Jr_(1966-)">my Familypedia profile</a>.</p>', $body);
if ($body !== $person->get('body')->value) {
  $person->setNewRevision(TRUE);
  $person->get('body')->value = $body;
  $person->setRevisionLogMessage('Replace the inherited broken Family Tree link with the supplied Familypedia profile.');
  $person->save();
}
foreach (['page/about-bruce-whealton', 'node/433'] as $source) {
  $ids = \Drupal::entityQuery('redirect')->accessCheck(FALSE)->condition('redirect_source.path', $source)->execute();
  $redirect = $ids ? Redirect::load(reset($ids)) : Redirect::create(['language' => 'en']);
  $redirect->setSource($source);
  $redirect->set('redirect_redirect', [['uri' => 'entity:node/' . $id]]);
  $redirect->setStatusCode(301);
  $redirect->save();
  echo 'Redirected ' . $source . ' to Person ' . $id . ".\n";
}
$menu = MenuLinkContent::load(34);
if ($menu && $menu->get('link')->uri === 'entity:node/433') {
  $menu->set('link', ['uri' => 'entity:node/' . $id]);
  $menu->save();
  echo "Updated About navigation.\n";
}
$manager = \Drupal::service('simple_sitemap.entity_manager')->setSitemaps(['default', 'index']);
$manager->setEntityInstanceSettings('node', '433', ['index' => FALSE]);
$manager->setEntityInstanceSettings('node', (string) $id, ['index' => TRUE, 'priority' => '0.8']);
$manager->setEntityInstanceSettings('node', (string) \Drupal::state()->get('person_identity.father_node'), ['index' => TRUE]);
echo "Updated sitemap inclusion.\n";
