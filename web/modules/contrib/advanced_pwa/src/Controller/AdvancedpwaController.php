<?php

namespace Drupal\advanced_pwa\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\advanced_pwa\Model\SubscriptionsDatastorage;
use Symfony\Component\HttpFoundation\Response;
use Drupal\image\Entity\ImageStyle;
use Psr\Log\LoggerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Class AdvancedpwaController.
 */
class AdvancedpwaController extends ControllerBase {

  protected $database;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('database'),
        $container->get('logger.factory')->get('advanced_pwa'),
        $container->get('entity_type.manager'),
        $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $database, LoggerInterface $logger, EntityTypeManagerInterface $entity_type, StateInterface $state) {
    $this->database = $database;
    $this->logger = $logger;
    try {
      $this->fileStorage = $entity_type->getStorage('file');
    } catch (InvalidPluginDefinitionException $e) {
    } catch (PluginNotFoundException $e) {
    }
    $this->state = $state;
  }

  /**
   * Subscribe.
   *
   * @return string
   *   Return Hello string.
   */
  public function subscribe(Request $request) {
    if ($request) {
      $message = 'Subscribe: ' . $request->getContent();
      $this->logger->info($message);

      $data = json_decode($request->getContent(), TRUE);
      $entry['subscription_endpoint'] = $data['endpoint'];
      $entry['subscription_data'] = serialize(['key' => $data['key'], 'token' => $data['token']]);
      $entry['registered_on'] = strtotime(date('Y-m-d H:i:s'));
      $success = SubscriptionsDatastorage::insert($entry);
      return new JsonResponse([$success]);
    }
  }

  /**
   * Un-subscribe.
   *
   * @return string
   *   Return Hello string.
   */
  public function unsubscribe(Request $request) {
    if ($request) {
      $message = 'Un-subscribe : ' . $request->getContent();
      $this->logger->info($message);

      $data = json_decode($request->getContent(), TRUE);
      $entry['subscription_endpoint'] = $data['endpoint'];
      $success = SubscriptionsDatastorage::delete($entry);
      return new JsonResponse([$success]);
    }
  }

  /**
   * List of all subscribed users.
   */
  public function subscriptionList() {
    // The table description.
    $header = [
      ['data' => $this->t('Id')],
      ['data' => $this->t('Subscription Endpoint')],
      ['data' => $this->t('Registeration Date')],
    ];
    $getFields = [
      'id',
      'subscription_endpoint',
      'registered_on',
    ];
    $query = $this->database->select(SubscriptionsDatastorage::$subscriptionTable);
    $query->fields(SubscriptionsDatastorage::$subscriptionTable, $getFields);
    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $result = $pager->execute();

    // Populate the rows.
    $rows = [];
    foreach ($result as $row) {
      $rows[] = [
        'data' => [
          'id' => $row->id,
          'register_id' => $row->subscription_endpoint,
          'date' => date('d/m/Y', $row->registered_on),
        ],
      ];
    }
    if (empty($rows)) {
      $markup = $this->t('No record found.');
    }
    else {
      $markup = $this->t('List of All Subscribed Users.');
    }
    $build = [
      '#markup' => $markup,
    ];
    // Generate the table.
    $build['config_table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];
    return $build;
  }

  /**
   * Route generates the manifest file for the browser.
   */
  public function advancedpwaGetManifest() {
    $configurations = $this->config('advanced_pwa.settings');
    $advanced_pwa_enabled = $configurations->get('status.all');
    if (!$advanced_pwa_enabled) {
      return new JsonResponse([]);
    }

    // Get all the current settings stored in advanced_pwa.settings.
    $config = $configurations->get();

    // Array filter used to filter the "_core:" key from the output.
    $allowed = [
      'name',
      'short_name',
      'icons',
      'start_url',
      'background_color',
      'theme_color',
      'display',
      'orientation',
    ];

    $filtered = [];

    foreach ($config as $config_key => $config_value) {
      if (!in_array($config_key, $allowed)) {
        continue;
      }

      if ($config_key == 'icons') {
        // Get the specific icons. Needed to get the correct path of the file.
        $icon = $configurations->get('icons.icon');

        // Get the file id and path.
        $fid = $icon[0];
        $file = $this->fileStorage->load($fid);
        $path = $file->getFileUri();

        $all_image_styles = ImageStyle::loadMultiple();
        foreach ($all_image_styles as $img) {
          $name_of_img = $img->getName();
          if (strpos($name_of_img, 'advanced_pwa') !== FALSE) {
            $image_style_config = (ImageStyle::load($name_of_img)->getEffects()->getConfiguration());
            foreach ($image_style_config as $config) {
              $image_style_height = $config['data']['height'];
              $image_style_width = $config['data']['width'];
              //TO DO : fix $imgdimensions to be defined
              $imgdimensions = $image_style_height . "x" . $image_style_width;
            }
            $image_styles[$name_of_img] = $imgdimensions;
          }
        }

        $config_value = [];

        foreach ($image_styles as $key => $value) {
          $config_value[] = [
            'src' => file_url_transform_relative(ImageStyle::load($key)->buildUrl($path)),
            'sizes' => $value,
            'type' => 'image/png',
          ];
        }
      }
      $filtered[$config_key] = $config_value;
    }

    // Finally, after all the magic went down we return a manipulated and
    // filtered array of our advanced_pwa.settings and output it to JSON format.
    return new JsonResponse($filtered);
  }

  /**
   * Import service worker js.
   */
  public function advancedpwaServiceWorkerFileData() {
    $query_string = $this->state->get('system.css_js_query_string') ?: 0;
    $path = drupal_get_path('module', 'advanced_pwa');
    $data = 'importScripts("' . $path . '/js/service_worker.js?' . $query_string . '");';

    return new Response($data, 200, [
      'Content-Type' => 'application/javascript',
      'Service-Worker-Allowed' => '/',
    ]);
  }

}
