<?php

namespace Drupal\advanced_pwa\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\file\FileUsage\FileUsageBase;

/**
 * Configure  advanced_pwa Manifest.
 */
class ManifestConfigurationForm extends ConfigFormBase {

  /**
   * The file storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * @var \Drupal\file\FileUsage\FileUsageBase
   */
  protected $fileUsage;

  /**
   * Constructs a SiteInformationForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator.
   * @param \Drupal\Core\Routing\RequestContext $request_context
   *   The request context.
   * @param \Drupal\Core\Entity\EntityStorageInterface $file_storage
   *   The request file_storage.
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The request currentUser.
   * @param \Drupal\file\FileUsage\FileUsageBase $fileUsage
   *   The request fileUsage.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AliasManagerInterface $alias_manager, PathValidatorInterface $path_validator, RequestContext $request_context, EntityStorageInterface $file_storage, AccountProxy $currentUser, FileUsageBase $fileUsage) {
    parent::__construct($config_factory);
    $this->aliasManager = $alias_manager;
    $this->pathValidator = $path_validator;
    $this->requestContext = $request_context;
    $this->fileStorage = $file_storage;
    $this->currentUser = $currentUser;
    $this->fileUsage = $fileUsage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('path_alias.manager'),
      $container->get('path.validator'),
      $container->get('router.request_context'),
      $container->get('entity_type.manager')->getStorage('file'),
      $container->get('current_user'),
      $container->get('file.usage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'advanced_pwa_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['advanced_pwa.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Get the default settings for the  advanced_pwa Module.
    $config = $this->config('advanced_pwa.settings');
    // Get the specific icons. Needed to get the correct path of the file.
    $icon = $this->config('advanced_pwa.settings')->get('icons.icon');
    // Get the file id and path.
    $fid = $icon[0];

    // Start form.
    $form['advanced_pwa_manifest_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Progressive Web App configuration'),
      '#open' => FALSE,
    ];
    $form['advanced_pwa_manifest_settings']['description'] = [
      '#markup' => $this->t('In order for push notifications and your Progressive Web App to work you will need to configure the settings below. After saving your changes you have to <B>clear site cache</B>'),
    ];

    // For now we will turn everything on or off with one checkbox. However we
    // should make it possible to easily extend this in the future.
    $form['advanced_pwa_manifest_settings']['status_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable push notifications'),
      '#default_value' => NULL !== $config->get('status.all') ? $config->get('status.all') : TRUE,
      '#description' => $this->t('Disabling the push notifications will ensure that no user will be able to receive push notifications'),
    ];

    $form['advanced_pwa_manifest_settings']['short_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Short name'),
      '#size' => 12,
      '#default_value' => $config->get('short_name'),
      '#required' => TRUE,
      '#description' => $this->t('This is the name the user will see when they add your website to their homescreen. You might want to keep this short.'),
    ];
    $form['advanced_pwa_manifest_settings']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#size' => 30,
      '#default_value' => $config->get('name'),
      '#description' => $this->t('Enter the name of your website.'),
    ];
    $form['advanced_pwa_manifest_settings']['icon'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('General App Icon'),
      '#description' => $this->t('Provide a square (.png) image. This image serves as your icon when the user adds the website to their home screen. <i>Minimum dimensions are 512*512.</i>If image having larger dimensions is submitted then it will be resized to 512px * 512px'),
      '#default_value' => $fid? [$fid] : [],
      '#required' => TRUE,
      '#upload_location' => \Drupal::config('system.file')->get('default_scheme'). '://images/pwaimages/',
      '#upload_validators' => [
        'file_validate_extensions' => ['png'],
        'file_validate_image_resolution' => ['512x512', '512x512'],
      ],
    ];
    $form['advanced_pwa_manifest_settings']['background_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Background Color'),
      '#default_value' => $config->get('background_color'),
      '#description' => $this->t('Select a background color for the launch screen. This is shown when the user opens the website from their homescreen.'),
    ];
    $form['advanced_pwa_manifest_settings']['theme_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Theme Color'),
      '#default_value' => $config->get('theme_color'),
      '#description' => $this->t('This color is used to create a consistent experience in the browser when the users launch your website from their homescreen.'),
    ];

    // Sub-section for Advanced Settings.
    $form['advanced_pwa_manifest_advanced_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];
    $form['advanced_pwa_manifest_advanced_settings']['notice'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Please notice:'),
      '#open' => FALSE,
      '#description' => $this->t('These settings have been set automatically to serve the most common use cases. Only change these settings if you know what you are doing.'),
    ];
    $form['advanced_pwa_manifest_advanced_settings']['start_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start URL'),
      '#size' => 15,
      '#disabled' => FALSE,
      '#description' => $this->t('The scope for the Service Worker.'),
      '#default_value' => $config->get('start_url'),
      '#field_prefix' => $this->requestContext->getCompleteBaseUrl(),
    ];
    $form['advanced_pwa_manifest_advanced_settings']['display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display'),
      '#default_value' => $config->get('display'),
      '#description' => $this->t('<u>When the site is being launched from the homescreen, you can launch it in:</u></br><b>Fullscreen:</b><i> This will cover up the entire display of the device.</i></br><b>Standalone:</b> <i>(default) Kind of the same as Fullscreen, but only shows the top info bar of the device. (Telecom provider, time, battery etc.)</i></br><b>Browser:</b> <i>It will simply just run from the browser on your device with all the user interface elements of the browser.</i>'),
      '#options' => [
        'fullscreen' => $this->t('Fullscreen'),
        'standalone' => $this->t('Standalone'),
        'browser' => $this->t('Browser'),
      ],
    ];
    $form['advanced_pwa_manifest_advanced_settings']['display_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Display type'),
      '#default_value' => $config->get('display_type'),
      '#description' => $this->t('Configure how the initial confirmation dialog should be displayed.<br><b>Modal</b> means default confirmation dialog box.<br>Choose <b>Embedded</b> if you want to apply different styling to the confirmation dialog box.'),
      '#options' => [
        'modal' => $this->t('Modal'),
        'embedded' => $this->t('Embedded'),
      ],
    ];
    $form['advanced_pwa_manifest_advanced_settings']['orientation'] = [
      '#type' => 'select',
      '#title' => $this->t('Orientation'),
      '#default_value' => $config->get('orientation'),
      '#description' => $this->t('Configures if the site should run in <b>Portrait</b> (default) or <b>Landscape</b> mode on the device when being launched from the homescreen.'),
      '#options' => [
        'portrait' => $this->t('Portrait'),
        'landscape' => $this->t('Landscape'),
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate front page path.
    if (($value = $form_state->getValue('start_url')) && $value[0] !== '/') {
      $form_state->setErrorByName('start_url', $this->t("The path '%path' has to start with a slash.", ['%path' => $form_state->getValue('start_url')]));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $currentUserId = $this->currentUser->id();
    $icon = $form_state->getValue('icon');
    // Load the object of the file by its fid.
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->fileStorage->load($icon[0]);
    // Set the status flag permanent of the file object.
    if (!empty($file)) {
      // Flag the file permanent.
      $file->setPermanent();
      // Save the file in the database.
      $file->save();
      $this->fileUsage->add($file, 'advanced_pwa', 'icon', $currentUserId);
    }

    $config = $this->config('advanced_pwa.settings');
    $config->set('status.all', $form_state->getValue('status_all'))
      ->set('name', $form_state->getValue('name'))
      ->set('short_name', $form_state->getValue('short_name'))
      ->set('start_url', $form_state->getValue('start_url'))
      ->set('background_color', $form_state->getValue('background_color'))
      ->set('theme_color', $form_state->getValue('theme_color'))
      ->set('display', $form_state->getValue('display'))
      ->set('display_type', $form_state->getValue('display_type'))
      ->set('orientation', $form_state->getValue('orientation'))
      ->set('icons.icon', $form_state->getValue('icon'))
      ->save();

    // $my_app_status = $form_state->getValue('status_all');
    // if(!$my_app_status)
    // {
    // \Drupal::configFactory()->getEditable('advanced_pwa.advanced_pwa')->delete();
    // }
    parent::submitForm($form, $form_state);
  }

}
