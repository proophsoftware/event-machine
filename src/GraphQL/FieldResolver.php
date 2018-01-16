<?php
declare(strict_types=1);

namespace Prooph\EventMachine\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

interface FieldResolver
{
    public function canResolve($source, array $args, ServerRequestInterface $context, ResolveInfo $info): bool;

    public function resolve($source, array $args, ServerRequestInterface $context, ResolveInfo $info): PromiseInterface;
}
