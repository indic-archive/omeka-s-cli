<?php

namespace IDAF;

use Symfony\Component\Console\Application;

use IDAF\Command\BackupCreate;

class OmekaSApp extends Application {

    public function __construct() {
        parent::__construct('Omeka S Command Line Tools', \Composer\InstalledVersions::getRootPackage()['version']);
    }

    public function initCommands() {
        // ... register commands
        $this->add(new BackupCreate());
    }
}