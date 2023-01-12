<?php

namespace Drupal\drupal_event\Plugin\Block;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides 'Event Menu' block.
 *
 * @Block(
 *   id = "event_sub_pages",
 *   admin_label = @Translation("Event Menu (Elements in same page)"),
 *   category = @Translation("Event")
 * )
 */
class EventMenuBlock extends EventMenuBlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function build() {
    $build = parent::build();

    // When Layout builder tried to render the block.
    if (!$this->node) {
      return $build;
    }
    // Set Documents preview block if exists.
    if (!empty($this->eventUtils->getEventDocuments($this->node->id()))) {
      $field_title = $this->getFieldDefinition('Documents', 'field_event_documents_title', 'string', [
        [
          '#markup' => $this->t('Documents'),
        ],
      ]);
      $field_link = $this->getFieldDefinition('Documents', 'field_event_documents_link', 'link', [$this->generateButton('documents')]);
      $build['elements'][] = [
        'content' => [
          '#theme' => 'event_elements_paragraph__preview',
          '#items' => [
            $this->renderField($field_title),
            $this->renderField($field_link),
          ],
        ],
      ];
    }
    // Set Multimedia preview block if event has multimedia as field.
    if ($this->node->hasField('field_multimedia') && !$this->node->get('field_multimedia')->isEmpty()) {
      $field_title = $this->getFieldDefinition('Gallery', 'field_multimedia_title', 'string', [
        [
          '#markup' => $this->t('Gallery'),
        ],
      ]);
      $field_link = $this->getFieldDefinition('Gallery', 'field_multimedia_link', 'link', [$this->generateButton('gallery')]);
      $build['elements'][] = [
        'content' => [
          '#theme' => 'event_elements_paragraph__preview',
          '#items' => [
            $this->renderField($field_title),
            $this->renderField($field_link),
          ],
        ],
      ];
    }

    return $build;
  }

}
