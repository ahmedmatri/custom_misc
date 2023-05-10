<?php

namespace Drupal\custom_misc\Twig;

use Drupal;
use Drupal\Core\Render\Element;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Template\Attribute;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Provides the Kint debugging function within Twig templates.
 */
class TwigExtension extends AbstractExtension {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * TwigExtension constructor.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   *   The plugin manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'custom_common';
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('children', [$this, 'children']),
      new TwigFilter('centrify', [$this, 'centrify']),
      new TwigFilter('spanify', [$this, 'spanify']),
      new TwigFilter('strong', [$this, 'strong']),
      new TwigFilter('bbcode', [$this, 'bbcode']),
      new TwigFilter('html_entity_decode', [$this, 'html_entity_decode']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('catch_cache', [$this, 'catchCache']),
      new TwigFunction('get_attributes', [$this, 'getAttributes']),
      new TwigFunction('get_state', [$this, 'getState']),
    ];
  }

  /**
   * Wrapper around render() for twig printed output.
   *
   * If an object is passed which does not implement __toString(),
   * RenderableInterface or toString() then an exception is thrown;
   * Other objects are casted to string. However in the case that the
   * object is an instance of a Twig_Markup object it is returned directly
   * to support auto escaping.
   *
   * If an array is passed it is rendered via render() and scalar values are
   * returned directly.
   *
   * @param mixed $arg
   *   String, Object or Render Array.
   *
   * @return mixed
   *   The rendered output or an Twig_Markup object.
   *
   * @throws \Exception
   *   When $arg is passed as an object which does not implement __toString(),
   *   RenderableInterface or toString().
   *
   * @see render
   * @see TwigNodeVisitor
   */
  public function children($element) {
    $return = [];
    if (!empty($element)) {
      foreach (Element::children($element) as $key) {
        $return[$key] = $element[$key];
      }
    }
    return $return;
  }


  /**
   * Twig filter that transforms [[text]] into <span>text</span>
   *
   * @param $element
   *
   * @return array
   */
  public function spanify($element) {
    $markup = render($element);
    $markup = (string) $markup;
    $markup = str_replace('[[', '<span>', $markup);
    $markup = str_replace(']]', '</span>', $markup);
    return ['#markup' => $markup];
  }

  /**
   * Twig filter that transforms [center]text[/center] into <span
   * class="text-center">text</span>
   *
   * @param $element
   *
   * @return array
   */
  public function centrify($element) {
    $markup = render($element);
    $markup = (string) $markup;
    $markup = str_replace('[center]', '<span class="text-center">', $markup);
    $markup = str_replace('[/center]', '</span>', $markup);
    return ['#markup' => $markup];
  }

  /**
   * Twig filter that transforms {{text}} into <strong>text</strong>
   *
   * @param $element
   *
   * @return array
   */
  public function strong($element) {
    $markup = render($element);
    $markup = (string) $markup;
    $markup = str_replace('{{', '<strong>', $markup);
    $markup = str_replace('}}', '</strong>', $markup);
    return ['#markup' => $markup];
  }

  /**
   * Twig filter that transforms {{text}} into <strong>text</strong>
   *
   * @param $element
   *
   * @return array
   */
  public function bbcode($element) {
    $markup = render($element);
    $markup = (string) $markup;
    $markup = str_replace('[br]', '<br>', $markup);
    return ['#markup' => $markup];
  }

  /**
   * html_entity_decode text
   *
   * @param $element
   *
   * @return array
   */
  public function html_entity_decode($element) {
    return html_entity_decode($element, ENT_QUOTES | ENT_HTML401);
  }

  /**
   * Adds cache metadata when rendering subcontents (not {{ content }})
   *
   * Just add {{ catch_cache(content) }} in the twig template
   *
   * @see https://www.drupal.org/project/drupal/issues/2660002
   *
   * @param array $content
   *   Rennder array
   *
   * @return mixed
   *   The rendered output.
   */

  public function catchCache($content) {
    $build = [];
    $metadata = new CacheableMetadata();

    if (!empty($content['#cache'])) {
      $cache = $content['#cache'];
      if (!empty($cache['contexts'])) {
        $metadata->addCacheContexts($cache['contexts']);
      }
      if (!empty($cache['tags'])) {
        $metadata->addCacheTags($cache['tags']);
      }
      if (!empty($cache['max-age'])) {
        $metadata->mergeCacheMaxAge($cache['max-age']);
      }
    }

    $metadata->applyTo($build);
    return $this->renderer->render($build);
  }

  /**
   * @param $field
   *
   * @return Attribute
   */
  public function getAttributes($field) {
    $attributes = (array_merge($field['#options']['attributes'], [
      'href' => $field['#url']->toString(),
      'title' => $field['#title'],
    ]));
    return (new Attribute($attributes));
  }

  /**
   * @param $field
   *
   * @return Attribute
   */
  public function getState($value) {
    return (Drupal::state()->get($value));
  }

}
