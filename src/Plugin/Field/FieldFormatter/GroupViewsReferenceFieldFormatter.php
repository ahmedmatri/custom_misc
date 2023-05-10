<?php

namespace Drupal\custom_misc\Plugin\Field\FieldFormatter;

use Drupal\views\Views;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\viewsreference\Plugin\Field\FieldFormatter\ViewsReferenceFieldFormatter;


/**
 * Field formatter for Viewsreference Field.
 *
 * @FieldFormatter(
 *   id = "group_viewsreference_formatter",
 *   label = @Translation("Group Views Reference"),
 *   field_types = {"viewsreference"}
 * )
 */
class GroupViewsReferenceFieldFormatter extends ViewsReferenceFieldFormatter {


  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $elements = [];

    foreach ($items as $delta => $item) {
      $view_name = $item->getValue()['target_id'];
      $display_id = $item->getValue()['display_id'];
      $argument = $item->getValue()['argument'];
      $title = $item->getValue()['title'];
      $view = Views::getView($view_name);
      // Someone may have deleted the View.
      if (!is_object($view)) {
        continue;
      }
      // No access.
      if (!$view->access($display_id)) {
        continue;
      }

      $view->setDisplay($display_id);

      if ($argument) {
        $view->element['#cache']['keys'][] = $argument;
        $arguments = [$argument];
        if (preg_match('/\//', $argument)) {
          $arguments = explode('/', $argument);
        }

        $node = \Drupal::routeMatch()->getParameter('node');
        $token_service = \Drupal::token();
        if (is_array($arguments)) {
          foreach ($arguments as $index => $argument) {
            if (!empty($token_service->scan($argument))) {
              $arguments[$index] = $token_service->replace($argument, ['node' => $node]);
            }
          }
        }

        $view->setArguments($arguments);
      }

      $view->preExecute();
      $view->execute($display_id);

      if ($title) {
        $title = $view->getTitle();
        $title_render_array = [
          '#theme' => $view->buildThemeFunctions('viewsreference__view_title'),
          '#title' => $title,
          '#view' => $view,
        ];
      }

      if ($this->getSetting('plugin_types')) {
        if ($title) {
          $elements[$delta]['title'] = $title_render_array;
        }
      }

      $elements[$delta]['contents'] = $view->buildRenderable($display_id);
    }

    return $elements;
  }

}
