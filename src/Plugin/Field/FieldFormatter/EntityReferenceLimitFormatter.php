<?php

namespace Drupal\drupal_event\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\link\LinkItemInterface;

/**
 * Plugin implementation of the 'entity reference rendered entity' formatter.
 *
 * @FieldFormatter(
 *   id = "limit_entity_reference_entity_view",
 *   label = @Translation("Rendered entity with limit"),
 *   description = @Translation("Display the referenced entities rendered by entity_view()."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceLimitFormatter extends EntityReferenceEntityFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'limit_number' => 8,
      'use_link' => FALSE,
      'use_more_link' => '',
      'override_url_title' => FALSE,
      'more_link_text' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState) {
    $elements = parent::settingsForm($form, $formState);

    $elements['limit_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Items to display'),
      '#default_value' => $this->getSetting('limit_number'),
      '#description' => $this->t("Choose who many items you want to display."),
      '#required' => TRUE,
      '#min' => 1,
    ];

    $elements['use_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add more link'),
      '#description' => $this->t("This will add a more link to the bottom
      of this list if there are more items than the limit. The link will be calculated as an item."),
      '#default_value' => $this->getSetting('use_link'),
    ];
    $fieldName = $this->fieldDefinition->getName();
    $elements['use_more_link'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#description' => $this->t("Note that this link is count as an item when is used.
      For e.g, if you want to see only @number items and you have a greatest number,
      than the formatter will display @prevNumber referenced entities and the link.
      If you have only @number from @number entities or less, the link will not appear.", [
        '@number' => $this->getSetting('limit_number'),
        '@prevNumber' => $this->getSetting('limit_number') - 1,
      ]),
      '#default_value' => $this->getSetting('use_more_link'),
      '#maxlength' => 2048,
      '#link_type' => LinkItemInterface::LINK_GENERIC,
      '#element_validate' => [[$this, 'validateMoreLink']],
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $fieldName . '][settings_edit_form][settings][use_link]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="fields[' . $fieldName . '][settings_edit_form][settings][use_link]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $elements['override_url_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override URL Title'),
      '#default_value' => $this->getSetting('override_url_title'),
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $fieldName . '][settings_edit_form][settings][use_link]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $elements['more_link_text'] = [
      '#type' => 'textfield',
      '#size' => 8,
      '#title' => $this->t('Title'),
      '#default_value' => $this->getSetting('more_link_text') ?: $this->t('see more'),
      '#maxlength' => 2048,
      '#element_validate' => [[$this, 'validateMoreLinkTitle']],
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $fieldName . '][settings_edit_form][settings][override_url_title]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="fields[' . $fieldName . '][settings_edit_form][settings][override_url_title]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Display only: @limit', ['@limit' => $this->getSetting('limit_number')]);
    if ($this->getSetting('use_link')) {
      $summary[] = $this->t('Display link: @more_link', ['@more_link' => $this->getSetting('use_more_link')]);
    }
    if ($this->getSetting('override_url_title')) {
      $summary[] = $this->t('Alter title: @more_link_text', ['@more_link_text' => $this->getSetting('more_link_text')]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    $limit = $this->getSetting('limit_number');
    if ($this->getSetting('use_link')) {
      $limit--;
    }
    $remains = count($elements) - $limit;
    if ($remains > 1) {
      $elements = array_slice($elements, 0, $limit);
      if ($this->getSetting('use_link')) {
        $url = Url::fromUserInput($this->getSetting('use_more_link'), [
          'absolute' => TRUE,
          'query' => [],
        ]);
        $title = $this->t("+@count", [
          '@count' => $remains,
        ]);
        if ($this->getSetting('override_url_title')) {
          $title = $this->t("@title", [
            '@title' => $this->getSetting('more_link_text') ?: $this->t('see more'),
          ]);
        }
        $moreLink = [
          '#type' => 'more_link',
          '#title' => $title,
          '#url' => $url,
          '#attributes' => ['class' => ['more-people', 'btn', 'btn-primary']],
          '#theme_wrappers' => [
            'container' => [
              '#attributes' => ['class' => ['see-more']],
            ],
          ],
        ];
        $elements[] = $moreLink;
      }
    }

    return $elements;
  }

  /**
   * Validates callback to ensure that use_more_link is not empty.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   */
  public function validateMoreLink(array $element, FormStateInterface $formState) {
    if (!$this->getSetting('use_link')) {
      $formState->setValueForElement($element, '');
      return;
    }
    $value = trim($element['#value']);

    if (empty($value)) {
      $formState->setError($element, $this->t('Url is required.'));
    }
  }

  /**
   * Validates callback to ensure that more_link_text is not empty.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   */
  public function validateMoreLinkTitle(array $element, FormStateInterface $formState) {
    if ($this->getSetting('override_url_title') && !$this->getSetting('more_link_text')) {
      $element['#required'] = TRUE;
      $formState->setError($element, 'Title is required when "Override Url Title" is checked.');
    }
    return $element;
  }

}
