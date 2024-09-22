<?php

namespace IDAF\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use IDAF\GithubRelease;

#[AsCommand(
    name: 'update',
    description: 'Update an Omeka S installation.',
    hidden: false,
    aliases: []
)]
class Update extends CommandBase
{
    protected function configure(): void {
        parent::configure();
        $this
            ->addOption('list', NULL, InputOption::VALUE_NONE , 'List available releases.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $styled_output = $this->getStyledOutput($input, $output);
        $site_directory = $this->getSiteDirectory($input, $output);
        /** @var \Symfony\Component\Console\Helper\QuestionHelper */
        $question_helper = $this->getHelper('question');

        $list = $input->getOption('list');

        
        $client = new \GuzzleHttp\Client();
        if (!$list) {
            $response = $client->request('GET', 'https://api.github.com/repos/omeka/omeka-s/releases/latest');
        }
        else {
            $response = $client->request('GET', 'https://api.github.com/repos/omeka/omeka-s/releases');
        }
        $body = json_decode($response->getBody()->getContents());

        if ($list) {
            $release_options = [];
            foreach ($body as $item) {
                $release_options[] = new GithubRelease($item);
            }
            $question = new ChoiceQuestion(
                'Please choose a release',
                array_merge(['Cancel'],  $release_options),
                0
            );
            $question->setErrorMessage('Selection %s is invalid.');

            $selected_release = $question_helper->ask($input, $output, $question);

            if (is_string($selected_release)) {
                $output->writeln('<comment>Operation has been cancelled.</comment>');
                return static::SUCCESS;
            }
            else {
                $output->writeln('You have just selected: '. $selected_release);
            }
        }
        else {
            $selected_release = new GithubRelease($body);
        }

        $download_url = NULL;
        if ($selected_release) {
            /** @var \IDAF\GithubRelease $selected_release */

            $download_url = $selected_release->getDownloadUrl();
            if (!empty($download_url)) {
                $db_not_matching_question = new ConfirmationQuestion("Update to {$selected_release}? [y/n]: ", false);

                if (!$question_helper->ask($input, $output, $db_not_matching_question)) {
                    $styled_output->note('Updating cancelled!');
                }
                else {
                    // $download_url = 'https://github.com/omeka/omeka-s/releases/download/v4.1.1/omeka-s-4.1.1.zip';

                    $release_filepath = $this->downloadRelease($download_url);
                    if ($release_filepath) {
                        $file_system = new Filesystem();

                        $extracted_dir = Path::join($this->getTempDirectory(), 'omeka-s');
                        $file_system->remove($extracted_dir);

                        $zip = new \ZipArchive;
                        $res = $zip->open($release_filepath);
                        if ($res === TRUE) {
                            $zip->extractTo($this->getTempDirectory());
                            $zip->close();
                            $styled_output->taskDone('Extracted zip file.');

                            $finder = new Finder();
                            $finder->in($extracted_dir);
                            $finder->depth('== 0');
                            $finder->exclude('modules');
                            $finder->exclude('themes');
                            $finder->exclude('files');
                            $finder->notName('config');

                            $output->writeln('Deleting files and directory from site:');
                            foreach ($finder as $file) {
                                $file_system->remove(Path::join($site_directory, $file->getRelativePathname()));
                                // $output->writeln('  Deleting existing ' . $file->getRelativePathname() . ' ...');
                                $styled_output->taskDone('  ' . $file->getRelativePathname());
                            }

                            $finder = new Finder();
                            $finder->in($extracted_dir);
                            $finder->exclude('modules');
                            $finder->exclude('themes');
                            $finder->exclude('files');
                            $finder->notPath('config/local.config.php');
                            $finder->notPath('config/database.ini');
                            $output->writeln('Copying files from new release ... ');
                            $styled_output->taskDone('  Done');
                            $file_system->mirror($extracted_dir, $site_directory, $finder, ['override' => TRUE]);
                            $styled_output->taskDone('Completed all operations!');
                            $styled_output->success('Now go to /admin page of your Omeka S site to apply and pending database updates.');
                        } else {
                            $styled_output->error('Could not open zip file downloaded!');
                            return static::FAILURE;
                        }
                    }
                    else {
                        $styled_output->error('Downloading failed!');
                        return static::FAILURE;
                    }
                }
            }
            else {
                $styled_output->error('Now download URL found!');
                return static::FAILURE;
            }
        }

        return static::SUCCESS;
    }

    /**
     * Get temporary directory for Omeka-S purposes.
     */
    private function getTempDirectory(): string {
        $path = Path::join(sys_get_temp_dir(), 'omeka-s-cli');
        if (!file_exists($path)) {
            mkdir($path);
        }
        return $path;
    }

    /**
     * Download a release file to temporary directory.
     */
    private function downloadRelease($url): ?string {
        $file_name = basename($url);

        $temp_dir = $this->getTempDirectory();

        if (!file_exists($temp_dir)) {
            mkdir($temp_dir);
        }

        $destination_filepath = Path::join($temp_dir, $file_name);
        if (!file_exists($destination_filepath)) { 
            copy($url, $destination_filepath);
        }
        if (filesize($destination_filepath)) {
            return $destination_filepath;
        }
        return FALSE;
    }
}