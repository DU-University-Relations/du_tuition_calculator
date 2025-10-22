<?php

namespace Drupal\du_tuition_calculator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams tuition data from the du_tuition entity to CSV for export.
 */
class ExportController extends ControllerBase {

  private const CSV_COLUMNS = [
    'WSTPTUI_ACAD_YEAR',
    'WSTPTUI_TERM_CODE',
    'WSTPTUI_PROGRAM_CODE',
    'WSTPTUI_PROGRAM_DESC',
    'WSTPTUI_COLL_DESC',
    'WSTPTUI_DEGC_CODE',
    'WSTPTUI_DEGC_DESC',
    'WSTPTUI_PER_TERM_BILLED_IND',
    'WSTPTUI_PER_CREDIT_AMT',
    'WSTPTUI_FLAT_RATE_IND',
    'WSTPTUI_PER_TERM_AMT',
    'WSTPTUI_PUBLISH_IND',
  ];

  public function __construct(private EntityTypeManagerInterface $etm) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('entity_type.manager'));
  }

  public function export(Request $request): StreamedResponse {
    $year = $request->query->get('year');
    $batchSize = max(200, (int) $request->query->get('chunk', 1000));

    $response = new StreamedResponse(function () use ($year, $batchSize) {
      $out = fopen('php://output', 'w');

      fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

      fputcsv($out, self::CSV_COLUMNS);

      $storage = $this->etm->getStorage('du_tuition');

      $baseQuery = $storage->getQuery()->accessCheck(TRUE);
      if (!empty($year)) {
        $baseQuery->condition('academic_year', $year);
      }
      $baseQuery
        ->sort('college', 'ASC') // WSTPTUI_COLL_DESC
        ->sort('program', 'ASC'); // WSTPTUI_PROGRAM_DESC  

      $ids = $baseQuery->execute();
      if (empty($ids)) {
        fclose($out);
        return;
      }

      foreach (array_chunk($ids, $batchSize) as $slice) {
        $entities = $storage->loadMultiple($slice);
        foreach ($entities as $e) {
          $row = [
            'WSTPTUI_ACAD_YEAR'           => $this->val($e, 'academic_year'),
            'WSTPTUI_TERM_CODE'           => $this->val($e, 'academic_term_code'),
            'WSTPTUI_PROGRAM_CODE'        => $this->val($e, 'program_code'),
            'WSTPTUI_PROGRAM_DESC'        => $this->val($e, 'program') ?: $this->val($e, 'title'),
            'WSTPTUI_COLL_DESC'           => $this->val($e, 'college'),
            'WSTPTUI_DEGC_CODE'           => $this->val($e, 'degree_code'),
            'WSTPTUI_DEGC_DESC'           => $this->val($e, 'degree'),
            'WSTPTUI_PER_TERM_BILLED_IND' => $this->ynIndicator($this->val($e, 'billed_per_term')),
            'WSTPTUI_PER_CREDIT_AMT'      => $this->val($e, 'per_credit'),
            'WSTPTUI_FLAT_RATE_IND'       => $this->ynIndicator($this->val($e, 'flat_rate')),
            'WSTPTUI_PER_TERM_AMT'        => $this->amountOrBlank($e, 'amount_per_term'),
            'WSTPTUI_PUBLISH_IND'         => $this->readPublishIndicator($e),
          ];

          $line = [];
          foreach (self::CSV_COLUMNS as $col) {
            $line[] = $row[$col] ?? '';
          }
          fputcsv($out, $line);
        }
        fflush($out);
      }

      fclose($out);
    });

    $fname = 'du_tuition_db'
      . ($year ? '_year-' . preg_replace('/\W+/', '', $year) : '')
      . '_' . date('Ymd_His') . '.csv';

    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $fname . '"');
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    return $response;
  }

  private function val($entity, string $field): string {
    if (!$entity->hasField($field)) {
      return '';
    }

    /** @var \Drupal\Core\Field\FieldItemListInterface $list */
    $list = $entity->get($field);
    if ($list->isEmpty()) {
      return '';
    }

    $values = [];
    foreach ($list as $item) {
      $arr = $item->toArray();

      if (array_key_exists('value', $arr)) {
        $v = $arr['value'];
      }
      elseif (array_key_exists('target_id', $arr)) {
        $v = $arr['target_id'];
        if (method_exists($item, 'entity') && $item->entity) {
          try { $v = $item->entity->label(); } catch (\Throwable $e) {}
        }
      }
      elseif (array_key_exists('status', $arr)) {
        $v = $arr['status'];
      }
      else {
        $v = '';
        foreach ($arr as $maybe) {
          if (is_scalar($maybe)) { $v = $maybe; break; }
        }
        if ($v === '') {
          $v = json_encode($arr, JSON_UNESCAPED_UNICODE);
        }
      }

      if (is_bool($v)) {
        $v = $v ? '1' : '0';
      } elseif (is_float($v) || is_int($v)) {
        $v = (string) $v;
      } elseif (is_array($v) || is_object($v)) {
        $v = json_encode($v, JSON_UNESCAPED_UNICODE);
      } elseif ($v === NULL) {
        $v = '';
      }

      $values[] = (string) $v;
    }

    return implode('; ', $values);
  }

  /**
   * For currency/amount fields: treat empty or zero-like values as blank.
   * Returns '' when the field is missing/empty or equals 0
   */
  private function amountOrBlank($entity, string $field): string {
    $raw = $this->val($entity, $field);
    $s = trim((string) $raw);
    if ($s === '') {
      return '';
    }
    $normalized = str_replace([','], [''], $s);
    if (is_numeric($normalized) && (float) $normalized == 0.0) {
      return '';
    }
    return $s;
  }

  /**
   * Reads a indicator from the entity:
   * - Checks common field names in order.
   * - Returns 'Y' or 'N' when determinable.
   * - Returns '' (blank) if nothing is set.
   */
  private function readPublishIndicator($entity): string {
    $candidates = ['publish_ind', 'published', 'is_published', 'publish', 'status'];
    foreach ($candidates as $field) {
      if ($entity->hasField($field)) {
        $raw = $this->val($entity, $field); 
        if ($raw === '' || $raw === null) {
          continue; 
        }
        $yn = $this->ynIndicator($raw);
        if ($yn === 'Y' || $yn === 'N') {
          return $yn;
        }
      }
    }
    return '';
  }

  private function ynIndicator($v): string {
    if ($v instanceof FieldItemListInterface) {
      $v = $this->val($v->getEntity(), $v->getName());
    }

    $s = trim((string) $v);
    if ($s === '') return '';

    $upper = strtoupper($s);
    if ($v === TRUE || $v === 1 || $s === '1' || $upper === 'Y' || $upper === 'TRUE') return 'Y';
    if ($v === FALSE || $v === 0 || $s === '0' || $upper === 'N' || $upper === 'FALSE') return 'N';

    return '';
  }
}
