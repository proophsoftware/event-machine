<?php
declare(strict_types = 1);

namespace Prooph\Workshop\Http;

use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Zend\Diactoros\Response\TextResponse;

final class Home implements MiddlewareInterface
{
    /**
     * @inheritdoc
     */
    public function process(RequestInterface $request, DelegateInterface $delegate)
    {
        return new TextResponse("It works");
    }
}
