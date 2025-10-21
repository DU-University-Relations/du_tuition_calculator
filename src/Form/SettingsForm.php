<?php

namespace Drupal\du_tuition_calculator\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\du_tuition_calculator\Service\CliQueueInvoker;

/**
 * Class SettingsForm.
 *
 * @package Drupal\du_tuition_calculator\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * CLI queue invoker service.
   *
   * @var \Drupal\du_tuition_calculator\Service\CliQueueInvoker
   */
  protected $cliRunner;

  /**
   * Constructs a SettingsForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AccountProxy $current_user,
    CliQueueInvoker $cli_runner
  ) {
    parent::__construct($config_factory);
    $this->currentUser = $current_user;
    $this->cliRunner = $cli_runner;
  }


  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('du_tuition_calculator.cli_queue_invoker')
    );
  }


  protected function getEditableConfigNames() {
    return ['du_tuition_calculator.settings'];
  }


  public function getFormId() {
    return 'du_tuition_calculator_config_settings';
  }


  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('du_tuition_calculator.settings');

    $admin_restrict = !$this->currentUser->hasPermission('administer DU tuition calculator');

    $form['admin'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Admin Settings'),
    ];

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
    $form['admin']['client_secret_info'] = [
      '#type' => 'item',
      '#title' => $this->t('Client Secret'),
      '#markup' => $this->t('Using Key: <code>tuition-calculator-key</code> (from environment via Key module).'),
      '#description' => $this->t('This value is not editable here. Update the Pantheon secret named <code>tuition-calculator-key</code> to change it.'),
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
    ];

    $form['content']['no_cost_results'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('No Cost Results'),
      '#description' => $this->t('Text displayed if the selections end in incomplete data or no cost results. Use %academic_year to represent the academic year selected.'),
      '#default_value' => $config->get('no_cost_results'),
    ];

    // === Queue runner (local / dev / test / live) ===
    $can_run_remote = $this->currentUser->hasPermission('run du tuition calculator queues');

    $form['queue_cli'] = [
      '#type' => 'details',
      '#title' => $this->t('Queue runner (local / dev / test / live)'),
      '#open' => TRUE,
      '#access' => $can_run_remote,
    ];

    $current_env = 'local';
    if ($env = getenv('PANTHEON_ENVIRONMENT')) {
      if (in_array($env, ['dev', 'test', 'live'], TRUE)) {
        $current_env = $env;
      }
    }

    $form['queue_cli']['current_env'] = [
      '#markup' => $this->t('<p><strong>Current environment:</strong> @env</p>', [
        '@env' => ucfirst($current_env),
      ]),
    ];

    $form['queue_cli']['actions'] = ['#type' => 'actions'];
    $form['queue_cli']['actions']['run_cli'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run All (du-tcq + both queues)'),
      '#submit' => ['::submitRunCli'],
      '#button_type' => 'primary',
    ];

    return parent::buildForm($form, $form_state);
  }


  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('du_tuition_calculator.settings');
    $values = $form_state->getValues();

    $admin_access = $this->currentUser->hasPermission('administer DU tuition calculator');

    if ($admin_access) {
      $config->set('api_url', trim($values['api_url'] ?? ''));
      $config->set('client_id', trim($values['client_id'] ?? ''));
      $config->set('client_secret', trim($values['client_secret'] ?? ''));
      $config->set('current_academic_year', $values['current_academic_year'] ?? '');
    }

    $config->set('annual_cost_of_tuition_text', $values['annual_cost_of_tuition_text']);
    $config->set('no_cost_results', $values['no_cost_results']);
    $config->save();

    parent::submitForm($form, $form_state);
  }


  public function submitRunCli(array &$form, FormStateInterface $form_state) {
    if (!$this->currentUser->hasPermission('run du tuition calculator queues')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $target = 'local';
    if ($env = getenv('PANTHEON_ENVIRONMENT')) {
      if (in_array($env, ['dev', 'test', 'live'], TRUE)) {
        $target = $env; 
      }
    }

    $result = $this->cliRunner->run($target, null);

    if ($result['ok']) {
      $this->messenger()->addStatus($this->t('All commands ran successfully on @target.', ['@target' => $target]));
      if (!empty($result['stdout'])) {
        $this->messenger()->addMessage('<pre>' . htmlspecialchars($result['stdout']) . '</pre>', 'status');
      }
    }
    else {
      $this->messenger()->addError($this->t('Run failed on @target (exit code @code).', [
        '@target' => $target,
        '@code' => (string) $result['exit_code'],
      ]));
      if (!empty($result['stderr'])) {
        $this->messenger()->addMessage('<pre>' . htmlspecialchars($result['stderr']) . '</pre>', 'error');
      }
    }
  }
}