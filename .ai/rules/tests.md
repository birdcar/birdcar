---
paths:
  - 'tests/**'
---

# Tests

## Tests pass PHPStan level 7
phpstan.neon analyses tests/ at level 7 with no baseline. tests/PHPStan holds two extensions: Pest closures bind to Tests\TestCase (Feature, Manual) or PHPUnit's TestCase, and artisan() returns PendingCommand. Larastan runs with parseModelCastsMethod so casts() resolve. Narrow string|false, DOMNodeList|false, RawMessage and TransportInterface with guards that fail the test (throw or Assert::fail); never use @phpstan-ignore, inline @var or silencing casts. Restart a Pest chain with ->and($subject)->not when ->not follows a mixin method. Pest loads every test file into one process, so prefix file-local helper functions with the file's subject (marketingSiteXpath) to avoid redeclare fatals. Unit tests don't boot the app: use Illuminate\Filesystem\Filesystem, not facades.
