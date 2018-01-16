<?php
declare(strict_types=1);

namespace Prooph\EventMachine\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;

final class FieldResolverProxy
{
    /**
     * @var FieldResolver
     */
    private $fieldResolver;

    public function __construct(FieldResolver $fieldResolver)
    {
        $this->fieldResolver = $fieldResolver;
    }

    public function __invoke($source, array $args, ServerRequestInterface $context, ResolveInfo $info)
    {
        $result = $this->fieldResolver->canResolve($source, $args, $context, $info)?
            $this->fieldResolver->resolve($source, $args, $context, $info)
            : null;

        if(null === $result) {
            return new FulfilledPromise();
        }

        return $result;
    }
}
