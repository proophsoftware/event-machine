<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Container;

use Psr\Container\ContainerInterface;

final class ReflectionBasedContainer implements ContainerInterface
{
    /**
     * @var array
     */
    private $aliasMap;

    /**
     * @var array
     */
    private $serviceFactoryMap;

    private $serviceFactory;

    public function __construct($serviceFactory, array $aliasMap = [], array $serviceFactoryMap = null)
    {
        if (null === $serviceFactoryMap) {
            $serviceFactoryMap = $this->scanServiceFactory($serviceFactory);
        }

        $this->serviceFactory = $serviceFactory;
        $this->aliasMap = $aliasMap;
        $this->serviceFactoryMap = $serviceFactoryMap;
    }

    /**
     * {@inheritdoc}
     */
    public function get($id)
    {
        $id = $this->aliasMap[$id] ?? $id;

        if (! $this->has($id)) {
            throw ServiceNotFound::withServiceId($id);
        }

        return ([$this->serviceFactory, $this->serviceFactoryMap[$id]])();
    }

    /**
     * {@inheritdoc}
     */
    public function has($id)
    {
        $id = $this->aliasMap[$id] ?? $id;

        return array_key_exists($id, $this->serviceFactoryMap);
    }

    /**
     * Cache the array and pass it to constructor again to avoid scanning of service factory
     *
     * @return array
     */
    public function getServiceFactoryMap(): array
    {
        return $this->serviceFactoryMap;
    }

    private function scanServiceFactory($serviceFactory): array
    {
        $serviceFactoryMap = [];

        $ref = new \ReflectionClass($serviceFactory);

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($returnType = $method->getReturnType()) {
                if (! $returnType->isBuiltin()) {
                    $returnTypeName = $method->getReturnType()->getName();

                    if (array_key_exists($returnTypeName, $serviceFactoryMap)) {
                        throw new \RuntimeException(sprintf(
                            'Duplicate return type in service factory detected. Method %s has the same return type like method %s. Type is %s',
                            $method->getName(),
                            $serviceFactoryMap[$returnTypeName],
                            $returnTypeName
                        ));
                    }

                    $serviceFactoryMap[$returnTypeName] = $method->getName();
                }
            }
        }

        return $serviceFactoryMap;
    }
}
