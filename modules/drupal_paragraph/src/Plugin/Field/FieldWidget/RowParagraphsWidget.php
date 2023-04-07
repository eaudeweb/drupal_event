<?php

namespace Drupal\drupal_paragraph\Plugin\Field\FieldWidget;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_browser\Plugin\Field\FieldWidget\ParagraphsBrowserWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'row_entity_reference_paragraphs' widget.
 *
 * @FieldWidget(
 *   id = "row_entity_reference_paragraphs",
 *   label = @Translation("Paragraphs row"),
 *   description = @Translation("A paragraphs row form widget."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class RowParagraphsWidget extends ParagraphsBrowserWidget implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a Paragraphs Row.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entityTypeManager, RendererInterface $renderer) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entityTypeManager;
    $this->parentFieldName = $this->fieldDefinition->getName();
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $widgetState = static::getWidgetState($element['#field_parents'], $this->parentFieldName, $form_state);
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $this->lastProcessedParagraph = $paragraph = $widgetState['paragraphs'][$delta]['entity'];
    $element['#paragraph_id'] = $paragraph->id();
    try {
      $element['top']['summary'] = $this->buildRow($paragraph);
    }
    catch (\Exception $e) {
      $element['top']['summary'] = ['error' => ['value' => [$this->t('There has been an error while generating this row.')]]];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $elements = parent::formMultipleElements($items, $form, $form_state);

    foreach (Element::children($elements) as $key) {
      $element = &$elements[$key];
      if (empty($element['top'])) {
        continue;
      }
      foreach (Element::children($element['top']) as $topKey) {
        if ($topKey == 'actions') {
          continue;
        }
        $element['top'][$topKey] = $this->buildCellsContainers($element['top'][$topKey]);
      }
    }

    $elements['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $elements['#prefix'] = str_replace('paragraphs-tabs-wrapper', 'raw-paragraphs-tabs-wrapper', $elements['#prefix']);
    return $elements;
  }

  /**
   * Includes each cell content in a container render element which has all
   * need attributes for the CSS grid table display.
   *
   * @param $elements
   *   A render array element.
   *
   * @return array
   *   A render array element.
   */
  protected function buildCellsContainers(array $elements) {
    foreach ($elements as $field => $component) {
      if ($field[0] === '#') {
        continue;
      }
      $span = !empty($component['span']) ? $component['span'] : 1;
      unset($component['span']);
      $container = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'paragraph-summary-component',
            "paragraph-summary-component-$field",
            "paragraph-summary-component-span-$span",
          ],
        ],
      ];
      $container['data'] = empty($component['value']) ?
        $component :
        ['#markup' => is_array($component['value']) ? implode("\n", $component['value']) : $component['value']];
      $elements[$field] = $container;
    }
    return $elements;
  }

  /**
   * Returns the field components for the Table Preview form display view of a paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   *
   * @return array
   *   The field components.
   */
  public function getFieldComponents(ParagraphInterface $paragraph) {
    $bundle = $paragraph->getType();
    $entityFormDisplay = EntityFormDisplay::load("paragraph.$bundle.table_preview");
    if (empty($entityFormDisplay)) {
      $entityFormDisplay = EntityFormDisplay::load("paragraph.$bundle.default");
    }
    $components = $entityFormDisplay->getComponents();
    uasort($components, 'Drupal\Component\Utility\SortArray::sortByWeightElement');

    return $components;
  }

  /**
   * Returns the render array with the paragraph table row.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function buildRow(ParagraphInterface $paragraph) {
    $row = [];
    $components = $this->getFieldComponents($paragraph);
    foreach (array_keys($components) as $fieldName) {
      $fieldDefinition = $paragraph->getFieldDefinition($fieldName);
      if (!($fieldDefinition instanceof FieldConfigInterface)
        || $paragraph->get($fieldName)->access('view') == FALSE) {
        // We do not add content to the summary from base fields, skip them
        // keeps performance while building the paragraph summary.
        continue;
      }
      $fieldColumn = $this->getFieldColumn($fieldName);
      if (empty($row[$fieldColumn]['value'])) {
        $row[$fieldColumn]['value'] = [];
      }
      if (empty($row[$fieldColumn]['span'])) {
        $row[$fieldColumn]['span'] = $this->getFieldSpan($fieldDefinition, $components);
      }
      /** @var \Drupal\Core\Field\FieldItemListInterface $fieldItemList */
      $fieldItemList = $paragraph->get($fieldName);
      if (empty($fieldItemList->getValue())) {
        continue;
      }
      $value = $this->buildRowValue($fieldDefinition, $fieldItemList);
      if (empty($value)) {
        continue;
      }
      $row[$fieldColumn]['value'][] = $value;
    }

    return $row;
  }

  /**
   * Build value for each type of field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface|NULL $fieldDefinition
   *   Field definition.
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The original (stored) value for the field.
   *
   * @return mixed|string|null
   */
  protected function buildRowValue(FieldDefinitionInterface $fieldDefinition, FieldItemListInterface $fieldItemList) {
    $value = NULL;
    switch ($fieldDefinition->getType()) {
      case 'boolean':
        $value = $this->renderBooleanField($fieldItemList);
        break;

      case 'list_text':
      case 'list_string':
        $value = $this->renderListField($fieldItemList);
        break;

      case 'string_long':
        $value = $this->renderStringField($fieldItemList, TRUE);
        $value = nl2br($value);
        break;
      case 'text_with_summary':
      case 'text_long':
      case 'string':
        $value = $this->renderStringField($fieldItemList, TRUE);
        break;

      case 'link':
        $value = $this->renderLinkField($fieldItemList);
        break;

      case 'entity_reference':
      case 'entity_reference_revisions':
        $value = $this->renderEntityReferenceField($fieldItemList);
        break;

      case 'daterange':
      case 'date':
        $value = $this->renderDateField($fieldItemList);
        break;
    }
    return $value;
  }

  /**
   * Returns the markup for a boolean field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The original (stored) value for the field.
   *
   * @return string
   */
  protected function renderBooleanField(FieldItemListInterface $fieldItemList) {
    return !empty($fieldItemList->value)
      ? '<span class="field-boolean-tick">' . html_entity_decode('&#10004;') . '</span>'
      : '';
  }

  /**
   * Returns the markup for a list field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The original (stored) value for the field.
   *
   * @return string
   */
  protected function renderListField(FieldItemListInterface $fieldItemList) {
    $allowedValues = $fieldItemList->getFieldDefinition()->getFieldStorageDefinition()->getSetting('allowed_values');
    $values = [];
    foreach ($fieldItemList as $item) {
      $values[] = $allowedValues[$item->value];
    }
    return implode(', ', $values);
  }

  /**
   * Returns the markup for a string field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The original (stored) value for the field.
   * @param bool $truncate
   *   A boolean to truncate the string. Defaults to FALSE.
   *
   * @return string
   */
  protected function renderStringField(FieldItemListInterface $fieldItemList, bool $truncate = FALSE) {
    $value = trim($fieldItemList->value);
    if ($truncate && strlen($value) > 600) {
      $value = Unicode::truncate($value, 600) . '...';
    }
    return $value;
  }

  /**
   * Returns the markup for a link field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The original (stored) value for the field.
   *
   * @return |null
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function renderLinkField(FieldItemListInterface $fieldItemList) {
    if (empty($fieldItemList->first())) {
      return NULL;
    }
    if (!empty($fieldItemList->title)) {
      return $fieldItemList->title;
    }
    // If title is not set, fallback to the uri.
    return $fieldItemList->uri;
  }

  /**
   * Returns the markup for an entity reference field. Also
   * entity_reference_revisions fields should use this method.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The original (stored) value for the field.
   *
   * @return mixed|null
   */
  protected function renderEntityReferenceField(FieldItemListInterface $fieldItemList) {
    $viewBuilder = NULL;
    $childrenView = [];
    foreach ($fieldItemList as $childEntityValue) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $childEntity */
      $childEntity = $childEntityValue->entity;
      if (empty($childEntity)) {
        continue;
      }
      if (empty($viewBuilder)) {
        $viewBuilder = $this->entityTypeManager->getViewBuilder($childEntity->getEntityTypeId());
      }
      $childView = $viewBuilder->view($childEntity, 'teaser');
      $childView['#attributes']['class'][] = 'entity-reference-field-wrapper';
      $childrenView[] = $this->renderer->render($childView);
    }
    $list = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $childrenView,
    ];
    return $this->renderer->render($list);
  }

  /**
   * Returns the date as a markup.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The original (stored) value for the field.
   *
   * @return string
   */
  protected function renderDateField(FieldItemListInterface $fieldItemList) {
    if ($fieldItemList->getFieldDefinition()->getType() == 'daterange') {
      return date(DateTimePlus::FORMAT, strtotime($fieldItemList->value)) . ' - ' . date(DateTimePlus::FORMAT, strtotime($fieldItemList->end_value));
    }
    return date(DateTimeItemInterface::DATE_STORAGE_FORMAT, strtotime($fieldItemList->value));
  }

  /**
   * We are using CSS grid template to display a table and the columns need to
   * have different widths based on the field type.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface|NULL $fieldDefinition
   *   Field definition.
   *
   * @return int
   */
  public function getFieldSpan(FieldDefinitionInterface $fieldDefinition = NULL, array $components) {
    if (empty($fieldDefinition)) {
      return 2;
    }

    return (int)array_search($fieldDefinition->getName(), array_keys($components)) + 1;
  }

  /**
   * {@inheritdoc}
   */
  public static function getWidgetState(array $parents, $fieldName, FormStateInterface $form_state) {
    // Fix some issues with the diff form save.
    return parent::getWidgetState($parents, $fieldName, $form_state) ?: [];
  }

  /**
   * Returns the column name where the field should be rendered.
   *
   * @param string $fieldName
   *   The field name.
   *
   * @return string
   *  The machine name of the column. Default column is the field name.
   */
  public function getFieldColumn(string $fieldName) {
    switch ($fieldName) {
      case 'field_location':
      case 'field_title':
        return 'title';
    }
    return $fieldName;
  }

}
