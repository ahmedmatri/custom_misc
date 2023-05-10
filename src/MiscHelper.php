<?php

namespace Drupal\custom_misc;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupContentType;
use Drupal\group\Entity\GroupInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Cache\Cache;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountProxyInterface;

/**
 *
 * Class MiscHelper.
 */
class MiscHelper {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * DruSymfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;


  /**
   * Constructs a new MiscHelper object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrentRouteMatch $current_route_match, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentRouteMatch = $current_route_match;
    $this->requestStack = $request_stack;
  }

  /**
   * Get the URL for the media thumbnail.
   *
   * @param \Drupal\media\MediaInterface $media
   * @param String $image_style
   *
   * @return String|null
   */
  function getMediaThumbnailUrl(MediaInterface $media, string $image_style = 'default'): string|null {
    if (!$media) {
      return NULL;
    }
    if ($media->bundle() == 'remote_video') {
      return ($media->field_media_oembed_video->getString());
    }
    $fid = $media->getSource()->getSourceFieldValue($media);
    $file = \Drupal\file\Entity\File::load($fid);
    if ($file->getFileUri()) {

      if ($style = ImageStyle::load($image_style)) {
        $url = ($style->buildUrl($file->getFileUri()));
      }
      else {
        $url = \Drupal::service('file_url_generator')
          ->generateAbsoluteString($file->getFileUri());
      }
      return ($url);
    }
  }

}
