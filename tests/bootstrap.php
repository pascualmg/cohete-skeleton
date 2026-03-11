<?php

require __DIR__ . '/../vendor/autoload.php';

use Rx\Scheduler;
use Rx\Scheduler\ImmediateScheduler;

Scheduler::setDefaultFactory(static fn () => new ImmediateScheduler());
