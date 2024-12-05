<?php

namespace Drupal\event_calendar\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Event Calendar Block.
 *
 * @Block(
 *   id = "event_calendar_block",
 *   admin_label = @Translation("イベントカレンダー"),
 * )
 */
class EventCalendarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];

    foreach ($node_types as $type_id => $type) {
      $setting_config = $this->configFactory->get('event_calendar.settings');
      $event_flag = $setting_config->get('event_flag_' . $type_id);
      // イベントの設定がonになっているものだけ表示.
      if ($event_flag) {
        $options[$type_id] = $type->label();
      }

    }

    $config = $this->getConfiguration();
    $form['event_node_type'] = [
      '#type' => 'select',
      '#title' => 'カレンダーの対象ノードタイプ',
      '#description' => 'このカレンダー ブロックのノードタイプを選択します。',
      '#options' => $options,
      '#default_value' => $config['event_node_type'] ?? '',
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // ユーザー入力値を設定に保存.
    $this->setConfigurationValue('event_node_type', $form_state->getValue('event_node_type'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $now = new DrupalDateTime('now');
    $firstDayOfMonth = $now->format('Y-m-01');
    $lastDayOfMonth = $now->format('Y-m-t');

    $startDate = new DrupalDateTime($firstDayOfMonth);
    $endDate = new DrupalDateTime($lastDayOfMonth);

    // 現在の月のカレンダーを構築.
    $calendarDays = [];
    // 月の最初の曜日 (0=日曜日, 6=土曜日)
    $startWeekday = (int) $startDate->format('w');
    // 月の日数.
    $totalDays = (int) $endDate->format('j');

    // 空白の日付を挿入（前月のプレースホルダー）.
    for ($i = 1; $i < $startWeekday; $i++) {
      $calendarDays[] = [
        'day' => NULL,
        'has_event' => FALSE,
      ];
    }

    $config = $this->getConfiguration();
    $setting_config = $this->configFactory->get('event_calendar.settings');
    $event_flag = $setting_config->get('event_flag_' . $config['event_node_type']);
    // イベント期間をマッピング.
    $eventDates = [];

    if ($event_flag) {
      // イベントデータを取得.
      $query = $this->database->select('event_calendars', 'ec');
      $query->fields('ec', ['start_date', 'end_date', 'nid']);
      $query->join('node_field_data', 'nfd', 'ec.nid = nfd.nid');
      $query->fields('nfd', ['type']);
      $query->condition('nfd.status', 1, '=');
      $query->condition('nfd.type', $config['event_node_type'], '=');
      $query->condition('ec.start_date', $lastDayOfMonth, '<=');
      $query->condition('ec.end_date', $firstDayOfMonth, '>=');
      $results = $query->execute();

      foreach ($results as $record) {
        $eventStart = new DrupalDateTime($record->start_date);
        $eventEnd = new DrupalDateTime($record->end_date);

        // イベント期間内の日付をすべて取得.
        $current = clone $eventStart;
        while ($current <= $eventEnd) {
          $day = $current->format('j');
          $eventDates[$day] = TRUE;
          $current->modify('+1 day');
        }
      }
    }

    // 現在月の日付を追加.
    for ($day = 1; $day <= $totalDays; $day++) {
      $calendarDays[] = [
        'day' => $day,
        'has_event' => !empty($eventDates[$day]),
      ];
    }

    $libraries = ['event_calendar/event_calendar_js'];

    if (!empty($setting_config->get('enabled'))) {
      $libraries[] = 'event_calendar/event_calendar_css';
    }

    return [
      '#theme' => 'event_calendar_block',
      '#calendar_days' => $calendarDays,
      '#event_node_type' => $config['event_node_type'],
      '#month' => $now->format('m'),
      '#day' => $now->format('j'),
      '#year' => $now->format('Y'),
      '#attached' => [
        'library' => $libraries,
        'drupalSettings' => [
          'eventCalendar' => [
            'event_node_type' => $config['event_node_type'],
          ],
        ],
      ],
    ];
  }

}
