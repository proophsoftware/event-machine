<?php
declare(strict_types = 1);

namespace ProophExample;

use Prooph\EventMachine\EventMachine;
use ProophExample\Aggregate\Aggregate;
use ProophExample\Aggregate\UserDescription;
use ProophExample\Messaging\Command;
use ProophExample\Messaging\MessageDescription;

chdir(dirname(__DIR__));

require 'vendor/autoload.php';

$eventMachine = new EventMachine();

$eventMachine->load(MessageDescription::class);
$eventMachine->load(UserDescription::class);

$eventMachine->bootstrap();

