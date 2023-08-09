<?php

namespace Drupal\commerce_license\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The Log Subscriber reacts to license events and logs them.
 */
class LogEventSubscriber implements EventSubscriberInterface {

  /**
   * The log storage.
   *
   * @var \Drupal\commerce_log\LogStorageInterface
   */
  protected $logStorage;


  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new OrderEventSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
    if ($this->moduleHandler->moduleExists('commerce_log')) {
      $this->logStorage = $entity_type_manager->getStorage('commerce_log');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'commerce_license.post_transition' => ['onLicensePostTransition'],
    ];
  }

  /**
   * Creates a log on license state update.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onLicensePostTransition(WorkflowTransitionEvent $event) {
    if (!$this->moduleHandler->moduleExists('commerce_log')) {
      return;
    }
    $transition = $event->getTransition();
    /** @var \Drupal\commerce_license\Entity\LicenseInterface $license */
    $license = $event->getEntity();
    $original_state_id = $license->getState()->getOriginalId();
    $original_state = $event->getWorkflow()->getState($original_state_id);

    $this->logStorage->generate($license, 'license_state_updated', [
      'transition_label' => $transition->getLabel(),
      'from_state' => $original_state ? $original_state->getLabel() : $original_state_id,
      'to_state' => $license->getState()->getLabel(),
    ])->save();
  }

}
