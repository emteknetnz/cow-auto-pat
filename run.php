<?php

use emteknetnz\CowAutoPat\Updater;

require __DIR__ . '/vendor/autoload.php';

$updater = new Updater();
$updater->update('dev/.cow.pat.json', 'dev/.prior.cow.pat.json', 'output.json');
