<?php

namespace Drupal\drupal_event\Services;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\views\Views;

/**
 * Provides a base class with common methods.
 *
 * @package Drupal\EDW
 */
class EventBaseUtils {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /***
   * The EventBaseUtils constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityRepositoryInterface $entityRepository) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityRepository = $entityRepository;
  }

  /**
   * Loads an entity by UUID.
   *
   * @param string $entityType
   *   The entity type ID to load from.
   * @param string $uuid
   *   The UUID of the entity to load.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity object, or NULL if there is no entity with the given UUID.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown in case the requested entity type does not support UUIDs.
   */
  public function loadEntityByUuid($entityType, $uuid) {
    return $this->entityRepository->loadEntityByUuid($entityType, $uuid);
  }

  /**
   * Builds the render array for the provided block.
   *
   * @param \Drupal\block_content\Entity\BlockContent $block
   *   The block to render.
   *
   * @return array|null
   *   A render array for the entity.
   */
  public function renderBlock(BlockContent $block) {
    return $this->entityTypeManager->getViewBuilder('block_content')->view($block);
  }

  /**
   * Return Document Type name for a Document.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node entity.
   *
   * @return null|string
   *   The document type name, NULL if the field is empty.
   */
  public function getDocumentType(Node $node) {
    return ($node->hasField('field_document_type')
      && !$node->get('field_document_type')->isEmpty()) ? $node->get('field_document_type')->entity->getName() : NULL;
  }

  /**
   * Loads a view and builds the render array for the given display.
   *
   * @param string $viewId
   *   The view ID to load.
   * @param string $displayId
   *   The ID of the display to mark as current.
   * @param array $args
   *   The arguments passed to the view.
   *
   * @return array|null
   *   A renderable array with #type 'view' or NULL if the display ID was
   *   invalid.
   */
  public function renderView(string $viewId, string $displayId, array $args = []) {
    $view = new Views();
    $view = $view->getView($viewId);
    if (is_object($view)) {
      $view->setArguments($args);
      $view->setDisplay($displayId);
      $view->preExecute();
      $view->execute();

      return $view->buildRenderable($displayId, $args);
    }
    return NULL;
  }

  /**
   * Get documents for an event.
   *
   * @param int $nid
   *   The node id.
   *
   * @return array|int
   *   An array with documents related with this node.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEventDocuments(int $nid) {
    return $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck()
      ->condition('type', ['document'], 'IN')
      ->condition('field_events', $nid, 'IN')
      ->condition('status', 1)
      ->execute();
  }

}
