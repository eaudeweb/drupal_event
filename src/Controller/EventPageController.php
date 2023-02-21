<?php

namespace Drupal\drupal_event\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\drupal_event\Services\EventBaseUtils;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for an event sub-page.
 */
class EventPageController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event utils.
   *
   * @var \Drupal\drupal_event\Services\EventBaseUtils
   */
  protected $eventUtils;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructor for Event Sub-page controller.
   */
  public function __construct(EventBaseUtils $eventUtils, RouteMatchInterface $routeMatch) {
    $this->entityTypeManager = $this->entityTypeManager();
    $this->eventUtils = $eventUtils;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event.utils'),
      $container->get('current_route_match')
    );
  }

  /**
   * Function used to render an empty page, usually filled with blocks.
   *
   * @return array
   *   The render array.
   */
  public function blankPage(Node $node) {
    return [];
  }

  /**
   * @param \Drupal\node\Entity\Node $node
   *   The node type.
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   */
  public function renderEventPage(Node $node, Paragraph $paragraph) {
    return [
      '#theme' => 'event__sub_page__full',
      '#attributes' => [
        'class' => ['event--element', 'event--sub-page']
      ],
      '#items' => [
        $this->entityTypeManager->getViewBuilder('node')->view($node, 'info'),
        $this->entityTypeManager->getViewBuilder('paragraph')->view($paragraph, 'event_page'),
      ],
    ];
  }

  /**
   * Route title callback.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node.
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *
   * @return string|null
   *   The title for the route page.
   */
  public function title(Node $node, Paragraph $paragraph) {
    // Display Abbreviation if the event has the field.
    if ($node->hasField('field_abbreviation')
      && !$node->get('field_abbreviation')->isEmpty()) {
      return $node->get('field_abbreviation')->value;
    }
    return $node->label();
  }

  /**
   * Checks access for a hidden paragraph.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    $paragraph = $this->routeMatch->getParameter('paragraph');
    $hide = ($paragraph instanceof Paragraph &&
      $paragraph->hasField('field_hide_content')
      && !$paragraph->get('field_hide_content')->isEmpty()
      && $paragraph->get('field_hide_content')->value);
    return AccessResult::allowedIf(
      ($account->hasPermission('access content') && !$hide)
      || $account->hasPermission('access content overview')
    );
  }

}
