<?php

declare(strict_types=1);

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\Support\Contracts\Services\TicketRouteManagerServiceInterface;
use AndyDefer\Support\Enums\TicketRoute;

if (! function_exists('support_route')) {
    /**
     * Génère une Closure prête pour une route Laravel.
     *
     * Instancie la Request (validation + Record via getRecord()),
     * instancie le TicketRouteManagerService, puis appelle la méthode
     * de service correspondant au cas de l'enum.
     *
     * @example
     * Route::post('/tickets', support_route(TicketRoute::STORE_TICKET));
     * Route::get('/tickets', support_route(TicketRoute::INDEX_TICKETS));
     */
    function support_route(TicketRoute $route): Closure
    {
        return function () use ($route) {
            /** @var AbstractRequest $request */
            $request = app($route->requestClass());

            /** @var TicketRouteManagerServiceInterface $manager */
            $manager = app(TicketRouteManagerServiceInterface::class);

            $method = $route->serviceMethod();

            $responseFactory = $manager->{$method}($request->getRecord());

            return $responseFactory->toResponse();
        };
    }
}
