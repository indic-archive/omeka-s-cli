<?php

namespace IDAF\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use IDAF\BackupFileInfo;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'backup:restore',
    description: 'Restore a backup over an existing Omeka S installation.',
    hidden: false,
    aliases: []
)]
class BackupRestore extends BackupRestoreBase
{
    protected function configure(): void {
        parent::configure();
        $this
            ->addOption('filter-name', NULL, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED , 'Filter by title of the backup. Provide a word or part of the title to match. It will be useful if there are long list of backups making it difficult to choose correct one.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $styled_output = $this->getStyledOutput($input, $output);
        /** @var \Symfony\Component\Console\Helper\FormatterHelper */
        $formatter = $this->getHelper('formatter');

        if ($output->isVeryVerbose()) {
            $config_file_path = $_SERVER['HOME'] . '/' . static::CONFIG_FILE_NAME;
            $output->writeln("<info>Configuration file path: $config_file_path</info>");
        }

        if (!$this->siteExists($input, $output)) {
            // white text on a red background
            $output->writeln('<error>Site does not exist thus cannot be restored!</error>');
            return Command::INVALID;
        }
        $site_directory = $input->getOption('site-dir');
        $backup_dir = $input->getOption('backup-dir');

        $site_name = $this->siteName($input, $output);

        $finder = new Finder();
        $finder->name('*--*.osb.tar.gz');

        $finder->depth('== 0'); // Not to search within subdirectories.
        $finder->sortByModifiedTime();
        $finder->reverseSorting();

        $backups = [];

        $filter_name = $input->getOption('filter-name');

        foreach ($finder->in($backup_dir) as $file) {
            $backup_file_info = new BackupFileInfo($site_name, $file);
            if (!empty($filter_name)) {
                $description = $backup_file_info->getName();
                foreach ($filter_name as $filter_name_value) {
                    $pattern = '/' . preg_quote($filter_name_value,) . '/';
                    if (!preg_match($pattern, $description)) {
                        continue 2;
                    }
                }
            }
            $backups[] = $backup_file_info;
            $base_name = $file->getBasename();
            $matches = NULL;
        }

        if (!empty($backups)) {
            /** @var \Symfony\Component\Console\Helper\QuestionHelper */
            $helper = $this->getHelper('question');

            $question = new ChoiceQuestion(
                'Please choose a backup to restore',
                // choices can also be PHP objects that implement __toString() method
                array_merge(['Cancel'],  $backups),
                0
            );
            $question->setErrorMessage('Selection %s is invalid.');

            $selected_backup_file_info = $helper->ask($input, $output, $question);

            if (is_string($selected_backup_file_info)) {
                $output->writeln('<comment>Operation has been cancelled.</comment>');
            }
            else {
                /** @var \IDAF\BackupFileInfo $selected_backup_file_info*/
                $output->writeln('You have just selected: '. $selected_backup_file_info);

                $output->writeln('<info>Reading database configs ...</info>');

                $existing_database_config_file_contents = $this->getDatabseConfig($site_directory, TRUE);
                // Read setting file before doing any restore.
                $existing_database_config = $this->getDatabseConfig($site_directory);

                $database_config_from_backup = $selected_backup_file_info->getDatabaseConfig();

                if ($existing_database_config['dbname'] != $database_config_from_backup['dbname']) {
                    $db_not_matching_question = new ConfirmationQuestion('Database name from the backup appears to be different than existing site. Are you sure to restore?', false);

                    if (!$helper->ask($input, $output, $db_not_matching_question)) {
                        return Command::FAILURE;
                    }
                }

                $output->writeln('<info>Removing all existing files of the site ...</info>');
                exec("rm -rf $site_directory/*");

                $output->writeln('<info>Extracting site files from the backup ...</info>');
                exec("tar --same-owner -zxf \"{$selected_backup_file_info->file->getPathname()}\" --directory $site_directory");
                // $tar_file_name
                // $tar = new Archive_Tar($selected_backup_file_info->file->getPathname());

                $output->writeln('<info>Updating database configurations ...</info>');
                file_put_contents($this->getDatabseConfigFilePath($site_directory), $existing_database_config_file_contents);


                $output->writeln('<info>Dropping all existing tables and views from the database ...</info>');

                $existing_database_config['driver'] ??= 'mysqli';
                $db_connection = DriverManager::getConnection($existing_database_config);

                // Disable foreign key checks. Or it will fail.
                // Here we wan to delete all tables.
                $db_connection->executeStatement("SET FOREIGN_KEY_CHECKS = 0;");

                $schema_manager = $db_connection->createSchemaManager();
        

                $tables = $schema_manager->listTables();

                foreach ($tables as $table) {
                    if ($output->isVeryVerbose()) {
                        $output->writeln('<info>Dropping database table ' . $table->getName() . ' ...</info>');
                    }
                    $schema_manager->dropTable($table->getName());
                }

                $views = $schema_manager->listViews();

                foreach ($views as $view) {
                    if ($output->isVeryVerbose()) {
                        $output->writeln('<info>Dropping database table ' . $view->getName() . ' ...</info>');
                    }
                    $schema_manager->dropView($view->getName());
                }

                $sql_dump_finder = new Finder();
                $sql_dump_finder->depth('== 0');
                $sql_dump_finder->name('*.osb.sql.gz');

                foreach ($sql_dump_finder->in($site_directory) as $file) {
                    $output->writeln('<info>Restoring database from dump ...</info>');
                    exec("gunzip -c {$file->getPathname()} | mysql --user={$existing_database_config['user']} --password={$existing_database_config['password']} --host={$existing_database_config['host']} {$existing_database_config['dbname']}");
                    exec("rm {$file->getPathname()} ");
                    break;
                }

                $backup_info_file_name = static::BACKUP_METADATA_FILENAME;
                if (file_exists($backup_info_file_name)) {
                    // Remove backup metadata file
                    exec("rm {$backup_info_file_name}");
                }

                $styled_output->success('Successfully restored from the backup ' . $selected_backup_file_info->getFileName());
            }

        }
        else {
            $output->writeln('<comment>No backups found to restore.</comment>');
        }

        return Command::SUCCESS;
    }
}