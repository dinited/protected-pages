<?php

namespace Drupal\protected_pages\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Password\PasswordInterface;
use Drupal\path_alias\AliasManager;
use Drupal\protected_pages\ProtectedPagesStorage;
use Drupal\protected_pages\Validator\WildCardPathValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an edit protected page form.
 */
class ProtectedPagesEditForm extends FormBase {

  /**
   * The protected pages storage service.
   *
   * @var \Drupal\protected_pages\ProtectedPagesStorage
   */
  protected $protectedPagesStorage;

  /**
   * The path validator.
   *
   * @var \Drupal\protected_pages\Validator\WildCardPathValidator
   */
  protected $pathValidator;

  /**
   * Provides the password hashing service object.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $password;

  /**
   * Provides messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * Path alias manager.
   *
   * @var \Drupal\path_alias\AliasManager
   */
  protected $aliasManager;

  /**
   * Constructs a new ProtectedPagesAddForm.
   *
   * @param \Drupal\protected_pages\Validator\WildCardPathValidator $path_validator
   *   The path validator.
   * @param \Drupal\Core\Password\PasswordInterface $password
   *   The password hashing service.
   * @param \Drupal\protected_pages\ProtectedPagesStorage $protectedPagesStorage
   *   The protected pages storage.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   * @param \Drupal\path_alias\AliasManager $aliasManager
   *   The path alias manager service.
   */
  public function __construct(WildCardPathValidator $path_validator, PasswordInterface $password, ProtectedPagesStorage $protectedPagesStorage, Messenger $messenger, AliasManager $aliasManager) {
    $this->pathValidator = $path_validator;
    $this->password = $password;
    $this->protectedPagesStorage = $protectedPagesStorage;
    $this->messenger = $messenger;
    $this->aliasManager = $aliasManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('protected_pages.wildcard_path_validator'),
      $container->get('password'),
      $container->get('protected_pages.storage'),
      $container->get('messenger'),
      $container->get('path_alias.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'protected_pages_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = NULL) {
    $fields = ['path'];
    $conditions = [];
    $conditions['general'][] = [
      'field' => 'pid',
      'value' => $pid,
      'operator' => '=',
    ];

    $path = $this->protectedPagesStorage->loadProtectedPage($fields, $conditions, TRUE);

    $form['rules_list'] = [
      '#title' => $this->t("Edit Protected Page relative path and password."),
      '#type' => 'details',
      '#description' => $this->t('Please enter the relative path and its corresponding
    password. When user opens this url, they will asked to enter password to
    view this page. For example, "/node/5", "/new-events" etc.'),
      '#open' => TRUE,
    ];
    $form['rules_list']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Relative path'),
      '#default_value' => $path,
      '#description' => $this->t('Enter relative Drupal path. For example, "/node/5", "/new-events" etc.'),
      '#required' => TRUE,
    ];
    $form['rules_list']['password'] = [
      '#type' => 'password_confirm',
      '#size' => 25,
    ];
    $form['rules_list']['pid'] = [
      '#type' => 'hidden',
      '#value' => $pid,
    ];
    $form['rules_list']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $entered_path = rtrim(trim($form_state->getValue('path')), " \\/");

    if (substr($entered_path, 0, 1) != '/') {
      $form_state->setErrorByName('path', $this->t('The path needs to start with a slash.'));
    }
    else {
      $normal_path = $this->aliasManager->getPathByAlias($form_state->getValue('path'));
      $path_alias = mb_strtolower($this->aliasManager->getAliasByPath($form_state->getValue('path')));
      if (!$this->pathValidator->isValid($normal_path)) {
        $form_state->setErrorByName('path', $this->t('Please enter a correct path!'));
      }
      $fields = ['pid'];
      $conditions = [];
      $conditions['or'][] = [
        'field' => 'path',
        'value' => $normal_path,
        'operator' => '=',
      ];
      $conditions['or'][] = [
        'field' => 'path',
        'value' => $path_alias,
        'operator' => '=',
      ];
      $conditions['and'][] = [
        'field' => 'pid',
        'value' => $form_state->getValue('pid'),
        'operator' => '<>',
      ];

      $pid = $this->protectedPagesStorage->loadProtectedPage($fields, $conditions, TRUE);
      if ($pid) {
        $form_state->setErrorByName('path', $this->t('Duplicate path entry is not allowed. There is already a path or its alias exists.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $page_data = [];
    $password = $form_state->getValue('password');
    if (!empty($password)) {
      $page_data['password'] = $this->password->hash(Html::escape($password));
    }
    $page_data['path'] = Html::escape($form_state->getValue('path'));

    $this->protectedPagesStorage->updateProtectedPage($page_data, $form_state->getValue('pid'));
    $this->messenger->addMessage($this->t('The protected page settings have been successfully saved.'));
    drupal_flush_all_caches();
    $form_state->setRedirect('protected_pages_list');
  }

}
