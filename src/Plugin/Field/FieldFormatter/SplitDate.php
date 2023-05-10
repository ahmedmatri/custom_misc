<?php

namespace Drupal\custom_misc\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldFormatter\DateTimeFormatterBase;

/**
 * Plugin implementation of the 'Split date' formatter for 'datetime' fields.
 *
 * @FieldFormatter(
 *   id = "split_date",
 *   label = @Translation("Split date"),
 *   field_types = {
 *     "datetime"
 *   }
 * )
 */
class SplitDate extends DateTimeFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'format_type' => 'medium',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function formatDate($date) {
    $format_type = $this->getSetting('format_type');
    $timezone = $this->getSetting('timezone_override') ?: $date->getTimezone()
      ->getName();
    return $this->dateFormatter->format($date->getTimestamp(), $format_type, '', $timezone != '' ? $timezone : NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $time = new DrupalDateTime();
    $format_types = $this->dateFormatStorage->loadMultiple();
    $options = [];
    foreach ($format_types as $type => $type_info) {
      $format = $this->dateFormatter->format($time->getTimestamp(), $type);
      $options[$type] = $type_info->label() . ' (' . $format . ')';
    }

    $form['format_type'] = [
      '#type' => 'select',
      '#title' => t('Date format'),
      '#description' => t("Choose a format for displaying the date. Be sure to set a format appropriate for the field, i.e. omitting time for a field that only has a date."),
      '#options' => $options,
      '#default_value' => $this->getSetting('format_type'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $date = new DrupalDateTime();
    $summary[] = t('Format: @display', ['@display' => $this->formatDate($date)]);
    return $summary;
  }


  /**
   * Creates a render array from a date object with ISO date attribute.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   A date object.
   *
   * @return array
   *   A render array.
   */
  protected function buildDateWithIsoAttribute(DrupalDateTime $date) {
    // Create the ISO date in Universal Time.
    $iso_date = $date->format("Y-m-d\TH:i:s") . 'Z';
    $this->setTimeZone($date);


    $build = [
      '#theme' => 'split-date',
      '#content' => [
        'day' => ['#markup' => date("d",($date->getTimestamp()))],
        'month' => ['#markup' => date("F",($date->getTimestamp()))],
        'year' => ['#markup' => date("Y",($date->getTimestamp()))],
      ],
      '#iso_date' => $iso_date,
      '#attributes' => [
        'datetime' => $iso_date,
      ],
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
    ];
    return $build;
  }

}
