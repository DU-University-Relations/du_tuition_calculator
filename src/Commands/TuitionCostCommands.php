<?php

namespace Drupal\du_tuition_calculator\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use GuzzleHttp\ClientInterface;

/**
 * A Drush commandfile for the Tuition Calculator.
 */
class TuitionCostCommands extends DrushCommands {

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
   * Constructs a TuitionCostCommands object.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   The logger factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client that can perform remote requests.
   */
  public function __construct(QueueFactory $queue_factory, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_channel_factory, ClientInterface $http_client) {
    $this->queueFactory = $queue_factory;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_channel_factory;
    $this->httpClient = $http_client;
  }

  /**
   * Get tuition years and add to queue for import.
   *
   * @command du:tuition-cost-queue
   *
   * @aliases du-tcq,du-tuition-cost-queue
   */
  public function tuitionCostQueue() {
    // Config.
    $config = $this->configFactory->get('du_tuition_calculator.settings');

    $current_academic_year = $config->get('current_academic_year');
    if (empty($current_academic_year)) {
      $current_year = date('Y');
      $current_academic_year = ($current_year - 1) . '-' . $current_year;
      $next_academic_year = $current_year . '-' . ($current_year + 1);
    }
    else {
      $years = explode('-', $current_academic_year);
      $next_academic_year = $years[1] . '-' . ($years[1] + 1);
    }

    // Add years to the year queue.
    $queue = $this->queueFactory->get('du_tuition_calculator_year_queue');
    $queue->createItem(['year' => $current_academic_year]);
    $queue->createItem(['year' => $next_academic_year]);
  }

  /**
   * Add an individual tuition cost to the tuition cost queue.
   *
   * @param string $tuitionID
   *   Tuition DU ID.
   *
   * @command du:tuition-cost-queue-ind
   *
   * @aliases du-tcqi,du-tuition-cost-queue-ind
   */
  public function tuitionCostQueueIndividual(string $tuitionID) {
    // Logger.
    $logger = $this->loggerFactory->get('du_tuition_calculator');

    // Config.
    $config = $this->configFactory->get('du_tuition_calculator.settings');

    if (empty($tuitionID)) {
      $error = 'The drush du:tuition-cost-queue-ind command was executed but did not have a Tuition ID.';
      $logger->error($error);
      return FALSE;
    }

    // Config variables.
    $url = $config->get('api_url');
    $client_id = $config->get('client_id');
    $client_secret = \Drupal::service('key.repository')->getKey('tuition_calculator_key')->getKeyValue();
    if (empty($client_secret)) {
      $logger->error('Missing client secret from Key. Check key name/sync.');
    }

    // Check for API URL before proceeding.
    if (empty($url)) {
      // Log error.
      $logger->error(
        'The tuition calculator individual importer was executed but lacks an API URL. Go to %path to configure.',
        [
          '%path' => '/admin/config/content/tuition-calculator',
        ]
      );
      return FALSE;
    }

    // Get division or equivalent value.
    $query_string = trim($tuitionID);

    // Grab json from url with DU ID.
    $uri = $url . '/' . urlencode($query_string);
    // Add credentials if they are set.
    if (!empty($client_id) && !empty($client_secret)) {
      $uri .= '?client_id=' . $client_id;
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

      // Add tuition cost to the queue.
      if (isset($data['id'])) {
        $queue->createItem($data);
        // Log event.
        $logger->info(
          'Tuition cost %id was queued for with du:tuition-cost-queue-ind.',
          ['%id' => $tuitionID]
        );
      }
      else {
        $error = 'The drush du:tuition-cost-queue-ind command was executed and got a response from the API, but did not have data.';
        $logger->error($error);
      }
    }
    catch (\Exception $e) {
      // Log error.
      $logger->error(
        'The query for "%query" at %uri returned a %status status code.',
        ['%query' => $query_string, '%uri' => $uri, '%status' => $e->getCode()]
      );
    }
  }

}
