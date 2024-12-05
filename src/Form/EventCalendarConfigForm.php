<?php

namespace Drupal\event_calendar\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Config EventCalendar settings form.
 */
class EventCalendarConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_calendar_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['event_calendar.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('event_calendar.settings');
    $config_enabled = $config->get('enabled');
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => 'イベントカレンダーのcssを有効化',
      '#description' => 'テーマ独自のスタイルを当てる際などはチェックを外すことで、無効化することができます。',
      '#default_value' => $config_enabled ? TRUE : FALSE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('event_calendar.settings')
      ->set('enabled', $form_state->getValue('enabled'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
