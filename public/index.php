<?php
declare(strict_types=1);

use \Psr\Http\Message\ServerRequestInterface as Request;

chdir(dirname(__DIR__));

require 'vendor/autoload.php';

$factories = require 'app/factories.php';

$app = new \Zend\Stratigility\MiddlewarePipe();

//Strategility 1.3 preparation for 2.0: see: https://docs.zendframework.com/zend-stratigility/migration/to-v2/
$app->raiseThrowables();

$errorHandler = new \Zend\Stratigility\Middleware\ErrorHandler(
    new \Zend\Diactoros\Response(),
    new \Zend\Stratigility\Middleware\ErrorResponseGenerator(true)
);

$errorHandler->attachListener(new \Prooph\Workshop\Infrastructure\Http\PsrErrorLogger(
    $factories['logger']()
));

$app->pipe($errorHandler);

$app->pipe(new \Zend\Expressive\Helper\BodyParams\BodyParamsMiddleware());

$app->pipe('/api/v1', function (Request $req, \Interop\Http\Middleware\DelegateInterface $delegate) use($factories) {
    /** @var FastRoute\Dispatcher $router */
    $router = require 'app/router.php';

    $route = $router->dispatch($req->getMethod(), $req->getUri()->getPath());

    if ($route[0] === FastRoute\Dispatcher::NOT_FOUND) {
        return $delegate->process($req);
    }

    if ($route[0] === FastRoute\Dispatcher::METHOD_NOT_ALLOWED) {
        return new \Zend\Diactoros\Response\EmptyResponse(405);
    }

    foreach ($route[2] as $name => $value) {
        $req = $req->withAttribute($name, $value);
    }

    if(!isset($factories['http'][$route[1]])) {
        throw new \RuntimeException("Http handler not found. Got " . $route[1]);
    }

    /** @var \Interop\Http\Middleware\ServerMiddlewareInterface $httpHandler */
    $httpHandler = $factories['http'][$route[1]]();

    return $httpHandler->process($req, $delegate);
});

$app->pipe('/', function(Request $req, \Interop\Http\Middleware\DelegateInterface $delegate) use($factories) {
    return $factories['http'][\Prooph\Workshop\Http\Home::class]()->process($req, $delegate);
});

$app->pipe(new \Zend\Stratigility\Middleware\NotFoundHandler(new Zend\Diactoros\Response()));

$server = \Zend\Diactoros\Server::createServer(
    $app,
    $_SERVER,
    $_GET,
    $_POST,
    $_COOKIE,
    $_FILES
);

$server->listen(new \Zend\Stratigility\NoopFinalHandler());