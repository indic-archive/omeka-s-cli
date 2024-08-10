<?php

namespace IDAF;

use Symfony\Component\Finder\SplFileInfo;
use IDAF\Command\BackupRestoreBase;
use Archive_Tar;
use Symfony\Component\Yaml\Yaml;

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
    if (preg_match('/' . preg_quote($site_name) . '\-\-(\d{4}\-\d{2}\-\d{2}\-\d{2}\-\d{2}\-\d{2})\-\-(.*).tar.gz/', $base_name, $matches)) {
      $this->date_time = \DateTime::createFromFormat(self::DATETIME_FORMAT, $matches[2]);
      $this->title = $matches[3];

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
      throw new NotOmekaSBackupFileException('Not a WWM server backup file.');
    }
  }

  public function getBasename() {
    return $this->file->getBasename();
  }

  public function getName() {
    return $this->title;
  }

  public function __toString() {
    $file_size = static::humanFileSize($this->file->getSize());
    return "Backup File: {$this->getBasename()}\n\tTitle: {$this->title}\n\tSize: {$file_size}\n\tDate and Time: {$this->date_time->format('Y-m-d H:i:s')}";
  }

  public static function humanFileSize($size, $unit="") {
    if( (!$unit && $size >= 1<<30) || $unit == "GB")
      return number_format($size/(1<<30),2)."GB";
    if( (!$unit && $size >= 1<<20) || $unit == "MB")
      return number_format($size/(1<<20),2)."MB";
    if( (!$unit && $size >= 1<<10) || $unit == "KB")
      return number_format($size/(1<<10),2)."KB";
    return number_format($size)." bytes";
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
}
