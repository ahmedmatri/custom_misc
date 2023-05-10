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
use function PHPUnit\Framework\arrayHasKey;

/**
 * Hook handler for the form_alter() hook.
 */
class FormAlterHookHandler implements ContainerInjectionInterface {

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
   * Disable a content form depending on criteria.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. The arguments that
   *   \Drupal::formBuilder()->getForm() was originally called with are
   *   available in the array $form_state->getBuildInfo()['args'].
   * @param string $form_id
   *   String representing the name of the form itself. Typically, this is the
   *   name of the function that generated the form.
   */
  public function formAlter(array &$form, FormStateInterface $form_state, $form_id) {
    if ($current_group = \Drupal::service('hbku_group.helper')
      ->getActiveGroup()) {
      if (arrayHasKey('gid', $form)) {
        unset($form['gid']);
      }
    }
  }

}
