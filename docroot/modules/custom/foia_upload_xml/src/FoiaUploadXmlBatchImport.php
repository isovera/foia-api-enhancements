<?php

namespace Drupal\foia_upload_xml;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\MigrateMessage;

/**
 * Class FoiaUploadXmlBatchImport.
 *
 * @package Drupal\foia_upload_xml
 */
class FoiaUploadXmlBatchImport {

  use StringTranslationTrait;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\foia_upload_xml\FoiaUploadXmlMigrationsProcessor
   */
  protected $migrationsProcessor;

  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * Creates a FoiaUploadXmlBatchImport object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\foia_upload_xml\FoiaUploadXmlMigrationsProcessor $foiaUploadXmlMigrationsProcessor
   *   A class to configure and process report migrations.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to be used as the owner of the imported node.
   */
  public function __construct(MessengerInterface $messenger, FoiaUploadXmlMigrationsProcessor $foiaUploadXmlMigrationsProcessor, AccountInterface $user) {
    $this->messenger = $messenger;
    $this->migrationsProcessor = $foiaUploadXmlMigrationsProcessor;
    $this->user = $user;
  }

  /**
   * Executes Migration's Import with Batch context.
   *
   * @param string $migration_list_item
   *   Migration ID.
   * @param array $context
   *   Batch Context.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function executeMigration($migration_list_item, array &$context) {
    $this->messenger->addStatus($migration_list_item . ' in progress.');
    $context['sandbox']['current_migration'] = $migration_list_item;

    // This assumes that source url has already been configured in
    // AgencyUploadXmlForm::getBatchOperations().
    $this->migrationsProcessor
      ->setMessenger(new MigrateMessage())
      ->process($migration_list_item, $this->sourceOverrides());

    $strings = ['@item' => $migration_list_item];
    $context['message'] = $this->t('@item processed.', $strings);
    $context['results'][] = $migration_list_item;
    $this->messenger->addStatus($this->t('@item execution completed.', $strings));
  }

  /**
   * Overrides for migration source plugin.
   *
   * These values will be merged with the ones defined in the migration's YAML
   * file.
   *
   * Note - attempts to override $source['urls'] here didn't work, instead we
   * load, set the url, and save the migration configuration in the form.
   *
   * @return array
   *   A single key 'source', and the value is an array of overrides.
   */
  protected function sourceOverrides() {
    $source = [
      'constants' => [
        'user_id' => $this->user->id(),
      ],
    ];

    return ['source' => $source];
  }

  /**
   * Finishing script for batch execution.
   *
   * @param bool $success
   *   Flags success/failure of batch step.
   * @param array $results
   *   Results of batch step.
   */
  public function executeMigrationFinished($success, array $results) {
    if ($success) {
      $message = $this->formatPlural(
        count($results),
        'One import step processed.',
        '@count import steps processed.'
      );
      $this->messenger->addStatus($message);
    }
    else {
      $message = $this->t('Finished with an error.');
      $this->messenger->addWarning($message);
    }

    // Providing data for the redirected page is done through $_SESSION.
    foreach ($results as $result) {
      $message = $this->t('Processed @title.', ['@title' => $result]);
      $this->messenger->addStatus($message);
    }
  }

}
