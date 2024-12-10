<?php

namespace Drupal\du_tuition_calculator\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Process list of tuition cost years to be imported.
 *
 * @QueueWorker(
 *   id = "du_tuition_calculator_year_queue",
 *   title = @Translation("Process tuition cost years to be imported.")
 * )
 */
class TuitionCalculatorYearQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
   * An ACME Services - Contents HTTP Client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The Queue Factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   The logger factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client that can perform remote requests.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_channel_factory,
    ClientInterface $http_client,
    QueueFactory $queue_factory,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_channel_factory;
    $this->httpClient = $http_client;
    $this->queueFactory = $queue_factory;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('http_client'),
      $container->get('queue'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    // Logger.
    $logger = $this->loggerFactory->get('du_tuition_calculator');

    // Config.
    $config = $this->configFactory->get('du_tuition_calculator.settings');

    // Config variables.
    $url = $config->get('api_url');
    $client_id = $config->get('client_id');
    $client_secret = $config->get('client_secret');

    // Check for API URL before proceeding.
    if (empty($url)) {
      // Log error.
      $logger->error(
        'The tuition calculator year importer was executed but lacks an API URL. Go to %path to configure.',
        [
          '%path' => '/admin/config/content/tuition-calculator',
        ]
      );
      return FALSE;
    }

    // Check query values.
    if (empty($item['year'])) {
      // Log error.
      $error = 'The tuition calculator year importer attempted to queue tuition cost for the year value was empty.';
      $logger->error($error);
      return FALSE;
    }

    // Get division or equivalent value.
    $query_string = $item['year'];

    // Grab json from url with year value.
    $uri = $url . '?academic_year=' . urlencode($query_string);
    // Add credentials if they are set.
    if (!empty($client_id) && !empty($client_secret)) {
      $uri .= '&client_id=' . $client_id;
      $uri .= '&client_secret=' . $client_secret;
    }
    try {
      $response = $this->httpClient->get($uri, ['headers' => ['Accept' => 'application/json']]);
      if ($response->getStatusCode() != 200) {
        throw new \Exception(getStatusCode());
      }

      $data = Json::decode((string) $response->getBody());

      // Get queue.
      $queue = $this->queueFactory->get('du_tuition_calculator_cost_queue');

      // Add each tuition cost from json to the tuition cost queue.
      if (!empty($data)) {
        $delete_query_ids = [];
        foreach ($data as $tuition_cost) {
          if (isset($tuition_cost['id'])) {
            $tuition_cost['delete'] = FALSE;
            $queue->createItem($tuition_cost);
            $delete_query_ids[] = $tuition_cost['id'];
          }
        }

        // Delete queue.
        if (!empty($delete_query_ids)) {
          $entity_storage = $this->entityTypeManager->getStorage('du_tuition');
          $query = $entity_storage->getQuery()
            ->condition('academic_year', $item['year'])
            ->condition('duid', $delete_query_ids, 'NOT IN');
          $ids = $query->accessCheck(TRUE)->execute();

          foreach ($ids as $id) {
            $queue->createItem([
              'id' => $id,
              'delete' => TRUE,
            ]);
          }
        }
      }

      // Log event.
      $logger->info(
        '%num tuition costs were queued for "%query" at %uri.',
        ['%num' => count($data), '%query' => $query_string, '%uri' => $uri]
      );
    }
    catch (\Exception $e) {
      // Log error.
      $logger->error(
        'The query for "%query" at %uri failed with a message: %message.',
        [
          '%query' => $query_string,
          '%uri' => $uri,
          '%message' => $e->getMessage(),
        ]
      );
    }
  }

}
