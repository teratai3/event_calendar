<?php

namespace Drupal\event_calendar\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * 日の記事データを一覧で表示.
   */
  public function index($node_type, $year, $month, $day) {
    $config = $this->configFactory->get('event_calendar.settings');
    $event_flag = $config->get('event_flag_' . $node_type);
    // 年と月をバリデート.
    if (!$event_flag || !checkdate($month, $day, $year)) {
      throw new NotFoundHttpException();
    }
    $selected_date = new DrupalDateTime("$year-$month-$day");

    $query = $this->database->select('event_calendars', 'ec');
    $query->fields('ec', ['start_date', 'end_date', 'nid']);
    // node_field_data テーブルを結合.
    $query->join('node_field_data', 'nfd', 'ec.nid = nfd.nid');
    $query->fields('nfd', ['nid', 'title', 'created', 'type']);

    // node__body テーブルを結合.
    $query->leftJoin('node__body', 'nb', 'ec.nid = nb.entity_id');
    $query->fields('nb', ['body_value', 'body_format']);

    $query->condition('nfd.status', 1, '=');
    $query->condition('nfd.type', $node_type, '=');
    $query->condition('ec.start_date', $selected_date->format('Y-m-d') . ' 23:59:59', '<=');
    $query->condition('ec.end_date', $selected_date->format('Y-m-d') . ' 00:00:00', '>=');
    $query->orderBy('ec.start_date', 'ASC');
    $query->orderBy('ec.end_date', 'ASC');
    $results = $query->execute();
    $events = [];
    foreach ($results as $record) {
      $events[] = [
        'title' => $record->title,
        'type' => $record->type,
        'body' => $record->body_value,
        'created' => date('Y-m-d', $record->created),
        'start_date' => $record->start_date,
        'end_date' => $record->end_date,
        'link' => Url::fromRoute('entity.node.canonical', ['node' => $record->nid])->toString(),
      ];
    }

    if (empty($events)) {
      throw new NotFoundHttpException();
    }

    return [
      '#theme' => 'event_calendar_page',
      '#events' => $events,
      '#title' => 'イベント ' . $selected_date->format('Y年m月d日'),
      '#selected_date' => $selected_date->format('Y年m月d日'),
      'pager' => [
        '#type' => 'pager',
      ],
    ];

  }

}
