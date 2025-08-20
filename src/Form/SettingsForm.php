<?php

namespace Drupal\du_tuition_calculator\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SettingsForm.
 *
 * @package Drupal\du_tuition_calculator\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Constructs a \Drupal\du_tuition_calculator\Form\SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountProxy $current_user) {
    parent::__construct($config_factory);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['du_tuition_calculator.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'du_tuition_calculator_config_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('du_tuition_calculator.settings');

    $admin_restrict = !$this->currentUser->hasPermission('administer DU tuition calculator');

    $form['admin'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Admin Settings'),
    ];

    // Connection information.
    $form['admin']['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Location'),
      '#description' => $this->t('URL to Events Endpoint'),
      '#default_value' => $config->get('api_url'),
      '#disabled' => $admin_restrict,
    ];
    $form['admin']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id'),
      '#disabled' => $admin_restrict,
    ];
    $form['admin']['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#default_value' => $config->get('client_secret'),
      '#disabled' => $admin_restrict,
    ];

    $current_year = date('Y');
    $academic_year_options = [
      ($current_year - 1) . '-' . $current_year => ($current_year - 1) . '-' . $current_year,
      $current_year . '-' . ($current_year + 1) => $current_year . '-' . ($current_year + 1),
    ];
    $default_academic_year = $config->get('current_academic_year') ?: ($current_year - 1) . '-' . $current_year;
    if (!in_array($default_academic_year, $academic_year_options)) {
      $academic_year_options[$default_academic_year] = $default_academic_year;
    }
    $form['admin']['current_academic_year'] = [
      '#type' => 'select',
      '#title' => $this->t('Current Academic Year'),
      '#options' => $academic_year_options,
      '#default_value' => $default_academic_year,
      '#description' => $this->t('The current academic year. This value is used to pull data from the API for the current and next academic year.'),
      '#disabled' => $admin_restrict,
    ];

    $form['content'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content Settings'),
    ];

    $form['content']['annual_cost_of_tuition_text'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Annual Cost of Tuition Helper Text'),
      '#description' => $this->t('Optional text to explain this data. Use %academic_year to represent the academic year selected and %credits to represent credits selected.'),
      '#default_value' => $config->get('annual_cost_of_tuition_text'),
      '#size' => 60,
    ];

    $form['content']['no_cost_results'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('No Cost Results'),
      '#description' => $this->t('Text displayed if the selections end in incomplete data or no cost results. Use %academic_year to represent the academic year selected.'),
      '#default_value' => $config->get('no_cost_results'),
      '#size' => 60,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('du_tuition_calculator.settings');
    $values = $form_state->getValues();

    $admin_access = $this->currentUser->hasPermission('administer DU tuition calculator');

  if ($admin_access) {
    $config->set('api_url', trim($values['api_url'] ?? ''));
    $config->set('client_id', trim($values['client_id'] ?? ''));
    $config->set('client_secret', trim($values['client_secret'] ?? ''));
    // $config->set('flat_rate_pricing', trim($values['flat_rate_pricing'] ?? ''));
    $config->set('current_academic_year', $values['current_academic_year'] ?? '');
  }


    $config->set('per_credit_hour_text', $values['per_credit_hour_text']);
    $config->set('annual_cost_of_tuition_text', $values['annual_cost_of_tuition_text']);
    $config->set('annual_cost_of_tuition_term_text', $values['annual_cost_of_tuition_term_text']);
    $config->set('calculator_header', $values['calculator_header']);
    $config->set('calculator_footer', $values['calculator_footer']);
    $config->set('no_cost_results', $values['no_cost_results']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
