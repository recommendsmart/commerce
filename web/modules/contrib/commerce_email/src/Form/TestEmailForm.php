<?php

namespace Drupal\commerce_email\Form;

use Drupal\commerce_email\EmailSenderInterface;
use Drupal\commerce_email\Entity\EmailInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests emails.
 */
class TestEmailForm extends FormBase {

  /**
   * The email sender.
   *
   * @var \Drupal\commerce_email\EmailSenderInterface
   */
  protected $emailSender;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_email.email_sender'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(EmailSenderInterface $email_sender, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->emailSender = $email_sender;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_email_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EmailInterface $commerce_email = NULL) {
    // Exit if for some reason a valid email entity is not passed in.
    if (empty($commerce_email)) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('Email not found.'),
      ];
    }

    $form_state->set('commerce_email', $commerce_email);

    // Get the target entity type ID and definition.
    $target_entity_type_id = $commerce_email->getTargetEntityTypeId();
    $target_entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);
    $token_types = array_merge([$target_entity_type_id], $commerce_email->getEvent()->getRelatedEntityTypeIds());

    // Prompt the user to supply the variables derived from the event context.
    $form['context'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Test context'),
    ];
    $form['context']['target_entity'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => $target_entity_type_id,
      '#title' => $target_entity_type->getLabel(),
      '#description' => $this->t('Reference the primary entity to use for token replacement in this email.'),
    ];

    // Test emails do not currently support related entity tokens. Only show a
    // notice if the email event actually uses them.
    // @see https://www.drupal.org/project/commerce_email/issues/3300087
    if (!empty($commerce_email->getEvent()->getRelatedEntityTypeIds())) {
      $form['context']['target_entity']['#description'] .= '<br />' . $this->t('Note: related entity tokens are <a href="https://www.drupal.org/project/commerce_email/issues/3300087">not currently replaced</a> in test emails.');
    }

    // Allow the user to override the default settings of this email.
    $form['from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From'),
      '#maxlength' => 255,
      '#default_value' => $commerce_email->getFrom(),
      '#required' => TRUE,
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('To'),
      '#maxlength' => 255,
      '#default_value' => $commerce_email->getTo(),
      '#required' => TRUE,
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['cc'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cc'),
      '#maxlength' => 255,
      '#default_value' => $commerce_email->getCc(),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['bcc'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bcc'),
      '#maxlength' => 255,
      '#default_value' => $commerce_email->getBcc(),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#maxlength' => 255,
      '#default_value' => '[TEST] ' . $commerce_email->getSubject(),
      '#required' => TRUE,
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#default_value' => $commerce_email->getBody(),
      '#rows' => 10,
      '#required' => TRUE,
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => $token_types,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Test email',
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getUserInput();

    /** @var \Drupal\commerce_email\Entity\EmailInterface $email */
    $commerce_email = $form_state->get('commerce_email');

    // Override the email configuration with form input.
    $commerce_email->setFrom($values['from']);
    $commerce_email->setTo($values['to']);
    $commerce_email->setCc($values['cc']);
    $commerce_email->setBcc($values['bcc']);
    $commerce_email->setSubject($values['subject']);
    $commerce_email->setBody($values['body']);

    $entity_storage = $this->entityTypeManager->getStorage($commerce_email->getTargetEntityTypeId());
    // If a target entity wasn't specified, generate a sample entity.
    if (!$form_state->getValue('target_entity')) {
      $bundle_ids = $this->entityTypeBundleInfo->getBundleInfo($commerce_email->getTargetEntityTypeId());
      $random_bundle = array_rand($bundle_ids);
      $entity = $entity_storage->createWithSampleValues($random_bundle);
    }
    else {
      $entity = $entity_storage->load($form_state->getValue('target_entity'));
    }
    // Send the email using the referenced entity.
    $this->emailSender->send($commerce_email, $entity);

    $this->messenger()->addMessage($this->t('Test email sent.'));
  }

}
