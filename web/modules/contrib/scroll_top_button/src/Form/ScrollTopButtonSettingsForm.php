<?php

namespace Drupal\scroll_top_button\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ScrollTopButtonSettingsForm.
 *
 * Admin config form definition for the scroll to top button options.
 *
 * @package Drupal\scroll_top_button\Form
 */
class ScrollTopButtonSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'scroll_top_button.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scroll_top_button_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('scroll_top_button.settings');

    $form['general_seettings'] = [
      '#type'  => 'details',
      '#title' => $this->t('General settings'),
      '#open' => TRUE,
    ];

    $form['general_seettings']['enabled'] = [
      '#type' => 'radios',
      '#title' => $this->t('Enable scroll to top button'),
      '#default_value' => $config->get('enabled') ? $config->get('enabled') : 'off',
      '#options' => ['on' => $this->t('Enable'), 'off' => $this->t('Disable')],
      '#required' => TRUE,
    ];

    $form['general_seettings']['show_on_admin'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show scroll to top button on admin pages'),
      '#default_value' => $config->get('show_on_admin'),
    ];

    $form['button_options'] = [
      '#type'  => 'details',
      '#title' => $this->t('Manage scroll to top button options'),
      '#open' => TRUE,
    ];

    $form['button_options']['button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button text'),
      '#default_value' => $config->get('button_text'),
      '#required' => TRUE,
      '#description' => $this->t('Text to show on the scroll to top button'),
    ];

    $form['button_options']['button_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Button style to display'),
      '#default_value' => $config->get('button_style'),
      '#options' => [
        'image' => $this->t('Image'),
        'link' => $this->t('Link'),
        'pill' => $this->t('Pill'),
        'tab' => $this->t('Tab'),
      ],
      '#required' => TRUE,
    ];

    $form['button_options']['button_animation'] = [
      '#type' => 'select',
      '#title' => $this->t('Button animation effect'),
      '#default_value' => $config->get('button_animation'),
      '#options' => [
        'fade' => $this->t('Fade'),
        'slide' => $this->t('Slide'),
        'none' => $this->t('None'),
      ],
      '#required' => TRUE,
      '#description' => $this->t('Choose a animation effect when button will display'),
    ];

    $form['button_options']['button_animation_speed'] = [
      '#title' => $this->t('Button animation speed (ms)'),
      '#description' => $this->t('Enter the value of button animation speed'),
      '#type' => 'number',
      '#required' => TRUE,
      '#default_value' => $config->get('button_animation_speed'),
    ];

    $form['scroll_options'] = [
      '#type'  => 'details',
      '#title' => $this->t('Manage page scrolling options'),
      '#open' => TRUE,
    ];

    $form['scroll_options']['scroll_distance'] = [
      '#title' => $this->t('Scroll distance (px)'),
      '#description' => $this->t('Distance from top before showing button'),
      '#type' => 'number',
      '#required' => TRUE,
      '#default_value' => $config->get('scroll_distance'),
    ];

    $form['scroll_options']['scroll_speed'] = [
      '#title' => $this->t('Speed scroll to top (ms)'),
      '#type' => 'number',
      '#required' => TRUE,
      '#default_value' => $config->get('scroll_speed'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->config(static::SETTINGS)
      // Set the submitted configuration setting.
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('show_on_admin', $form_state->getValue('show_on_admin'))
      ->set('button_text', $form_state->getValue('button_text'))
      ->set('button_style', $form_state->getValue('button_style'))
      ->set('button_animation', $form_state->getValue('button_animation'))
      ->set('button_animation_speed', $form_state->getValue('button_animation_speed'))
      ->set('scroll_distance', $form_state->getValue('scroll_distance'))
      ->set('scroll_speed', $form_state->getValue('scroll_speed'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
