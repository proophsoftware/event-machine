<?php
declare(strict_types=1);

namespace Prooph\EventMachine\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;

final class FieldResolverChain implements FieldResolver
{
    /**
     * @var FieldResolver[]
     */
    private $chain;

    public function __construct(FieldResolver ...$fieldResolvers)
    {
        $this->chain = $fieldResolvers;
    }

    public function canResolve($source, array $args, ServerRequestInterface $context, ResolveInfo $info): bool
    {
        foreach ($this->chain as $resolver) {
            if($resolver->canResolve($source, $args, $context, $info)) {
                return true;
            }
        }

        return false;
    }

    public function resolve($source, array $args, ServerRequestInterface $context, ResolveInfo $info): PromiseInterface
    {
        foreach ($this->chain as $resolver) {
            if($resolver->canResolve($source, $args, $context, $info)) {
                return $resolver->resolve($source, $args, $context, $info);
            }
        }

        return new FulfilledPromise();
    }
}
