#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';
use IDAF\OmekaSApp;

$application = new OmekaSApp();
$application->initCommands();

$application->run();