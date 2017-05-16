<?php
declare(strict_types = 1);

namespace ProophExample;

use Prooph\EventMachine\EventMachine;
use ProophExample\Aggregate\Aggregate;
use ProophExample\Messaging\Command;

chdir(dirname(__DIR__));

require 'vendor/autoload.php';

$eventMachine = new EventMachine();

$eventMachine->load(include 'examples/Aggregate')