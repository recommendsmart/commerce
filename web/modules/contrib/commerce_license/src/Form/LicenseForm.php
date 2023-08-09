<?php

namespace Drupal\commerce_license\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for License edit forms.
 *
 * @ingroup commerce_license
 */
class LicenseForm extends ContentEntityForm {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity definition manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $entityDefinitionUpdateManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->entityDefinitionUpdateManager = $container->get('entity.definition_update_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    if (!$this->licenseProductVariationTypesExist()) {
      $message = $this->t('At least one product variation type must enable the <strong>Provides a license</strong> trait in order to create licenses.');
      $this->messenger()->addError($message);
      $this->logger('commerce_license')->error($message);
      return $form;
    }

    /** @var \Drupal\commerce_license\Entity\LicenseInterface $license */
    $license = $this->entity;
    $form = parent::form($form, $form_state);

    $form['#theme'] = 'commerce_license_edit_form';
    $form['#attached']['library'][] = 'commerce_license/form';
    $form['#tree'] = TRUE;
    // By default, an expiration type is preselected on the add form
    // because the field is required.
    // Select an empty value instead, to force the user to choose.
    $user_input = $form_state->getUserInput();
    if ($this->operation === 'add' &&
      $this->entity->get('expiration_type')->isEmpty()) {
      if (!empty($form['expiration_type']['widget'][0]['target_plugin_id'])) {
        $form['expiration_type']['widget'][0]['target_plugin_id']['#empty_value'] = '';
        if (empty($user_input['expiration_type'][0]['target_plugin_id'])) {
          $form['expiration_type']['widget'][0]['target_plugin_id']['#default_value'] = '';
          unset($form['expiration_type']['widget'][0]['target_plugin_configuration']);
        }
      }
    }

    // Remove the anonymous and authenticated roles from the role options.
    if (isset($form['license_role'])) {
      $roles_to_remove = [
        RoleInterface::ANONYMOUS_ID,
        RoleInterface::AUTHENTICATED_ID,
      ];
      $form['license_role']['widget']['#options'] = array_diff_key($form['license_role']['widget']['#options'], array_combine($roles_to_remove, $roles_to_remove));
    }

    // Changed must be sent to the client, for later overwrite error checking.
    $form['changed'] = [
      '#type' => 'hidden',
      '#default_value' => $license->getChangedTime(),
    ];

    $last_saved = $this->dateFormatter->format($license->getChangedTime());
    $created = $this->dateFormatter->format($license->getCreatedTime());
    $granted = $license->getGrantedTime() !== NULL ? $this->dateFormatter->format($license->getGrantedTime()) : NULL;
    $expires_time = $license->getExpiresTime();
    $expires = NULL;
    if ($expires_time !== NULL) {
      if ($expires_time > 0) {
        $expires = $this->dateFormatter->format($license->getExpiresTime());
      }
      else {
        $expires = $this->t('Never');
      }
    }
    $renewed = $license->getRenewedTime() !== NULL ? $this->dateFormatter->format($license->getRenewedTime()) : NULL;
    $form['advanced'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-meta']],
      '#weight' => 99,
    ];
    $form['meta'] = [
      '#attributes' => ['class' => ['entity-meta__header']],
      '#type' => 'container',
      '#group' => 'advanced',
      '#weight' => -100,
      'state' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $license->getState()->getLabel(),
        '#attributes' => [
          'class' => ['entity-meta__title'],
        ],
        // Hide the rendered state if there's a widget for it.
        '#access' => empty($form['store_id']),
      ],
      'date' => NULL,
    ];
    if ($expires !== NULL) {
      $form['meta']['expires'] = $this->fieldAsReadOnly($this->t('Expires'), $expires);
    }
    if ($renewed !== NULL) {
      $form['meta']['renewed'] = $this->fieldAsReadOnly($this->t('Renewed'), $renewed);
    }
    if ($granted !== NULL) {
      $form['meta']['granted'] = $this->fieldAsReadOnly($this->t('Granted'), $granted);
    }
    $form['meta']['changed'] = $this->fieldAsReadOnly($this->t('Changed'), $last_saved);
    $form['meta']['created'] = $this->fieldAsReadOnly($this->t('Created'), $created);

    $form['owner'] = [
      '#type' => 'details',
      '#title' => $this->t('Owner'),
      '#group' => 'advanced',
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['license-form-owner'],
      ],
      '#weight' => 91,
    ];

    // Move uid widget to the sidebar, or provide read-only alternatives.
    $owner = $license->getOwner();
    if (isset($form['uid'])) {
      $form['uid']['#group'] = 'owner';
    }
    elseif ($owner->isAuthenticated()) {
      $owner_link = $owner->toLink()->toString();
      $form['owner']['uid'] = $this->fieldAsReadOnly($this->t('Owner'), $owner_link);
    }

    return $form;
  }

  /**
   * Builds a read-only form element for a field.
   *
   * @param string $label
   *   The element label.
   * @param string $value
   *   The element value.
   *
   * @return array
   *   The form element.
   */
  protected function fieldAsReadOnly(string $label, string $value): array {
    return [
      '#type' => 'item',
      '#wrapper_attributes' => [
        'class' => ['container-inline'],
      ],
      '#title' => $label,
      '#markup' => $value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);

    $entity = $this->entity;

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label License.', [
          '%label' => $entity->label(),
        ]));

        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label License.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.commerce_license.canonical', ['commerce_license' => $entity->id()]);
  }

  /**
   * {@inheritDoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = [];
    if ($this->licenseProductVariationTypesExist()) {
      $actions = parent::actions($form, $form_state);
    }
    return $actions;
  }

  /**
   * Whether any product variations with the "commerce_license" trait exist.
   *
   * If no product variations with the "commerce_license" trait exist, the
   * add form will have multiple issues.
   *
   * @return bool
   *   Whether any exist.
   */
  protected function licenseProductVariationTypesExist(): bool {
    return ($this->entityDefinitionUpdateManager->getFieldStorageDefinition('license_type', 'commerce_product_variation') !== NULL);
  }

}
