<?php

namespace Drupal\drupal_event\Plugin\Block;

use Drupal\Core\Url;

/**
 * Provides 'Event Menu' block for event elements displayed in separated pages.
 *
 * @Block(
 *   id = "event_separate_sub_pages",
 *   admin_label = @Translation("Event Menu (Elements in different routes)"),
 *   category = @Translation("Event")
 * )
 */
class EventPillsMenuBlock extends EventMenuBlockBase {

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

    // Add a button for Event homepage.
    array_unshift($build['elements'], [
      'content' => [
        '#theme' => 'event_elements_paragraph__preview',
        '#items' => [
          '#type' => 'link',
          '#title' => $this->t('Event information'),
          '#attributes' => ['class' => [
            'btn',
            $this->isEventHomepage() ? 'btn-primary' : 'btn-outline-primary',
          ]],
          '#url' => Url::fromRoute('entity.node.canonical', ['node' => $this->node->id()]),
        ],
      ],
    ]);

    return $build;
  }

}
