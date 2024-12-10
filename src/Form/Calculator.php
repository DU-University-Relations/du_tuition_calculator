<?php

namespace Drupal\du_tuition_calculator\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\du_tuition_calculator\TuitionCalculatorQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Calculator.
 *
 * @package Drupal\du_tuition_calculator\Form
 */
class Calculator extends FormBase {

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\du_tuition_calculator\TuitionCalculatorQuery definition.
   *
   * @var Drupal\du_tuition_calculator\TuitionCalculatorQuery
   */
  protected $tuitionQuery;

  /**
   * Constructs a \Drupal\du_tuition_calculator\Form\Calculator object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\du_tuition_calculator\TuitionCalculatorQuery $tuition_query
   *   The tuition calculator query object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TuitionCalculatorQuery $tuition_query) {
    $this->configFactory = $config_factory;
    $this->tuitionQuery = $tuition_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('du_tuition_calculator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'du_tuition_calculator_calculator';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('du_tuition_calculator.settings');
    $current_academic_year = $config->get('current_academic_year');
    $current_year = date('Y');
    if (empty($current_academic_year)) {
      $current_academic_year = ($current_year - 1) . '-' . $current_year;
      $next_academic_year = $current_year . '-' . ($current_year + 1);
    }
    else {
      $years = explode('-', $current_academic_year);
      $next_academic_year = $years[1] . '-' . ($years[1] + 1);
    }

    // Get program and college information.
    $program_filter = [
      'academic_year' => [$current_academic_year, $next_academic_year],
    ];
    $programs = $this->tuitionQuery->getPrograms($program_filter, FALSE);
    $program_data = $this->tuitionQuery->getProgramsData($programs, 'all');

    // Start year options.
    $year_range = range(2016, date('Y'));
    $year_options = [NULL => ''] + array_combine($year_range, $year_range);

    // Add JS and CSS.
    $form['#attached']['library'][] = 'du_tuition_calculator/du-tuition-calculator';
    $form['#attached']['drupalSettings']['du_tuition_calculator']['tc_variables'] = [
      'programs' => $program_data['program_data'],
      'currentAcademicYear' => $current_academic_year,
      'nextAcademicYear' => $next_academic_year,
      'flatRateLink' => '/node/' . $config->get('flat_rate_pricing'),
    ];

    $header = $config->get('calculator_header');
    if (!empty($header)) {
      $header = str_replace('%current_academic_year', $current_academic_year, $header);
      $form['tc_header'] = [
        '#markup' => '<div class="dutc-header">' . $header . '</div>',
      ];
    }

    $form['currently_enrolled'] = [
      '#type' => 'select',
      '#title' => $this->t('Are you currently enrolled at University of Denver or do you plan to enroll?'),
      '#options' => [
        NULL => '',
        'current' => 'Currently Enrolled (continuing)',
        'new' => 'Plan to enroll (new)',
      ],
      '#attributes' => [
        'class' => ['chosen-select'],
        'data-placeholder' => 'Choose an option...',
      ],
      '#prefix' => '<div class="dutc-field dutc-currently-enrolled">',
      '#suffix' => '</div>',
    ];

    $form['search_option'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose One'),
      '#options' => [
        'degree' => $this->t('Degree/Program'),
        'college' => $this->t('College/School'),
      ],
      '#default_value' => 'degree',
      '#prefix' => '<div class="dutc-field dutc-search-option">',
      '#suffix' => '</div>',
    ];

    $form['college'] = [
      '#type' => 'select',
      '#title' => $this->t('College'),
      '#options' => [NULL => ''] + $program_data['college_list'],
      '#attributes' => [
        'class' => ['chosen-select'],
        'data-placeholder' => 'Choose a College...',
      ],
      '#prefix' => '<div class="dutc-field dutc-college">',
      '#suffix' => '</div>',
    ];

    $form['degree'] = [
      '#type' => 'select',
      '#title' => $this->t('Search for a Program'),
      '#options' => [NULL => ''] + $program_data['program_list'],
      '#attributes' => [
        'class' => ['chosen-select'],
        'data-placeholder' => 'Choose a Program...',
      ],
      '#prefix' => '<div class="dutc-field dutc-degree">',
      '#suffix' => '</div>',
    ];

    $form['semester'] = [
      '#type' => 'select',
      '#title' => $this->t('Which quarter or semester did you/will you enter the program?'),
      '#options' => [
        NULL => '',
        'fall' => $this->t('Fall Quarter/Semester'),
        'winter' => $this->t('Winter Quarter'),
        'spring' => $this->t('Spring Quarter/Semester'),
        'summer' => $this->t('Summer Quarter/Semester'),
      ],
      '#attributes' => [
        'class' => ['chosen-select'],
        'data-placeholder' => 'Choose quarter/semester...',
      ],
      '#prefix' => '<div class="dutc-field dutc-semester">',
      '#suffix' => '</div>',
    ];

    $form['start_year'] = [
      '#title' => $this->t('What year did you enter the program?'),
      '#type' => 'select',
      '#options' => $year_options,
      '#attributes' => [
        'class' => ['chosen-select'],
        'data-placeholder' => 'Choose start year...',
      ],
      '#prefix' => '<div class="dutc-field dutc-year">',
      '#suffix' => '</div>',
    ];

    $form['academic_year'] = [
      '#title' => $this->t('Which academic year’s tuition rate would you like to view?'),
      '#type' => 'select',
      '#options' => [
        NULL => '',
        'current' => $this->t('Current Academic Year'),
        'next' => $this->t('Next Academic Year'),
      ],
      '#attributes' => [
        'class' => ['chosen-select'],
        'data-placeholder' => 'Choose academic year...',
      ],
      '#prefix' => '<div class="dutc-field dutc-academic-year">',
      '#suffix' => '</div>',
    ];

    $form['credits'] = [
      '#type' => 'select',
      '#title' => $this->t('How many credits will you take per year?'),
      '#options' => [NULL => ''] + array_combine(range(1, 72), range(1, 72)),
      '#attributes' => [
        'class' => ['chosen-select'],
        'data-placeholder' => 'Choose number of credits...',
      ],
      '#prefix' => '<div class="dutc-field dutc-credits">',
      '#suffix' => '</div>',
    ];

    $find = ['%academic_year', '%credits'];
    $replace = [
      '<span class="dutc-ayear"></span>',
      '<span class="dutc-selected-credits"></span>',
    ];
    $cost_markup = '<div class="dutc-ayear-disclaimer">Pricing information for the next' .
      ' academic year was not available. The displayed pricing is for the current' .
      ' academic year (' . $current_academic_year . ').</div>';
    $cost_markup .= '<div class="dutc-per-credit">Tuition Rate per ' .
      '<span class="dutc-per-credit-term">Credit</span>:<div class="dutc-per-credit-cost"></div></div>';
    $credit_disclaimer = $config->get('per_credit_hour_text');
    if (!empty($credit_disclaimer)) {
      $credit_disclaimer = str_replace($find, $replace, $credit_disclaimer);
      $cost_markup .= '<div class="dutc-disclaimer">' . $credit_disclaimer . '</div>';
    }
    $cost_markup .= '<div class="dutc-annual">Estimated Annual Tuition:<div class="dutc-annual-cost"></div></div>';
    $disclaimer = $config->get('annual_cost_of_tuition_text');
    $disclaimer_term = $config->get('annual_cost_of_tuition_term_text');
    if (!empty($disclaimer)) {
      $disclaimer = str_replace($find, $replace, $disclaimer);
      $disclaimer_term = str_replace($find, $replace, $disclaimer_term);
      $cost_markup .= '<div class="dutc-disclaimer">' .
        '<div class="dutc-disclaimer-credit">' . $disclaimer . '</div>' .
        '<div class="dutc-disclaimer-term">' . $disclaimer_term . '</div>' .
        '</div>';
    }
    $form['cost'] = [
      '#markup' => $cost_markup,
      '#prefix' => '<div class="dutc-field dutc-cost">',
      '#suffix' => '</div>',
    ];

    $no_cost_results = $config->get('no_cost_results');
    if (!empty($no_cost_results)) {
      $no_cost_results = str_replace($find, $replace, $no_cost_results);
    }
    $form['no_cost'] = [
      '#markup' => $no_cost_results,
      '#prefix' => '<div class="dutc-field dutc-no-cost">',
      '#suffix' => '</div>',
    ];

    $footer = $config->get('calculator_footer');
    if (!empty($footer)) {
      $footer = str_replace('%current_academic_year', $current_academic_year, $footer);
      $form['tc_footer'] = [
        '#markup' => '<div class="dutc-footer">' . $footer . '</div>',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Form should not be submitted.
  }

}
