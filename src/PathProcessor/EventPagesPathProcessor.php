<?php

namespace Drupal\drupal_event\PathProcessor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Processes the inbound/outbound path using path alias lookups.
 *
 * Inspired by the subpathauto module, this is used to make Event sub-page routes
 * that rely on canonical URLs work with URL aliases.
 *
 * E.g. we defined a route for the /node/{node}/{paragraph} path. This path will
 * not work on /node-alias/paragraph-field-title-id by default. This path processor
 * will strip components from the end of the URL until the remaining path matches
 * an URL alias.
 *
 * When an URL alias is found, the canonical URL for that alias is taken and
 * combined with the stripped URL components. If this resulting URL matches a
 * route, we go to that page.
 */
class EventPagesPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * An alias manager for looking up the system path.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The path processor.
   *
   * @var \Drupal\Core\PathProcessor\InboundPathProcessorInterface
   */
  protected $pathProcessor;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Builds PathProcessor object.
   *
   * @param \Drupal\Core\PathProcessor\InboundPathProcessorInterface $pathProcessor
   *   The path processor.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\path_alias\AliasManagerInterface $aliasManager
   *   An alias manager for looking up the system path.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match manager.
   */
  public function __construct(InboundPathProcessorInterface $pathProcessor, EntityTypeManagerInterface $entityTypeManager, AliasManagerInterface $aliasManager, RouteMatchInterface $routeMatch) {
    $this->pathProcessor = $pathProcessor;
    $this->entityTypeManager = $entityTypeManager;
    $this->aliasManager = $aliasManager;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    // Rewrite /events/name/paragraph-title to /node/nid/pid.
    if (preg_match('!^\/events\/(\S*)\/(\S*)!', $path, $matches)) {
      $pathArray = explode('/', ltrim($path, '/'));
      array_splice($pathArray, 2);
      $alias = '/' . implode('/', $pathArray);
      $path = $this->aliasManager->getPathByAlias($alias);
      $nid = str_replace('/node/', '', $path);
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if (!$node instanceof Node) {
        return $alias;
      }
      // Load event elements.
      $eventPages = $node->get('field_event_elements')->referencedEntities();
      foreach ($eventPages as $eventPage) {
        if ($eventPage->get('field_title_id')->isEmpty()) {
          continue;
        }
        $label = $eventPage->get('field_title_id')->value;
        if ($label == $matches[2]) {
          $pid = $eventPage->id();
          return $path . DIRECTORY_SEPARATOR . $pid;
        }
      }
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CamelCaseParameterName)
   * @SuppressWarnings(PHPMD.CamelCaseVariableName)
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    if (!isset($options['route'])) {
      return $path;
    }
    /** @var Route $route */
    $route = $options['route'];
    if ($route->hasOption('parameters')) {
      $parameters = $route->getOption('parameters');
      if (!isset($parameters['paragraph'])) {
        return $path;
      }
      $this->processOutboundPath($path, $options, $bubbleable_metadata);
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CamelCaseParameterName)
   * @SuppressWarnings(PHPMD.CamelCaseVariableName)
   */
  protected function processOutboundPath(&$path, $options, BubbleableMetadata &$bubbleable_metadata = NULL) {
    // Rewrite /node/nid/pid to /event/name/paragraph-title.
    if (preg_match('!^\/node\/(\d*)\/(\d*)!', $path, $matches)) {
      $nid = $matches[1];
      $pid = $matches[2];
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if (!$node instanceof Node) {
        return;
      }
      $paragraph = $this->entityTypeManager->getStorage('paragraph')->load($pid);
      if (!$paragraph instanceof Paragraph) {
        return;
      }
      $alias = $this->aliasManager->getAliasByPath('/node/' . $node->id());
      if ($paragraph->get('field_title_id')->isEmpty()) {
        return;
      }
      $label = $paragraph->get('field_title_id')->value;
      $path = $alias . DIRECTORY_SEPARATOR . $label;
      if ($bubbleable_metadata) {
        $bubbleable_metadata->addCacheTags($node->getCacheTags());
      }
    }
  }

}
