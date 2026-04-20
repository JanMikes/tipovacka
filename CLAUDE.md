# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

## Commands for Development

All commands must be run inside Docker:

```bash
# Run all quality checks (ALWAYS run before committing)
docker compose exec web composer quality

# Individual commands
docker compose exec web composer test:unit         # Unit tests
docker compose exec web composer test              # All tests
docker compose exec web composer cs:check          # Check code style
docker compose exec web composer cs:fix            # Fix code style
docker compose exec web composer phpstan           # Static analysis (level 8)
docker compose exec web composer db:reset          # Reset database with fixtures
docker compose exec web bin/console make:migration # Create migration
```

## Architecture Overview

### CQRS with Symfony Messenger

Three message buses:

**Command Bus** (`command.bus`): Write operations with `doctrine_transaction` middleware - auto-flushes on success, rolls back on exception.

**Query Bus** (`query.bus`): Read operations via `App\Query\QueryBus` class (NOT `MessageBusInterface`). Provides type-safe results via PHPStan generics.

**Event Bus** (`event.bus`): Domain events with zero-or-more handlers. Used for side effects (emails, logging).

### Directory Structure

```
src/
├── Command/        # Commands + Handlers (write operations)
├── Controller/     # Single-action controllers
│   └── Portal/     # Authenticated user portal
├── Entity/         # Domain entities with PHP 8.4 property hooks
├── Enum/           # Value objects (UserRole)
├── Event/          # Domain events + handlers
├── Exception/      # Domain exceptions with #[WithHttpStatus]
├── Form/           # FormData + FormType pairs
├── Query/          # QueryMessage + Handler + Result (read operations)
├── Repository/     # EntityManager composition (NO ServiceEntityRepository)
└── Service/        # Identity providers, Voters, Subscribers and others...
```

## Key Patterns

### Entities (PHP 8.4 Property Hooks)

```php
#[ORM\Entity]
class User
{
    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,          // ID passed from outside
        #[ORM\Column(length: 255)]
        private(set) string $name,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $this->createdAt;
    }

    // Behavior methods, NOT setters
    public function updateDetails(string $name, \DateTimeImmutable $now): void
    {
        $this->name = $name;
        $this->updatedAt = $now;
    }
}
```

**Rules:**
- Use `private(set)` for constructor properties, `public private(set)` for updatable fields
- ID generated externally via `ProvideIdentity`, passed to constructor
- Let Doctrine infer types from PHP; use `Types::` constants only when needed (TEXT, DECIMAL)
- Explicit table name only for SQL reserved words (`#[ORM\Table(name: 'users')]`)
- No getters — use property hooks or direct property access
- **Do not write trivial accessor methods like `isX(): bool`, `getX(): T`, `hasX(): bool`.** If the state can be exposed directly, declare the backing field `public private(set)` (or `public readonly` for constructor-only fields) and read the property directly: `$user->isVerified`, NOT `$user->isVerified()`. If the accessor computes something non-trivial (e.g. `hasPassword = null !== $this->password && '' !== $this->password`), use a virtual property with a `get` hook: `public bool $hasPassword { get => ...; }`. Methods like `getUserIdentifier()`, `getRoles()`, `getPassword()` are allowed **only** because Symfony Security interfaces require them.

### Repositories (Composition)

```php
final class UserRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        // NO flush() - doctrine_transaction middleware handles it
    }

    public function findByEmail(string $email): ?User
    {
        return $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
```

**Rules:**
- NEVER extend `ServiceEntityRepository`
- NEVER call `flush()` - middleware handles it (except in DataFixtures)
- NEVER use `getRepository()` or `findBy()`/`findOneBy()` - use QueryBuilder
- Use `EntityManager::find()` for ID lookups
- No repository interfaces - inject concrete classes

### Commands

```php
// Command (readonly DTO)
final readonly class CreatePlaceCommand
{
    public function __construct(
        public string $name,
        public string $address,
        public Uuid $ownerId,
    ) {}
}

// Handler
#[AsMessageHandler]
final readonly class CreatePlaceHandler
{
    public function __invoke(CreatePlaceCommand $command): Place
    {
        // Create and persist entity
        // Returns entity or void
    }
}
```

### Queries (Type-Safe)

- Queries never return domain entities - instead returns query result objects (DTO) specific for that query

```php
// Message (implements QueryMessage<ResultType>)
/**
 * @implements QueryMessage<GetDashboardStatsResult>
 */
final readonly class GetDashboardStats implements QueryMessage {}

// Handler (named with Query suffix)
#[AsMessageHandler]
final readonly class GetDashboardStatsQuery
{
    public function __invoke(GetDashboardStats $query): GetDashboardStatsResult
    {
        return new GetDashboardStatsResult(...);
    }
}

// Result DTO
final readonly class GetDashboardStatsResult
{
    public function __construct(
        public int $totalUsers,
        public int $verifiedUsers,
    ) {}
}

// Usage - inject QueryBus, NOT MessageBusInterface
$stats = $this->queryBus->handle(new GetDashboardStats());
// $stats is typed as GetDashboardStatsResult
```

### Domain Events

```php
// Entity records events
class User implements EntityWithEvents
{
    use HasEvents;

    public function __construct(/* ... */)
    {
        $this->recordThat(new UserRegistered(
            userId: $this->id,
            email: $this->email,
            occurredOn: $this->createdAt,
        ));
    }
}

// Event (readonly DTO with occurredOn)
final readonly class UserRegistered
{
    public function __construct(
        public Uuid $userId,
        public string $email,
        public \DateTimeImmutable $occurredOn,
    ) {}
}

// Handler
#[AsMessageHandler]
final readonly class SendWelcomeEmailHandler
{
    public function __invoke(EmailVerified $event): void
    {
        // Side effect
    }
}
```

For delete events, use `#[HasDeleteDomainEvent(EventClass::class)]` attribute on entity.

### Single-Action Controllers

```php
#[Route('/portal/places', name: 'portal_place_list')]
final class PlaceListController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {}

    public function __invoke(): Response
    {
        // Single action
    }
}
```

**Rules:**
- Route at class level, NOT method level
- One controller = one route = one `__invoke()` method
- Use `final` modifier

### Forms (FormData + FormType)

```php
// FormData with validation
final class PlaceFormData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name = '';

    public static function fromPlace(Place $place): self { /* ... */ }
}

// FormType maps to FormData
final class PlaceFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PlaceFormData::class]);
    }
}
```

### Exceptions

```php
#[WithHttpStatus(409)]
final class UserAlreadyExists extends \DomainException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf('User with email "%s" already exists.', $email));
    }
}
```

## Conventions

- All files: `declare(strict_types=1);`
- Commands, events, DTOs: `final readonly`
- All entity IDs: UUID v7 via `ProvideIdentity` interface
- ID generation: Production uses `RandomIdentityProvider`, tests use `PredictableIdentityProvider`
- **Logging exceptions**: Always use `'exception' => $e` in logger context, never `$e->getMessage()`. Monolog extracts the message, trace, and class automatically from the `exception` key.
- **Naming — prefer domain-oriented names over technical suffixes.**
  - **Interfaces**: NO `Interface` suffix. `interface Rule`, not `interface RuleInterface`. Existing examples: `SoftDeletable`, `EntityWithEvents`, `QueryMessage`, `ProvideIdentity`, `DeleteDomainEvent`, `Rule`.
  - **Exceptions**: NO `Exception` suffix. `UserAlreadyExists`, not `UserAlreadyExistsException`. `SportNotFound`, not `SportNotFoundException`. Existing examples in `src/Exception/`: `UserNotFound`, `UserAlreadyExists`, `UnverifiedUser`, `InvalidCurrentPassword`, `SportNotFound`.
  - Apply this wherever it makes sense: traits, abstract classes, handlers — use the domain word, skip the technical tag. Exceptions to the rule (pun intended) exist only where a framework contract forces a suffix (e.g. Symfony form types end in `FormType`).
- **Migrations are generated, never hand-written.** After changing entities / ORM attributes, run `docker compose exec web bin/console doctrine:migrations:diff` and commit whatever it produces. Hand-written migrations drift from Doctrine's expected schema (custom index names, DB-only defaults, duplicated comments) and break the `migrations-up-to-date` CI check. Only add data-seed `addSql('INSERT …')` statements and the like on top of the generated schema changes; never re-type schema DDL by hand.
- **No `DC2Type` column comments.** In Doctrine 3 DBAL 4+ the `DC2Type:<type>` comment convention is no longer needed — type info comes from the mapping. Let `doctrine:migrations:diff` decide; do not manually add `COMMENT ON COLUMN … IS '(DC2Type:…)'` statements.
- **Express partial unique indexes in the mapping**, not only in SQL. Use `#[ORM\UniqueConstraint(name: '…', columns: ['…'], options: ['where' => '…'])]` at the entity class level (Doctrine 3 DBAL 4+ supports this on PostgreSQL). Otherwise `schema:validate` keeps flagging drift.

## Frontend

### Turbo

Hotwire Turbo is installed but **disabled globally** via `data-turbo="false"` on `<body>` in `base.html.twig`. To enable Turbo on specific elements, add `data-turbo="true"`:

```twig
<form data-turbo="true">...</form>
<a href="..." data-turbo="true">Link</a>
```

## Testing

- `tests/Unit/` - Domain logic (no database, fast)
- `tests/Integration/` - Repository/controller tests (uses DAMA DoctrineTestBundle)
- **MockClock**: Tests use fixed time `2025-06-15 12:00:00 UTC` - never use `new \DateTimeImmutable()` (without argument)
- **Fixtures**: Prefer using fixture data over creating test data dynamically. See [.claude/FIXTURES.md](.claude/FIXTURES.md) for reference constants and available test data

