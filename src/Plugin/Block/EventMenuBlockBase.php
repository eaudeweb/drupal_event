<?php

namespace Drupal\drupal_event\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\drupal_event\Services\EventBaseUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base block implementation that most event menus plugins will extend.
 */
abstract class EventMenuBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The EventBaseUtils service.
   *
   * @var \Drupal\drupal_event\Services\EventBaseUtils
   */
  protected $eventUtils;

  /**
   * The current node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node = NULL;

  /**
   * The current paragraph.
   *
   * @var \Drupal\paragraphs\Entity\Paragraph
   */
  protected $paragraph = NULL;

  /**
   * The Event Menu constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match manager.
   * @param \Drupal\drupal_event\Services\EventBaseUtils $eventBaseUtils
   *   The EventBaseUtils service.
   */
  public function __construct(array $configuration, $pluginId, $pluginDefinition, RouteMatchInterface $routeMatch, EventBaseUtils $eventBaseUtils) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->routeMatch = $routeMatch;
    if (in_array($this->routeMatch->getRouteName(), ['entity.node.canonical', 'entity.node.event_page'])) {
      $this->node = $this->routeMatch->getParameter('node');
      $this->paragraph = $this->routeMatch->getParameter('paragraph');
    }
    $this->eventUtils = $eventBaseUtils;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('current_route_match'),
      $container->get('event.utils'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function build() {
    $build = [];

    // When Layout builder tried to render the block.
    if (!$this->node) {
      return $build;
    }

    $display = [
      'label' => 'hidden',
      'type' => 'entity_reference_revisions_entity_view',
      'settings' => [
        'view_mode' => 'preview',
        'link' => '',
      ],
    ];
    // Get paragraphs (like Schedule, Speakers, HTML) if exists.
    if (!$this->node->get('field_event_elements')->isEmpty()) {
      $elements = $this->node->get('field_event_elements')->view($display);
      foreach (Element::children($elements) as $delta) {
        /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
        $paragraph = $this->node->get('field_event_elements')->get($delta)->entity;
        // Do not render if user checked "Hide this tab in public event page".
        if ($this->hideParagraph($paragraph)) {
          continue;
        }
        $build['elements'][$delta] = $elements[$delta];
        // Add an extra button for using "entity.node.event_page".
        $build['elements'][$delta]['button'] = $this->generateRoute($elements[$delta]['#paragraph']);
      }
    }

    return $build;
  }

  /**
   * Builds a renderable array for a fully themed field.
   *
   * @param string $title
   *   Field title.
   * @param string $fieldName
   *   Field name.
   * @param string $entityType
   *   Field type (link, string, etc.).
   * @param array $items
   *   Array with items.
   *
   * @return array
   *   The definition of the field ready to be rendered.
   */
  protected function getFieldDefinition(string $title, string $fieldName, string $entityType = 'link', array $items = []) {
    return [
      'title' => $title,
      'label' => 'hidden',
      'view_mode' => 'preview',
      'field_name' => $fieldName,
      'field_type' => 'entity_reference',
      'entity_type' => $entityType,
      'formatter' => '',
      'is_multiple' => FALSE,
      'items' => $items,
      'attributes' => ['class' => []],
    ];
  }

  /**
   * Renders an element.
   *
   * @param array $fieldDefinition
   *   The definition of the field to which the formatter is associated.
   *
   * @return array
   *   The rendered element.
   */
  protected function renderField(array $fieldDefinition) {
    $element = [
      '#theme' => 'field',
      '#title' => $fieldDefinition['title'],
      '#label_display' => $fieldDefinition['label'],
      '#view_mode' => $fieldDefinition['view_mode'],
      '#language' => 'en',
      '#field_name' => $fieldDefinition['field_name'],
      '#field_type' => $fieldDefinition['field_type'],
      '#entity_type' => $fieldDefinition['entity_type'],
      '#bundle' => $this->node->bundle(),
      '#object' => $this->node,
      '#formatter' => $fieldDefinition['formatter'],
      '#is_multiple' => FALSE,
      '#third_party_settings' => [],
      '#attributes' => $fieldDefinition['attributes'],
    ];
    return array_merge($element, $fieldDefinition['items']);
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  protected function generateButton($fragment) {
    return [
      '#type' => 'link',
      '#title' => $this->t('find out more'),
      '#attributes' => ['class' => ['btn', 'btn-primary', 'stretched-link']],
      '#url' => Url::fromRoute('entity.node.canonical', ['node' => $this->node->id()], [
        'query' => [],
        'fragment' => $fragment,
      ]),
    ];
  }

  /**
   * Generate route for each sub-page.
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  protected function generateRoute(Paragraph $paragraph) {
    return [
      '#type' => 'link',
      '#title' => $paragraph->get('field_title')->value,
      '#attributes' => ['class' => [
        'btn',
        $this->paragraph instanceof Paragraph && $this->paragraph->id() == $paragraph->id() ? 'btn-primary' : 'btn-outline-primary',
      ]],
      '#url' => Url::fromRoute('entity.node.event_page', [
        'node' => $this->node->id(),
        'paragraph' => $paragraph->id(),
      ]),
    ];
  }

  /**
   * Verify if the paragraph needs to be hidden in the event page.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The paragraph.
   *
   * @return bool
   *   TRUE if the paragraph needs to be hidden, FALSE otherwise.
   */
  protected function hideParagraph(Paragraph $paragraph) {
    if (!$paragraph->hasField('field_hide_content')) {
      return FALSE;
    }
    if (!$paragraph->get('field_hide_content')->isEmpty()
      && $paragraph->get('field_hide_content')->value) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $cache = new Cache();
    return $cache::mergeContexts(parent::getCacheContexts(), ['url']);
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIf(
      $this->node instanceof Node
      && $this->node->bundle() == 'event'
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function isEventHomepage() {
    return $this->routeMatch->getRouteName() == 'entity.node.canonical';
  }

}
