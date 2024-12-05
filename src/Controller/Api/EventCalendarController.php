<?php

namespace Drupal\event_calendar\Controller\Api;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for fetching calendar data.
 */
class EventCalendarController extends ControllerBase {

  /**
   * The Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Block Manager Service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  public function __construct(Connection $database, BlockManagerInterface $block_manager, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->blockManager = $block_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('plugin.manager.block'),
      $container->get('config.factory')
    );
  }

  /**
   * 月のカレンダー取得.
   */
  public function index() {
    // Get the query parameters (year and month).
    $month = \Drupal::request()->query->get('month');
    $year = \Drupal::request()->query->get('year');

    $now = new DrupalDateTime('now');
    if (!$month || !$year) {
      $month = $now->format('m');
      $year = $now->format('Y');
    }

    $firstDayOfMonth = new DrupalDateTime("$year-$month-01");
    $lastDayOfMonth = new DrupalDateTime($firstDayOfMonth->format('Y-m-t'));

    // 現在の月のカレンダーを構築.
    $calendarDays = [];
    // 月の最初の曜日 (0=日曜日, 6=土曜日)
    $startWeekday = (int) $firstDayOfMonth->format('w');
    $totalDays = (int) $lastDayOfMonth->format('j');

    // 空白の日付を挿入（前月のプレースホルダー）.
    for ($i = 0; $i < $startWeekday; $i++) {
      $calendarDays[] = [
        'day' => NULL,
        'has_event' => FALSE,
      ];
    }

    $eventDates = [];
    $plugin_block = $this->blockManager->createInstance('event_calendar_block');
    $block_config = $plugin_block->getConfiguration();
    $event_node_type = !empty($block_config['event_node_type']) ? $block_config['event_node_type'] : '';
    $config = $this->configFactory->get('event_calendar.settings');
    $event_flag = $config->get('event_flag_' . $event_node_type);

    if ($event_flag) {
      // イベントデータを取得.
      $query = $this->database->select('event_calendars', 'ec');
      $query->fields('ec', ['start_date', 'end_date', 'nid']);
      $query->join('node_field_data', 'nfd', 'ec.nid = nfd.nid');
      $query->fields('nfd', ['type']);
      $query->condition('nfd.type', $event_node_type, '=');
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

    // Add current month's days.
    for ($day = 1; $day <= $totalDays; $day++) {
      $calendarDays[] = [
        'day' => $day,
        'has_event' => !empty($eventDates[$day]),
      ];
    }

    return new JsonResponse([
      'calendar_days' => $calendarDays,
      'month' => $month,
      'year' => $year,
      'current_date' => $now->format('Y-m-j'),
    ]);
  }

}
