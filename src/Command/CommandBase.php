<?php

namespace IDAF\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use IDAF\IDAFOutputStyle;

abstract class CommandBase extends Command {

    const CONFIG_FILE_NAME = '.omeka-s-cli.yml';

    /**
     * Styled output helper.
     *
     * @var \IDAF\IDAFOutputStyle
     */
    protected $styledOutput;

    public function getConfig(): array {
        $config = [];

        $config_file_path = $_SERVER['HOME'] . '/' . static::CONFIG_FILE_NAME;
        if (file_exists($config_file_path)) {
            $config = Yaml::parseFile($config_file_path);
        }

        return $config;
    }

    protected function configure(): void {

        $config = $this->getConfig();

        if (isset($config['site-dir'])) {
            $default_root_dir = $config['site-dir'];
        }
        else {
            $default_root_dir = getcwd();
        }

        $this
            ->addOption('site-dir', 'r', InputOption::VALUE_REQUIRED, 'Omeka S site directory.', $default_root_dir)
        ;
    }

    /**
     * Get styled output helper.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *  Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *  Output
     * @return \IDAF\IDAFOutputStyle
     */
    protected function getStyledOutput(InputInterface $input, OutputInterface $output) {
        if (!$this->styledOutput) {
            $this->styledOutput = new IDAFOutputStyle($input, $output);
        }
        return $this->styledOutput;
    }
}