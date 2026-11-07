<?php

declare(strict_types=1);

namespace AndyDefer\Support\Enums;

use AndyDefer\Repository\Contracts\EnumerableInterface;

enum NotificationType: string implements EnumerableInterface
{
    case TICKET_CREATED = 'support_ticket_created';
    case TICKET_ASSIGNED = 'support_ticket_assigned';
    case TICKET_REASSIGNED = 'support_ticket_reassigned';
    case TICKET_UNASSIGNED = 'support_ticket_unassigned';
    case TICKET_STATUS_CHANGED = 'support_ticket_status_changed';
    case TICKET_PRIORITY_CHANGED = 'support_ticket_priority_changed';
    case TICKET_RESOLVED = 'support_ticket_resolved';
    case TICKET_CLOSED = 'support_ticket_closed';
    case TICKET_REOPENED = 'support_ticket_reopened';
    case TICKET_MESSAGE = 'support_ticket_message';
    case TICKET_MESSAGE_REPLY = 'support_ticket_message_reply';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::TICKET_CREATED => 'Ticket créé',
            self::TICKET_ASSIGNED => 'Ticket assigné',
            self::TICKET_REASSIGNED => 'Ticket réassigné',
            self::TICKET_UNASSIGNED => 'Ticket désassigné',
            self::TICKET_STATUS_CHANGED => 'Statut du ticket changé',
            self::TICKET_PRIORITY_CHANGED => 'Priorité du ticket changée',
            self::TICKET_RESOLVED => 'Ticket résolu',
            self::TICKET_CLOSED => 'Ticket fermé',
            self::TICKET_REOPENED => 'Ticket rouvert',
            self::TICKET_MESSAGE => 'Nouveau message',
            self::TICKET_MESSAGE_REPLY => 'Réponse à un message',
        };
    }
}
