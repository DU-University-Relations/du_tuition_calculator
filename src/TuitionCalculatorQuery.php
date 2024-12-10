<?php

namespace Drupal\du_tuition_calculator;

use Drupal\Core\Database\Connection;

/**
 * Tuition Calculator service class.
 */
class TuitionCalculatorQuery {

  /**
   * Drupal\Core\Database\Connection definition.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs an TuitionCalculatorQuery object.
   *
   * @param Drupal\Core\Database\Connection $connection
   *   The Database.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Get colleges.
   *
   * @param array $filters
   *   Filters to use on the call.
   *
   * @return array
   *   Return array of college data.
   */
  public function getColleges(array $filters) {
    $query = $this->connection->select('du_tuition', 'dt')
      ->fields('dt', ['college', 'college_code']);
    if (!empty($filters['academic_year'])) {
      if (is_array($filters['academic_year'])) {
        $query->condition('academic_year', $filters['academic_year'], 'IN');
      }
      else {
        $query->condition('academic_year', $filters['academic_year']);
      }
    }
    $query->condition('status', 1);
    $query->orderBy('college');
    $colleges = $query->distinct()->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $colleges;
  }

  /**
   * Get programs.
   *
   * @param array $filters
   *   Filters to use on the call.
   * @param bool $list
   *   Returns minimal information is list is true.
   *
   * @return array
   *   Return array of program data.
   */
  public function getPrograms(array $filters, $list = TRUE) {
    $query = $this->connection->select('du_tuition', 'dt');
    if ($list) {
      $query->fields('dt', ['program', 'program_code']);
    }
    else {
      $query->fields('dt');
    }
    if (!empty($filters['college'])) {
      $query->condition('college', $filters['college']);
    }
    if (!empty($filters['college_code'])) {
      $query->condition('college_code', $filters['college_code']);
    }
    if (!empty($filters['academic_year'])) {
      if (is_array($filters['academic_year'])) {
        $query->condition('academic_year', $filters['academic_year'], 'IN');
      }
      else {
        $query->condition('academic_year', $filters['academic_year']);
      }
    }
    $query->condition('status', 1);
    $query->orderBy('program');
    $programs = $query->distinct()->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $programs;
  }

  /**
   * Get program data.
   *
   * @param array $programs
   *   Array of raw program data.
   * @param string $data
   *   Structured lists to return.
   *
   * @return array
   *   Organized program data.
   */
  public function getProgramsData(array $programs, $data = 'all') {
    $program_list = $program_data = $college_list = [];

    foreach ($programs as $program) {
      $academic_year = $program['academic_year'];
      $years = explode('-', $academic_year);
      $term_code = $program['academic_term_code'];
      $replace_strings = [
        $academic_year . '_',
        $term_code . '_',
      ];
      $program_id = str_replace($replace_strings, '', $program['duid']);

      // Program list.
      if ($data == 'all' || $data == 'program list') {
        $program_list[$program_id] = $program['program'] . ' (' . $program['program_code'] . ')';
      }

      // College list.
      if ($data == 'all' || $data == 'college list') {
        $college_list[$program['college_code']] = $program['college'];
      }

      // Program data.
      if ($data == 'all' || $data == 'program data') {
        $program_data[$program_id]['program'] = $program['program'];
        $program_data[$program_id]['program_code'] = $program['program_code'];
        $program_data[$program_id]['college'] = $program['college'];
        $program_data[$program_id]['college_code'] = $program['college_code'];
        $program_data[$program_id][$academic_year][$term_code] = [
          'average_credits' => $program['average_credits'],
          'per_credit' => $program['per_credit'],
          'amount_per_term' => $program['amount_per_term'],
          'billed_per_term' => $program['billed_per_term'],
          'flat_rate' => $program['flat_rate'],
        ];
        $program_data[$program_id][$academic_year]['years'] = [
          'first' => $years[0],
          'second' => $years[1],
        ];
      }
    }

    if ($data == 'program list') {
      asort($program_list);
      return $program_list;
    }
    if ($data == 'college list') {
      asort($college_list);
      return $college_list;
    }
    if ($data == 'program data') {
      return $program_data;
    }

    asort($program_list);
    asort($college_list);
    return [
      'program_list' => $program_list,
      'college_list' => $college_list,
      'program_data' => $program_data,
    ];
  }

}
