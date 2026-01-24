# Phase 5: Recurring Tasks - Implementation Plan

**Last Updated:** 2026-01-24
**Status:** Ready for Implementation (Updated for current architecture)
**Prerequisites:** Phases 1-4 completed

---

## Executive Summary

This document provides the updated Phase 5 implementation plan for recurring tasks, revised to account for architecture changes made during Phases 1-3. Key updates include:

1. **Recurrence fields must be re-added** - They were removed in Phase 2 cleanup
2. **Follow new service architecture** - TaskStateService, TaskUndoService patterns
3. **Re-create InvalidRecurrenceException** - Deleted in Phase 2, needs recreation with mapper
4. **Integration with existing parser infrastructure** - Follow NaturalLanguageParserService patterns
5. **UI Design System compliance** - Follow UI-PHASE-MODIFICATIONS.md specifications

---

## Architecture Context (Current State)

### Service Architecture (Post-Phase 2 Refactoring)

The service layer has been split for single responsibility:

```
src/Service/
├── TaskService.php           # Core CRUD operations only
├── TaskStateService.php      # State serialization for undo
├── TaskUndoService.php       # Undo token creation and restoration
├── ProjectService.php        # Core CRUD operations only
├── ProjectStateService.php   # State serialization for undo
├── ProjectUndoService.php    # Undo token creation and restoration
├── UndoService.php           # Redis-based undo token storage (shared)
└── Parser/                   # Natural language parsing
    ├── NaturalLanguageParserService.php
    ├── DateParserService.php
    ├── ProjectParserService.php
    ├── TagParserService.php
    └── PriorityParserService.php
```

### Key Patterns to Follow

| Pattern | Location | Notes |
|---------|----------|-------|
| State Serialization | `TaskStateService.serializeTaskState()` | Include recurrence fields |
| State Restoration | `Task.restoreFromState()` | Use this for undo, not reflection |
| Undo Token Creation | `TaskUndoService.createUndoToken()` | Returns `UndoToken\|null` |
| Undo Consumption | `UndoService.consumeUndoToken()` | Atomic via Lua script |
| Parser Structure | `NaturalLanguageParserService` | Returns highlights, warnings |
| Exception Mapping | `ExceptionMapperRegistry` | Auto-discovery with tags |
| Ownership Check | `OwnershipChecker` | Use on all mutations |
| DTO Validation | Symfony Validator constraints | In constructor properties |

### Current Task Entity State

**Important:** Recurrence properties were **removed** in Phase 2 (Issue 4.2.6) because they were not implemented. They must be re-added:

```php
// Properties that NEED TO BE ADDED to src/Entity/Task.php:
private bool $isRecurring = false;
private ?string $recurrenceRule = null;
private ?string $recurrenceType = null;  // 'absolute' | 'relative'
private ?\DateTimeImmutable $recurrenceEndDate = null;
```

### Existing Fields Available for Use

The `originalTask` relationship already exists in Task entity and can be repurposed for recurring chain tracking:

```php
// Already exists in Task.php:
#[ORM\ManyToOne(targetEntity: Task::class)]
#[ORM\JoinColumn(name: 'original_task_id', nullable: true)]
private ?Task $originalTask = null;
```

---

## Sub-Phase 5.1: Database Schema & Entity Updates

### Objective
Add recurrence columns to Task entity and create migration.

### Tasks

- [ ] **5.1.1** Add recurrence properties to Task entity
  ```php
  // src/Entity/Task.php - ADD these properties:

  #[ORM\Column(type: 'boolean', options: ['default' => false])]
  private bool $isRecurring = false;

  #[ORM\Column(type: 'text', nullable: true)]
  private ?string $recurrenceRule = null;  // Original user input, always preserved

  #[ORM\Column(type: 'string', length: 20, nullable: true)]
  private ?string $recurrenceType = null;  // 'absolute' or 'relative'

  #[ORM\Column(type: 'date_immutable', nullable: true)]
  private ?\DateTimeImmutable $recurrenceEndDate = null;
  ```

- [ ] **5.1.2** Add getters and setters for recurrence properties
  ```php
  public function isRecurring(): bool { return $this->isRecurring; }
  public function setIsRecurring(bool $isRecurring): self { ... }

  public function getRecurrenceRule(): ?string { return $this->recurrenceRule; }
  public function setRecurrenceRule(?string $rule): self { ... }

  public function getRecurrenceType(): ?string { return $this->recurrenceType; }
  public function setRecurrenceType(?string $type): self { ... }

  public function getRecurrenceEndDate(): ?\DateTimeImmutable { return $this->recurrenceEndDate; }
  public function setRecurrenceEndDate(?\DateTimeImmutable $date): self { ... }
  ```

- [ ] **5.1.3** Update `restoreFromState()` method to include recurrence fields
  ```php
  // src/Entity/Task.php - UPDATE restoreFromState():

  public function restoreFromState(array $state): void
  {
      // ... existing field restoration ...

      if (array_key_exists('isRecurring', $state)) {
          $this->isRecurring = $state['isRecurring'];
      }
      if (array_key_exists('recurrenceRule', $state)) {
          $this->recurrenceRule = $state['recurrenceRule'];
      }
      if (array_key_exists('recurrenceType', $state)) {
          $this->recurrenceType = $state['recurrenceType'];
      }
      if (array_key_exists('recurrenceEndDate', $state)) {
          $this->recurrenceEndDate = $state['recurrenceEndDate'];
      }
  }
  ```

- [ ] **5.1.4** Create database migration
  ```php
  // migrations/Version20260124XXXXXX.php

  public function up(Schema $schema): void
  {
      $this->addSql('ALTER TABLE tasks ADD is_recurring BOOLEAN NOT NULL DEFAULT FALSE');
      $this->addSql('ALTER TABLE tasks ADD recurrence_rule TEXT DEFAULT NULL');
      $this->addSql('ALTER TABLE tasks ADD recurrence_type VARCHAR(20) DEFAULT NULL');
      $this->addSql('ALTER TABLE tasks ADD recurrence_end_date DATE DEFAULT NULL');

      // Add index for efficient filtering
      $this->addSql('CREATE INDEX idx_tasks_owner_recurring ON tasks (owner_id, is_recurring)');
  }
  ```

- [ ] **5.1.5** Create RecurrenceType enum
  ```php
  // src/Enum/RecurrenceType.php

  namespace App\Enum;

  enum RecurrenceType: string
  {
      case ABSOLUTE = 'absolute';
      case RELATIVE = 'relative';

      public function getLabel(): string
      {
          return match($this) {
              self::ABSOLUTE => 'Absolute (based on schedule)',
              self::RELATIVE => 'Relative (based on completion)',
          };
      }

      public static function isValid(string $value): bool
      {
          return in_array($value, array_column(self::cases(), 'value'), true);
      }
  }
  ```

### Completion Criteria
- [ ] Task entity has all recurrence properties
- [ ] Migration runs successfully
- [ ] `restoreFromState()` handles recurrence fields
- [ ] RecurrenceType enum created with validation
- [ ] Index created for owner + is_recurring queries

### Files to Create/Update
```
src/Entity/Task.php (updated)
src/Enum/RecurrenceType.php (new)
migrations/Version20260124XXXXXX.php (new)
```

---

## Sub-Phase 5.2: Recurrence Rule Parser

### Objective
Create a parser that converts natural language recurrence patterns into structured data.

### Architecture

Follow the established parser pattern from `NaturalLanguageParserService`:

```
src/Service/Recurrence/
├── RecurrenceRuleParser.php      # Main parser service
├── RecurrenceRule.php            # Immutable value object (result)
├── RecurrenceParseResult.php     # Result with warnings
└── InvalidRecurrenceException.php # Parsing errors

tests/Unit/Service/Recurrence/
└── RecurrenceRuleParserTest.php
```

### Tasks

- [ ] **5.2.1** Create RecurrenceRule value object
  ```php
  // src/Service/Recurrence/RecurrenceRule.php

  namespace App\Service\Recurrence;

  final readonly class RecurrenceRule
  {
      public function __construct(
          public string $originalText,          // Always preserved for display
          public string $type,                   // 'absolute' | 'relative'
          public string $interval,               // 'day' | 'week' | 'month' | 'year'
          public int $count = 1,                 // Every X intervals
          public array $days = [],               // For weekly: ['Mon', 'Wed', 'Fri']
          public ?int $dayOfMonth = null,        // For monthly: 1-31, -1 for last day
          public ?int $monthOfYear = null,       // For yearly: 1-12
          public ?string $time = null,           // HH:MM format (24-hour)
          public ?\DateTimeImmutable $endDate = null,
      ) {}

      public function toArray(): array
      {
          return [
              'originalText' => $this->originalText,
              'type' => $this->type,
              'interval' => $this->interval,
              'count' => $this->count,
              'days' => $this->days,
              'dayOfMonth' => $this->dayOfMonth,
              'monthOfYear' => $this->monthOfYear,
              'time' => $this->time,
              'endDate' => $this->endDate?->format('Y-m-d'),
          ];
      }

      public static function fromArray(array $data): self
      {
          return new self(
              originalText: $data['originalText'] ?? '',
              type: $data['type'] ?? 'absolute',
              interval: $data['interval'] ?? 'day',
              count: $data['count'] ?? 1,
              days: $data['days'] ?? [],
              dayOfMonth: $data['dayOfMonth'] ?? null,
              monthOfYear: $data['monthOfYear'] ?? null,
              time: $data['time'] ?? null,
              endDate: isset($data['endDate'])
                  ? new \DateTimeImmutable($data['endDate'])
                  : null,
          );
      }
  }
  ```

- [ ] **5.2.2** Create InvalidRecurrenceException (re-create, was deleted in Phase 2)
  ```php
  // src/Exception/InvalidRecurrenceException.php

  namespace App\Exception;

  use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

  class InvalidRecurrenceException extends BadRequestHttpException
  {
      private string $errorCode = 'INVALID_RECURRENCE';
      private array $details;

      public function __construct(
          string $message,
          array $details = [],
          ?\Throwable $previous = null
      ) {
          parent::__construct($message, $previous, 400);
          $this->details = $details;
      }

      public function getErrorCode(): string
      {
          return $this->errorCode;
      }

      public function getDetails(): array
      {
          return $this->details;
      }

      // Factory methods for specific error cases
      public static function unsupportedPattern(string $pattern, string $suggestion = ''): self
      {
          $message = sprintf("Pattern '%s' is not supported", $pattern);
          $details = ['recurrence_rule' => [$message]];
          if ($suggestion) {
              $details['suggestion'] = $suggestion;
          }
          return new self($message, $details);
      }

      public static function hourlyNotSupported(): self
      {
          return self::unsupportedPattern(
              'hourly recurrence',
              'Use daily, weekly, monthly, or yearly patterns'
          );
      }

      public static function ordinalDayNotSupported(string $pattern): self
      {
          return self::unsupportedPattern(
              $pattern,
              "Try 'every Monday' instead of 'every first Monday'"
          );
      }

      public static function invalidDate(string $date): self
      {
          return new self(
              sprintf("Invalid date '%s'", $date),
              ['recurrence_rule' => [sprintf("'%s' is not a valid date", $date)]]
          );
      }

      public static function invalidDayOfMonth(int $day): self
      {
          return new self(
              sprintf("Invalid day of month: %d", $day),
              ['recurrence_rule' => ['Day of month must be between 1 and 31, or -1 for last day']]
          );
      }

      public static function unparseable(string $input): self
      {
          return new self(
              'Could not parse recurrence rule',
              ['recurrence_rule' => [sprintf("Could not parse '%s'", $input)]]
          );
      }
  }
  ```

- [ ] **5.2.3** Create InvalidRecurrenceExceptionMapper
  ```php
  // src/EventListener/ExceptionMapper/Domain/InvalidRecurrenceExceptionMapper.php

  namespace App\EventListener\ExceptionMapper\Domain;

  use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
  use App\EventListener\ExceptionMapper\ExceptionMapping;
  use App\Exception\InvalidRecurrenceException;
  use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

  #[AutoconfigureTag('app.exception_mapper')]
  final readonly class InvalidRecurrenceExceptionMapper implements ExceptionMapperInterface
  {
      public function canHandle(\Throwable $exception): bool
      {
          return $exception instanceof InvalidRecurrenceException;
      }

      public function map(\Throwable $exception): ExceptionMapping
      {
          assert($exception instanceof InvalidRecurrenceException);

          return new ExceptionMapping(
              errorCode: $exception->getErrorCode(),
              message: $exception->getMessage(),
              statusCode: 400,
              details: $exception->getDetails(),
          );
      }

      public function getPriority(): int
      {
          return 100; // Domain exceptions have highest priority
      }
  }
  ```

- [ ] **5.2.4** Create RecurrenceParseResult
  ```php
  // src/Service/Recurrence/RecurrenceParseResult.php

  namespace App\Service\Recurrence;

  final readonly class RecurrenceParseResult
  {
      public function __construct(
          public ?RecurrenceRule $rule,
          public array $warnings = [],
          public bool $success = true,
      ) {}

      public static function success(RecurrenceRule $rule, array $warnings = []): self
      {
          return new self($rule, $warnings, true);
      }

      public static function failure(array $warnings): self
      {
          return new self(null, $warnings, false);
      }
  }
  ```

- [ ] **5.2.5** Create RecurrenceRuleParser service
  ```php
  // src/Service/Recurrence/RecurrenceRuleParser.php

  namespace App\Service\Recurrence;

  use App\Exception\InvalidRecurrenceException;

  class RecurrenceRuleParser
  {
      private const DAY_NAMES = [
          'monday' => 'Mon', 'mon' => 'Mon',
          'tuesday' => 'Tue', 'tue' => 'Tue',
          'wednesday' => 'Wed', 'wed' => 'Wed',
          'thursday' => 'Thu', 'thu' => 'Thu',
          'friday' => 'Fri', 'fri' => 'Fri',
          'saturday' => 'Sat', 'sat' => 'Sat',
          'sunday' => 'Sun', 'sun' => 'Sun',
      ];

      private const MONTH_NAMES = [
          'january' => 1, 'jan' => 1,
          'february' => 2, 'feb' => 2,
          'march' => 3, 'mar' => 3,
          'april' => 4, 'apr' => 4,
          'may' => 5,
          'june' => 6, 'jun' => 6,
          'july' => 7, 'jul' => 7,
          'august' => 8, 'aug' => 8,
          'september' => 9, 'sep' => 9,
          'october' => 10, 'oct' => 10,
          'november' => 11, 'nov' => 11,
          'december' => 12, 'dec' => 12,
      ];

      /**
       * Parse a natural language recurrence rule.
       *
       * @throws InvalidRecurrenceException if pattern cannot be parsed
       */
      public function parse(string $rule): RecurrenceRule
      {
          $originalText = trim($rule);
          $input = strtolower($originalText);
          $input = preg_replace('/\s+/', ' ', $input); // Normalize whitespace

          // Detect type: every! = relative, every = absolute
          $type = 'absolute';
          if (str_starts_with($input, 'every!')) {
              $type = 'relative';
              $input = trim(substr($input, 6));
          } elseif (str_starts_with($input, 'every')) {
              $input = trim(substr($input, 5));
          }

          // Check for unsupported patterns first
          $this->validateNotUnsupported($input);

          // Extract end date if present
          $endDate = $this->extractEndDate($input);
          if ($endDate !== null) {
              $input = preg_replace('/\s*until\s+\S+$/', '', $input);
          }

          // Extract time component if present
          $time = $this->extractTime($input);
          if ($time !== null) {
              $input = preg_replace('/\s*at\s+\S+\s*(am|pm)?$/i', '', $input);
          }

          // Try parsing different patterns
          return $this->parseDaily($input, $originalText, $type, $time, $endDate)
              ?? $this->parseWeekly($input, $originalText, $type, $time, $endDate)
              ?? $this->parseMonthly($input, $originalText, $type, $time, $endDate)
              ?? $this->parseYearly($input, $originalText, $type, $time, $endDate)
              ?? throw InvalidRecurrenceException::unparseable($originalText);
      }

      private function validateNotUnsupported(string $input): void
      {
          // Check for hourly patterns
          if (preg_match('/\d+\s*hours?/', $input) || str_contains($input, 'hourly')) {
              throw InvalidRecurrenceException::hourlyNotSupported();
          }

          // Check for minute patterns
          if (preg_match('/\d+\s*minutes?/', $input)) {
              throw InvalidRecurrenceException::unsupportedPattern(
                  'minute-based recurrence',
                  'Minimum interval is daily'
              );
          }

          // Check for ordinal day patterns (every first Monday, etc.)
          if (preg_match('/every\s+(first|second|third|fourth|fifth|last)\s+\w+day/i', $input)) {
              throw InvalidRecurrenceException::ordinalDayNotSupported($input);
          }
      }

      private function parseDaily(
          string $input,
          string $originalText,
          string $type,
          ?string $time,
          ?\DateTimeImmutable $endDate
      ): ?RecurrenceRule {
          // "day" | "X days" | "daily"
          if ($input === 'day' || $input === 'daily') {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'day',
                  count: 1,
                  time: $time,
                  endDate: $endDate,
              );
          }

          if (preg_match('/^(\d+)\s*days?$/', $input, $matches)) {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'day',
                  count: (int) $matches[1],
                  time: $time,
                  endDate: $endDate,
              );
          }

          return null;
      }

      private function parseWeekly(
          string $input,
          string $originalText,
          string $type,
          ?string $time,
          ?\DateTimeImmutable $endDate
      ): ?RecurrenceRule {
          // "week" | "X weeks" | "weekly" | "biweekly"
          if ($input === 'week' || $input === 'weekly') {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'week',
                  count: 1,
                  time: $time,
                  endDate: $endDate,
              );
          }

          if ($input === 'biweekly') {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'week',
                  count: 2,
                  time: $time,
                  endDate: $endDate,
              );
          }

          if (preg_match('/^(\d+)\s*weeks?$/', $input, $matches)) {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'week',
                  count: (int) $matches[1],
                  time: $time,
                  endDate: $endDate,
              );
          }

          // Day names: "monday" | "mon, wed, fri" | "weekday" | "weekend"
          if ($input === 'weekday') {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'week',
                  days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                  time: $time,
                  endDate: $endDate,
              );
          }

          if ($input === 'weekend') {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'week',
                  days: ['Sat', 'Sun'],
                  time: $time,
                  endDate: $endDate,
              );
          }

          // Parse day names (comma-separated)
          $days = $this->parseDayNames($input);
          if (!empty($days)) {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'week',
                  days: $days,
                  time: $time,
                  endDate: $endDate,
              );
          }

          return null;
      }

      private function parseMonthly(
          string $input,
          string $originalText,
          string $type,
          ?string $time,
          ?\DateTimeImmutable $endDate
      ): ?RecurrenceRule {
          // "month" | "X months" | "monthly" | "quarterly"
          if ($input === 'month' || $input === 'monthly') {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'month',
                  count: 1,
                  time: $time,
                  endDate: $endDate,
              );
          }

          if ($input === 'quarterly') {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'month',
                  count: 3,
                  time: $time,
                  endDate: $endDate,
              );
          }

          if (preg_match('/^(\d+)\s*months?$/', $input, $matches)) {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'month',
                  count: (int) $matches[1],
                  time: $time,
                  endDate: $endDate,
              );
          }

          // Day of month: "15th" | "the 15th" | "the last day"
          $dayOfMonth = $this->parseDayOfMonth($input);
          if ($dayOfMonth !== null) {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'month',
                  dayOfMonth: $dayOfMonth,
                  time: $time,
                  endDate: $endDate,
              );
          }

          return null;
      }

      private function parseYearly(
          string $input,
          string $originalText,
          string $type,
          ?string $time,
          ?\DateTimeImmutable $endDate
      ): ?RecurrenceRule {
          // "year" | "X years" | "yearly" | "annually"
          if (in_array($input, ['year', 'yearly', 'annually'], true)) {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'year',
                  count: 1,
                  time: $time,
                  endDate: $endDate,
              );
          }

          if (preg_match('/^(\d+)\s*years?$/', $input, $matches)) {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'year',
                  count: (int) $matches[1],
                  time: $time,
                  endDate: $endDate,
              );
          }

          // Specific date: "january 15" | "jan 15th"
          $monthDay = $this->parseMonthAndDay($input);
          if ($monthDay !== null) {
              return new RecurrenceRule(
                  originalText: $originalText,
                  type: $type,
                  interval: 'year',
                  monthOfYear: $monthDay['month'],
                  dayOfMonth: $monthDay['day'],
                  time: $time,
                  endDate: $endDate,
              );
          }

          return null;
      }

      private function parseDayNames(string $input): array
      {
          $days = [];
          $parts = preg_split('/[\s,]+/', $input);

          foreach ($parts as $part) {
              $normalized = strtolower(trim($part));
              if (isset(self::DAY_NAMES[$normalized])) {
                  $days[] = self::DAY_NAMES[$normalized];
              }
          }

          return array_values(array_unique($days));
      }

      private function parseDayOfMonth(string $input): ?int
      {
          // "last day" | "the last day"
          if (preg_match('/(?:the\s+)?last\s+day/', $input)) {
              return -1;
          }

          // "15th" | "the 15th" | "15"
          if (preg_match('/(?:the\s+)?(\d{1,2})(?:st|nd|rd|th)?/', $input, $matches)) {
              $day = (int) $matches[1];
              if ($day < 1 || $day > 31) {
                  throw InvalidRecurrenceException::invalidDayOfMonth($day);
              }
              return $day;
          }

          return null;
      }

      private function parseMonthAndDay(string $input): ?array
      {
          // "january 15" | "jan 15th"
          foreach (self::MONTH_NAMES as $name => $month) {
              if (preg_match("/^{$name}\s+(\d{1,2})(?:st|nd|rd|th)?$/i", $input, $matches)) {
                  $day = (int) $matches[1];

                  // Validate February 30/31
                  if ($month === 2 && $day > 29) {
                      throw InvalidRecurrenceException::invalidDate("{$name} {$day}");
                  }

                  return ['month' => $month, 'day' => $day];
              }
          }

          return null;
      }

      private function extractTime(string &$input): ?string
      {
          // "at 2pm" | "at 2:30pm" | "at 14:00" | "at noon" | "at midnight"
          if (preg_match('/at\s+(noon|midnight)$/i', $input, $matches)) {
              return match (strtolower($matches[1])) {
                  'noon' => '12:00',
                  'midnight' => '00:00',
              };
          }

          // 12-hour format: 2pm, 2:30pm
          if (preg_match('/at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)$/i', $input, $matches)) {
              $hour = (int) $matches[1];
              $minute = (int) ($matches[2] ?? 0);
              $meridiem = strtolower($matches[3]);

              if ($meridiem === 'pm' && $hour !== 12) {
                  $hour += 12;
              } elseif ($meridiem === 'am' && $hour === 12) {
                  $hour = 0;
              }

              return sprintf('%02d:%02d', $hour, $minute);
          }

          // 24-hour format: 14:00
          if (preg_match('/at\s+(\d{1,2}):(\d{2})$/', $input, $matches)) {
              return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
          }

          return null;
      }

      private function extractEndDate(string $input): ?\DateTimeImmutable
      {
          // "until March 1" | "until 2026-06-30"
          if (preg_match('/until\s+(.+)$/i', $input, $matches)) {
              try {
                  return new \DateTimeImmutable($matches[1]);
              } catch (\Exception) {
                  return null;
              }
          }

          return null;
      }
  }
  ```

### Completion Criteria
- [ ] RecurrenceRule value object created with serialization
- [ ] RecurrenceRuleParser handles all required patterns
- [ ] Parser is lenient with case and abbreviations
- [ ] InvalidRecurrenceException re-created with exception mapper
- [ ] Time parsing supports both 12-hour and 24-hour formats
- [ ] End date extraction works
- [ ] Unsupported patterns (hourly, ordinal) rejected with clear messages

### Files to Create
```
src/Service/Recurrence/RecurrenceRule.php (new)
src/Service/Recurrence/RecurrenceRuleParser.php (new)
src/Service/Recurrence/RecurrenceParseResult.php (new)
src/Exception/InvalidRecurrenceException.php (new - was deleted in Phase 2)
src/EventListener/ExceptionMapper/Domain/InvalidRecurrenceExceptionMapper.php (new)
tests/Unit/Service/Recurrence/RecurrenceRuleParserTest.php (new)
tests/Unit/Service/Recurrence/RecurrenceRuleTest.php (new)
tests/Unit/Exception/InvalidRecurrenceExceptionTest.php (new)
tests/Unit/EventListener/ExceptionMapper/Domain/InvalidRecurrenceExceptionMapperTest.php (new)
```

---

## Sub-Phase 5.3: Next Date Calculator

### Objective
Create service to calculate the next occurrence date based on recurrence rule and type.

### Tasks

- [ ] **5.3.1** Create NextDateCalculator service
  ```php
  // src/Service/Recurrence/NextDateCalculator.php

  namespace App\Service\Recurrence;

  class NextDateCalculator
  {
      /**
       * Calculate the next occurrence date.
       *
       * @param RecurrenceRule $rule The recurrence rule
       * @param \DateTimeImmutable $referenceDate For absolute: current due date. For relative: completion date.
       * @param string $userTimezone The user's timezone (e.g., 'America/Los_Angeles')
       * @return \DateTimeImmutable The next occurrence in UTC
       */
      public function calculate(
          RecurrenceRule $rule,
          \DateTimeImmutable $referenceDate,
          string $userTimezone = 'UTC'
      ): \DateTimeImmutable {
          $tz = new \DateTimeZone($userTimezone);
          $reference = $referenceDate->setTimezone($tz);

          $next = match ($rule->interval) {
              'day' => $this->calculateDaily($rule, $reference),
              'week' => $this->calculateWeekly($rule, $reference),
              'month' => $this->calculateMonthly($rule, $reference),
              'year' => $this->calculateYearly($rule, $reference),
              default => throw new \InvalidArgumentException("Unknown interval: {$rule->interval}"),
          };

          // Apply time if specified
          if ($rule->time !== null) {
              [$hour, $minute] = explode(':', $rule->time);
              $next = $next->setTime((int) $hour, (int) $minute);
          }

          // Convert back to UTC for storage
          return $next->setTimezone(new \DateTimeZone('UTC'));
      }

      /**
       * Check if a next instance should be created (end date check).
       */
      public function shouldCreateNextInstance(
          RecurrenceRule $rule,
          \DateTimeImmutable $nextDate
      ): bool {
          if ($rule->endDate === null) {
              return true;
          }

          return $nextDate <= $rule->endDate;
      }

      private function calculateDaily(RecurrenceRule $rule, \DateTimeImmutable $reference): \DateTimeImmutable
      {
          return $reference->modify("+{$rule->count} days");
      }

      private function calculateWeekly(RecurrenceRule $rule, \DateTimeImmutable $reference): \DateTimeImmutable
      {
          if (empty($rule->days)) {
              // Simple weekly: add N weeks
              return $reference->modify("+{$rule->count} weeks");
          }

          // Find next occurrence of any specified day
          $currentDayNum = (int) $reference->format('N'); // 1=Mon, 7=Sun
          $dayNumbers = $this->dayNamesToNumbers($rule->days);

          // Find the next day that's after today
          foreach (range(1, 7) as $offset) {
              $checkDate = $reference->modify("+{$offset} days");
              $checkDayNum = (int) $checkDate->format('N');

              if (in_array($checkDayNum, $dayNumbers, true)) {
                  return $checkDate;
              }
          }

          // Fallback to next week
          return $reference->modify('+1 week');
      }

      private function calculateMonthly(RecurrenceRule $rule, \DateTimeImmutable $reference): \DateTimeImmutable
      {
          if ($rule->dayOfMonth === null) {
              // Simple monthly: add N months
              return $reference->modify("+{$rule->count} months");
          }

          // Specific day of month
          $targetMonth = $reference->modify('+1 month');

          if ($rule->dayOfMonth === -1) {
              // Last day of month
              return new \DateTimeImmutable($targetMonth->format('Y-m-t'), $targetMonth->getTimezone());
          }

          // Handle months with fewer days
          $maxDays = (int) $targetMonth->format('t');
          $day = min($rule->dayOfMonth, $maxDays);

          return $targetMonth->setDate(
              (int) $targetMonth->format('Y'),
              (int) $targetMonth->format('m'),
              $day
          );
      }

      private function calculateYearly(RecurrenceRule $rule, \DateTimeImmutable $reference): \DateTimeImmutable
      {
          if ($rule->monthOfYear === null) {
              // Simple yearly: add N years
              return $reference->modify("+{$rule->count} years");
          }

          // Specific month and day
          $year = (int) $reference->format('Y') + 1;
          $month = $rule->monthOfYear;
          $day = $rule->dayOfMonth ?? 1;

          // Handle February 29 in non-leap years
          if ($month === 2 && $day === 29 && !$this->isLeapYear($year)) {
              $day = 28;
          }

          // Handle days exceeding month length
          $maxDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
          $day = min($day, $maxDays);

          return new \DateTimeImmutable(
              sprintf('%d-%02d-%02d', $year, $month, $day),
              $reference->getTimezone()
          );
      }

      private function dayNamesToNumbers(array $days): array
      {
          $map = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
          return array_map(fn($d) => $map[$d] ?? 1, $days);
      }

      private function isLeapYear(int $year): bool
      {
          return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
      }
  }
  ```

### Completion Criteria
- [ ] Daily calculation works for N days
- [ ] Weekly calculation works for specific days
- [ ] Monthly calculation handles day-of-month edge cases
- [ ] Yearly calculation handles leap years
- [ ] Time component applied correctly
- [ ] End date check works
- [ ] DST transitions handled

### Files to Create
```
src/Service/Recurrence/NextDateCalculator.php (new)
tests/Unit/Service/Recurrence/NextDateCalculatorTest.php (new)
```

---

## Sub-Phase 5.4: DTO Updates

### Objective
Update DTOs to handle recurrence fields in requests and responses.

### Tasks

- [ ] **5.4.1** Update CreateTaskRequest DTO
  ```php
  // src/DTO/CreateTaskRequest.php - ADD these properties:

  #[Assert\Type('boolean')]
  public readonly bool $isRecurring = false,

  #[Assert\Type('string')]
  #[Assert\Length(max: 1000)]
  public readonly ?string $recurrenceRule = null,

  // NOTE: recurrenceType is parsed from recurrenceRule, not provided directly
  ```

- [ ] **5.4.2** Update UpdateTaskRequest DTO
  ```php
  // src/DTO/UpdateTaskRequest.php - ADD these properties:

  #[Assert\Type('boolean')]
  public readonly ?bool $isRecurring = null,

  #[Assert\Type('string')]
  #[Assert\Length(max: 1000)]
  public readonly ?string $recurrenceRule = null,
  ```

- [ ] **5.4.3** Update TaskResponse DTO
  ```php
  // src/DTO/TaskResponse.php - ADD these properties and update fromEntity():

  public readonly bool $isRecurring,
  public readonly ?string $recurrenceRule,
  public readonly ?string $recurrenceType,
  public readonly ?string $recurrenceEndDate,

  public static function fromEntity(Task $task, ?string $undoToken = null): self
  {
      return new self(
          // ... existing fields ...
          isRecurring: $task->isRecurring(),
          recurrenceRule: $task->getRecurrenceRule(),
          recurrenceType: $task->getRecurrenceType(),
          recurrenceEndDate: $task->getRecurrenceEndDate()?->format('Y-m-d'),
          undoToken: $undoToken,
      );
  }
  ```

- [ ] **5.4.4** Create TaskStatusResult DTO
  ```php
  // src/DTO/TaskStatusResult.php

  namespace App\DTO;

  use App\Entity\Task;
  use App\ValueObject\UndoToken;

  final readonly class TaskStatusResult
  {
      public function __construct(
          public Task $task,
          public ?Task $nextTask = null,
          public ?UndoToken $undoToken = null,
      ) {}

      public function toArray(): array
      {
          $data = TaskResponse::fromEntity(
              $this->task,
              $this->undoToken?->token
          )->toArray();

          if ($this->nextTask !== null) {
              $data['nextTask'] = TaskResponse::fromEntity($this->nextTask)->toArray();
          }

          return $data;
      }
  }
  ```

### Completion Criteria
- [ ] CreateTaskRequest accepts recurrence fields
- [ ] UpdateTaskRequest accepts recurrence fields
- [ ] TaskResponse includes recurrence fields
- [ ] TaskStatusResult handles next task for recurring completion
- [ ] Validation prevents recurrenceRule without isRecurring

### Files to Update/Create
```
src/DTO/CreateTaskRequest.php (updated)
src/DTO/UpdateTaskRequest.php (updated)
src/DTO/TaskResponse.php (updated)
src/DTO/TaskStatusResult.php (new)
tests/Unit/DTO/CreateTaskRequestTest.php (updated)
tests/Unit/DTO/UpdateTaskRequestTest.php (updated)
tests/Unit/DTO/TaskStatusResultTest.php (new)
```

---

## Sub-Phase 5.5: Service Layer Updates

### Objective
Update TaskService, TaskStateService, and TaskUndoService to handle recurring tasks.

### Tasks

- [ ] **5.5.1** Update TaskStateService to serialize recurrence fields
  ```php
  // src/Service/TaskStateService.php - UPDATE serializeTaskState():

  public function serializeTaskState(Task $task): array
  {
      return [
          // ... existing fields ...
          'isRecurring' => $task->isRecurring(),
          'recurrenceRule' => $task->getRecurrenceRule(),
          'recurrenceType' => $task->getRecurrenceType(),
          'recurrenceEndDate' => $task->getRecurrenceEndDate()?->format('Y-m-d'),
      ];
  }
  ```

- [ ] **5.5.2** Update TaskService.create() for recurrence
  ```php
  // src/Service/TaskService.php - UPDATE create():

  if ($request->isRecurring && $request->recurrenceRule !== null) {
      // Parse and validate the recurrence rule
      $rule = $this->recurrenceRuleParser->parse($request->recurrenceRule);

      $task->setIsRecurring(true);
      $task->setRecurrenceRule($request->recurrenceRule);  // Store original text
      $task->setRecurrenceType($rule->type);

      // Apply time from recurrence if not explicitly set
      if ($rule->time !== null && $request->dueTime === null) {
          $task->setDueTime(new \DateTimeImmutable($rule->time));
      }
  }
  ```

- [ ] **5.5.3** Add RecurrenceRuleParser dependency to TaskService
  ```php
  // src/Service/TaskService.php - UPDATE constructor:

  public function __construct(
      // ... existing dependencies ...
      private readonly RecurrenceRuleParser $recurrenceRuleParser,
      private readonly NextDateCalculator $nextDateCalculator,
  ) {}
  ```

- [ ] **5.5.4** Implement completeRecurringTask() method
  ```php
  // src/Service/TaskService.php - ADD method:

  /**
   * Complete a recurring task and create the next instance.
   *
   * @return TaskStatusResult Contains completed task, next task (if created), and undo token
   */
  public function completeRecurringTask(Task $task, User $user): TaskStatusResult
  {
      $this->ownershipChecker->checkOwnership($task);

      if (!$task->isRecurring()) {
          throw new \InvalidArgumentException('Task is not recurring');
      }

      // Serialize state before mutation
      $previousState = $this->taskStateService->serializeTaskState($task);

      // Mark current task as completed
      $task->setStatus('completed');
      $task->setCompletedAt(new \DateTimeImmutable());

      // Parse recurrence rule
      $rule = $this->recurrenceRuleParser->parse($task->getRecurrenceRule());

      // Calculate next due date
      $referenceDate = $task->getRecurrenceType() === 'absolute'
          ? ($task->getDueDate() ?? new \DateTimeImmutable())
          : new \DateTimeImmutable();  // completion time for relative

      $userTimezone = $user->getTimezone() ?? 'UTC';
      $nextDate = $this->nextDateCalculator->calculate($rule, $referenceDate, $userTimezone);

      $nextTask = null;

      // Check if we should create next instance (end date check)
      if ($this->nextDateCalculator->shouldCreateNextInstance($rule, $nextDate)) {
          $nextTask = $this->createNextRecurringInstance($task, $nextDate, $rule);
      }

      // Create undo token
      $undoToken = $this->taskUndoService->createUndoToken(
          UndoAction::STATUS_CHANGE,
          $task,
          $previousState
      );

      $this->entityManager->flush();

      return new TaskStatusResult($task, $nextTask, $undoToken);
  }

  private function createNextRecurringInstance(
      Task $completedTask,
      \DateTimeImmutable $nextDueDate,
      RecurrenceRule $rule
  ): Task {
      $nextTask = new Task();

      // Copy properties
      $nextTask->setOwner($completedTask->getOwner());
      $nextTask->setTitle($completedTask->getTitle());
      $nextTask->setDescription($completedTask->getDescription());
      $nextTask->setProject($completedTask->getProject());
      $nextTask->setPriority($completedTask->getPriority());
      $nextTask->setStatus('pending');

      // Set recurrence properties
      $nextTask->setIsRecurring(true);
      $nextTask->setRecurrenceRule($completedTask->getRecurrenceRule());
      $nextTask->setRecurrenceType($completedTask->getRecurrenceType());
      $nextTask->setRecurrenceEndDate($completedTask->getRecurrenceEndDate());

      // Set due date
      $nextTask->setDueDate($nextDueDate);

      // Set time if specified in rule
      if ($rule->time !== null) {
          $nextTask->setDueTime(new \DateTimeImmutable($rule->time));
      }

      // Set original_task_id for chain tracking
      if ($completedTask->getOriginalTask() === null) {
          // This is the first task being completed
          $nextTask->setOriginalTask($completedTask);
      } else {
          // This is a recurrence, copy the original
          $nextTask->setOriginalTask($completedTask->getOriginalTask());
      }

      // Copy tags
      foreach ($completedTask->getTags() as $tag) {
          $nextTask->addTag($tag);
      }

      $this->entityManager->persist($nextTask);

      return $nextTask;
  }
  ```

- [ ] **5.5.5** Implement completeForever() method
  ```php
  // src/Service/TaskService.php - ADD method:

  /**
   * Permanently complete a recurring task without creating next instance.
   */
  public function completeForever(Task $task): array
  {
      $this->ownershipChecker->checkOwnership($task);

      if (!$task->isRecurring()) {
          throw new \InvalidArgumentException('Task is not recurring');
      }

      // Serialize state before mutation
      $previousState = $this->taskStateService->serializeTaskState($task);

      // Complete and remove recurring flag
      $task->setStatus('completed');
      $task->setCompletedAt(new \DateTimeImmutable());
      $task->setIsRecurring(false);
      // Keep recurrence_rule for audit purposes

      // Create undo token
      $undoToken = $this->taskUndoService->createUndoToken(
          UndoAction::STATUS_CHANGE,
          $task,
          $previousState
      );

      $this->entityManager->flush();

      return ['task' => $task, 'undoToken' => $undoToken?->token];
  }
  ```

- [ ] **5.5.6** Update changeStatus() to handle recurring tasks
  ```php
  // src/Service/TaskService.php - UPDATE changeStatus():

  public function changeStatus(Task $task, string $newStatus): array
  {
      $this->ownershipChecker->checkOwnership($task);

      if (!in_array($newStatus, ['pending', 'in_progress', 'completed'], true)) {
          throw InvalidStatusException::forValue($newStatus);
      }

      // Handle recurring task completion specially
      if ($newStatus === 'completed' && $task->isRecurring()) {
          $result = $this->completeRecurringTask($task, $task->getOwner());
          return [
              'task' => $result->task,
              'nextTask' => $result->nextTask,
              'undoToken' => $result->undoToken?->token,
          ];
      }

      // ... existing non-recurring logic ...
  }
  ```

### Completion Criteria
- [ ] TaskStateService serializes recurrence fields
- [ ] TaskService.create() validates and stores recurrence
- [ ] completeRecurringTask() creates next instance correctly
- [ ] completeForever() works without creating next instance
- [ ] changeStatus() integrates with recurring completion
- [ ] Chain tracking via originalTask works correctly

### Files to Update
```
src/Service/TaskService.php (updated)
src/Service/TaskStateService.php (updated)
tests/Unit/Service/TaskServiceTest.php (updated)
tests/Unit/Service/TaskStateServiceTest.php (updated)
```

---

## Sub-Phase 5.6: Repository Updates

### Objective
Add query methods for recurring tasks and chain history.

### Tasks

- [ ] **5.6.1** Add recurring task filter to TaskRepository
  ```php
  // src/Repository/TaskRepository.php - ADD method:

  /**
   * Find recurring tasks by filter criteria.
   */
  public function findRecurringTasks(
      User $owner,
      array $filters = [],
      int $page = 1,
      int $perPage = 20
  ): array {
      $qb = $this->createQueryBuilder('t')
          ->where('t.owner = :owner')
          ->andWhere('t.isRecurring = true')
          ->setParameter('owner', $owner)
          ->orderBy('t.dueDate', 'ASC');

      // Apply additional filters...

      return $this->paginate($qb, $page, $perPage);
  }
  ```

- [ ] **5.6.2** Add recurring chain query
  ```php
  // src/Repository/TaskRepository.php - ADD method:

  /**
   * Find all tasks in a recurring chain (original + all recurrences).
   */
  public function findRecurringChain(User $owner, string $originalTaskId): array
  {
      return $this->createQueryBuilder('t')
          ->where('t.owner = :owner')
          ->andWhere('t.originalTask = :id OR t.id = :id')
          ->setParameter('owner', $owner)
          ->setParameter('id', $originalTaskId)
          ->orderBy('t.createdAt', 'ASC')
          ->getQuery()
          ->getResult();
  }

  /**
   * Count completed instances in a recurring chain.
   */
  public function countCompletedInChain(User $owner, string $originalTaskId): int
  {
      return (int) $this->createQueryBuilder('t')
          ->select('COUNT(t.id)')
          ->where('t.owner = :owner')
          ->andWhere('t.originalTask = :id OR t.id = :id')
          ->andWhere('t.status = :status')
          ->setParameter('owner', $owner)
          ->setParameter('id', $originalTaskId)
          ->setParameter('status', 'completed')
          ->getQuery()
          ->getSingleScalarResult();
  }
  ```

- [ ] **5.6.3** Add original_task_id filter support
  ```php
  // src/Repository/TaskRepository.php - UPDATE applyFilters():

  // In the filter application logic:
  if (isset($filters['original_task_id'])) {
      $qb->andWhere('t.originalTask = :originalTaskId')
         ->setParameter('originalTaskId', $filters['original_task_id']);
  }

  if (isset($filters['is_recurring'])) {
      $qb->andWhere('t.isRecurring = :isRecurring')
         ->setParameter('isRecurring', (bool) $filters['is_recurring']);
  }
  ```

### Completion Criteria
- [ ] Recurring task filtering works
- [ ] Chain queries include ownership validation
- [ ] Count queries for chain history work
- [ ] Filter by is_recurring works
- [ ] Filter by original_task_id works

### Files to Update
```
src/Repository/TaskRepository.php (updated)
tests/Integration/Repository/TaskRepositoryTest.php (updated)
```

---

## Sub-Phase 5.7: Controller Updates

### Objective
Update API endpoints for recurring task operations.

### Tasks

- [ ] **5.7.1** Update status change response for recurring tasks
  ```php
  // src/Controller/Api/TaskController.php - UPDATE changeStatus():

  #[Route('/{id}/status', name: 'change_status', methods: ['PATCH'])]
  public function changeStatus(string $id, Request $request): JsonResponse
  {
      $task = $this->getTaskOrFail($id);
      $data = $this->validationHelper->decodeJsonBody($request);

      $newStatus = $data['status'] ?? throw ValidationException::missingField('status');

      $result = $this->taskService->changeStatus($task, $newStatus);

      $response = TaskResponse::fromEntity(
          $result['task'],
          $result['undoToken'] ?? null
      )->toArray();

      // Include next task for recurring completions
      if (isset($result['nextTask'])) {
          $response['nextTask'] = TaskResponse::fromEntity($result['nextTask'])->toArray();
      }

      return $this->responseFormatter->success($response);
  }
  ```

- [ ] **5.7.2** Add complete-forever endpoint
  ```php
  // src/Controller/Api/TaskController.php - ADD method:

  #[Route('/{id}/complete-forever', name: 'complete_forever', methods: ['POST'])]
  public function completeForever(string $id): JsonResponse
  {
      $task = $this->getTaskOrFail($id);

      $result = $this->taskService->completeForever($task);

      $response = TaskResponse::fromEntity(
          $result['task'],
          $result['undoToken'] ?? null
      )->toArray();

      $response['message'] = 'Task completed permanently. No more recurrences will be created.';

      return $this->responseFormatter->success($response);
  }
  ```

- [ ] **5.7.3** Add recurring history endpoint
  ```php
  // src/Controller/Api/TaskController.php - ADD method:

  #[Route('/{id}/recurring-history', name: 'recurring_history', methods: ['GET'])]
  public function recurringHistory(string $id): JsonResponse
  {
      $task = $this->getTaskOrFail($id);

      // Find the original task ID
      $originalId = $task->getOriginalTask()?->getId() ?? $task->getId();

      $chain = $this->taskRepository->findRecurringChain(
          $this->ownershipChecker->getCurrentUser(),
          $originalId
      );

      $completedCount = $this->taskRepository->countCompletedInChain(
          $this->ownershipChecker->getCurrentUser(),
          $originalId
      );

      $data = array_map(fn($t) => [
          'id' => $t->getId(),
          'status' => $t->getStatus(),
          'dueDate' => $t->getDueDate()?->format('Y-m-d'),
          'completedAt' => $t->getCompletedAt()?->format(\DateTimeInterface::RFC3339),
      ], $chain);

      return $this->responseFormatter->success([
          'data' => $data,
          'meta' => [
              'originalTaskId' => $originalId,
              'totalCompletions' => $completedCount,
              'totalInstances' => count($chain),
          ],
      ]);
  }
  ```

### Completion Criteria
- [ ] Status change returns nextTask for recurring
- [ ] complete-forever endpoint works
- [ ] recurring-history endpoint returns chain
- [ ] All endpoints validate ownership
- [ ] API responses follow established format

### Files to Update
```
src/Controller/Api/TaskController.php (updated)
tests/Functional/Api/RecurringTaskApiTest.php (new)
```

---

## Sub-Phase 5.8: UI Integration

### Objective
Add recurring task indicator and edit UI per UI-PHASE-MODIFICATIONS.md.

### Tasks

- [ ] **5.8.1** Add recurring indicator to task card
  ```twig
  {# templates/components/task-item.html.twig - ADD in metadata row: #}

  {# Recurring indicator #}
  {% if task.isRecurring %}
      <span class="inline-flex items-center"
            title="{{ task.recurrenceRule }}"
            x-data
            x-tooltip="'{{ task.recurrenceRule }}'">
          <svg class="w-4 h-4 text-gray-400 hover:text-gray-600 transition-colors"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
              {# arrow-path icon (Heroicons) #}
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
      </span>
  {% endif %}
  ```

- [ ] **5.8.2** Add recurrence field to task edit form
  ```twig
  {# templates/components/task-form.html.twig - ADD recurrence field: #}

  <div class="mt-4" x-show="showAdvanced || isRecurring">
      <label class="text-xs font-medium text-gray-500 mb-1 block">
          Recurrence
      </label>
      <input type="text"
             name="recurrence_rule"
             x-model="recurrenceRule"
             placeholder="e.g., every Monday, every 2 weeks, every month on the 15th"
             class="w-full rounded-md border-gray-300 shadow-sm text-sm
                    focus:border-indigo-500 focus:ring-indigo-500
                    placeholder:text-gray-400">
      <p class="text-xs text-gray-400 mt-1">
          Examples: "every day", "every Monday at 2pm", "every! 3 days" (relative to completion)
      </p>
      <label class="inline-flex items-center mt-2">
          <input type="checkbox"
                 name="is_recurring"
                 x-model="isRecurring"
                 class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
          <span class="ml-2 text-sm text-gray-600">Enable recurring</span>
      </label>
  </div>
  ```

- [ ] **5.8.3** Add complete-forever option to recurring task menu
  ```twig
  {# templates/components/task-actions-dropdown.html.twig - ADD: #}

  {% if task.isRecurring %}
      <button @click="completeForever('{{ task.id }}')"
              class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
          <svg class="w-4 h-4 inline-block mr-2">...</svg>
          Complete Forever
      </button>
  {% endif %}
  ```

### Completion Criteria
- [ ] Recurring indicator displays in task card
- [ ] Tooltip shows recurrence rule text
- [ ] Edit form has recurrence field
- [ ] Complete forever option in menu
- [ ] Follows UI Design System (see UI-PHASE-MODIFICATIONS.md)

### Files to Update
```
templates/components/task-item.html.twig (updated)
templates/components/task-form.html.twig (updated)
templates/components/task-actions-dropdown.html.twig (updated)
assets/js/task-manager.js (updated)
```

---

## Sub-Phase 5.9: Comprehensive Tests

### Objective
Comprehensive test coverage for recurring task functionality.

### Tasks

- [ ] **5.9.1** Parser unit tests
  ```php
  // tests/Unit/Service/Recurrence/RecurrenceRuleParserTest.php

  Test coverage:
  - testParseEveryDay()
  - testParseEveryXDays()
  - testParseDaily()
  - testParseEveryWeek()
  - testParseEveryMonday()
  - testParseMultipleDays()
  - testParseMultipleDaysWithTime()
  - testParseWeekday()
  - testParseWeekend()
  - testParseBiweekly()
  - testParseEveryMonth()
  - testParseMonthlyOnDay()
  - testParseLastDayOfMonth()
  - testParseQuarterly()
  - testParseEveryYear()
  - testParseYearly()
  - testParseAnnually()
  - testParseSpecificMonthAndDay()
  - testParseLeapYearDate()
  - testParseWithTime12Hour()
  - testParseWithTime24Hour()
  - testParseWithNoon()
  - testParseWithMidnight()
  - testParseWithEndDate()
  - testParseRelative()
  - testParseAbsoluteDefault()
  - testParseCaseInsensitive()
  - testParseWithAbbreviations()
  - testParseWithExtraSpaces()
  - testInvalidHourlyPatternThrows()
  - testInvalidOrdinalPatternThrows()
  - testInvalidDateThrows()
  - testInvalidDayOfMonthThrows()
  - testUnparseablePatternThrows()
  ```

- [ ] **5.9.2** Calculator unit tests
  ```php
  // tests/Unit/Service/Recurrence/NextDateCalculatorTest.php

  Test coverage:
  - testAbsoluteDailyNextDate()
  - testAbsoluteWeeklyNextDate()
  - testAbsoluteWeeklySpecificDays()
  - testAbsoluteMonthlyNextDate()
  - testAbsoluteMonthlyDayOfMonth()
  - testAbsoluteYearlyNextDate()
  - testRelativeDailyNextDate()
  - testRelativeWeeklyNextDate()
  - testDayOfMonthExceedsMonth()
  - testLeapYearFebruary29()
  - testNonLeapYearFebruary29()
  - testLastDayOfMonth()
  - testWithTimezone()
  - testTimeApplied()
  - testShouldCreateNextInstanceTrue()
  - testShouldCreateNextInstanceFalse()
  ```

- [ ] **5.9.3** Service integration tests
  ```php
  // tests/Functional/Api/RecurringTaskApiTest.php

  Test coverage:
  - testCreateRecurringTask()
  - testCreateRecurringTaskWithInvalidRule()
  - testCompleteRecurringTaskCreatesNewInstance()
  - testNewInstanceHasCorrectDueDate()
  - testOriginalTaskIdChainMaintained()
  - testTagsCopiedToNewInstance()
  - testProjectCopiedToNewInstance()
  - testCompleteForeverNoNewInstance()
  - testCompleteForeverRequiresRecurringTask()
  - testAbsoluteRecurrenceFromOriginalDate()
  - testRelativeRecurrenceFromCompletionDate()
  - testEndDateReachedNoNewInstance()
  - testRecurringHistoryEndpoint()
  - testRecurringHistoryOwnershipEnforced()
  - testQueryByOriginalTaskId()
  - testQueryByIsRecurring()
  - testUndoRecurringCompletion()
  ```

- [ ] **5.9.4** Exception mapper test
  ```php
  // tests/Unit/EventListener/ExceptionMapper/Domain/InvalidRecurrenceExceptionMapperTest.php

  Test coverage:
  - testCanHandle()
  - testCannotHandleOtherExceptions()
  - testMapReturnsCorrectErrorCode()
  - testMapReturnsCorrectStatusCode()
  - testMapIncludesDetails()
  - testGetPriority()
  ```

### Completion Criteria
- [ ] Parser edge cases tested
- [ ] Calculator accuracy verified
- [ ] Completion flow tested end-to-end
- [ ] Chain tracking tested
- [ ] Undo functionality tested
- [ ] Invalid pattern handling tested
- [ ] Exception mapper tested
- [ ] All tests passing

### Files to Create
```
tests/Unit/Service/Recurrence/RecurrenceRuleParserTest.php (new)
tests/Unit/Service/Recurrence/RecurrenceRuleTest.php (new)
tests/Unit/Service/Recurrence/NextDateCalculatorTest.php (new)
tests/Unit/Exception/InvalidRecurrenceExceptionTest.php (new)
tests/Unit/EventListener/ExceptionMapper/Domain/InvalidRecurrenceExceptionMapperTest.php (new)
tests/Functional/Api/RecurringTaskApiTest.php (new)
```

---

## Phase 5 Deliverables Checklist

At the end of Phase 5, verify the following:

### Database & Entity
- [ ] Task entity has recurrence properties (re-added)
- [ ] Migration runs successfully
- [ ] RecurrenceType enum created
- [ ] Index on owner_id + is_recurring exists

### Parser
- [ ] Recurrence rule parser handles all patterns
- [ ] Parser supports both 12-hour and 24-hour time formats
- [ ] Parser is lenient with case, abbreviations, whitespace
- [ ] Multiple days with time supported
- [ ] Original text always preserved in storage
- [ ] InvalidRecurrenceException re-created with mapper

### Recurrence Types
- [ ] Absolute recurrence calculates from schedule
- [ ] Relative recurrence calculates from completion

### Date Calculation
- [ ] Day-of-month edge cases handled
- [ ] Leap year handling correct
- [ ] Last day of month works correctly
- [ ] Time component applied correctly
- [ ] End date respected

### Task Completion
- [ ] Completing recurring task creates new instance
- [ ] original_task_id chain maintained correctly
- [ ] Complete-forever endpoint works
- [ ] Tags copied to new instances
- [ ] Undo works for recurring completion

### Error Handling
- [ ] Invalid patterns rejected with helpful messages
- [ ] Unsupported patterns have clear errors
- [ ] Invalid dates caught

### API
- [ ] Status change returns nextTask for recurring
- [ ] complete-forever endpoint works
- [ ] recurring-history endpoint works
- [ ] All endpoints validate ownership

### UI
- [ ] Recurring indicator displays with tooltip
- [ ] Edit form includes recurrence field
- [ ] Complete forever option in menu

### Testing
- [ ] Parser tests comprehensive (30+ tests)
- [ ] Calculator tests complete (15+ tests)
- [ ] Integration tests passing (15+ tests)
- [ ] Exception mapper tested

---

## Architecture Alignment Notes

### Patterns to Follow (From Phases 1-3)

| Pattern | Example Location | Apply To |
|---------|-----------------|----------|
| State Serialization | TaskStateService | Extend for recurrence fields |
| Undo Token Creation | TaskUndoService | Reuse for recurring completion |
| Exception Mapper | ExceptionMapperRegistry | InvalidRecurrenceExceptionMapper |
| DTO Validation | CreateTaskRequest | Add recurrence constraints |
| Ownership Validation | OwnershipChecker | All recurring operations |
| Service Delegation | TaskService -> TaskUndoService | Recurring logic in TaskService |

### Breaking Changes from Phase 2

The Phase 2 review removed recurrence fields from Task entity (Issue 4.2.6). Phase 5 must:

1. **Re-add recurrence fields** via migration
2. **Re-create InvalidRecurrenceException** and its mapper
3. **Follow the refactored service architecture** (TaskService, TaskStateService, TaskUndoService)

### Integration Points

| Component | Integration |
|-----------|-------------|
| NaturalLanguageParserService | Could optionally detect recurrence in future |
| TaskStateService | Must serialize/deserialize recurrence fields |
| TaskUndoService | Handles undo for recurring completion |
| ResponseFormatter | Used for all API responses |
| ExceptionMapperRegistry | Auto-discovers InvalidRecurrenceExceptionMapper |

---

## Document History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-01-24 | Original plan |
| 2.0 | 2026-01-24 | Updated for Phase 1-3 architecture changes: service refactoring, removed recurrence fields, exception mapper registry |
