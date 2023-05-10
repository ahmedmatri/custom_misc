<?php

declare(strict_types=1);

namespace Drupal\custom_misc\HookHandler;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_share_client\Service\StateInformationInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook handler for the form_alter() hook.
 */
class WidgetParagraphsAlterHookHandler implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The machine name of the locked policy.
   */
  const LOCKED_POLICY = 'locked';

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The state information service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $stateInformation;


  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\entity_share_client\Service\StateInformationInterface $stateInformation
   *   The state information service.
   */
  public function __construct(
    MessengerInterface $messenger,
    AccountInterface   $current_user,
  ) {
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('current_user'),
    );
  }


  /**
   * Conditional field state api
   *
   * @param $element
   * @param $form_state
   * @param $context
   *
   * @return void/
   */
  public function widgetAlter(&$element, &$form_state, $context) {
    if (!in_array($element['#paragraph_type'], ['event', 'news'])) {
      return;
    }
    $field_name = 'field_references';
    $this->_set_state($element, $field_name, 'invisible', ["value" => '0',]);
  }

  /**
   * @param $element
   * @param $field_name
   * @param $state_key
   * @param array $conditions
   *
   * @return void
   */
  function _set_state(&$element, $field_name, $state_key, array $conditions) {
    if (!isset($element['subform'][$field_name])) {
      return;
    }
    $variation_field = $element['subform']['field_select'];

    // construct variation input name to use it in #states
    $variation_input_name = array_shift($variation_field['widget']['#parents']);
    $variation_input_name .= '[' . implode('][', $variation_field['widget']['#parents']) . ']';
    $element['subform'][$field_name]['#states'][$state_key][':input[name="' . $variation_input_name . '"]'][] = $conditions;

  }

}
