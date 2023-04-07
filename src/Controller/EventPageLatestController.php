<?php

namespace Drupal\drupal_event\Controller;

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Controller to see the latest revision for an event sub-page.
 */
class EventPageLatestController extends EventPageController {

  /**
   * Render latest version for an event page.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node entity.
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The paragraph entity.
   */
  public function renderEventPage(Node $node, Paragraph $paragraph) {
    $paragraph = $this->eventUtils->getLatestRevision($paragraph);
    return parent::renderEventPage($node, $paragraph);
  }

  /**
   * Displays a node revision.
   *
   * @param \Drupal\node\Entity\Node $node_revision
   *   The node revision.
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The paragraph entity.
   *
   * @return array
   *   An array suitable for \Drupal\Core\Render\RendererInterface::render().
   */
  public function revisionEventPage(Node $node_revision, Paragraph $paragraph) {
    $key = array_search($paragraph->id(), array_column($node_revision->get('field_event_elements')->getValue(), 'target_id'));
    if (is_int($key)) {
      $rid = $node_revision->get('field_event_elements')->get($key)->target_revision_id;
      $paragraph = $this->eventUtils->loadRevision($rid, 'paragraph');
    }
    return parent::renderEventPage($node_revision, $paragraph);
  }

}
