<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Infrastructure\WriteModel;


use Prooph\EventSourcing\Aggregate\AggregateRepository;
use Prooph\Workshop\Model\Configuration;
use Prooph\Workshop\Model\Configuration\ConfigurationRepository;
use Ramsey\Uuid\Uuid;

final class ProophConfigurationRepository extends AggregateRepository implements ConfigurationRepository
{

    public function get(Uuid $configurationId): Configuration
    {
        return $this->getAggregateRoot($configurationId->toString());
    }

    public function save(Configuration $configuration): void
    {
        $this->saveAggregateRoot($configuration);
    }
}
