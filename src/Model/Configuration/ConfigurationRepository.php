<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Model\Configuration;


use Prooph\Workshop\Model\Configuration;
use Ramsey\Uuid\Uuid;

interface ConfigurationRepository
{
    public function get(Uuid $configurationId): Configuration;

    public function save(Configuration $configuration): void;
}
