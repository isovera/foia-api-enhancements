<?php

namespace Drupal\foia_upload_xml\Commands;

use Drush\Utils\FsUtils;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\foia_upload_xml\FoiaUploadXmlReportParser;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\foia_upload_xml\FoiaUploadXmlMigrationsProcessor;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile for the FoiaUploadXmlCommands.
 *
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class FoiaUploadXmlCommands extends DrushCommands {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The report parser.
   *
   * @var \Drupal\foia_upload_xml\FoiaUploadXmlReportParser
   */
  protected $reportParser;

  /**
   * The migrations processor.
   *
   * @var \Drupal\foia_upload_xml\FoiaUploadXmlMigrationsProcessor
   */
  protected $migrationsProcessor;

  public function __construct(MessengerInterface $messenger, FoiaUploadXmlMigrationsProcessor $migrationsProcessor, FoiaUploadXmlReportParser $reportParser) {
    parent::__construct();
    $this->messenger = $messenger;
    $this->migrationsProcessor = $migrationsProcessor;
    $this->reportParser = $reportParser;
  }


  /**
   * Command description here.
   *
   * @param $directory
   *   Argument description.
   *
   * @usage foia_upload_xml:bulkProcess /path/to/files/directory
   *   Usage description
   *
   * @command foia_upload_xml:bulkProcess
   * @aliases fuxb
   * @bootstrap full
   */
  public function bulkProcess($directory) {
    $rows = [];
    $realpath = FsUtils::realpath($directory);
    $files = glob("$realpath/*.xml");
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

      $source = File::create([
        'uid' => 1,
        'status' => 0, // temporary
        'uri' => $filepath,
      ]);

      try {
        $report_data = $this->reportParser->parse($source);
        $agency = $report_data['agency'] ?? FALSE;
        $year = $report_data['report_year'] ?? date('Y');

        $this->migrationsProcessor->setSourceFile($source)
          ->setUser(User::load(1))
          ->processAll();

        $status = \Drupal::database()
          ->select('migrate_map_foia_agency_report', 'm')
          ->fields('m', ['source_row_status'])
          ->condition('sourceid1', $year)
          ->condition('sourceid2', $agency)
          ->execute()
          ->fetchField();

        if ((int) $status === MigrateIdMapInterface::STATUS_FAILED) {
          throw new \Exception(\Drupal::translation()
            ->translate('The file @file was unable to be imported.', [
              '@file' => $filepath,
            ]));
        }

        $rows[] = [
          'file' => $info['basename'],
          'status' => 'Processed',
        ];
      } catch (\Exception $e) {
        \Drupal::logger('foia_upload_xml')
          ->warning(t('Foia Upload XML Bulk Upload: Failed to import @file.', [
            '@file' => $info['basename'],
          ]));
        $rows[] = [
          'file' => $info['basename'],
          'status' => 'Failed',
        ];
      }
    }

    return new RowsOfFields($rows);
  }
}
