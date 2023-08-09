<?php

namespace Drupal\commerce_email\Plugin\Commerce\EmailEvent;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OrderTransitionDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The workflow manager.
   *
   * @var \Drupal\state_machine\WorkflowManagerInterface
   */
  protected $workflowManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = new static();
    $instance->workflowManager = $container->get('plugin.manager.workflow');
    $instance->stringTranslation = $container->get('string_translation');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $derivative_definitions = [];
    $workflows = $this->workflowManager->getDefinitions();

    foreach ($workflows as $workflow) {
      if ($workflow['group'] !== 'commerce_order') {
        continue;
      }
      foreach ($workflow['transitions'] as $id => $transition) {
        // The order place transition is already handled, skip it.
        if ($id === 'place') {
          continue;
        }
        $derivative_definitions[$id] = array_merge($base_plugin_definition, [
          'label' => $this->t('Order "@transition" transition', [
            '@transition' => $id,
          ]),
          'event_name' => "commerce_order.$id.post_transition",
        ]);
      }
    }

    return $derivative_definitions;
  }

}