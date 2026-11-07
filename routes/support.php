<?php

declare(strict_types=1);

use AndyDefer\Support\Enums\TicketRoute;
use Illuminate\Support\Facades\Route;

/*
 * Support API routes.
 *
 * Every route is bound to a TicketRoute enum case, which links the
 * inbound Request to the matching TicketRouteManagerService method
 * through the `support_route` helper.
 */

Route::name('api.')
    ->middleware(['nemesis.api.verified'])
    ->group(function (): void {

        Route::prefix('/tickets')->name('tickets.')->group(function (): void {

            Route::post('/', support_route(TicketRoute::STORE_TICKET))->name('store');
            Route::get('/', support_route(TicketRoute::INDEX_TICKETS))->name('index');

            Route::prefix('/{id}')
                ->where(['id' => '[0-9a-f-]{36}'])
                ->group(function (): void {

                    Route::get('/', support_route(TicketRoute::SHOW_TICKET))->name('show');
                    Route::patch('/', support_route(TicketRoute::UPDATE_TICKET))->name('update');
                    Route::delete('/', support_route(TicketRoute::DESTROY_TICKET))->name('destroy');

                    Route::post('/assign', support_route(TicketRoute::ASSIGN_TICKET))->name('assign');
                    Route::post('/reassign', support_route(TicketRoute::REASSIGN_TICKET))->name('reassign');
                    Route::post('/unassign', support_route(TicketRoute::UNASSIGN_TICKET))->name('unassign');
                    Route::post('/resolve', support_route(TicketRoute::RESOLVE_TICKET))->name('resolve');
                    Route::post('/close', support_route(TicketRoute::CLOSE_TICKET))->name('close');
                    Route::post('/reopen', support_route(TicketRoute::REOPEN_TICKET))->name('reopen');
                    Route::post('/status', support_route(TicketRoute::UPDATE_STATUS))->name('status');
                    Route::post('/priority', support_route(TicketRoute::UPDATE_PRIORITY))->name('priority');
                });
        });

        Route::prefix('/tickets/{ticketId}/messages')
            ->where(['ticketId' => '[0-9a-f-]{36}'])
            ->name('tickets.messages.')
            ->group(function (): void {

                Route::post('/', support_route(TicketRoute::STORE_MESSAGE))->name('store');
                Route::get('/', support_route(TicketRoute::INDEX_MESSAGES))->name('index');
                Route::get('/thread', support_route(TicketRoute::THREAD_MESSAGES))->name('thread');
                Route::post('/read-all', support_route(TicketRoute::MARK_ALL_MESSAGES_READ))->name('read-all');

                Route::prefix('/{id}')
                    ->where(['id' => '[0-9a-f-]{36}'])
                    ->group(function (): void {

                        Route::get('/', support_route(TicketRoute::SHOW_MESSAGE))->name('show');
                        Route::post('/dispatch', support_route(TicketRoute::MARK_MESSAGE_DISPATCHED))->name('dispatch');
                        Route::post('/read', support_route(TicketRoute::MARK_MESSAGE_READ))->name('read');
                    });
            });
    });
