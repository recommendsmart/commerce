<?php

namespace Drupal\pwa\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Basic configuration form.
 */
class BasicConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pwa_basic_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['pwa.config'];
  }

  /**
   * {@inheritdoc}
    */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('pwa.config');

    $form['short_name'] = [
      "#type" => 'textfield',
      "#title" => $this->t('Short name'),
      "#description" => $this->t("A short application name, this one gets displayed on the user's homescreen."),
      '#default_value' => $config->get('short_name'),
      '#required' => TRUE,
      '#maxlength' => 25,
      '#size' => 30,
    ];

    $form['description'] = [
      "#type" => 'textfield',
      "#title" => $this->t('Description'),
      "#description" => $this->t('The description of your PWA.'),
      '#default_value' => $config->get('description'),
      '#maxlength' => 255,
      '#size' => 60,
    ];

    $form['cache_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cache version'),
      '#description' => $this->t('Changing this number will invalidate all Service Worker caches. Use it when assets have significantly changed or if you want to force a cache refresh for all clients.'),
      '#size' => 5,
      '#default_value' => $config->get('cache_version') ?: 1,
    ];

    return parent::buildForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('pwa.config');

    // Save new config data
    $config
      ->set('short_name', $form_state->getValue('short_name'))
      ->set('description', $form_state->getValue('description'))
      ->set('cache_version', $form_state->getValue('cache_version'))
      ->save();

    parent::submitForm($form, $form_state);

  }

}
