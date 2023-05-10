<?php

namespace Drupal\custom_misc\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entitygroupfield\Plugin\Field\FieldWidget\EntityGroupFieldWidgetBase;

/**
 * Plugin implementation of the 'entitygroupfield_select_widget' widget.
 *
 * @FieldWidget(
 *   id = "custom_entitygroupfield_select_widget",
 *   label = @Translation("Custom group select"),
 *   field_types = {
 *     "entitygroupfield"
 *   }
 * )
 */
class CustomEntityGroupFieldSelectWidget extends EntityGroupFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  protected function buildAddElement($entity_plugin_id, array $existing_gcontent) {
    // Get the list of all allowed groups, given the circumstances.
    $allowed_groups = $this->getAllowedGroups($entity_plugin_id, $existing_gcontent);

    // If there are no available groups, don't build a form element.
    if (empty($allowed_groups)) {
      return [];
    }

    return [
      '#type' => 'select',
      '#title' => $this->getSetting('label'),
      '#description' => $this->getSetting('help_text'),
      '#options' => $allowed_groups,
      '#empty_option' => $this->t('- Select a group -'),
      '#empty_value' => '',
    ];
  }

  /**
   * Gets a list of groups with a specific plugin installed.
   *
   * @param string $plugin_id
   *   The plugin ID to filter the groups.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   The list of group entities.
   */
  protected function getPluginGroups($plugin_id) {
    $group_types = $this->getPluginGroupTypes($plugin_id);
    return empty($group_types) ? [] : $this->entityTypeManager
      ->getStorage('group')->loadByProperties(['type' => $group_types]);
  }

  /**
   * Gets allowed group options for a select form element.
   *
   * @param string $entity_plugin_id
   *   The plugin ID to get existing content.
   * @param array $existing_gcontent
   *   The existing group content.
   *
   * @return array
   *   Allowed groups options using optgroup for the group types.
   */
  protected function getAllowedGroups($entity_plugin_id, array $existing_gcontent) {
    $groups = $this->getPluginGroups($entity_plugin_id);
    // If there are no groups with the plugin enabled, return early.
    if (empty($groups)) {
      return [];
    }

    $allowed_groups = [];
    $all_restricted = TRUE;
    /** @var \Drupal\Core\Session\AccountInterface $account */
    $account = $this->currentUser->getAccount();

    $excluded_groups = [];
    if ($existing_gcontent) {
      foreach ($existing_gcontent as $gcontent) {
        // Do not count the content if it was removed.
        if ($gcontent['mode'] == 'removed') {
          continue;
        }
        if (isset($gcontent['entity'])) {
          $excluded_groups[] = $gcontent['entity']->gid->getString();
        }
      }
    }

    /** @var \Drupal\group\Entity\Group $group */
    foreach ($groups as $group) {
      if (in_array($group->id(), $excluded_groups)) {
        continue;
      }
      // Check creation permissions.
      $can_create = FALSE;
      if ($entity_plugin_id == 'group_membership') {
        $can_create = $group->hasPermission("administer members", $account);
      }
      if (!$can_create) {
        $can_create = $group->hasPermission("create $entity_plugin_id entity", $account);
      }
      if ($can_create) {
        $all_restricted = FALSE;
        $group_bundle = $group->bundle();
        $group_bundle_label = $group->getGroupType()->label();
        $allowed_groups[$group_bundle_label][$group->id()] = $this->entityRepository->getTranslationFromContext($group)->label();
      }
    }

    return $allowed_groups;
  }


  /**
   * {@inheritdoc}
   *
   * @see \Drupal\content_translation\Controller\ContentTranslationController::prepareTranslation()
   *   Uses a similar approach to populate a new translation.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $account = $this->currentUser->getAccount();
    $field_name = $this->fieldDefinition->getName();
    $parents = $element['#field_parents'];
    $info = [];

    $gcontent_entity = NULL;
    $host = $items->getEntity();
    $widget_state = static::getWidgetState($parents, $field_name, $form_state);

    $target_type = $this->getFieldSetting('target_type');

    $item_mode = isset($widget_state['gcontent'][$delta]['mode']) ? $widget_state['gcontent'][$delta]['mode'] : 'edit';

    $show_must_be_saved_warning = !empty($widget_state['gcontent'][$delta]['show_warning']);

    if (isset($widget_state['gcontent'][$delta]['entity'])) {
      $gcontent_entity = $widget_state['gcontent'][$delta]['entity'];
    } elseif (isset($items[$delta]->entity)) {
      $gcontent_entity = $items[$delta]->entity;
      // We don't have a widget state yet, get from selector settings.
      $item_mode = isset($widget_state['gcontent'][$delta]['mode']) ? $widget_state['gcontent'][$delta]['mode'] : 'closed';
    } elseif (isset($widget_state['selected_bundle'])) {
      $entity_type = $this->entityTypeManager->getDefinition($target_type);
      $bundle_key = $entity_type->getKey('bundle');

      $gcontent_entity = $this->entityTypeManager->getStorage($target_type)->create([
        $bundle_key => $widget_state['selected_bundle'],
        'gid' => $widget_state['selected_group'],
      ]);
      $item_mode = 'edit';
    }

    if ($item_mode == 'collapsed') {
      $item_mode = 'closed';
    }

    if ($item_mode == 'closed') {
      // Validate closed gcontent and expand if needed.
      // @todo Consider recursion.
      $violations = $gcontent_entity->validate();
      $violations->filterByFieldAccess();
      if (count($violations) > 0) {
        $item_mode = 'edit';
        $messages = [];
        foreach ($violations as $violation) {
          $messages[] = $violation->getMessage();
        }
        $info['validation_error'] = [
          '#type' => 'container',
          '#markup' => $this->t('@messages', ['@messages' => strip_tags(implode('\n', $messages))]),
          '#attributes' => ['class' => ['messages', 'messages--warning']],
        ];
      }
    }

    if ($gcontent_entity) {
      $group = $gcontent_entity->getGroup();
      $entity_plugin_id = isset($widget_state['entity_plugin_id']) ? $widget_state['entity_plugin_id'] : $gcontent_entity->getContentPlugin()->getPluginId();
      $element_parents = $parents;
      $element_parents[] = $field_name;
      $element_parents[] = $delta;
      $element_parents[] = 'subform';

      $id_prefix = implode('-', array_merge($parents, [$field_name, $delta]));
      $wrapper_id = Html::getUniqueId($id_prefix . '-item-wrapper');

      $element += [
        '#type' => 'container',
        '#element_validate' => [[$this, 'elementValidate']],
        '#gcontent_type' => $gcontent_entity->bundle(),
        'subform' => [
          '#type' => 'container',
          '#parents' => $element_parents,
        ],
      ];

      // Setting label if field is not multiple.
      if (!$this->getSetting('multiple')) {
        $element['label'] = [
          '#type' => 'label',
          '#title' => $this->fieldDefinition->getLabel(),
          '#weight' => -1000,
        ];
      }

      $element['#prefix'] = '<div id="' . $wrapper_id . '">';
      $element['#suffix'] = '</div>';

      // Check permissions.
      if ($entity_plugin_id == 'group_membership') {
        if ($items->getEntity()->id() == $account->id()) {
          $can_delete = $group->hasPermission("leave group", $account);
          $can_edit = $group->hasPermission("update own group_membership content", $account);
        } else {
          $can_delete = $group->hasPermission("administer members", $account);
          $can_edit = $group->hasPermission("administer members", $account);
        }
      } else {
        $can_delete = $host->isNew() ? FALSE : $group->hasPermission("delete any $entity_plugin_id content", $account);
        $can_edit = $group->hasPermission("update any $entity_plugin_id content", $account);
      }
      // Checking if can delete own.
      if (!$can_delete && $entity_plugin_id == 'group_membership') {
        if ($gcontent_entity->id() == $account->id()) {
          $can_delete = $group->hasPermission("leave group", $account);
        }
      }
      if (!$can_delete && $gcontent_entity->getOwnerId() == $account->id()) {
        $can_delete = $group->hasPermission("delete own $entity_plugin_id content", $account);
      }
      // Checking if can update own.
      if (!$can_edit) {
        $group_content_owner = $gcontent_entity->getOwnerId();
        // In case of membership the value to compare is the entity
        // instead owner.
        if ($entity_plugin_id == 'group_membership') {
          $group_content_owner = $gcontent_entity->getEntity()->id();
        }
        if ($group_content_owner == $account->id()) {
          $can_edit = $group->hasPermission("update own $entity_plugin_id content", $account);
        }
      }

      $item_bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($target_type);
      if (isset($item_bundles[$gcontent_entity->bundle()])) {
        $element['top'] = [
          '#type' => 'container',
          '#weight' => -500,
          '#attributes' => [
            'class' => [
              'gcontent-type-top',
            ],
          ],
        ];

        $element['top']['gcontent_type_title'] = [
          '#type' => 'container',
          '#weight' => 0,
          '#attributes' => [
            'class' => [
              'gcontent-type-title',
            ],
          ],
        ];

        $element['top']['gcontent_type_title']['info'] = [
          '#markup' => $gcontent_entity->getGroup()->label(),
        ];

        $actions = [];
        $links = [];

        // Hide the button when translating.
        if ($item_mode != 'remove') {
          $links['remove_button'] = [
            '#type' => 'submit',
            '#value' => $this->t('Remove'),
            '#name' => strtr($id_prefix, '-', '_') . '_remove',
            '#weight' => 501,
            '#submit' => [[get_class($this), 'gcontentItemSubmit']],
            '#limit_validation_errors' => [
              array_merge($parents, [$field_name, 'add_more']),
            ],
            '#delta' => $delta,
            '#ajax' => [
              'callback' => [get_class($this), 'itemAjax'],
              'wrapper' => $widget_state['ajax_wrapper_id'],
              'effect' => 'fade',
            ],
            '#access' => $can_delete,
            '#prefix' => '<li class="remove dropbutton__item dropbutton__item--extrasmall">',
            '#suffix' => '</li>',
            '#gcontent_mode' => 'remove',
          ];

        }

        if ($item_mode == 'edit') {

          if (isset($items[$delta]->entity)) {
            $links['collapse_button'] = [
              '#type' => 'submit',
              '#value' => $this->t('Collapse'),
              '#name' => strtr($id_prefix, '-', '_') . '_collapse',
              '#weight' => 499,
              '#submit' => [[get_class($this), 'gcontentItemSubmit']],
              '#delta' => $delta,
              '#limit_validation_errors' => [
                array_merge($parents, [$field_name, 'add_more']),
              ],
              '#ajax' => [
                'callback' => [get_class($this), 'itemAjax'],
                'wrapper' => $widget_state['ajax_wrapper_id'],
                'effect' => 'fade',
              ],
              '#access' => $can_edit,
              '#prefix' => '<li class="collapse dropbutton__item dropbutton__item--extrasmall">',
              '#suffix' => '</li>',
              '#gcontent_mode' => 'collapsed',
              '#gcontent_show_warning' => TRUE,
            ];
          }

          $info['remove_button_info'] = [
            '#type' => 'container',
            '#markup' => $this->t('You are not allowed to remove this item.'),
            '#attributes' => ['class' => ['messages', 'messages--warning']],
            '#access' => !$can_delete,
          ];
        } elseif ($item_mode == 'closed') {
          $links['edit_button'] = [
            '#type' => 'submit',
            '#value' => $this->t('Edit'),
            '#name' => strtr($id_prefix, '-', '_') . '_edit',
            '#weight' => 500,
            '#submit' => [[get_class($this), 'gcontentItemSubmit']],
            '#limit_validation_errors' => [
              array_merge($parents, [$field_name, 'add_more']),
            ],
            '#delta' => $delta,
            '#ajax' => [
              'callback' => [get_class($this), 'itemAjax'],
              'wrapper' => $widget_state['ajax_wrapper_id'],
              'effect' => 'fade',
            ],
            '#access' => $can_edit,
            '#prefix' => '<li class="edit dropbutton__item dropbutton__item--extrasmall">',
            '#suffix' => '</li>',
            '#gcontent_mode' => 'edit',
          ];

          if ($show_must_be_saved_warning) {
            $info['must_be_saved_info'] = [
              '#type' => 'container',
              '#markup' => $this->t('You have unsaved changes on this item.'),
              '#attributes' => ['class' => ['messages', 'messages--warning']],
            ];
          }

          $info['edit_button_info'] = [
            '#type' => 'container',
            '#markup' => $this->t('You are not allowed to edit this item.'),
            '#attributes' => ['class' => ['messages', 'messages--warning']],
            '#access' => !$can_edit && $can_delete,
          ];

          $info['remove_button_info'] = [
            '#type' => 'container',
            '#markup' => $this->t('You are not allowed to remove this item.'),
            '#attributes' => ['class' => ['messages', 'messages--warning']],
            '#access' => !$can_delete && $can_edit,
          ];

          $info['edit_remove_button_info'] = [
            '#type' => 'container',
            '#markup' => $this->t('You are not allowed to edit or remove this item.'),
            '#attributes' => ['class' => ['messages', 'messages--warning']],
            '#access' => !$can_edit && !$can_delete,
          ];
        } elseif ($item_mode == 'remove') {

          $element['top']['gcontent_type_title']['info'] = [
            '#markup' => $this->t('Deleted: %group relation', ['%group' => $gcontent_entity->getGroup()->label()]),
          ];

          $links['confirm_remove_button'] = [
            '#type' => 'submit',
            '#value' => $this->t('Confirm removal'),
            '#name' => strtr($id_prefix, '-', '_') . '_confirm_remove',
            '#weight' => 503,
            '#submit' => [[get_class($this), 'gcontentItemSubmit']],
            '#limit_validation_errors' => [
              array_merge($parents, [$field_name, 'add_more']),
            ],
            '#delta' => $delta,
            '#ajax' => [
              'callback' => [get_class($this), 'itemAjax'],
              'wrapper' => $widget_state['ajax_wrapper_id'],
              'effect' => 'fade',
            ],
            '#prefix' => '<li class="confirm-remove dropbutton__item dropbutton__item--extrasmall">',
            '#suffix' => '</li>',
            '#gcontent_mode' => 'removed',
          ];

          $links['restore_button'] = [
            '#type' => 'submit',
            '#value' => $this->t('Restore'),
            '#name' => strtr($id_prefix, '-', '_') . '_restore',
            '#weight' => 504,
            '#submit' => [[get_class($this), 'gcontentItemSubmit']],
            '#limit_validation_errors' => [
              array_merge($parents, [$field_name, 'add_more']),
            ],
            '#delta' => $delta,
            '#ajax' => [
              'callback' => [get_class($this), 'itemAjax'],
              'wrapper' => $widget_state['ajax_wrapper_id'],
              'effect' => 'fade',
            ],
            '#prefix' => '<li class="restore dropbutton__item dropbutton__item--extrasmall">',
            '#suffix' => '</li>',
            '#gcontent_mode' => 'edit',
          ];
        }
        if (count($links)) {
          $show_links = 0;
          foreach ($links as $link_item) {
            if (!isset($link_item['#access']) || $link_item['#access']) {
              $show_links++;
            }
          }
          if ($show_links > 0) {

            $element['top']['links'] = $links;
            if ($show_links > 1) {
              $element['top']['links']['#theme_wrappers'] = ['dropbutton_wrapper', 'entitygroupfield_dropbutton_wrapper'];
              $element['top']['links']['prefix'] = [
                '#markup' => '<ul class="dropbutton dropbutton--multiple dropbutton--extrasmall dropbutton--gin">',
                '#weight' => -999,
              ];
              $element['top']['links']['suffix'] = [
                '#markup' => '</li>',
                '#weight' => 999,
              ];
            } else {
              $element['top']['links']['#theme_wrappers'] = ['entitygroupfield_dropbutton_wrapper'];
              foreach ($links as $key => $link_item) {
                unset($element['top']['links'][$key]['#prefix']);
                unset($element['top']['links'][$key]['#suffix']);
              }
            }
            $element['top']['links']['#weight'] = 2;
          }
        }

        if (count($info)) {
          $show_info = FALSE;
          foreach ($info as $info_item) {
            if (!isset($info_item['#access']) || $info_item['#access']) {
              $show_info = TRUE;
              break;
            }
          }

          if ($show_info) {
            $element['info'] = $info;
            $element['info']['#weight'] = 998;
          }
        }

        if (count($actions)) {
          $show_actions = FALSE;
          foreach ($actions as $action_item) {
            if (!isset($action_item['#access']) || $action_item['#access']) {
              $show_actions = TRUE;
              break;
            }
          }

          if ($show_actions) {
            $element['actions'] = $actions;
            $element['actions']['#type'] = 'actions';
            $element['actions']['#weight'] = 999;
          }
        }
      }

      $display = EntityFormDisplay::collectRenderDisplay($gcontent_entity, $this->getSetting('form_display_mode'));

      if ($item_mode == 'edit') {
        $display->buildForm($gcontent_entity, $element['subform'], $form_state);
        // Fixing subform pathauto states.
        if (isset($element['subform']['path']['widget'][0]['pathauto'])) {
          $selector = sprintf('input[name="%s[%d][subform][path][0][%s]"]', $field_name, $element['#delta'], 'pathauto');
          $element['subform']['path']['widget'][0]['alias']['#states'] = [
            'disabled' => [
              $selector => ['checked' => TRUE],
            ],
          ];
        }
      } else {
        $element['subform'] = [];
      }
      $element['subform']['entity_id']['#access'] = FALSE;
      $element['subform']['#attributes']['class'][] = 'gcontent-subform';
      $element['subform']['#access'] = $can_edit;

      if ($item_mode == 'removed') {
        $element['#access'] = FALSE;
      }

      $widget_state['gcontent'][$delta]['entity'] = $gcontent_entity;
      $widget_state['gcontent'][$delta]['display'] = $display;
      $widget_state['gcontent'][$delta]['mode'] = $item_mode;

      static::setWidgetState($parents, $field_name, $form_state, $widget_state);
    } else {
      $element['#access'] = FALSE;
    }
    return $element;
  }

}
