# Laravel Support

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andydefer/laravel-support.svg?style=flat-square)](https://packagist.org/packages/andydefer/laravel-support)
[![Total Downloads](https://img.shields.io/packagist/dt/andydefer/laravel-support.svg?style=flat-square)](https://packagist.org/packages/andydefer/laravel-support)
[![License](https://img.shields.io/packagist/l/andydefer/laravel-support.svg?style=flat-square)](https://packagist.org/packages/andydefer/laravel-support)

Système complet de gestion de tickets de support pour Laravel : cycle de vie, assignation polymorphe, messagerie, notifications, API REST prête à consommer.

---

## Sommaire

1. [Fonctionnalités](#fonctionnalités)
2. [Prérequis](#prérequis)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Concepts](#concepts)
6. [Utilisation rapide](#utilisation-rapide)
7. [API REST](#api-rest)
8. [Service métier](#service-métier)
9. [Notifications](#notifications)
10. [Hooks d'extension](#hooks-dextension)
11. [Modèles et migrations](#modèles-et-migrations)
12. [Tests](#tests)
13. [Architecture](#architecture)
14. [Contribuer](#contribuer)
15. [Licence](#licence)

---

## Fonctionnalités

- **Cycle de vie complet** : création, assignation, réassignation, désassignation, résolution, fermeture, réouverture
- **Assignation polymorphe** : n'importe quel modèle Eloquent (`User`, `Admin`, `Agent`…) peut être assigné
- **Messagerie filaire** : réponses imbriquées (`reply_to_id`), messages internes vs publics
- **Statuts et priorités** : `OPEN` / `PENDING` / `RESOLVED` / `CLOSED`, `LOW` / `NORMAL` / `HIGH` / `URGENT`
- **Notifications best-effort** : n'échouent jamais la persistance métier
- **API REST auto-enregistrée** : 19 routes prêtes via `TicketRoute` enum
- **Architecture ADR** : Route → Request → Record → Service → Data
- **Hooks extensibles** : `before*` / `after*` pour l'autorisation, le logging, les métriques
- **DTOs typés de bout en bout** : `AbstractRecord` en entrée, `AbstractData` en sortie, jamais de modèle brut
- **Filtres avancés** : recherche full-text, plage de dates, cluster JSON (`whereCluster`)
- **Soft deletes** sur tickets et messages
- **UUID v4** partout
- **Métadonnées JSON libres** sur tickets et messages

---

## Prérequis

- **PHP** : `^8.2`
- **Laravel** : `^11.0` ou `^12.0`
- **Base de données** : MySQL, PostgreSQL, SQLite (avec support JSON)

---

## Installation

```bash
composer require andydefer/laravel-support
```

Le `SupportServiceProvider` s'enregistre automatiquement via l'auto-discovery Laravel.

### Publier les migrations

```bash
php artisan vendor:publish --tag=support-migrations
php artisan migrate
```

### Publier les routes (optionnel)

```bash
php artisan vendor:publish --tag=support-routes
```

Les routes sont publiées dans `routes/support.php` et peuvent être personnalisées (préfixe, middleware, etc.).

---

## Configuration

Le package ne fournit **aucun fichier de configuration**. Les valeurs par défaut sont :

| Élément | Valeur |
|---|---|
| Tables | `support_tickets`, `support_ticket_messages` |
| Préfixe des routes | `api.` |
| Middleware par défaut | `nemesis.api.verified` |
| Pagination tickets | 15 par page |
| Pagination messages | 30 par page |
| Pagination thread | 50 par page |
| Format de référence | `TKT-XXXXXXXXXX` |

---

## Concepts

### Ticket

Unité centrale du domaine. Chaque ticket possède :
- une **référence** unique lisible (`TKT-AB12CD34EF`)
- un **créateur** (relation polymorphe)
- un **résolveur** optionnel (relation polymorphe)
- un **statut**, une **priorité**
- des **horodatages de cycle de vie** (`assigned_at`, `resolved_at`, `closed_at`)

### Message

Message rattaché à un ticket. Possède :
- un **expéditeur** identifié par son rôle (`CREATOR` ou `RESOLVER`)
- un **statut** de livraison (`SENT`, `DISPATCHED`, `READ`)
- un indicateur **interne/public**
- la possibilité de **répondre à un message parent**

### Participant

Deux participants sont associés à un ticket :
- **Créateur** — l'auteur du ticket
- **Résolveur** — l'agent assigné (optionnel)

Un message envoyé par un participant **n'auto-notifie jamais** son expéditeur.

---

## Utilisation rapide

### Créer un ticket

```php
use AndyDefer\Support\Contracts\Services\SupportServiceInterface;
use AndyDefer\Support\Enums\TicketPriority;

$service = app(SupportServiceInterface::class);

$ticket = $service->createTicket(
    creator: $user,
    title: 'Problème de connexion',
    description: 'Impossible de me connecter depuis ce matin.',
    priority: TicketPriority::HIGH,
);
```

### Assigner un ticket

```php
$service->assignTicket($ticket, $agent);
```

### Ajouter un message

```php
$service->addMessage(
    ticket: $ticket,
    sender: $user,
    content: 'Voici plus de détails...',
    isInternal: false,
);
```

### Résoudre et fermer

```php
$service->resolveTicket($ticket);
$service->closeTicket($ticket);
```

### Rechercher des tickets

```php
use AndyDefer\Support\Records\TicketFiltersRecord;
use AndyDefer\Support\Enums\TicketStatus;

$filters = new TicketFiltersRecord(
    search: 'connexion',
    status: TicketStatus::OPEN,
    is_assigned: true,
);

$paginator = $service->paginateTickets($filters, perPage: 20);
```

---

## API REST

Toutes les routes sont **déjà enregistrées** par le package et protégées par `nemesis.api.verified`.

### Tickets

| Méthode | URI | Action | Description |
|---|---|---|---|
| `POST` | `/api/tickets` | `store` | Créer un ticket |
| `GET` | `/api/tickets` | `index` | Lister les tickets |
| `GET` | `/api/tickets/{id}` | `show` | Afficher un ticket |
| `PATCH` | `/api/tickets/{id}` | `update` | Modifier titre/description/priorité |
| `DELETE` | `/api/tickets/{id}` | `destroy` | Supprimer un ticket |
| `POST` | `/api/tickets/{id}/assign` | `assign` | Assigner un résolveur |
| `POST` | `/api/tickets/{id}/reassign` | `reassign` | Changer de résolveur |
| `POST` | `/api/tickets/{id}/unassign` | `unassign` | Retirer le résolveur |
| `POST` | `/api/tickets/{id}/resolve` | `resolve` | Marquer résolu |
| `POST` | `/api/tickets/{id}/close` | `close` | Fermer |
| `POST` | `/api/tickets/{id}/reopen` | `reopen` | Réouvrir |
| `POST` | `/api/tickets/{id}/status` | `status` | Changer le statut |
| `POST` | `/api/tickets/{id}/priority` | `priority` | Changer la priorité |

### Messages

| Méthode | URI | Action | Description |
|---|---|---|---|
| `POST` | `/api/tickets/{ticketId}/messages` | `store` | Envoyer un message |
| `GET` | `/api/tickets/{ticketId}/messages` | `index` | Lister les messages |
| `GET` | `/api/tickets/{ticketId}/messages/thread` | `thread` | Fil complet paginé |
| `POST` | `/api/tickets/{ticketId}/messages/read-all` | `read-all` | Tout marquer lu |
| `GET` | `/api/tickets/{ticketId}/messages/{id}` | `show` | Afficher un message |
| `POST` | `/api/tickets/{ticketId}/messages/{id}/dispatch` | `dispatch` | Marquer distribué |
| `POST` | `/api/tickets/{ticketId}/messages/{id}/read` | `read` | Marquer lu |

### Exemple de requête

```bash
curl -X POST https://votre-app.com/api/tickets \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Problème de connexion",
    "description": "Impossible de me connecter.",
    "priority": "high",
    "metadata": {"browser": "Chrome", "os": "Linux"}
  }'
```

### Exemple de réponse

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "reference": "TKT-AB12CD34EF",
  "title": "Problème de connexion",
  "description": "Impossible de me connecter.",
  "createdByType": "App\\Models\\User",
  "createdById": "42",
  "assignedToType": null,
  "assignedToId": null,
  "status": "open",
  "priority": "high",
  "assignedAt": null,
  "resolvedAt": null,
  "closedAt": null,
  "metadata": {"browser": "Chrome", "os": "Linux"},
  "createdAt": "2026-11-07T10:30:00+00:00",
  "updatedAt": "2026-11-07T10:30:00+00:00"
}
```

### Codes d'erreur

Toutes les erreurs respectent ce format :

```json
{
  "errorCode": "TICKET_NOT_FOUND",
  "message": "Ticket not found",
  "status": 404
}
```

| Code | HTTP | Description |
|---|---|---|
| `TICKET_NOT_FOUND` | 404 | Ticket inexistant |
| `MESSAGE_NOT_FOUND` | 404 | Message inexistant |
| `RESOLVER_NOT_FOUND` | 404 | Résolveur inexistant |
| `REPLY_TO_NOT_FOUND` | 404 | Message parent inexistant |
| `TICKET_ALREADY_ASSIGNED` | 409 | Ticket déjà assigné |
| `TICKET_NOT_ASSIGNED` | 409 | Ticket non assigné |
| `TICKET_ALREADY_CLOSED` | 409 | Ticket déjà fermé |
| `TICKET_ALREADY_RESOLVED` | 409 | Ticket déjà résolu |
| `MESSAGE_NOT_IN_TICKET` | 409 | Message hors du ticket |
| `MESSAGE_ALREADY_READ` | 409 | Message déjà lu |
| `INVALID_TICKET_STATUS_TRANSITION` | 422 | Transition de statut invalide |
| `RESOLVER_TYPE_NOT_NOTIFIABLE` | 422 | Résolveur non notifiable |
| `CREATOR_TYPE_NOT_NOTIFIABLE` | 422 | Créateur non notifiable |
| `UNAUTHORIZED_ACTION` | 403 | Action non autorisée |
| `NOT_A_PARTICIPANT` | 403 | Non participant au ticket |
| `TICKET_CREATION_FAILED` | 500 | Échec de création |

---

## Service métier

`SupportServiceInterface` expose **deux niveaux d'API** :

### Cycle de vie tickets

```php
createTicket(Model $creator, string $title, string $description, ?TicketPriority $priority = null, ?StrictAssociative $metadata = null): Ticket
assignTicket(Ticket $ticket, Model $resolver): Ticket
changeResolver(Ticket $ticket, Model $newResolver): Ticket
unassignTicket(Ticket $ticket): Ticket
updateStatus(Ticket $ticket, TicketStatus $status): Ticket
updatePriority(Ticket $ticket, TicketPriority $priority): Ticket
resolveTicket(Ticket $ticket): Ticket
closeTicket(Ticket $ticket): Ticket
reopenTicket(Ticket $ticket): Ticket
```

### Lecture tickets

```php
listTickets(TicketFiltersRecord $filters, int $limit = 50): Collection
paginateTickets(TicketFiltersRecord $filters, int $perPage = 15, int $page = 1): LengthAwarePaginator
findTicket(string $ticketId): ?Ticket
findTicketByReference(string $reference): ?Ticket
countTickets(TicketFiltersRecord $filters): int
```

### Messages

```php
addMessage(Ticket $ticket, Model $sender, string $content, ?TicketMessage $replyTo = null, bool $isInternal = false, ?StrictAssociative $metadata = null): TicketMessage
listMessages(TicketMessageFiltersRecord $filters, int $limit = 100): Collection
paginateMessages(TicketMessageFiltersRecord $filters, int $perPage = 30, int $page = 1): LengthAwarePaginator
threadMessages(Ticket $ticket, bool $includeInternal = false): Collection
findMessage(string $messageId): ?TicketMessage
countMessages(TicketMessageFiltersRecord $filters): int
markMessageDispatched(TicketMessage $message): TicketMessage
markMessageRead(TicketMessage $message): TicketMessage
markAllMessagesRead(Ticket $ticket, Model $reader): int
```

---

## Notifications

Le package émet automatiquement des notifications vers les participants via `andydefer/laravel-notification`.

### Types émis

| Événement | `NotificationType` | Destinataire |
|---|---|---|
| Assignation | `TICKET_ASSIGNED` | Nouveau résolveur |
| Réassignation | `TICKET_REASSIGNED` | Ancien résolveur |
| Désassignation | `TICKET_UNASSIGNED` | Résolveur retiré |
| Changement de statut | `TICKET_STATUS_CHANGED` | Tous les participants |
| Changement de priorité | `TICKET_PRIORITY_CHANGED` | Tous les participants |
| Résolution | `TICKET_RESOLVED` | Tous les participants |
| Fermeture | `TICKET_CLOSED` | Tous les participants |
| Réouverture | `TICKET_REOPENED` | Tous les participants |
| Nouveau message | `TICKET_MESSAGE` | Autres participants |
| Réponse | `TICKET_MESSAGE_REPLY` | Autres participants |

### Comportement

- **Best-effort** : un échec de notification ne provoque jamais de rollback métier
- **Canal** : `DatabaseChannel` uniquement, `limit_per_channel = 1`
- **Payload** : `ticket_id`, `ticket_reference`, `ticket_title`, `notification_type`
- **Destinataire exclu** : le sender d'un message n'est jamais notifié de son propre message

### Prérequis

Le résolveur et le créateur **doivent implémenter** `AndyDefer\LaravelNotification\Contracts\NotifiableInterface`.

---

## Hooks d'extension

`TicketRouteManagerService` (non-`final`) expose des hooks protégés que vous pouvez surcharger :

```php
protected function beforeStoreTicket(Model $creator, string $title, string $description, ?TicketPriority $priority, ?StrictAssociative $metadata): ?ErrorResponseData
protected function afterStoreTicket(Ticket $ticket): ?ErrorResponseData
protected function beforeAssignTicket(string $ticketId, Model $resolver): ?ErrorResponseData
protected function afterAssignTicket(Ticket $ticket, Model $resolver): ?ErrorResponseData
// ... un before/after par action
```

### Contrat

- Retourner `null` → continue l'exécution normale
- Retourner un `ErrorResponseData` → interrompt et renvoie la réponse

### Exemple : bloquer la fermeture d'un ticket non résolu

```php
namespace App\Services;

use AndyDefer\Nemesis\Datas\ErrorResponseData;
use AndyDefer\Support\Enums\ErrorCode;
use AndyDefer\Support\Enums\TicketStatus;
use AndyDefer\Support\Models\Ticket;
use AndyDefer\Support\Services\TicketRouteManagerService;

final class CustomTicketRouteManagerService extends TicketRouteManagerService
{
    protected function beforeCloseTicket(string $ticketId): ?ErrorResponseData
    {
        $ticket = $this->support->findTicket($ticketId);

        if ($ticket !== null && $ticket->status !== TicketStatus::RESOLVED) {
            return ErrorCode::INVALID_TICKET_STATUS_TRANSITION->toResponseData(
                'Le ticket doit être résolu avant d\'être fermé.'
            );
        }

        return null;
    }
}
```

Puis enregistrez votre implémentation dans un Service Provider :

```php
$this->app->bind(
    \AndyDefer\Support\Contracts\Services\TicketRouteManagerServiceInterface::class,
    \App\Services\CustomTicketRouteManagerService::class,
);
```

---

## Modèles et migrations

### Schéma `support_tickets`

| Colonne | Type | Description |
|---|---|---|
| `id` | UUID (PK) | Identifiant |
| `reference` | string(32) unique | Référence lisible |
| `title` | string(191) | Sujet |
| `description` | longtext | Description |
| `created_by_type` | string(191) | Morph class du créateur |
| `created_by_id` | string(191) | ID du créateur |
| `assigned_to_type` | string(191) nullable | Morph class du résolveur |
| `assigned_to_id` | string(191) nullable | ID du résolveur |
| `status` | string(32) | `open` / `pending` / `resolved` / `closed` |
| `priority` | string(32) | `low` / `normal` / `high` / `urgent` |
| `assigned_at` | timestamp nullable | Date d'assignation |
| `resolved_at` | timestamp nullable | Date de résolution |
| `closed_at` | timestamp nullable | Date de fermeture |
| `metadata` | json nullable | Données libres |
| `created_at`, `updated_at`, `deleted_at` | timestamps | — |

### Schéma `support_ticket_messages`

| Colonne | Type | Description |
|---|---|---|
| `id` | UUID (PK) | Identifiant |
| `ticket_id` | UUID (FK) | Ticket parent |
| `reply_to_id` | UUID (FK, nullable) | Message parent |
| `sender_type` | string(32) | `creator` / `resolver` |
| `content` | longtext | Contenu |
| `status` | string(32) | `sent` / `dispatched` / `read` |
| `is_internal` | boolean | Message interne |
| `metadata` | json nullable | Données libres |
| `created_at`, `updated_at`, `deleted_at` | timestamps | — |

### Relations Eloquent

```php
// Ticket
$ticket->createdBy;   // MorphTo — Model|null
$ticket->assignedTo;  // MorphTo — Model|null

// TicketMessage
$message->ticket;     // BelongsTo — Ticket
$message->replyTo;    // BelongsTo — TicketMessage|null
$message->replies;    // HasMany   — Collection<int, TicketMessage>
```

---

## Tests

Le package fournit les factories pour faciliter vos tests.

```php
use AndyDefer\Support\Models\Ticket;
use AndyDefer\Support\Models\TicketMessage;
use AndyDefer\Support\Enums\TicketStatus;
use AndyDefer\Support\Enums\SenderType;

// Créer un ticket
$ticket = Ticket::factory()->create([
    'status' => TicketStatus::OPEN,
]);

// Créer un message pour ce ticket
$message = TicketMessage::factory()
    ->forTicket($ticket)
    ->fromResolver()
    ->internal()
    ->create();

// Réponse à un message
$reply = TicketMessage::factory()
    ->replyingTo($message)
    ->create();
```

### Exemple de test

```php
public function test_it_assigns_a_ticket(): void
{
    // Arrange: create an unassigned ticket and an agent
    $ticket = Ticket::factory()->create();
    $agent = User::factory()->create();

    // Act: assign the ticket
    $result = $this->service->assignTicket($ticket, $agent);

    // Assert: the ticket has the new resolver
    $this->assertSame($agent->id, $result->assigned_to_id);
    $this->assertNotNull($result->assigned_at);
}
```

---

## Architecture

Le package suit le pattern **ADR (Action-Domain-Responder)** :

```
HTTP Route
    ↓
support_route(TicketRoute::X)       — helper d'orchestration
    ↓
AbstractRequest::getRecord()         — validation + DTO
    ↓
TicketRouteManagerService           — hooks before/after + résolution
    ↓
SupportService                      — logique métier pure
    ↓
Repositories + Eloquent             — persistance
    ↓
NotificationService                 — effets de bord isolés
    ↓
DataBuilder::from(Model)            — DTO de sortie
    ↓
ResponseFactory::json(Data)         — réponse HTTP typée
```

### Points clés

- **Couche HTTP** (`TicketRouteManagerService`) : autorisation, résolution d'acteurs, orchestration
- **Couche métier** (`SupportService`) : invariants, notifications, transactions
- **Hooks `before*` / `after*`** : extension sans modifier le cœur
- **Jamais de modèle brut** dans la réponse HTTP : toujours un `AbstractData`
- **Effets de bord isolés** : les notifications ne rollbackent jamais les écritures

---

## Contribuer

Les contributions sont les bienvenues.

```bash
git clone https://github.com/andydefer/laravel-support.git
cd laravel-support
composer install
composer test
```

Merci de :

- respecter **PSR-12** et **PSR-4**
- ajouter des **tests** pour toute nouvelle fonctionnalité
- documenter les **méthodes publiques** avec PHPDoc
- utiliser `declare(strict_types=1)` en tête de fichier

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)