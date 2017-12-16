<?php

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
        if(null === $serviceFactoryMap) {
            $serviceFactoryMap = $this->scanServiceFactory($serviceFactory);
        }

        $this->serviceFactory = $serviceFactory;
        $this->aliasMap = $aliasMap;
        $this->serviceFactoryMap = $serviceFactoryMap;
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $id = $this->aliasMap[$id] ?? $id;

        if(!$this->has($id)) {
            throw ServiceNotFound::withServiceId($id);
        }

        return ([$this->serviceFactory, $this->serviceFactoryMap[$id]])();
    }

    /**
     * @inheritdoc
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
            if($returnType = $method->getReturnType()) {
                if(!$returnType->isBuiltin()) {
                    $serviceFactoryMap[$method->getReturnType()->getName()] = $method->getName();
                }
            }
        }

        return $serviceFactoryMap;
    }
}
