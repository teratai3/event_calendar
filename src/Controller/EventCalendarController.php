<?php

namespace Drupal\event_calendar\Controller;

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

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    // データベース接続をサービスコンテナから取得.
      $container->get('database')
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

    // イベントデータを取得.
    $query = $this->database->select('event_calendars', 'ec')
      ->fields('ec')
      ->condition('start_date', $lastDayOfMonth->format('Y-m-d'), '<=')
      ->condition('end_date', $firstDayOfMonth->format('Y-m-d'), '>=')
      ->execute();

    if (!empty($query)) {
      foreach ($query as $record) {
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
