---
description: 'Create an async event with handlers using Symfony Messenger and the event store pattern'
agent: agent
tools:
  - file_search
  - read_file
  - create_file
  - semantic_search
---

# Create Async Event & Handler

Use this prompt to add a new domain event and its async handler, following the clean-bitrix event system architecture.

## Event system overview

Clean-bitrix uses **two-table architecture** for async events:
- `EventStoreTable` — immutable log (one row per published event)
- `EventDispatchTable` — dispatch queue (one row per subscriber per event)

Events are cached by `spl_object_id` — each domain object writes its events only once, preventing duplicates.

## Information needed

1. **Aggregate** — Which entity triggers this event? (e.g., `User`, `Project`, `File`)
2. **Event name** — What happened? (e.g., `UserCreated`, `ProjectArchived`, `FileUploaded`)
3. **Event data** — What properties should the event capture?
4. **Handler action** — What should happen when the event is dispatched?

## File structure

### Event definition (Domain layer)

```
Domain/{Aggregate}/Event/
  {AggregateEvent}.php          ← Domain event
```

Example:

```php
namespace Domain\User\Event;

declare(strict_types=1);

use Domain\Event\EventInterface;

final class UserCreatedEvent implements EventInterface
{
    public function __construct(
        private readonly int $userId,
        private readonly string $email,
        private readonly int $createdAt,
    ) {}
    
    public function userId(): int
    {
        return $this->userId;
    }
    
    public function email(): string
    {
        return $this->email;
    }
    
    public function createdAt(): int
    {
        return $this->createdAt;
    }
    
    /**
     * Serializable for Symfony Messenger
     */
    #[\Serializable]
    public function serialize(): string
    {
        return json_encode([
            'userId' => $this->userId,
            'email' => $this->email,
            'createdAt' => $this->createdAt,
        ]);
    }
}
```

### Handler (Infrastructure layer)

```
Infrastructure/Event/Handler/
  {AggregateEvent}Handler.php   ← Async handler
```

Example:

```php
namespace Infrastructure\Event\Handler;

declare(strict_types=1);

use Domain\User\Event\UserCreatedEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UserCreatedEventHandler
{
    public function __invoke(UserCreatedEvent $event): void
    {
        // Send welcome email, initialize profile, etc.
        // Handler has access to user context from $event
    }
}
```

## Publishing events from aggregates

Inside a Domain aggregate:

```php
namespace Domain\User\Aggregate;

final class User implements UserInterface
{
    use PublishesEventsTrait;  // Provides event publishing interface
    
    public static function create(string $email, string $name): self
    {
        $user = new self();
        $user->userId = /* id */;
        $user->email = $email;
        
        $user->addEvent(new UserCreatedEvent($user->userId, $email, time()));
        
        return $user;
    }
}
```

## Retry policy (optional)

Configure per-handler retry behavior:

```php
#[AsMessageHandler]
final class UserCreatedEventHandler
{
    public function __invoke(UserCreatedEvent $event): void
    {
        // With automatic retry on transient failures
        // Exponential backoff configured via RetryPolicy
    }
}
```

## Context restoration

When handling events asynchronously, the handler may need user/context information:

```php
use Bitrix\Main\Engine\CurrentUser;
use Domain\User\Infrastructure\Repository\Bitrix\UserRepository;

final class UserCreatedEventHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}
    
    public function __invoke(UserCreatedEvent $event): void
    {
        // Event contains user context; restore as needed
        $user = $this->userRepository->findById($event->userId());
        
        // Restore CurrentUser if permission checks needed
        // ...
    }
}
```

## Tasks performed

This prompt will:
1. ✅ Create event class in `Domain/{Aggregate}/Event/`
2. ✅ Create handler class in `Infrastructure/Event/Handler/`
3. ✅ Add Symfony `#[AsMessageHandler]` attribute
4. ✅ Generate serialization & deserialization logic
5. ✅ Create ADR documenting the event design (if complex)
6. ✅ Add to Messenger transport configuration (if needed)

Ask for clarifications before generation.

