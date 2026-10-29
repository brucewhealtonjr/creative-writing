<?php

namespace Drupal\person_identity;

use Drupal\node\NodeInterface;

/** Shared formatting for titles, aliases and autocomplete-created people. */
final class Identity {

  public static function label(string $name, string $birth = '', string $death = ''): string {
    $name = trim($name);
    $birth = self::displayDate($birth);
    $death = self::displayDate($death);
    return $name . (($birth !== '' || $death !== '') ? " ($birth-$death)" : '');
  }

  public static function alias(string $name, string $birth = '', string $death = ''): string {
    // Preserve name case, underscores, parentheses and the life-date separator.
    $name = preg_replace('/[^\pL\pN _-]+/u', '', trim($name));
    $name = preg_replace('/[ _]+/u', '_', $name);
    $clean = static fn(string $date): string => preg_replace('/[^\pL\pN-]+/u', '_', trim($date));
    $birth = self::displayDate($birth);
    $death = self::displayDate($death);
    $dates = ($birth !== '' || $death !== '') ? '_(' . trim($clean($birth), '_') . '-' . trim($clean($death), '_') . ')' : '';
    return '/person/' . $name . $dates;
  }

  private static function displayDate(string $date): string {
    $date = trim($date);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? substr($date, 0, 4) : $date;
  }

  public static function normalize(NodeInterface $node): void {
    if ($node->bundle() !== 'person' || !$node->hasField('field_person_name')) {
      return;
    }
    if ($node->get('field_person_name')->isEmpty()) {
      // Core's reference widget supplies a title when creating a missing relative.
      $title = trim((string) $node->label());
      if (preg_match('/^(.+?)\s*\(([^()]*)\)$/u', $title, $match)
        && preg_match('/^((?:c\.?\s*)?\d{4}|\?*)\s*[-–]\s*((?:c\.?\s*)?\d{4}|\?*)$/u', $match[2], $dates)) {
        $node->set('field_person_name', trim($match[1]));
        $node->set('field_person_birth', trim($dates[1], '? '));
        $node->set('field_person_death', trim($dates[2], '? '));
      }
      else {
        $node->set('field_person_name', $title);
      }
    }
    $node->setTitle(self::label(
      (string) $node->get('field_person_name')->value,
      (string) $node->get('field_person_birth')->value,
      (string) $node->get('field_person_death')->value,
    ));
    if (!$node->get('field_person_death')->isEmpty()) {
      $node->set('field_person_life_status', 'deceased');
    }
  }
}
