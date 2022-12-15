<?php

namespace Drupal\drupal_event\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldFormatter\DateTimeFormatterBase;

/**
 * Plugin implementation of the 'Flexible daterange' interval field formatter.
 *
 * @FieldFormatter(
 *   id = "flexible_daterange_interval",
 *   label = @Translation("Interval"),
 *   field_types = {
 *     "daterange"
 *   }
 * )
 */
class FlexibleDateRangeIntervalFormatter extends DateTimeFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'date_format' => 'd M Y',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $format = $this->getSetting('date_format');
    $element = [];
    /** @var \Drupal\Core\Field\FieldItemListInterface $item */
    foreach ($items as $delta => $item) {
      $startDatetime = $item->start_date;
      $endDatetime = $item->end_date;
      $sameYear = $startDatetime->format('Y') == $endDatetime->format('Y');
      $sameMonth = $startDatetime->format('m') == $endDatetime->format('m') && $sameYear;
      $sameDay = $startDatetime->format('d') == $endDatetime->format('d') && $sameMonth;
      $markup = $this->t('@startday - @endday', [
        '@startday' => $startDatetime->format($format),
        '@endday' => $endDatetime->format($format),
      ]);
      if ($sameDay) {
        $markup = $startDatetime->format($format);
      }
      elseif ($sameMonth) {
        $markup = $this->t('@startday - @endday @month @year', [
          '@startday' => $startDatetime->format('d'),
          '@endday' => $endDatetime->format('d'),
          '@month' => $startDatetime->format('M'),
          '@year' => $startDatetime->format('Y'),
        ]);
      }
      elseif ($sameYear) {
        $markup = $this->t('@startday - @endday @year', [
          '@startday' => $startDatetime->format('d M'),
          '@endday' => $endDatetime->format('d M'),
          '@year' => $startDatetime->format('Y'),
        ]);
      }
      $element[$delta] = [
        '#type' => 'markup',
        '#markup' => $markup,
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatDate($date) {
    $format = $this->getSetting('date_format');
    $timezone = $this->getSetting('timezone_override') ?: $date->getTimezone()->getName();
    return $this->dateFormatter->format($date->getTimestamp(), 'custom', $format, $timezone != '' ? $timezone : NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState) {
    $form = parent::settingsForm($form, $formState);

    $form['date_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date/time format'),
      '#description' => $this->t('See <a href="https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters" target="_blank">the documentation for PHP date formats</a>.'),
      '#default_value' => $this->getSetting('date_format'),
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $date = new DrupalDateTime();
    $this->setTimeZone($date);
    $endDate = new DrupalDateTime('1 day');

    $summary = [];
    $markup = $this->t('@startday - @endday', [
      '@startday' => $date->format('d'),
      '@endday' => $endDate->format('d'),
    ]) . ' ' . $endDate->format('M Y');
    $summary[] = $this->t('Same month: @markup', ['@markup' => $markup]);
    $endDate = new DrupalDateTime('1 month 3 days');
    $markup = $this->t('@startday - @endday', [
      '@startday' => $date->format('d M'),
      '@endday' => $endDate->format('d M'),
    ]) . ' ' . $date->format('Y');
    $summary[] = $this->t('Same year: @markup', ['@markup' => $markup]);

    return $summary;
  }

}
