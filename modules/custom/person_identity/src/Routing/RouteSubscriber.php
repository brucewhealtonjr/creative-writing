<?php

namespace Drupal\person_identity\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

final class RouteSubscriber extends RouteSubscriberBase {
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('gutenberg.content.search')) {
      $route->setDefault('_controller', '\Drupal\person_identity\Controller\ContentSearchController::search');
    }
  }
}
