<?php

namespace IDAF\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


abstract class BackupRestoreBase extends CommandBase {

    const BACKUP_METADATA_FILENAME = '.omeka-s-cli-backup.info.yml';
    const DATABASE_CONFIG_FILENAME = 'config/database.ini';

    protected function configure(): void {
        $config = $this->getConfig();
        $default_backup_dir = NULL;
        if (isset($config['backup-dir'])) {
            $default_backup_dir = $config['backup-dir'];
        }

        parent::configure();
        $this
            ->addOption('backup-dir', 'b', InputOption::VALUE_REQUIRED, 'Directory to keep backup files.', $default_backup_dir)
        ;
    }

    /**
     * Get backup directory
     */
    protected function getBackupDir(InputInterface $input, OutputInterface $output) : string {
        $backup_dir = $input->getOption('backup-dir');
        return rtrim($backup_dir,"/") . '/';
    }

    protected function siteExists(InputInterface $input, OutputInterface $output) {
        $site_directory = $input->getOption('site-dir');
        $backup_dir = $this->getBackupDir($input, $output);


        if ($input->getOption('verbose')) {
            $output->writeln("<info>Site Dir: $site_directory</info>");
            $output->writeln("<info>Backup dir: $backup_dir</info>");
        }

        if (file_exists($site_directory . '/config/database.ini')) {
            // The site does exist.
            return TRUE;
        }
        return FALSE;
    }

    protected function siteName(InputInterface $input, OutputInterface $output) {
        $site_directory = $input->getOption('site-dir');
        if (file_exists($site_directory)) {
            return basename($site_directory);
        }
        return NULL;
    }

    protected function getDatabseConfigFilePath($site_directory) {
        return $site_directory . '/' . static::DATABASE_CONFIG_FILENAME;
    }

    /**
     * Read database configuration.
     */
    protected function getDatabseConfig($site_directory, $get_file_contents = FALSE) {
        $database_config_file_contents = file_get_contents($this->getDatabseConfigFilePath($site_directory));
        if (!$get_file_contents) {
            return parse_ini_string($database_config_file_contents);
        }
        return $database_config_file_contents;
    }
}