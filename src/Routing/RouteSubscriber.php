<?php

/**
 * @file
 * Contains \Drupal\empty_front_page\Routing\RouteSubscriber.
 */

namespace Drupal\custom_misc\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber to alter translation route
 * Users need update permissions to translate content.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    $admin_routes = [
      'view.group_members.page_1',
      'view.group_nodes.page_1',
      'view.group_groups.page_1',
      'view.group_media.page',
      'view.group_media.documents',
      'view.media_library.group_documents',
      'view.media_library.group_media',
			'entity.group.menu'
    ];
    foreach ($admin_routes as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setOption('_admin_route', true);
      }
    }

  }

  /**
   * {@inheritdoc}
   */
    public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // Ensure to run after the content_translation route subscriber.
    // @see \Drupal\content_translation\Routing\ContentTranslationRouteSubscriber
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -215];
    return $events;
  }

}
