<?php

namespace Drupal\block_visibility_conditions\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Not {ContentEntityType}'-base condition.
 *
 * The block will still be shown on all other pages. This differs from the
 * negated condition, which will only be evaluated on entity type pages, which
 * means the block won't be shown on other pages like views.
 */
abstract class NotConditionPluginBase extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  protected const CONTENT_ENTITY_TYPE = '';

  /**
   * The EntityTypeManager object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The CurrentRouteMatch object.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The content entity type.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityType
   */
  protected $contentEntityType;

  /**
   * The bundle.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityType
   */
  protected $bundle;

  /**
   * Creates a new NotNodeType instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The route match.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, CurrentRouteMatch $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;

    /** @var \Drupal\Core\Config\Entity\ConfigEntityType $contentEntityType */
    $this->contentEntityType = $this->entityTypeManager->getDefinition(static::CONTENT_ENTITY_TYPE);
    $this->bundle = $this->entityTypeManager->getDefinition($this->contentEntityType->getBundleOf());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Disallow negation of this condition.
    unset($form['negate']);

    // Create list of content types.
    $options = [];
    $node_types = $this->entityTypeManager->getStorage(static::CONTENT_ENTITY_TYPE)
      ->loadMultiple();
    foreach ($node_types as $type) {
      $options[$type->id()] = $type->label();
    }
    $form['bundles'] = [
      '#title' => $this->contentEntityType->getLabel(),
      '#description' => $this->t('The %content_entity_type_label(s) to hide the block on. The block will still be shown on all other pages, including non-%bundle_label pages.<br>This differs from the negated condition "%content_entity_type_label", which will only be evaluated on %bundle_label pages, which means the block won\'t be shown on other pages like views.', [
        '%content_entity_type_label' => $this->contentEntityType->getLabel(),
        '%bundle_label' => $this->bundle->getLabel(),
      ]),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $this->configuration['bundles'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['bundles'] = array_filter($form_state->getValue('bundles'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    if (count($this->configuration['bundles']) > 1) {
      $bundles = $this->configuration['bundles'];
      $last = array_pop($bundles);
      $bundles = implode(', ', $bundles);

      return $this->t('The %content_entity_type_label is %bundles or %last.', [
        '%content_entity_type_label' => $this->contentEntityType->getLabel(),
        '%bundles' => $bundles,
        '%last' => $last,
      ]);
    }
    $bundle = reset($this->configuration['bundles']);

    return $this->t('The %content_entity_type_label is %bundle', [
      '%content_entity_type_label' => $this->contentEntityType->getLabel(),
      '%bundle' => $bundle,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    // Check if a setting has been set.
    if (empty($this->configuration['bundles'])) {
      return TRUE;
    }

    // Check if we are dealing with a node.
    $entity = $this->routeMatch->getParameter($this->contentEntityType->getBundleOf());
    if (is_scalar($entity)) {
      $entity_storage = $this->entityTypeManager->getStorage($this->contentEntityType->getBundleOf());
      $entity = $entity_storage->load($entity);
    }

    if (empty($entity)) {
      return TRUE;
    }

    return empty($this->configuration['bundles'][$entity->bundle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['bundles' => []] + parent::defaultConfiguration();
  }

}
