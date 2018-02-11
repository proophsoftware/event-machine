<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\FulfilledPromise;

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
        $result = $this->fieldResolver->canResolve($source, $args, $context, $info) ?
            $this->fieldResolver->resolve($source, $args, $context, $info)
            : null;

        if (null === $result) {
            return new FulfilledPromise();
        }

        return $result;
    }
}
