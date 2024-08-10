<?php

namespace IDAF\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


abstract class BackupRestore extends CommandBase {

    const BACKUP_METADATA_FILENAME = '.omeka-s-cli-backup.info.yml';

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

    protected function siteExists(InputInterface $input, OutputInterface $output) {
        $site_directory = $input->getOption('site-dir');
        $backup_dir = $input->getOption('backup-dir');


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
}