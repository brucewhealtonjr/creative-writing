<?php

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/** Add optional birth and other names without prescribing a surname convention. */
function person_identity_post_update_add_other_names(&$sandbox = NULL): void {
  foreach ([
    'field_person_birth_name' => ['Birth name', 1, 'Name at birth, if different from the public Full name. Optional for any person, regardless of gender.'],
    'field_person_other_names' => ['Other names', -1, 'Other married, former, pen, or commonly used names. Keep the preferred public name in Full name.'],
  ] as $name => [$label, $cardinality, $description]) {
    if (!FieldStorageConfig::loadByName('node', $name)) {
      FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => $name, 'type' => 'string', 'cardinality' => $cardinality, 'settings' => ['max_length' => 160]])->save();
    }
    if (!FieldConfig::load('node.person.' . $name)) {
      FieldConfig::create(['entity_type' => 'node', 'bundle' => 'person', 'field_name' => $name, 'label' => $label, 'description' => $description])->save();
    }
    $display = EntityFormDisplay::load('node.person.default');
    $display->setComponent($name, ['type' => 'string_textfield', 'weight' => $name === 'field_person_birth_name' ? -14 : -13, 'settings' => ['size' => 60, 'placeholder' => '']])->save();
  }
}

/** Add distinct authorship and subject relationships to bibliographic records. */
function person_identity_post_update_add_book_people(&$sandbox = NULL): void {
  foreach (['field_book_authors' => 'Author(s)', 'field_book_subjects' => 'Subject(s)'] as $name => $label) {
    if (!FieldStorageConfig::loadByName('node', $name)) {
      FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => $name, 'type' => 'entity_reference', 'cardinality' => -1, 'settings' => ['target_type' => 'node']])->save();
    }
    foreach (['book_cover_and_description', 'external_published_books'] as $bundle) {
      if (!FieldConfig::load('node.' . $bundle . '.' . $name)) {
        FieldConfig::create([
          'entity_type' => 'node', 'bundle' => $bundle, 'field_name' => $name, 'label' => $label,
          'description' => $name === 'field_book_authors' ? 'Who wrote this work? Select a Person by name and dates. This is separate from the Drupal account that posted the page.' : 'Who is this work about? Select a Person only when that person is a subject of this particular work. Authorship alone does not establish subject matter.',
          'settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['person' => 'person'], 'sort' => ['field' => 'title', 'direction' => 'ASC'], 'auto_create' => FALSE]],
        ])->save();
      }
      $form = EntityFormDisplay::load('node.' . $bundle . '.default');
      if ($form) {
        $form->setComponent($name, ['type' => 'entity_reference_autocomplete', 'weight' => $name === 'field_book_authors' ? 15 : 16, 'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => 'Search Person names and dates']])->save();
      }
      $view = \Drupal\Core\Entity\Entity\EntityViewDisplay::load('node.' . $bundle . '.default');
      if ($view) {
        $view->setComponent($name, ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => $name === 'field_book_authors' ? 1 : 10, 'settings' => ['link' => TRUE]])->save();
      }
    }
  }
}
