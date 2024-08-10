<?php

namespace IDAF;

use Symfony\Component\Finder\SplFileInfo;
use IDAF\Command\BackupRestoreBase;
use Archive_Tar;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Helper\FormatterHelper;

class BackupFileInfo {
  const DATETIME_FORMAT = 'Y-m-d-H-i-s';

  public $file;

  protected $site_name;

  /**
   * @var \DateTime
   */
  protected $date_time;

  protected $title;

  public function __construct(string $site_name, SplFileInfo $file) {
    $this->file = $file;
    $this->site_name = $site_name;

    $base_name = $file->getBasename();

    $matches = NULL;
    if (preg_match('/.*\-\-(\d{4}\-\d{2}\-\d{2}\-\d{2}\-\d{2}\-\d{2})\-\-(.*).osb.tar.gz/', $base_name, $matches)) {
      $this->date_time = \DateTime::createFromFormat(self::DATETIME_FORMAT, $matches[1]);
      $this->title = $matches[2];

      $backup_tar = new Archive_Tar($this->file->getPathname());
      /** @var string $backup_metadata_string */
      $backup_metadata_string = $backup_tar->extractInString(BackupRestoreBase::BACKUP_METADATA_FILENAME);
      if ($backup_metadata_string) {
        $backup_meta_data = Yaml::parse($backup_metadata_string);

        if (!empty($backup_meta_data['title'])) {
          $this->title = $backup_meta_data['title'];
        }
      }

    }
    else {
      throw new NotOmekaSBackupFileException('Not an Omeka S backup.');
    }
  }

  public function getBasename() {
    return $this->file->getBasename();
  }

  public function getName() {
    return $this->title;
  }

  public function __toString() {
    $file_size = FormatterHelper::formatMemory($this->file->getSize());
    return "Backup File: {$this->getBasename()}\n\tTitle: {$this->title}\n\tSize: {$file_size}\n\tDate and Time: {$this->date_time->format('Y-m-d H:i:s')}";
  }

  public static function getBackupFileName($site_name, $title, $timestamp_string, $db_only = FALSE) {
    // Remove anything which isn't a word, whitespace, number
    // or any of the following caracters -_~,;[]().
    // If you don't need to handle multi-byte characters
    // you can use preg_replace rather than mb_ereg_replace
    // Thanks @Łukasz Rysiak!
    $filename_safe_description = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $title);
    // Remove any runs of periods (thanks falstro!)
    $filename_safe_description = mb_ereg_replace("([\.]{2,})", '', $filename_safe_description);
    // Replace spaces with hyphens
    $filename_safe_description = mb_ereg_replace("\s+", '-', $filename_safe_description);
    // Replace double hyphen with single as double hyphens have special purpose in backup file names.
    $filename_safe_description = mb_ereg_replace('\-{2,}', '-', $filename_safe_description);

    $backup_file_name = $site_name . '--'  . $timestamp_string . '--' . $filename_safe_description;
    if ($db_only) {
      $backup_file_name .= '.sql';
    }
    else {
      $backup_file_name .= '.tar';
    }
    return $backup_file_name;
  }

  public static function validBackupFile(SplFileInfo $file) {
    return (bool) preg_match('/(.*?)\-\-(.*?)\-\-(\d{4}\-\d{2}\-\d{2}\-\d{2}\-\d{2}\-\d{2})\-\-(.*).tar.gz/', $file->getBasename());
  }

  public static function getFromFile(SplFileInfo $file) {
    if (preg_match('/(.*?)\-\-(.*?)\-\-(\d{4}\-\d{2}\-\d{2}\-\d{2}\-\d{2}\-\d{2})\-\-(.*).tar.gz/', $file->getBasename(), $matches)) {
      return new BackupFileInfo($matches[1], $file);
    }
  }

  public function getDatabaseConfig() {
    $backup_tar = new Archive_Tar($this->file->getPathname());
    /** @var string */
    $database_config_string = $backup_tar->extractInString(BackupRestoreBase::DATABASE_CONFIG_FILENAME);
    return parse_ini_string($database_config_string);
  }

  public function getFileName() {
    $this->file->getFileName();
  }
}
