<?php

namespace IDAF\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Archive_Tar;
use Symfony\Component\Yaml\Yaml;
use IDAF\BackupFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\FormatterHelper;


#[AsCommand(
    name: 'backup:create',
    description: 'Creates a backup for the Omeka S installation.',
    hidden: false,
    aliases: []
)]
class BackupCreate extends BackupRestoreBase
{

    protected function configure(): void {
        parent::configure();
        $this
            ->addArgument('title', InputArgument::REQUIRED, 'Title for this backup. It will become part of the backup file name.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $styled_output = $this->getStyledOutput($input, $output);

        $site_directory = $this->getSiteDirectory($input, $output);
        $backup_dir = $this->getBackupDir($input, $output);

        $title = $input->getArgument('title');

        $timestamp_string = date(BackupFileInfo::DATETIME_FORMAT);


        if (!$this->siteExists($input, $output)) {
            $styled_output->error("Not a valid Omeka S installation at ' . $site_directory");
            return Command::INVALID;
        }
        else {
            // $styled_output->taskDone('Found settings.php');
            $database_config = $this->getDatabseConfig($site_directory);
            $site_name = $this->siteName($input, $output);

            $db_dump_file_name = $backup_dir . 'db-dump-' . $site_name . '-' . $timestamp_string . '.osb.sql.gz';

            $output->writeln('Creating database dump ...');
            exec("mysqldump --user={$database_config['user']} --password={$database_config['password']} --host={$database_config['host']} --no-tablespaces {$database_config['dbname']} | gzip > $db_dump_file_name");
            if (file_exists($db_dump_file_name)) {
                $styled_output->taskDone('Database dump has been created');

                $tar_file_name = BackupFileInfo::getBackupFileName($site_name, $title, $timestamp_string);
                $tar_file_path = $backup_dir . $tar_file_name;

                $metadata_file_path = $site_directory  . '/' . static::BACKUP_METADATA_FILENAME;
                if (file_exists($metadata_file_path)) {
                    // Remove any leftover metadata file from previous restore operation.
                    unlink($metadata_file_path);
                }

                $backup_metadata = [
                    'title' => $title,
                    'datetime' => $timestamp_string,
                ];
                file_put_contents($metadata_file_path, Yaml::dump($backup_metadata));

                $output->write('Creating archive of files ...');
                $tar = new Archive_Tar($tar_file_path);
                $tar->addModify([$site_directory], '', $site_directory);
                $styled_output->taskDone();

                $output->write('Adding database dump to the archive ...');
                $tar->addModify([$db_dump_file_name], '', $backup_dir);
                $styled_output->taskDone();

                // Remove database dump.
                unlink($db_dump_file_name);

                // Remove backup metadata .
                unlink($metadata_file_path);

                $output->write('Compressing the archive ...');
                exec('gzip "' . $tar_file_path . '"');
                $styled_output->taskDone();
                $styled_output->success('Backup created "' . $tar_file_name . '.gz" (' . FormatterHelper::formatMemory(filesize($tar_file_path . '.gz')) . ')');
                return Command::SUCCESS;
            }
        }

        return Command::FAILURE;
    }
}