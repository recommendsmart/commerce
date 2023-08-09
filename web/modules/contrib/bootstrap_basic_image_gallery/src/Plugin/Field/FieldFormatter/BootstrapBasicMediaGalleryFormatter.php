<?php

namespace Drupal\bootstrap_basic_image_gallery\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\file\FileStorageInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;

/**
 * Plugin implementation of the 'BootstrapBasicMediaGalleryFormatter' formatter.
 *
 * @FieldFormatter(
 *   id = "bootstrap_basic_media_gallery_formatter",
 *   label = @Translation("Bootstrap Basic Media Gallery"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class BootstrapBasicMediaGalleryFormatter extends ImageFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Image style storage.
   *
   * @var Drupal\Core\Entity\EntityStorageInterface
   */
  protected $imageStyleStorage;

  /**
   * Theme manager.
   *
   * @var Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Configuration.
   *
   * @var Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The storage handler class for files.
   *
   * @var \Drupal\file\FileStorage
   */
  protected $fileStorage;

  /**
   * The media view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $mediaViewBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityStorageInterface $image_style_storage, ThemeManagerInterface $theme_manager, ConfigFactory $configFactory, EntityDisplayRepositoryInterface $entity_display_repository, FileStorageInterface $file_storage, EntityViewBuilderInterface $media_view_builder) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->imageStyleStorage = $image_style_storage;
    $this->themeManager = $theme_manager;
    $this->configFactory = $configFactory;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->fileStorage = $file_storage;
    $this->mediaViewBuilder = $media_view_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('theme.manager'),
      $container->get('config.factory'),
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager')->getStorage('file'),
      $container->get('entity_type.manager')->getViewBuilder('media')
    );
  }

  /**
   * {@inheritdoc}
   *
   * This has to be overridden because FileFormatterBase expects $item to be
   * of type \Drupal\file\Plugin\Field\FieldType\FileItem and calls
   * isDisplayed() which is not in FieldItemInterface.
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return [];
    }

    // Determine the width of the column.
    $thumbnailsPerRow = $this->getSetting('thumbnails_per_row');
    $thumbnailWidth = 100;
    if ($thumbnailsPerRow > 0) {
      $thumbnailWidth = (100 / $thumbnailsPerRow);
    }
    // Build the render array to pass to the template file.
    $imageGallery = [
      '#attached' => [
        'library' => [
          // Attach the basic library for this module to provide needed css.
          'bootstrap_basic_image_gallery/bootstrap_basic',
          // Attach the library which allows previewing images on hover.
          'bootstrap_basic_image_gallery/hover_preview_media',
        ],
      ],
      '#theme' => 'bootstrap_basic_media_gallery',
      '#main' => [],
      '#thumbnails' => [
        'class' => 'bscol-' . $thumbnailsPerRow,
        'width' => $thumbnailWidth,
        'images' => [],
      ],
      '#lazyload' => ($this->getSetting('lazyload') ? 'lazy' : ''),
      '#modal' => [
        'id' => Html::getUniqueId('bootstrap-basic-image-gallery-modal'),
        'label' => $this->fieldDefinition->getLabel(),
      ],
      '#carousel' => [
        'id' => Html::getUniqueId('bootstrap-basic-image-gallery-carousel'),
        'autoplay' => ($this->getSetting('carousel_autorotate') ? 'carousel' : 'false'),
        'interval' => $this->getSetting('carousel_interval'),
        'images' => [],
      ],
    ];

    // Load the configuration settings.
    $configuration_settings = $this->configFactory->get('bootstrap_basic_image_gallery.settings');

    if (!$configuration_settings->get('prevent_load_bootstrap')) {
      // Check to make sure the theme isn't already including bootstrap.
      $bootstrap_included = FALSE;
      foreach ($this->themeManager->getActiveTheme()->getLibraries() as $library) {
        if ($bootstrap_included = preg_match('%^bootstrap%', $library)) {
          break;
        }
      }

      // Attach the bootstrap core library if its not already included in theme.
      if (!$bootstrap_included) {
        $imageGallery['#attached']['library'][] = 'bootstrap_basic_image_gallery/bootstrap_components';
      }
    }

    // Attach the lazy load library.
    if ($this->getSetting('lazyload')) {
      $imageGallery['#attached']['library'][] = 'bootstrap_basic_image_gallery/lazyload';
    }

    // Collect cache tags to be added for each thumbnail in the field.
    $thumbnail_cache_tags = [];
    $thumbnail_image_style = $this->getSetting('thumbnail_image_style');
    if (!empty($thumbnail_image_style)) {
      $image_style = $this->imageStyleStorage->load($thumbnail_image_style);
      $thumbnail_cache_tags = $image_style->getCacheTags();
    }

    // Get the main media view mode.
    $main_media_view_mode = $this->getSetting('main_media_view_mode');

    // Get the modal image style.
    $modal_image_style_setting = $this->getSetting('modal_image_style');
    if (!empty($modal_image_style_setting)) {
      $modal_image_style = $this->imageStyleStorage->load($modal_image_style_setting);
    }

    // Loop over the files and render them.
    foreach ($files as $delta => $media) {

      $item = $media->get('thumbnail')->first();
      $source = $media->getSource()->getSourceFieldValue($media);
      if ($source) {
        $image_uri = $this->fileStorage->load($source)->getFileUri();
        $url = \Drupal::service('file_url_generator')->generate($image_uri);
      }
      else {
        return [];
      }

      // Extract field item attributes for the theme function, and unset them
      // from the $item so that the field template does not re-render them.
      // $item = $file->_referringItem;.
      $item_attributes = $item->_attributes;
      unset($item->_attributes);

      // Create the main image container.
      if ($delta == 0) {
        // Collect cache tags to be added for each item in the field.
        $modal_cache_tags = [];
        if (isset($modal_image_style)) {
          $modal_cache_tags = $modal_image_style->getCacheTags();
        }
        $imageGallery['#main'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => "main-media-set",
          ],
        ];
      }

      $media_id = $media->id();
      $data_attr_media_id = Html::getUniqueId('main-media-id-' . $media_id);
      $imageGallery['#main']["main_media_$media_id"] = [
        '#type' => 'container',
        '#attributes' => [
          'data-main-media-id' => $data_attr_media_id,
          'class' => [($delta == 0) ? NULL : 'd-none'],
        ],
      ];
      $imageGallery['#main']["main_media_$media_id"][] = $this->mediaViewBuilder->view($media, $main_media_view_mode);

      $item_attributes['data-main-media-id-src'] = $data_attr_media_id;

      // Generate the thumbnail.
      if ($thumbnailsPerRow > 0) {
        $imageGallery['#thumbnails']['images'][] = [
          '#theme' => 'image_formatter',
          '#item' => $item,
          '#item_attributes' => $item_attributes,
          '#image_style' => $thumbnail_image_style,
          '#cache' => [
            'tags' => Cache::mergeTags($thumbnail_cache_tags, $media->getCacheTags()),
          ],
          '#media_item' => $media,
        ];
      }

      // Add the carousel image container.
      $imageGallery['#carousel']['images'][$delta] = [
        '#theme' => 'image_formatter',
        '#item' => $item,
        '#item_attributes' => $item_attributes,
        '#image_style' => $modal_image_style_setting,
        '#caption' => $item->__get('alt'),
        '#cache' => [
          'tags' => Cache::mergeTags($modal_cache_tags, $media->getCacheTags()),
        ],
        '#media_item' => $media,
      ];
      // Pass image differently depending on if we are lazy loading.
      if ($this->getSetting('lazyload')) {
        $imageGallery['#carousel']['images'][$delta]['#attributes']['data-src'] = $url->toString();
      }
      else {
        $imageGallery['#carousel']['images'][$delta]['#uri'] = $image_uri;
      }
    }
    return [$imageGallery];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary[] = $this->t('Main media view mode: <strong>@settingValue</strong>', [
      '@settingValue' => (empty($this->getSetting('main_media_view_mode')) ? 'Default' : $this->getSetting('main_media_view_mode')),
    ]);
    $summary[] = $this->t('Thumbnails Per Row: <strong>@settingValue</strong>', [
      '@settingValue' => $this->getSetting('thumbnails_per_row'),
    ]);
    $summary[] = $this->t('Thumbnail Image Style: <strong>@settingValue</strong>', [
      '@settingValue' => (empty($this->getSetting('thumbnail_image_style')) ? 'Original Image' : $this->getSetting('thumbnail_image_style')),
    ]);
    $summary[] = $this->t('Modal Image Style: <strong>@settingValue</strong>', [
      '@settingValue' => (empty($this->getSetting('modal_image_style')) ? 'Original Image' : $this->getSetting('modal_image_style')),
    ]);
    $summary[] = $this->t('Autorotate Carousel? <strong>@settingValue</strong>', [
      '@settingValue' => ($this->getSetting('carousel_autorotate') ? 'Yes' : 'No'),
    ]);
    if ($this->getSetting('carousel_autorotate')) {
      $summary[] = $this->t('Autorotate Carousel Interval <strong>@settingValue</strong>', [
        '@settingValue' => (empty($this->getSetting('carousel_interval')) ? 5000 : $this->getSetting('carousel_interval')),
      ]);
    }
    $summary[] = $this->t('Lazy Load Images? <strong>@settingValue</strong>', [
      '@settingValue' => ($this->getSetting('lazyload') ? 'Yes' : 'No'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $image_styles = image_style_options(FALSE);

    $elements = [];
    $elements['main_media_view_mode'] = [
      '#title' => $this->t('Main media view mode'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('main_media_view_mode'),
      '#empty_option' => $this->t('None (Default)'),
      '#options' => $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type')),
      '#description' => $this->t('Media view mode used for rendering the main image.'),
    ];
    $elements['thumbnails_per_row'] = [
      '#type' => 'number',
      '#title' => $this->t('Thumbnails Per Row'),
      '#description' => $this->t('Number of thumbnails displayed per row under the main image.'),
      '#min' => 0,
      '#default_value' => $this->getSetting('thumbnails_per_row'),
    ];
    $elements['thumbnail_image_style'] = [
      '#title' => $this->t('Thumbnail Image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('thumbnail_image_style'),
      '#empty_option' => $this->t('None (original image)'),
      '#options' => $image_styles,
      '#description' => $this->t('Image style used for rendering the thumbnails.'),
    ];
    $elements['modal_image_style'] = [
      '#title' => $this->t('Modal Image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('modal_image_style'),
      '#empty_option' => $this->t('None (original image)'),
      '#options' => $image_styles,
      '#description' => $this->t('Image style used for rendering the image in the modal popup (on click).'),
    ];
    $elements['carousel_autorotate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autorotate Carousel?'),
      '#description' => $this->t('Decides whether or not the carousel auto-rotates after opening.'),
      '#default_value' => $this->getSetting('carousel_autorotate'),
    ];
    $elements['carousel_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Autorotate Carousel Interval'),
      '#description' => $this->t('The amount of time to delay (in milliseconds) between automatically cycling an image.'),
      '#default_value' => $this->getSetting('carousel_interval'),
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][carousel_autorotate]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $elements['lazyload'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Lazy Load Images?'),
      '#description' => $this->t('Decides whether or not the images in the popup will be lazy loaded. If yes, the images will not be loaded by the user until they are viewed. This speeds up page loading time.'),
      '#default_value' => $this->getSetting('lazyload'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'main_media_view_mode' => '',
      'thumbnail_image_style' => '',
      'thumbnails_per_row' => '3',
      'modal_image_style' => '',
      'carousel_autorotate' => 0,
      'carousel_interval' => 5000,
      'lazyload' => 1,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    foreach (['thumbnail_image_style', 'modal_image_style'] as $setting_name) {
      $style_id = $this->getSetting($setting_name);
      /** @var \Drupal\image\ImageStyleInterface $style */
      if ($style_id && $style = $this->imageStyleStorage->load($style_id)) {
        // If this formatter uses a valid image style to display the image, add
        // the image style configuration entity as dependency of this formatter.
        $dependencies[$style->getConfigDependencyKey()][] = $style->getConfigDependencyName();
      }
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $changed = parent::onDependencyRemoval($dependencies);
    foreach (['thumbnail_image_style', 'modal_image_style'] as $setting_name) {
      $style_id = $this->getSetting($setting_name);
      /** @var \Drupal\image\ImageStyleInterface $style */
      if ($style_id && $style = $this->imageStyleStorage->load($style_id)) {
        if (!empty($dependencies[$style->getConfigDependencyKey()][$style->getConfigDependencyName()])) {
          $replacement_id = $this->imageStyleStorage->getReplacementId($style_id);
          // If a valid replacement has been provided in the storage, replace
          // the image style with the replacement and signal that the formatter
          // plugin settings were updated.
          if ($replacement_id && $this->imageStyleStorage->load($replacement_id)) {
            $this->setSetting($setting_name, $replacement_id);
            $changed = TRUE;
          }
        }
      }
    }
    return $changed;
  }

}
