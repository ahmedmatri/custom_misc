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
use Drupal\custom_misc\Plugin\Field\FieldFormatter\MediaThumbnailFormatter;
use Drupal\user\Entity\User;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook handler for the form_alter() hook.
 */
class ParagraphHookHandler implements ContainerInjectionInterface {

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
   * @param $variables
   *
   * @return void
   */
  public function paragraphAlter(&$variables) {

    $paragraph = $variables['paragraph'];


    //    Paragraph tabs-Widget get element start --->
    if ($paragraph->hasField('field_references_revisions') && !$paragraph->field_references_revisions->isEmpty()) {
      foreach ($paragraph->field_references_revisions->referencedEntities() as $entity) {
        $elements[$entity->id()] = ($entity->hasField('field_title') && !$entity->field_title->isEmpty()) ? $entity->field_title->getstring() : NULL;
      }
      $variables['children'] = $elements;
    }
    //--->   Paragraph tabs-Widget get element end -


    //    Automatic select item inject start -->
    if ($paragraph->hasField('field_select') && $paragraph->field_select->getString() == 0) {
      $view = Views::getView($paragraph->bundle());
      if ($view) {
        $view->setDisplay('block');
        $variables['view'] = $view->render();
      }
    }
    //--->   Automatic select item inject start -

  }

}
