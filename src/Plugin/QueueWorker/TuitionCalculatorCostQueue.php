<?php

namespace Drupal\du_tuition_calculator\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process list of tuition costs to be imported.
 *
 * @QueueWorker(
 *   id = "du_tuition_calculator_cost_queue",
 *   title = @Translation("Process tuition costs and import.")
 * )
 */
class TuitionCalculatorCostQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   The logger factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_channel_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_channel_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    // Logger.
    $logger = $this->loggerFactory->get('du_tuition_calculator');

    // Entity storage.
    $entity_storage = $this->entityTypeManager->getStorage('du_tuition');

    if (!empty($item['delete'])) {
      $tuition = $entity_storage->load($item['id']);
      if (isset($tuition)) {
        $tuition->delete();
        $logger->info(
          'A tuition cost (entity id: %id) has been deleted.',
          ['%id' => $item['id']]
        );
      }
    }
    else {
      // Search for existing tuition cost.
      $query = $entity_storage->getQuery()
        ->condition('duid', $item['id'])
        ->condition('academic_term', $item['academicTerm'])
        ->condition('academic_term_code', $item['academicTermCode']);
      $ids = $query->accessCheck(TRUE)->execute();
      $tuition_costs = $entity_storage->loadMultiple($ids);

      // Last updated.
      $du_last_updated = NULL;
      if (!empty($item['lastUpdated'])) {
        $du_last_updated = strtotime($item['lastUpdated']);
      }

      // Update tuition costs.
      if (!empty($tuition_costs)) {
        foreach ($tuition_costs as $tuition_cost) {
          $last_updated = $tuition_cost->get('updated_du')->value;
          if ($last_updated <= $du_last_updated) {
            $this->setTuitionValues($tuition_cost, $item);
            $tuition_cost->setStatus(TRUE);
            $tuition_cost->save();
            $logger->info(
              'The tuition cost (id: %id) for %name (DU ID: #duid) has been updated.',
              [
                '%id' => $tuition_cost->id(),
                '%name' => $tuition_cost->getTitle(),
                '%duid' => $item['id'],
              ]
            );
          }
          else {
            $logger->info(
              'The tuition cost (id: %id) for %name (DU ID: #duid) did not require updating.',
              [
                '%id' => $tuition_cost->id(),
                '%name' => $tuition_cost->getTitle(),
                '%duid' => $item['id'],
              ]
            );
          }
        }
      }
      elseif ($ids !== NULL) {
        // Create new entity.
        $tuition_cost = $entity_storage->create();
        $this->setTuitionValues($tuition_cost, $item);
        $tuition_cost->setStatus(TRUE);
        $tuition_cost->enforceIsNew();
        $tuition_cost->save();
        $logger->info(
          'The tuition cost (id: %id) for %name (DU ID: #duid) has been created.',
          [
            '%id' => $tuition_cost->id(),
            '%name' => $tuition_cost->getTitle(),
            '%duid' => $item['id'],
          ]
        );
      }
      else {
        $logger->warning(
          'An attempt to import %name (DU ID: %duid) failed during the query process.',
          [
            '%name' => $item['program'],
            '%duid' => $item['id'],
          ]
        );
      }
    }
  }

  /**
   * Add item values to a tuition cost.
   *
   * @param object $tuition_cost
   *   Entity being created or updated.
   * @param array $data
   *   Array of data to update or add to the entity.
   */
  public function setTuitionValues($tuition_cost, array $data) {
    // Title.
    if ($data['program']) {
      $tuition_cost->set('title', $data['program']);
    }
    elseif ($data['degree']) {
      $tuition_cost->set('title', $data['degree']);
    }

    if ($data['id']) {
      $tuition_cost->set('duid', $data['id']);
    }

    if ($data['academicYear']) {
      $tuition_cost->set('academic_year', $data['academicYear']);
    }

    if ($data['academicTerm']) {
      $tuition_cost->set('academic_term', $data['academicTerm']);
    }

    if ($data['academicTermCode']) {
      $tuition_cost->set('academic_term_code', $data['academicTermCode']);
    }

    if ($data['cohortCode']) {
      $tuition_cost->set('cohort_code', $data['cohortCode']);
    }

    if ($data['college']) {
      $tuition_cost->set('college', $data['college']);
    }

    if ($data['collegeCode']) {
      $tuition_cost->set('college_code', $data['collegeCode']);
    }

    if ($data['department']) {
      $tuition_cost->set('department', $data['department']);
    }

    if ($data['departmentCode']) {
      $tuition_cost->set('department_code', $data['departmentCode']);
    }

    if ($data['degree']) {
      $tuition_cost->set('degree', $data['degree']);
    }

    if ($data['degreeCode']) {
      $tuition_cost->set('degree_code', $data['degreeCode']);
    }

    if ($data['detailCode']) {
      $tuition_cost->set('detail_code', $data['detailCode']);
    }

    if ($data['level']) {
      $tuition_cost->set('level', $data['level']);
    }

    if ($data['levelCode']) {
      $tuition_cost->set('level_code', $data['levelCode']);
    }

    if ($data['major']) {
      $tuition_cost->set('major', $data['major']);
    }

    if ($data['majorCode']) {
      $tuition_cost->set('major_code', $data['majorCode']);
    }

    if ($data['costPerCredit']) {
      $tuition_cost->set('per_credit', $data['costPerCredit']);
    }

    if ($data['averageCreditsPerYear']) {
      $tuition_cost->set('average_credits', $data['averageCreditsPerYear']);
    }

    if ($data['program']) {
      $tuition_cost->set('program', $data['program']);
    }
    elseif ($data['degree']) {
      $tuition_cost->set('program', $data['degree']);
    }

    if ($data['programCode']) {
      $tuition_cost->set('program_code', $data['programCode']);
    }

    $amount_per_term = $data['amountPerTerm'] ?? 0;
    $tuition_cost->set('amount_per_term', $amount_per_term);

    $billed_per_term = FALSE;
    if (!empty($data['billedPerTerm']) && $data['billedPerTerm'] == 'Y') {
      $billed_per_term = TRUE;
    }
    $tuition_cost->set('billed_per_term', $billed_per_term);

    $flat_rate = FALSE;
    if (!empty($data['flatRate']) && $data['flatRate'] == 'Y') {
      $flat_rate = TRUE;
    }
    $tuition_cost->set('flat_rate', $flat_rate);

    if ($data['lastUpdated']) {
      $tuition_cost->set('updated_du', strtotime($data['lastUpdated']));
    }

  }

}
