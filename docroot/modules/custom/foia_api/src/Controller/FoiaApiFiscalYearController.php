<?php

namespace Drupal\foia_api\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Gets jsonapi data
 *
 * @package Drupal\foia_api\Controller
 */
class FoiaApiFiscalYearController extends ControllerBase  implements ContainerInjectionInterface  {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * FoiaApiFiscalYearController constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Controller\ControllerBase|void
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Get an array of report years for published Annual FOIA Report Data nodes.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function get() {
    $query = $this->connection->select('node__field_foia_annual_report_yr', 'y')
      ->fields('y', ['field_foia_annual_report_yr_value']);
    $query->join('node_field_data', 'n', 'n.nid = y.entity_id');
    $query->condition('n.status', 1);
    $data = $query->distinct()->execute()->fetchCol();

    return JsonResponse::create($data);
  }
}