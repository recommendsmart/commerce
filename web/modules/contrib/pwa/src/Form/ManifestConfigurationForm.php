<?php

namespace Drupal\pwa\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileStorageInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\pwa\ManifestInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manifest configuration form.
 */
class ManifestConfigurationForm extends ConfigFormBase {

  /**
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The file entity storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $fileStorage;

  /**
   * The file system helper service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file usage backend.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * The manifest service.
   *
   * @var \Drupal\pwa\ManifestInterface
   */
  protected $manifest;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Constructor; saves dependencies.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tags invalidator service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   *
   * @param \Drupal\file\FileStorageInterface $fileStorage
   *   The file entity storage.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system helper service.
   *
   * @param \Drupal\file\FileUsage\FileUsageInterface $fileUsage
   *   The file usage backend.
   *
   * @param \Drupal\pwa\ManifestInterface $manifest
   *   The manifest service.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   */
  public function __construct(
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    ConfigFactoryInterface        $configFactory,
    FileStorageInterface          $fileStorage,
    FileSystemInterface           $fileSystem,
    FileUsageInterface            $fileUsage,
    ManifestInterface             $manifest,
    MessengerInterface            $messenger,
    StreamWrapperManagerInterface $streamWrapperManager
  ) {

    parent::__construct($configFactory);

    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->fileStorage          = $fileStorage;
    $this->fileSystem           = $fileSystem;
    $this->fileUsage            = $fileUsage;
    $this->manifest             = $manifest;
    // \Drupal\Core\Messenger\MessengerTrait::messenger() defaults to getting
    // the messenger service via the static \Drupal::messenger() method if
    // $this->messenger has not been set, and so is not 100% true dependency
    // injection unless we save it here for it to find.
    $this->messenger            = $messenger;
    $this->streamWrapperManager = $streamWrapperManager;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache_tags.invalidator'),
      $container->get('config.factory'),
      $container->get('entity_type.manager')->getStorage('file'),
      $container->get('file_system'),
      $container->get('file.usage'),
      $container->get('pwa.manifest'),
      $container->get('messenger'),
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pwa_manifest_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['pwa.config'];
  }

  /**
   * {@inheritdoc}
   *
   * @todo Can we use the injected 'stream_wrapper_manager' service rather than
   *   file_create_url() to build $files_path?
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $host = $this->getRequest()->server->get('HTTP_HOST');
    $files_path = \Drupal::service('file_url_generator')->generateAbsoluteString("public://pwa") . '/';
    if (substr($files_path, 0, 7) == 'http://') {
      $files_path = str_replace('http://', '', $files_path);
    }
    elseif (substr($files_path, 0, 8) == 'https://') {
      $files_path = str_replace('https://', '', $files_path);
    }
    if (substr($files_path, 0, 4) == 'www.') {
      $files_path = str_replace('www.', '', $files_path);
    }
    $host = $this->getRequest()->server->get('HTTP_HOST');
    if (substr($files_path, 0, strlen($host)) == $host) {
      $files_path = str_replace($host, '', $files_path);
    }
    $wrapper = $this->streamWrapperManager->getViaScheme(
      $this->config('system.file')->get('default_scheme')
    );
    $realpath = $this->fileSystem->realpath(
      $this->config('system.file')->get('default_scheme') . "://"
    );

    $config = $this->config('pwa.config');

    $form['name'] = [
      "#type" => 'textfield',
      '#title' => $this->t('Web app name'),
      '#description' => $this->t("The name for the application that needs to be displayed to the user."),
      '#default_value' => $config->get('site_name'),
      '#required' => TRUE,
      "#maxlength" => 55,
      '#size' => 60,
    ];

    $form['short_name'] = [
      "#type" => 'textfield',
      "#title" => $this->t('Short name'),
      "#description" => $this->t("A short application name, this one gets displayed on the user's homescreen."),
      '#default_value' => $config->get('short_name'),
      '#required' => TRUE,
      '#maxlength' => 25,
      '#size' => 30,
    ];

    $form['lang'] = [
      "#type" => 'textfield',
      "#title" => $this->t('Lang'),
      "#description" => $this->t('The default language of the manifest.'),
      '#default_value' => $config->get('lang'),
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

    $form['start_url'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Start URL'),
      '#description' => $this->t('Start URL.'),
      '#default_value' => $config->get('start_url'),
      '#rows' => 1
    ];

    $form['scope'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scope'),
      '#description' => $this->t('Restricts what web pages can be viewed while the manifest is applied. If the user navigates outside the scope, it reverts to a normal web page inside a browser tab or window.'),
      '#default_value' => $config->get('scope'),
      '#maxlength' => 255,
      '#size' => 60,
    ];

    $form['theme_color'] = [
      "#type" => 'color',
      "#title" => $this->t('Theme color'),
      "#description" => $this->t('This color sometimes affects how the application is displayed by the OS.'),
      '#default_value' => $config->get('theme_color'),
      '#required' => TRUE,
    ];

    $form['background_color'] = [
      "#type" => 'color',
      "#title" => $this->t('Background color'),
      "#description" => $this->t('This color gets shown as the background when the application is launched'),
      '#default_value' => $config->get('background_color'),
      '#required' => TRUE,
    ];

    $id = $this->getDisplayValue($config->get('display'), TRUE);

    $form['display'] = [
      "#type" => 'select',
      "#title" => $this->t('Display type'),
      "#description" => $this->t('This determines which UI elements from the OS are displayed.'),
      "#options" => [
        '1' => $this->t('fullscreen'),
        '2' => $this->t('standalone'),
        '3' => $this->t('minimal-ui'),
        '4' => $this->t('browser'),
      ],
      '#default_value' => $id,
      '#required' => TRUE,
    ];

    $form['cross_origin'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('The site is behind HTTP basic authentication'),
      '#description' => $this->t('This will ensure any login credentials are passed to the manifest.'),
      '#title_display' => 'after',
      '#default_value' => $config->get('cross_origin'),
    ];

    $validators = [
      'file_validate_extensions' => ['png'],
      'file_validate_image_resolution' => ['512x512', '512x512'],
    ];

    $form['default_image'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use the theme image'),
      "#description" => $this->t('This depends on the logo that the theme generates'),
      "#default_value" => $config->get('default_image'),
    ];

    $form['images'] = [
      '#type' => 'fieldset',
      '#states' => [
        'invisible' => [
          ':input[name="default_image"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['images']['image'] = [
      '#type' => 'managed_file',
      '#name' => 'image',
      '#title' => $this->t('Image'),
      '#size' => 20,
      '#description' => $this->t('This image is your application icon. (png files only, format: (512x512)'),
      '#upload_validators' => $validators,
      '#upload_location' => 'public://pwa/',
    ];

    $bobTheHTMLBuilder = '<label>Current Image:</label> <br/> <img src="' . $config->get('image') . '" width="200"/>';
    if ($config->get('default_image') == 0) {
      $form['images']['current_image'] = [
        '#markup' => $bobTheHTMLBuilder,
        '#name' => 'current image',
        '#id' => 'current_image',
      ];
    }

    return parent::buildForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    parent::validateForm($form, $form_state);

    $default_image = $form_state->getValue('default_image');
    $img = $form_state->getValue(['image', 0]);
    $config = $this->config('pwa.config');

    if ($config->get('default_image') && !$default_image && !isset($img)) {
      $form_state->setErrorByName('image', $this->t('Upload a image, or chose the theme image.'));
    }

  }

  /**
   * {@inheritdoc}
   *
   * @todo Can we use the injected 'theme.manager' service rather than
   *   theme_get_setting() and then do
   *   $this->themeManager->getActiveTheme()->getLogo()?
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('pwa.config');

    $display = $this->getDisplayValue($form_state->getValue('display'), FALSE);

    $fid = $form_state->getValue(['image', 0]);
    $default_image = $form_state->getValue('default_image');

    if ($config->get('default_image') == 0) {
      if (isset($fid) || $default_image == 1) {
        $this->manifest->deleteImage();
      }
    }

    $configTheme = $this->config('system.theme');
    $nameOfDefaultTheme = $configTheme->get('default');

    // Get image from theme
    if ($default_image) {
      $theme_image = theme_get_setting('logo.url', $nameOfDefaultTheme);
      if (substr($theme_image, strlen($theme_image) - 3, 3) != 'png') {
        $this->messenger()
          ->addWarning($this->t('The theme image is not a .png file, your users may not be able to add this website to the homescreen.'));
      }
      $image_size = getimagesize(getcwd() . $theme_image);
      if ($image_size && ($image_size[0] != $image_size[1])) {
        $this->messenger()
          ->addWarning($this->t('The theme image is not a square, your application image maybe altered (recommended size: 512x512).'));
      }
    }

    // Save new config data
    $config
      ->set('site_name', $form_state->getValue('name'))
      ->set('short_name', $form_state->getValue('short_name'))
      ->set('theme_color', $form_state->getValue('theme_color'))
      ->set('background_color', $form_state->getValue('background_color'))
      ->set('description', $form_state->getValue('description'))
      ->set('lang', $form_state->getValue('lang'))
      ->set('display', $display)
      ->set('default_image', $default_image)
      ->set('start_url', $form_state->getValue('start_url'))
      ->set('scope', $form_state->getValue('scope'))
      ->set('cross_origin', $form_state->getValue('cross_origin'))
      ->save();

    // Save image if exists
    if (!empty($fid)) {
      $file = $this->fileStorage->load($fid);

      $file->setPermanent();
      $file->save();

      $this->fileUsage->add($file, 'PWA', 'PWA', $this->currentUser()->id());

      // Save new image.
      $wrapper = $this->streamWrapperManager->getViaScheme(
        $this->config('system.file')->get('default_scheme')
      );
      $files_path = '/' . $wrapper->basePath() . '/pwa/';
      $file_uri = $files_path . $file->getFilename();

      $file_path = $wrapper->realpath() . '/pwa/' . $file->getFilename();

      $config->set('image', $file_uri)->save();

      // for image_small
      $newSize = 192;
      $oldSize = 512;

      $src = imagecreatefrompng($file_path);
      $dst = imagecreatetruecolor($newSize, $newSize);

      // Make transparent background.
      $color = imagecolorallocatealpha($dst, 0, 0, 0, 127);
      imagefill($dst, 0, 0, $color);
      imagesavealpha($dst, TRUE);

      imagecopyresampled($dst, $src, 0, 0, 0, 0, $newSize, $newSize, $oldSize, $oldSize);
      $path_to_copy = $file_path . 'copy.png';
      $stream = fopen($path_to_copy, 'w+');
      if ($stream == TRUE) {
        imagepng($dst, $stream);
        $config->set('image_small', $file_uri . 'copy.png')
          ->save();
      }

      // for image_very_small
      $newSize = 144;
      $oldSize = 512;

      $src = imagecreatefrompng($file_path);
      $dst = imagecreatetruecolor($newSize, $newSize);

      // Make transparent background.
      $color = imagecolorallocatealpha($dst, 0, 0, 0, 127);
      imagefill($dst, 0, 0, $color);
      imagesavealpha($dst, TRUE);

      imagecopyresampled($dst, $src, 0, 0, 0, 0, $newSize, $newSize, $oldSize, $oldSize);
      $path_to_copy = $file_path . 'copy2.png';
      if ($stream = fopen($path_to_copy, 'w+')) {
        imagepng($dst, $stream);
        $config->set('image_very_small', $file_uri . 'copy2.png')
          ->save();
      }
    }
    $this->cacheTagsInvalidator->invalidateTags(['manifestjson']);

    parent::submitForm($form, $form_state);

  }

  /**
   *
   * function converts an id to a display string or a string to an id
   *
   * @param $value
   * @param boolean $needId
   *
   * @return int|string
   */
  private function getDisplayValue($value, $needId) {
    if ($needId) {
      $id = 1;
      switch ($value) {
        case 'standalone':
          $id = 2;
          break;
        case 'minimal-ui':
          $id = 3;
          break;
        case 'browser':
          $id = 4;
          break;
      }
      return $id;
    }
    else {
      $display = '';
      switch ($value) {
        case 1:
          $display = 'fullscreen';
          break;
        case 2:
          $display = 'standalone';
          break;
        case 3:
          $display = 'minimal-ui';
          break;
        case 4:
          $display = 'browser';
          break;
      }
    }
    return $display;
  }

}
