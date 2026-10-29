<?php

namespace Drupal\person_identity\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Link-picker results with proper Drupal aliases and installation base paths. */
final class ContentSearchController extends ControllerBase {

  public function search(Request $request): JsonResponse {
    if (!in_array($request->query->get('type', 'post'), ['post', ''], TRUE)) {
      return new JsonResponse([]);
    }
    $term = mb_substr(trim((string) $request->query->get('search', '')), 0, 160);
    $limit = max(1, min(20, (int) $request->query->get('per_page', 20)));
    $query = $this->entityTypeManager()->getStorage('node')->getQuery()->accessCheck(TRUE)
      ->condition('status', 1)->sort('changed', 'DESC')->range(0, $limit);
    if ($term !== '') {
      $query->condition('title', $term, 'CONTAINS');
    }
    $results = [];
    foreach ($this->entityTypeManager()->getStorage('node')->loadMultiple($query->execute()) as $node) {
      $langcode = (string) $request->query->get('langcode', '');
      if ($langcode !== '' && $node->hasTranslation($langcode)) {
        $node = $node->getTranslation($langcode);
      }
      if (!$node->access('view')) {
        continue;
      }
      $results[] = [
        'id' => (int) $node->id(),
        'title' => $node->label(),
        'type' => $node->type->entity->label(),
        'language_id' => $node->language()->getId(),
        'url' => $node->toUrl('canonical')->toString(),
      ];
    }
    return new JsonResponse($results, 200, ['Cache-Control' => 'private, no-store']);
  }
}
