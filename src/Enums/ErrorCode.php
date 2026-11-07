<?php

declare(strict_types=1);

namespace AndyDefer\Support\Enums;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Contracts\ErrorDescribable;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
use AndyDefer\PhpVo\Enums\HttpStatusCode;

enum ErrorCode: string implements ErrorDescribable
{
    // Not found (404)
    case TICKET_NOT_FOUND = 'TICKET_NOT_FOUND';
    case MESSAGE_NOT_FOUND = 'MESSAGE_NOT_FOUND';
    case RESOLVER_NOT_FOUND = 'RESOLVER_NOT_FOUND';
    case REPLY_TO_NOT_FOUND = 'REPLY_TO_NOT_FOUND';

    // Conflict (409)
    case TICKET_ALREADY_ASSIGNED = 'TICKET_ALREADY_ASSIGNED';
    case TICKET_NOT_ASSIGNED = 'TICKET_NOT_ASSIGNED';
    case TICKET_ALREADY_CLOSED = 'TICKET_ALREADY_CLOSED';
    case TICKET_ALREADY_RESOLVED = 'TICKET_ALREADY_RESOLVED';
    case MESSAGE_NOT_IN_TICKET = 'MESSAGE_NOT_IN_TICKET';
    case MESSAGE_ALREADY_READ = 'MESSAGE_ALREADY_READ';

    // Unprocessable (422)
    case INVALID_TICKET_STATUS_TRANSITION = 'INVALID_TICKET_STATUS_TRANSITION';
    case INVALID_TICKET_PRIORITY = 'INVALID_TICKET_PRIORITY';
    case INVALID_TICKET_STATUS = 'INVALID_TICKET_STATUS';
    case RESOLVER_TYPE_NOT_NOTIFIABLE = 'RESOLVER_TYPE_NOT_NOTIFIABLE';
    case CREATOR_TYPE_NOT_NOTIFIABLE = 'CREATOR_TYPE_NOT_NOTIFIABLE';
    case INVALID_UUID = 'INVALID_UUID';

    // Forbidden (403)
    case UNAUTHORIZED_ACTION = 'UNAUTHORIZED_ACTION';
    case NOT_A_PARTICIPANT = 'NOT_A_PARTICIPANT';

    // Server (500)
    case TICKET_CREATION_FAILED = 'TICKET_CREATION_FAILED';
    case MESSAGE_CREATION_FAILED = 'MESSAGE_CREATION_FAILED';
    case NOTIFICATION_FAILED = 'NOTIFICATION_FAILED';

    public function getHttpStatusCode(): HttpStatusCode
    {
        return match ($this) {
            self::TICKET_NOT_FOUND,
            self::MESSAGE_NOT_FOUND,
            self::RESOLVER_NOT_FOUND,
            self::REPLY_TO_NOT_FOUND => HttpStatusCode::NOT_FOUND,

            self::TICKET_ALREADY_ASSIGNED,
            self::TICKET_NOT_ASSIGNED,
            self::TICKET_ALREADY_CLOSED,
            self::TICKET_ALREADY_RESOLVED,
            self::MESSAGE_NOT_IN_TICKET,
            self::MESSAGE_ALREADY_READ => HttpStatusCode::CONFLICT,

            self::INVALID_TICKET_STATUS_TRANSITION,
            self::INVALID_TICKET_PRIORITY,
            self::INVALID_TICKET_STATUS,
            self::RESOLVER_TYPE_NOT_NOTIFIABLE,
            self::CREATOR_TYPE_NOT_NOTIFIABLE,
            self::INVALID_UUID => HttpStatusCode::UNPROCESSABLE_ENTITY,

            self::UNAUTHORIZED_ACTION,
            self::NOT_A_PARTICIPANT => HttpStatusCode::FORBIDDEN,

            self::TICKET_CREATION_FAILED,
            self::MESSAGE_CREATION_FAILED,
            self::NOTIFICATION_FAILED => HttpStatusCode::INTERNAL_SERVER_ERROR,
        };
    }

    public function getMessage(): string
    {
        return match ($this) {
            self::TICKET_NOT_FOUND => 'Ticket not found',
            self::MESSAGE_NOT_FOUND => 'Message not found',
            self::RESOLVER_NOT_FOUND => 'Resolver not found',
            self::REPLY_TO_NOT_FOUND => 'Parent message to reply to not found',

            self::TICKET_ALREADY_ASSIGNED => 'Ticket is already assigned to a resolver',
            self::TICKET_NOT_ASSIGNED => 'Ticket has no resolver assigned',
            self::TICKET_ALREADY_CLOSED => 'Ticket is already closed',
            self::TICKET_ALREADY_RESOLVED => 'Ticket is already resolved',
            self::MESSAGE_NOT_IN_TICKET => 'Message does not belong to the given ticket',
            self::MESSAGE_ALREADY_READ => 'Message is already marked as read',

            self::INVALID_TICKET_STATUS_TRANSITION => 'Invalid ticket status transition',
            self::INVALID_TICKET_PRIORITY => 'Invalid ticket priority',
            self::INVALID_TICKET_STATUS => 'Invalid ticket status',
            self::RESOLVER_TYPE_NOT_NOTIFIABLE => 'Resolver model must implement NotifiableInterface',
            self::CREATOR_TYPE_NOT_NOTIFIABLE => 'Creator model must implement NotifiableInterface',
            self::INVALID_UUID => 'Provided value is not a valid UUID',

            self::UNAUTHORIZED_ACTION => 'You are not allowed to perform this action',
            self::NOT_A_PARTICIPANT => 'You are not a participant of this ticket',

            self::TICKET_CREATION_FAILED => 'Ticket creation failed',
            self::MESSAGE_CREATION_FAILED => 'Message creation failed',
            self::NOTIFICATION_FAILED => 'Failed to dispatch notification',
        };
    }

    public function getLabel(): string
    {
        return $this->getMessage();
    }

    public function toResponseData(
        ?string $message = null,
        array|StrictAssociative|StrictDataObject|null $errors = null,
    ): ErrorResponseData {
        return ErrorResponseData::from([
            'errorCode' => $this->value,
            'message'   => $message ?? $this->getMessage(),
            'status'    => $this->getHttpStatusCode(),
            'errors'    => $errors,
        ]);
    }

    public function toJsonResponseFactory(
        ?string $message = null,
        array|StrictAssociative|StrictDataObject|null $errors = null,
    ): ResponseFactory {
        return ResponseFactory::json(
            $this->toResponseData($message, $errors),
            $this->getHttpStatusCode(),
        );
    }
}
