<?php

declare(strict_types = 1);

return \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $r) {
    $r->addRoute(
        ['GET'],
        '/',
        \Prooph\Workshop\Http\Home::class
    );

    $r->addRoute(
        ['POST'],
        '/messagebox',
        \Prooph\Workshop\Http\MessageBox::class
    );
});
