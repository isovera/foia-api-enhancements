<?php

namespace Drupal\foia_upload_xml\Commands;

use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\file\FileInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\foia_upload_xml\FoiaUploadXmlReportParser;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\foia_upload_xml\FoiaUploadXmlMigrationsProcessor;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile for the FoiaUploadXmlCommands.
 */
class FoiaUploadXmlCommands extends DrushCommands {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The migrations processor.
   *
   * @var \Drupal\foia_upload_xml\FoiaUploadXmlMigrationsProcessor
   */
  protected $migrationsProcessor;

  /**
   * The report parser.
   *
   * @var \Drupal\foia_upload_xml\FoiaUploadXmlReportParser
   */
  protected $reportParser;

  /**
   * FoiaUploadXmlCommands constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\foia_upload_xml\FoiaUploadXmlMigrationsProcessor $migrationsProcessor
   *   The migrations processor.
   * @param \Drupal\foia_upload_xml\FoiaUploadXmlReportParser $reportParser
   *   The report parser.
   */
  public function __construct(Connection $database, LoggerChannelFactoryInterface $loggerChannelFactory, MessengerInterface $messenger, FoiaUploadXmlMigrationsProcessor $migrationsProcessor, FoiaUploadXmlReportParser $reportParser) {
    parent::__construct();
    $this->connection = $database;
    $this->loggerFactory = $loggerChannelFactory;
    $this->messenger = $messenger;
    $this->migrationsProcessor = $migrationsProcessor;
    $this->reportParser = $reportParser;
  }

  /**
   * Bulk import report xml files that are contained in a given directory.
   *
   * @param string $directory
   *   The path to the directory containing the report xml files.  This can
   *   be an absolute path on the server or a relative path from the site's
   *   docroot.
   *
   * @usage foia-upload-xml:bulk-upload sites/default/files/report-files
   *   Bulk import all report *.xml files that are in the directory
   *   /path/to/docroot/sites/default/files/report-files.
   *
   * @command foia-upload-xml:bulk-upload
   * @aliases fuxml
   * @bootstrap full
   */
  public function bulkUpload($directory) {
    $rows = [
      [
        'file' => 'File',
        'status' => 'Status',
      ],
    ];
    $files = $this->getXmlFiles($directory);
    foreach ($files as $filepath) {
      $info = pathinfo($filepath);
      if (!is_file($filepath)) {
        continue;
      }

      if (!is_readable($filepath)) {
        $this->messenger->addWarning(t('Skipped @file: File not readable.', [
          '@file' => $info['basename'],
        ]));

        $rows[] = [
          'file' => $info['basename'],
          'status' => 'Skipped, file not readable',
        ];
        continue;
      }

      // This file does not need to be saved or moved, as each file will be
      // processed in place.  It is wrapped in a FileInterface class in order
      // to be used by the migrations processor.
      $source = File::create([
        'uid' => 1,
        'status' => 0,
        'uri' => $filepath,
      ]);

      try {
        $this->migrationsProcessor->setSourceFile($source)
          ->setUser(User::load(1))
          ->processAll();

        $status = $this->migrationStatus($source);
        if ($status === MigrateIdMapInterface::STATUS_FAILED) {
          throw new \Exception(dt("The file $filepath was unable to be imported."));
        }

        $rows[] = [
          'file' => $info['basename'],
          'status' => 'Processed',
        ];
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('foia_upload_xml')
          ->warning(dt("Foia Upload XML Bulk Upload: Failed to import {$info['basename']}."));
        $rows[] = [
          'file' => $info['basename'],
          'status' => 'Failed',
        ];
      }
    }

    return new RowsOfFields($rows);
  }

  /**
   * Get the xml files from a given directory.
   *
   * @param string $directory
   *   The directory path where the xml files live.
   *
   * @return array|false
   *   The xml files contained in the given directory, an empty array if none
   *   are found, or false on error.
   */
  protected function getXmlFiles($directory) {
    $realpath = realpath($directory) ?: $directory;
    return glob("$realpath/*.xml");
  }

  /**
   * Check the status of the annual report import by agency and report year.
   *
   * @param \Drupal\file\FileInterface $file
   *   The report's source xml file.
   *
   * @return int
   *   The value of the source_row_status column in the
   *   migrate_map_foia_agency_report table for the agency and report year
   *   of the given source file.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function migrationStatus(FileInterface $file) {
    $report_data = $this->reportParser->parse($file);
    $agency = $report_data['agency'] ?? FALSE;
    $year = $report_data['report_year'] ?? date('Y');

    $status = $this->connection->select('migrate_map_foia_agency_report', 'm')
      ->fields('m', ['source_row_status'])
      ->condition('sourceid1', $year)
      ->condition('sourceid2', $agency)
      ->execute()
      ->fetchField();

    return (int) $status;
  }

}
