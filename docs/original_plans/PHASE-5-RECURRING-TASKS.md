# Phase 5: Recurring Tasks

## Overview
**Duration**: Week 5
**Goal**: Implement the complete recurring task system including recurrence rule parsing, absolute vs relative recurrence, task instance generation on completion, and the original_task_id chain tracking.

**Last Updated**: 2026-01-24 (Updated for Phase 1-3 architecture changes)

## Prerequisites
- Phases 1-4 completed
- Date parsing working
- Task completion working

---

## Architecture Context (IMPORTANT - Read Before Implementation)

This plan has been updated to account for architecture changes that occurred during Phases 1-3. Implementers MUST be aware of the following:

### Critical Discovery: Recurrence Fields Were Removed

During Phase 2 cleanup (Issue 4.2.6), the recurrence properties were **removed** from the Task entity because they weren't implemented. Phase 5 must:

1. **Re-add recurrence fields** to Task entity via new migration
2. **Re-create `InvalidRecurrenceException`** (was deleted in Phase 2)
3. **Create exception mapper** for `InvalidRecurrenceException`

### Current Service Architecture (Post-Phase 2 Refactoring)

The service layer has been split for single responsibility. Follow these patterns:

```
src/Service/
├── TaskService.php           # Core CRUD operations only
├── TaskStateService.php      # State serialization for undo
├── TaskUndoService.php       # Undo token creation and restoration
├── UndoService.php           # Redis-based undo token storage (shared)
└── Parser/                   # Natural language parsing (follow this pattern)
    ├── NaturalLanguageParserService.php
    ├── DateParserService.php
    └── ...
```

### Key Patterns to Follow

| Pattern | Location | Notes |
|---------|----------|-------|
| State Serialization | `TaskStateService.serializeTaskState()` | Extend for recurrence fields |
| State Restoration | `Task.restoreFromState()` | Use this for undo, not reflection |
| Undo Token Creation | `TaskUndoService.createUndoToken()` | Returns `UndoToken\|null` |
| Undo Consumption | `UndoService.consumeUndoToken()` | Atomic via Lua script |
| Exception Mapping | `ExceptionMapperRegistry` | Auto-discovery with `#[AutoconfigureTag]` |
| Ownership Check | `OwnershipChecker` | Use on all mutations |
| DTO Validation | Symfony Validator constraints | In constructor properties |

### Existing Field Available for Reuse

The `originalTask` relationship already exists in Task entity and can be used for recurring chain tracking:

```php
// Already exists in Task.php:
#[ORM\ManyToOne(targetEntity: Task::class)]
#[ORM\JoinColumn(name: 'original_task_id', nullable: true)]
private ?Task $originalTask = null;
```

---

## Pre-Implementation: Database Migration Required

Before starting Sub-Phase 5.1, create a migration to add recurrence fields:

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

Also update `Task.restoreFromState()` to handle recurrence fields for undo operations.

---

## Sub-Phase 5.1: Recurrence Rule Parser

### Objective
Create a parser that converts natural language recurrence patterns into structured data.

### Tasks

- [ ] **5.1.1** Create RecurrenceRuleParser service
  ```php
  // src/Service/Recurrence/RecurrenceRuleParser.php
  
  public function parse(string $rule): RecurrenceRule
  {
      // Parse natural language into structured RecurrenceRule
      // Throws InvalidRecurrenceException if unparseable
  }
  ```

- [ ] **5.1.2** Create RecurrenceRule value object
  ```php
  // src/ValueObject/RecurrenceRule.php
  
  Properties:
  - originalText: string (preserved for display - ALWAYS STORED)
  - type: 'absolute'|'relative'
  - interval: 'day'|'week'|'month'|'year'
  - count: int (every X intervals)
  - days: array (for weekly: ['Mon', 'Wed'])
  - dayOfMonth: ?int (for monthly: 15, or -1 for last day)
  - monthOfYear: ?int (for yearly: 1-12)
  - time: ?string (HH:MM format, 24-hour)
  - endDate: ?DateTimeImmutable
  
  STORAGE FORMAT:
  The originalText is ALWAYS stored in the database alongside the parsed fields.
  This preserves the user's original input for display purposes:
  - User sees: "every Monday at 2pm"
  - Database stores: originalText="every Monday at 2pm", interval="week", 
                     days=["Mon"], time="14:00", type="absolute"
  ```

- [ ] **5.1.3** Define parser leniency rules
  ```php
  PARSER LENIENCY:
  
  The parser should be lenient and handle common variations:
  
  Case insensitivity:
  - "every monday" = "every Monday" = "EVERY MONDAY"
  - "every mon" = "every Mon"
  
  Abbreviations:
  - "mon", "tue", "wed", "thu", "fri", "sat", "sun"
  - "jan", "feb", "mar", etc.
  
  Whitespace tolerance:
  - "every  Monday" (extra spaces) → valid
  - "every Monday" vs "everyMonday" → only first is valid
  
  Common synonyms:
  - "daily" = "every day"
  - "weekly" = "every week"
  - "monthly" = "every month"
  - "yearly" = "every year" = "annually"
  - "biweekly" = "every 2 weeks"
  - "quarterly" = "every 3 months"
  
  Ordinal handling:
  - "every 1st" = "every 1"
  - "every 15th" = "every 15"
  ```

- [ ] **5.1.4** Implement daily recurrence parsing
  ```php
  Patterns:
  - "every day" → interval: day, count: 1, type: absolute
  - "every! day" → interval: day, count: 1, type: relative
  - "every 3 days" → interval: day, count: 3, type: absolute
  - "every! 3 days" → interval: day, count: 3, type: relative
  - "daily" → interval: day, count: 1, type: absolute
  ```

- [ ] **5.1.5** Implement weekly recurrence parsing
  ```php
  Patterns:
  - "every week" → interval: week, count: 1
  - "every 2 weeks" → interval: week, count: 2
  - "every Monday" → interval: week, days: ['Mon']
  - "every Mon, Wed, Fri" → interval: week, days: ['Mon', 'Wed', 'Fri']
  - "every weekday" → interval: week, days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']
  - "every weekend" → interval: week, days: ['Sat', 'Sun']
  - "weekly" → interval: week, count: 1
  - "biweekly" → interval: week, count: 2
  
  MULTIPLE DAYS WITH TIME:
  - "every Mon, Wed at 2pm" → interval: week, days: ['Mon', 'Wed'], time: '14:00'
  - "every Mon, Wed, Fri at 9:30am" → days: ['Mon', 'Wed', 'Fri'], time: '09:30'
  - Time applies to ALL specified days
  ```

- [ ] **5.1.6** Implement monthly recurrence parsing
  ```php
  Patterns:
  - "every month" → interval: month, count: 1
  - "every 15th" → interval: month, dayOfMonth: 15
  - "monthly on the 15th" → interval: month, dayOfMonth: 15
  - "every month on the last day" → interval: month, dayOfMonth: -1
  - "every 2 months" → interval: month, count: 2
  - "quarterly" → interval: month, count: 3
  
  NEGATIVE DAY-OF-MONTH HANDLING:
  dayOfMonth: -1 means "last day of month"
  - February (non-leap): -1 → 28th
  - February (leap): -1 → 29th
  - April: -1 → 30th
  - January: -1 → 31st
  
  FIXED DAY-OF-MONTH HANDLING (for months with fewer days):
  dayOfMonth: 31 in a 30-day month:
  - Use last day of month (30th)
  - Same for 29th, 30th, 31st in February
  
  Examples:
  - "every 31st" in April → April 30
  - "every 30th" in February → February 28/29
  - "every 29th" in non-leap February → February 28
  ```

- [ ] **5.1.7** Implement yearly recurrence parsing
  ```php
  Patterns:
  - "every year" → interval: year, count: 1
  - "yearly" → interval: year, count: 1
  - "annually" → interval: year, count: 1
  - "every January 15" → interval: year, monthOfYear: 1, dayOfMonth: 15
  - "every January 15th" → interval: year, monthOfYear: 1, dayOfMonth: 15
  - "every Jan 15" → interval: year, monthOfYear: 1, dayOfMonth: 15
  - "every 2 years" → interval: year, count: 2
  
  LEAP YEAR HANDLING:
  - "every February 29" → In non-leap years, use February 28
  - Optionally: skip non-leap years (configurable, default: use Feb 28)
  ```

- [ ] **5.1.8** Implement time component parsing
  ```php
  TIME FORMAT SUPPORT:
  Both 12-hour and 24-hour formats are supported:
  
  12-hour format:
  - "2pm", "2:30pm", "2:30 pm"
  - "9am", "9:30am", "9:30 am"
  - "12pm" (noon), "12am" (midnight)
  
  24-hour format:
  - "14:00", "14:30"
  - "09:30", "9:30"
  - "00:00" (midnight), "12:00" (noon)
  
  Special keywords:
  - "noon" → 12:00
  - "midnight" → 00:00
  
  Combined patterns:
  - "every Monday at 2pm" → days: ['Mon'], time: '14:00'
  - "every Monday at 14:00" → days: ['Mon'], time: '14:00'
  - "every day at 9:30am" → interval: day, time: '09:30'
  - "every day at 09:30" → interval: day, time: '09:30'
  - "every 15th at noon" → dayOfMonth: 15, time: '12:00'
  - "every Mon, Wed at 2pm" → days: ['Mon', 'Wed'], time: '14:00'
  
  Storage: Always stored as 24-hour format (HH:MM)
  ```

- [ ] **5.1.9** Implement end date parsing
  ```php
  Patterns:
  - "every Monday until March 1" → endDate: 2026-03-01
  - "every week until 2026-06-30" → endDate: 2026-06-30
  ```

- [ ] **5.1.10** Handle invalid/unsupported patterns
  ```php
  UNSUPPORTED PATTERNS (return error):
  
  Time-based intervals (not supported):
  - "every 5 hours" → ERROR: Hourly recurrence not supported
  - "every minute" → ERROR: Minute-based recurrence not supported
  - "every 30 minutes" → ERROR: Minute-based recurrence not supported
  
  Ordinal day-of-week (not supported in v1):
  - "every first Monday" → ERROR: Ordinal day patterns not supported
  - "every third Tuesday" → ERROR: Ordinal day patterns not supported
  - "every last Friday" → ERROR: Ordinal day patterns not supported
  
  Invalid dates:
  - "every February 30" → ERROR: Invalid date (February has max 29 days)
  - "every February 31" → ERROR: Invalid date (February has max 29 days)
  - "every 32nd" → ERROR: Invalid day of month (max 31)
  - "every 0th" → ERROR: Invalid day of month (min 1)
  
  Unparseable:
  - "whenever I feel like it" → ERROR: Could not parse recurrence rule
  - "sometimes weekly" → ERROR: Could not parse recurrence rule
  
  ERROR RESPONSE FORMAT:
  {
    "error": {
      "code": "INVALID_RECURRENCE",
      "message": "Could not parse recurrence rule",
      "details": {
        "recurrence_rule": ["Pattern 'every third Tuesday' is not supported. Try 'every Tuesday' instead."]
      }
    }
  }
  ```

### Completion Criteria
- [ ] Daily patterns parsed correctly
- [ ] Weekly patterns with day names work
- [ ] Weekly patterns with multiple days AND time work
- [ ] Monthly patterns work with day-of-month edge cases
- [ ] Yearly patterns work including leap year handling
- [ ] Time component extracted (both 12-hour and 24-hour formats)
- [ ] Parser is lenient with case and abbreviations
- [ ] End date parsed
- [ ] Invalid patterns rejected with helpful error messages
- [ ] Original text always preserved in storage

### Files to Create
```
src/Service/Recurrence/
├── RecurrenceRuleParser.php
├── RecurrenceRule.php              # Value object (immutable, like UndoToken)
└── RecurrenceParseResult.php       # Optional: result with warnings

src/Exception/
└── InvalidRecurrenceException.php  # Re-create (was deleted in Phase 2)

src/EventListener/ExceptionMapper/Domain/
└── InvalidRecurrenceExceptionMapper.php  # Required for API error responses

tests/Unit/Service/Recurrence/
├── RecurrenceRuleParserTest.php
└── RecurrenceRuleTest.php

tests/Unit/Exception/
└── InvalidRecurrenceExceptionTest.php

tests/Unit/EventListener/ExceptionMapper/Domain/
└── InvalidRecurrenceExceptionMapperTest.php
```

**Note on InvalidRecurrenceException**: This exception was deleted during Phase 2 cleanup. It must be re-created following the existing exception pattern (extending `BadRequestHttpException`) with an exception mapper that implements `ExceptionMapperInterface` and uses `#[AutoconfigureTag('app.exception_mapper')]` for auto-discovery.

---

## Sub-Phase 5.2: Recurrence Types (Absolute vs Relative)

### Objective
Implement the distinction between absolute ("every") and relative ("every!") recurrence.

### Tasks

- [ ] **5.2.1** Document recurrence type semantics
  ```
  ABSOLUTE (every):
  - Next occurrence based on ORIGINAL schedule
  - "every Monday" → always next Monday, regardless of when completed
  - "every 15th" → always 15th of next month
  - If you complete late, next date is still based on schedule
  
  RELATIVE (every!):
  - Next occurrence based on COMPLETION date
  - "every! week" → 7 days from when you complete
  - "every! 3 days" → 3 days from completion
  - Completion date determines next date
  ```

- [ ] **5.2.2** Implement type detection in parser
  ```php
  // Detection logic:
  // - "every!" prefix → relative
  // - "every" prefix → absolute
  // - Default when ambiguous → absolute
  
  public function parse(string $rule): RecurrenceRule
  {
      if (str_starts_with(strtolower($rule), 'every!')) {
          $type = 'relative';
          $rule = substr($rule, 6); // Remove 'every!'
      } else {
          $type = 'absolute';
      }
      // ... continue parsing
  }
  ```

- [ ] **5.2.3** Store type in database
  ```php
  // Task entity fields (must be RE-ADDED - see Architecture Context):
  - is_recurring: bool (default false)
  - recurrence_rule: ?string (original text - ALWAYS PRESERVED)
  - recurrence_type: ?string ('absolute'|'relative')
  - recurrence_end_date: ?DateTimeImmutable

  // Also update Task::restoreFromState() for undo support:
  public function restoreFromState(array $state): void
  {
      // ... existing fields ...
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

- [ ] **5.2.4** Create RecurrenceType enum
  ```php
  // src/Enum/RecurrenceType.php
  
  enum RecurrenceType: string
  {
      case ABSOLUTE = 'absolute';
      case RELATIVE = 'relative';
  }
  ```

### Completion Criteria
- [ ] Absolute type detected correctly
- [ ] Relative type (every!) detected correctly
- [ ] Type stored in database
- [ ] Original text stored in database
- [ ] Default is absolute when ambiguous

### Files to Create/Update
```
src/Enum/RecurrenceType.php (new)
src/Entity/Task.php (updated)
src/Service/Recurrence/RecurrenceRuleParser.php (updated)
```

---

## Sub-Phase 5.3: Next Date Calculator

### Objective
Create service to calculate the next occurrence date based on recurrence rule and type.

### Tasks

- [ ] **5.3.1** Create NextDateCalculator service
  ```php
  // src/Service/Recurrence/NextDateCalculator.php
  
  public function calculate(
      RecurrenceRule $rule,
      DateTimeImmutable $referenceDate,  // Current due date or completion date
      string $userTimezone
  ): DateTimeImmutable
  {
      // Calculate next occurrence
      // All calculations in user timezone
      // Return UTC timestamp
  }
  ```

- [ ] **5.3.2** Implement absolute date calculation
  ```php
  // For absolute recurrence, reference date is the ORIGINAL due date
  
  public function calculateAbsolute(
      RecurrenceRule $rule,
      DateTimeImmutable $currentDueDate,
      string $timezone
  ): DateTimeImmutable
  {
      // "every Monday" → find next Monday after currentDueDate
      // "every 15th" → find 15th of next month after currentDueDate
      // "every 2 weeks" → add 2 weeks to currentDueDate
      // "every year" → add 1 year to currentDueDate
      // "every January 15" → find next January 15 after currentDueDate
  }
  ```

- [ ] **5.3.3** Implement relative date calculation
  ```php
  // For relative recurrence, reference date is COMPLETION date (now)
  
  public function calculateRelative(
      RecurrenceRule $rule,
      DateTimeImmutable $completionDate,
      string $timezone
  ): DateTimeImmutable
  {
      // "every! 3 days" → completionDate + 3 days
      // "every! week" → completionDate + 7 days
      // "every! year" → completionDate + 1 year
  }
  ```

- [ ] **5.3.4** Handle specific day calculations
  ```php
  // For "every Monday, Wednesday":
  // Find next occurrence of ANY specified day
  // If completed on Monday, next is Wednesday of same week
  // If completed on Thursday, next is Monday of next week
  ```

- [ ] **5.3.5** Handle day-of-month edge cases
  ```php
  // For dayOfMonth > days in target month:
  // Use last day of month
  
  private function resolveDayOfMonth(int $dayOfMonth, int $month, int $year): int
  {
      $maxDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
      
      if ($dayOfMonth === -1) {
          // -1 means last day
          return $maxDays;
      }
      
      // Clamp to valid range
      return min($dayOfMonth, $maxDays);
  }
  ```

- [ ] **5.3.6** Handle timezone and DST
  ```php
  // All calculations in user timezone
  // Handle DST transitions:
  // - "2pm every day" stays 2pm local time even across DST
  // - Spring forward: 2am doesn't exist → use 3am
  // - Fall back: 2am occurs twice → use first occurrence
  
  // Final conversion to UTC for storage
  ```

- [ ] **5.3.7** Handle end date checking
  ```php
  public function shouldCreateNextInstance(
      RecurrenceRule $rule,
      DateTimeImmutable $nextDate
  ): bool
  {
      if ($rule->getEndDate() === null) {
          return true;
      }
      return $nextDate <= $rule->getEndDate();
  }
  ```

### Completion Criteria
- [ ] Absolute calculation correct for all patterns
- [ ] Relative calculation correct
- [ ] Yearly patterns calculated correctly
- [ ] Day-of-month edge cases handled
- [ ] Timezone handling works
- [ ] DST transitions handled
- [ ] End date respected

### Files to Create
```
src/Service/Recurrence/
└── NextDateCalculator.php

tests/Unit/Service/Recurrence/
└── NextDateCalculatorTest.php
```

---

## Sub-Phase 5.4: Task Completion Flow for Recurring Tasks

### Objective
Implement the logic that creates a new task instance when a recurring task is completed.

### Architecture Note
Follow the existing service patterns:
- Use `TaskStateService` for serializing task state before mutations
- Use `TaskUndoService` for creating undo tokens
- Add `RecurrenceRuleParser` and `NextDateCalculator` as dependencies to `TaskService`

### Tasks

- [ ] **5.4.0** Add dependencies to TaskService
  ```php
  // src/Service/TaskService.php - UPDATE constructor:

  public function __construct(
      // ... existing dependencies ...
      private readonly RecurrenceRuleParser $recurrenceRuleParser,
      private readonly NextDateCalculator $nextDateCalculator,
  ) {}
  ```

- [ ] **5.4.1** Update TaskService.changeStatus() (note: method is changeStatus, not updateStatus)
  ```php
  // src/Service/TaskService.php
  
  public function updateStatus(Task $task, string $status): TaskStatusResult
  {
      if ($status === 'completed' && $task->isRecurring()) {
          return $this->completeRecurringTask($task);
      }
      
      // Regular status update
      $task->setStatus($status);
      if ($status === 'completed') {
          $task->setCompletedAt(new DateTimeImmutable());
      }
      
      return new TaskStatusResult($task, null, $undoToken);
  }
  ```

- [ ] **5.4.2** Implement completeRecurringTask()
  ```php
  // Follow the existing pattern from TaskService methods
  private function completeRecurringTask(Task $task, User $user): TaskStatusResult
  {
      $this->ownershipChecker->checkOwnership($task);

      // 1. Serialize state BEFORE mutation (for undo support)
      $previousState = $this->taskStateService->serializeTaskState($task);

      // 2. Mark current task as completed
      $task->setStatus('completed');
      $task->setCompletedAt(new DateTimeImmutable());

      // 3. Parse the recurrence rule
      $rule = $this->recurrenceRuleParser->parse($task->getRecurrenceRule());

      // 4. Calculate next due date
      $referenceDate = $task->getRecurrenceType() === 'absolute'
          ? ($task->getDueDate() ?? new DateTimeImmutable())
          : new DateTimeImmutable(); // completion time for relative

      $userTimezone = $user->getTimezone() ?? 'UTC';
      $nextDate = $this->nextDateCalculator->calculate(
          $rule,
          $referenceDate,
          $userTimezone
      );

      // 5. Check if should create next instance (end date check)
      $nextTask = null;
      if ($this->nextDateCalculator->shouldCreateNextInstance($rule, $nextDate)) {
          $nextTask = $this->createNextRecurringInstance($task, $nextDate, $rule);
      }

      // 6. Create undo token (following TaskUndoService pattern)
      $undoToken = $this->taskUndoService->createUndoToken(
          UndoAction::STATUS_CHANGE,
          $task,
          $previousState
      );

      $this->entityManager->flush();

      return new TaskStatusResult($task, $nextTask, $undoToken);
  }
  ```

- [ ] **5.4.3** Implement createNextRecurringInstance()
  ```php
  // Note: Use actual entity method names (getOwner not getUser, etc.)
  private function createNextRecurringInstance(
      Task $completedTask,
      \DateTimeImmutable $nextDueDate,
      RecurrenceRule $rule
  ): Task {
      $nextTask = new Task();

      // Copy properties (use correct method names from Task entity)
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
      if ($rule->time !== null) {
          $nextTask->setDueTime(new \DateTimeImmutable($rule->time));
      }

      // Set original_task_id for chain tracking (uses existing relationship)
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

- [ ] **5.4.4** Create TaskStatusResult DTO
  ```php
  // src/DTO/TaskStatusResult.php
  // Follow existing DTO patterns (readonly, toArray method)

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

- [ ] **5.4.5** Update API response for recurring completion
  ```php
  PATCH /api/v1/tasks/{id}/status
  {"status": "completed"}
  
  Response (recurring task):
  {
    "data": {
      "id": 123,
      "status": "completed",
      "completed_at": "2026-01-23T15:30:00Z",
      ...
    },
    "next_task": {
      "id": 456,
      "title": "Weekly team meeting",
      "due_date": "2026-01-30T14:00:00Z",
      "original_task_id": 123,
      ...
    },
    "undo_token": "undo_xyz789",
    "undo_expires_at": "2026-01-23T15:31:00Z"
  }
  ```

### Completion Criteria
- [ ] Completing recurring task creates new instance
- [ ] New instance has correct due date
- [ ] original_task_id chain maintained
- [ ] Tags copied to new instance
- [ ] Undo token provided
- [ ] Response includes next_task

### Files to Create/Update
```
src/Service/TaskService.php (updated - add recurrence dependencies and methods)
src/Service/TaskStateService.php (updated - serialize recurrence fields)
src/DTO/TaskStatusResult.php (new)
src/DTO/CreateTaskRequest.php (updated - add recurrence fields)
src/DTO/UpdateTaskRequest.php (updated - add recurrence fields)
src/DTO/TaskResponse.php (updated - add recurrence fields)
src/Controller/Api/TaskController.php (updated)

tests/Unit/DTO/TaskStatusResultTest.php (new)
```

**DTO Updates Required:**

```php
// src/DTO/CreateTaskRequest.php - ADD:
#[Assert\Type('boolean')]
public readonly bool $isRecurring = false,

#[Assert\Type('string')]
#[Assert\Length(max: 1000)]
public readonly ?string $recurrenceRule = null,

// src/DTO/TaskResponse.php - ADD to fromEntity():
isRecurring: $task->isRecurring(),
recurrenceRule: $task->getRecurrenceRule(),
recurrenceType: $task->getRecurrenceType(),
recurrenceEndDate: $task->getRecurrenceEndDate()?->format('Y-m-d'),
```

---

## Sub-Phase 5.5: Original Task ID Chain Tracking

### Objective
Implement the original_task_id chain for tracking all instances of a recurring task.

### Tasks

- [ ] **5.5.1** Document chain semantics
  ```
  CHAIN STRUCTURE:
  - First task: original_task_id = NULL
  - All subsequent: original_task_id = ID of first task
  
  EXAMPLE:
  Task 100 (first): original_task_id = NULL
  Task 101 (second): original_task_id = 100
  Task 102 (third): original_task_id = 100
  Task 103 (fourth): original_task_id = 100
  
  QUERY ALL INSTANCES:
  WHERE original_task_id = 100 OR id = 100
  ```

- [ ] **5.5.2** Add filter for chain queries
  ```php
  GET /api/v1/tasks?original_task_id=100
  
  // Returns all tasks in the recurring chain
  // Includes the original task (id=100) and all recurrences
  ```

- [ ] **5.5.3** Create chain query in repository
  ```php
  // src/Repository/TaskRepository.php
  // IMPORTANT: Include ownership validation in all queries

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

- [ ] **5.5.4** Add chain info to task response
  ```php
  // When fetching a recurring task, include chain info
  
  {
    "id": 103,
    "title": "Weekly meeting",
    "is_recurring": true,
    "original_task_id": 100,
    "chain_info": {
      "position": 4,          // 4th in chain
      "completed_count": 3,   // 3 completed before this
      "total_count": 4        // Total in chain
    }
  }
  ```

- [ ] **5.5.5** Create endpoint for chain history
  ```php
  GET /api/v1/tasks/{id}/recurring-history
  
  // Returns all completed instances of the recurring chain
  // Useful for seeing completion history
  
  Response:
  {
    "data": [
      { "id": 100, "completed_at": "2026-01-06T14:00:00Z" },
      { "id": 101, "completed_at": "2026-01-13T14:15:00Z" },
      { "id": 102, "completed_at": "2026-01-20T14:05:00Z" }
    ],
    "meta": {
      "original_task_id": 100,
      "total_completions": 3
    }
  }
  ```

### Completion Criteria
- [ ] Chain correctly maintained
- [ ] Query by original_task_id works
- [ ] Chain history endpoint functional
- [ ] Chain info in task response

### Files to Update
```
src/Repository/TaskRepository.php (updated)
src/Controller/Api/TaskController.php (updated)
```

---

## Sub-Phase 5.6: Complete Forever Endpoint

### Objective
Create endpoint to permanently complete a recurring task without creating next instance.

### Tasks

- [ ] **5.6.1** Create complete-forever endpoint
  ```php
  POST /api/v1/tasks/{id}/complete-forever
  
  Behavior:
  1. Mark task as completed
  2. Do NOT create next instance
  3. Remove recurring flag (optional, or set a "permanently_completed" flag)
  
  Response:
  {
    "data": {
      "id": 123,
      "status": "completed",
      "is_recurring": false,
      "completed_at": "2026-01-23T15:30:00Z"
    },
    "message": "Task completed permanently. No more recurrences will be created."
  }
  ```

- [ ] **5.6.2** Implement in TaskService
  ```php
  public function completeForever(Task $task): Task
  {
      if (!$task->isRecurring()) {
          throw new BadRequestException('Task is not recurring');
      }
      
      $task->setStatus('completed');
      $task->setCompletedAt(new DateTimeImmutable());
      $task->setIsRecurring(false);
      // Keep recurrence_rule for history/audit purposes
      
      return $task;
  }
  ```

- [ ] **5.6.3** Add to controller
  ```php
  #[Route('/api/v1/tasks/{id}/complete-forever', methods: ['POST'])]
  public function completeForever(int $id): JsonResponse
  {
      $task = $this->getTaskOrFail($id);
      $this->taskService->completeForever($task);
      return $this->json([...]);
  }
  ```

### Completion Criteria
- [ ] Endpoint completes without creating next instance
- [ ] Recurring flag removed
- [ ] Works only for recurring tasks
- [ ] Response indicates permanent completion

### Files to Update
```
src/Controller/Api/TaskController.php (updated)
src/Service/TaskService.php (updated)
```

---

## Sub-Phase 5.7: Recurring Task API Integration

### Objective
Ensure API properly handles recurring task creation and updates.

### Tasks

- [ ] **5.7.1** Update task creation for recurring
  ```php
  POST /api/v1/tasks
  {
    "title": "Weekly team meeting",
    "due_date": "2026-01-27T14:00:00Z",
    "recurrence_rule": "every Monday at 2pm",
    "is_recurring": true
  }
  
  // Validates recurrence_rule
  // Sets recurrence_type based on parsing
  // Sets due_date to first occurrence if not provided
  ```

- [ ] **5.7.2** Handle recurrence rule validation
  ```php
  // On creation/update, validate recurrence_rule
  
  if ($data['is_recurring'] && !empty($data['recurrence_rule'])) {
      try {
          $rule = $this->parser->parse($data['recurrence_rule']);
          $task->setRecurrenceType($rule->getType());
      } catch (InvalidRecurrenceException $e) {
          throw new ValidationException([
              'recurrence_rule' => [$e->getMessage()]
          ]);
      }
  }
  ```

- [ ] **5.7.3** Handle update of recurrence rule
  ```php
  PATCH /api/v1/tasks/{id}
  {
    "recurrence_rule": "every 2 weeks"
  }
  
  // Updates rule for this task only
  // Does NOT affect already-created future instances
  // Only affects next instance created on completion
  ```

- [ ] **5.7.4** Validate is_recurring consistency
  ```php
  // Validation rules:
  // - is_recurring=true requires recurrence_rule
  // - recurrence_rule without is_recurring=true → error
  // - Setting is_recurring=false clears recurrence fields
  ```

### Completion Criteria
- [ ] Recurring tasks creatable via API
- [ ] Invalid recurrence rules rejected with helpful messages
- [ ] Updates work correctly
- [ ] Validation messages helpful

### Files to Update
```
src/Controller/Api/TaskController.php (updated)
src/Service/TaskService.php (updated)
```

---

## Sub-Phase 5.7.5: Recurring Task UI Indicator

### Objective
Add visual indicator for recurring tasks in the task list UI.

### Visual Specification (per UI-DESIGN-SYSTEM.md)

```twig
{# RECURRING TASK INDICATOR: #}
{# - Icon: arrow-path (Heroicons), w-4 h-4 text-gray-400 #}
{# - Position: in metadata row, after priority stars #}
{# - Hover: text-gray-600 transition-colors #}
{# - Tooltip: shows recurrence_rule text (e.g., "every Monday at 2pm") #}
{#   Use title attribute or Alpine.js tooltip component #}
{# - When editing: show editable recurrence field with placeholder examples #}

{# Example implementation in task item: #}
<div class="task-metadata flex items-center gap-2 text-sm text-gray-500">
    {# Priority stars #}
    {% if task.priority > 0 %}
        <span class="priority-stars">{{ '★'|repeat(task.priority) }}</span>
    {% endif %}

    {# Recurring indicator #}
    {% if task.isRecurring %}
        <span class="inline-flex items-center" title="{{ task.recurrenceRule }}">
            <svg class="w-4 h-4 text-gray-400 hover:text-gray-600 transition-colors"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {# arrow-path icon #}
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
        </span>
    {% endif %}

    {# Due date #}
    {% if task.dueDate %}
        <span class="{% if task.isOverdue %}text-red-600{% endif %}">
            {{ task.dueDate|date('M j') }}
        </span>
    {% endif %}
</div>

{# Edit mode recurrence field: #}
<div x-show="editing" class="mt-2">
    <label class="text-xs font-medium text-gray-500 mb-1 block">Recurrence</label>
    <input type="text"
           x-model="recurrenceRule"
           placeholder="e.g., every Monday, every 2 weeks, every month on the 15th"
           class="w-full rounded-md border-gray-300 shadow-sm text-sm
                  focus:border-indigo-500 focus:ring-indigo-500
                  placeholder:text-gray-400">
    <p class="text-xs text-gray-400 mt-1">
        Examples: "every day", "every Monday at 2pm", "every! 3 days" (relative to completion)
    </p>
</div>
```

---

## Sub-Phase 5.8: Recurring Task Tests

### Objective
Comprehensive test coverage for recurring task functionality.

### Tasks

- [ ] **5.8.1** Parser tests
  ```php
  // tests/Unit/Service/Recurrence/RecurrenceRuleParserTest.php
  
  Daily patterns:
  - testParseEveryDay()
  - testParseEveryXDays()
  - testParseDaily()
  
  Weekly patterns:
  - testParseEveryWeek()
  - testParseEveryMonday()
  - testParseMultipleDays()
  - testParseMultipleDaysWithTime()  // "every Mon, Wed at 2pm"
  - testParseWeekday()
  - testParseWeekend()
  - testParseBiweekly()
  
  Monthly patterns:
  - testParseEveryMonth()
  - testParseMonthlyOnDay()
  - testParseLastDayOfMonth()
  - testParseQuarterly()
  
  Yearly patterns:
  - testParseEveryYear()
  - testParseYearly()
  - testParseAnnually()
  - testParseSpecificMonthAndDay()  // "every January 15"
  - testParseLeapYearDate()  // "every February 29"
  
  Time patterns:
  - testParseWithTime12Hour()  // "at 2pm"
  - testParseWithTime24Hour()  // "at 14:00"
  - testParseWithNoon()
  - testParseWithMidnight()
  
  End date:
  - testParseWithEndDate()
  
  Type detection:
  - testParseRelative()  // "every!"
  - testParseAbsoluteDefault()
  
  Leniency:
  - testParseCaseInsensitive()
  - testParseWithAbbreviations()
  - testParseWithExtraSpaces()
  
  Invalid patterns:
  - testInvalidHourlyPatternThrows()
  - testInvalidOrdinalPatternThrows()  // "every third Tuesday"
  - testInvalidDateThrows()  // "every February 30"
  - testInvalidDayOfMonthThrows()  // "every 32nd"
  - testUnparseablePatternThrows()
  ```

- [ ] **5.8.2** Next date calculator tests
  ```php
  // tests/Unit/Service/Recurrence/NextDateCalculatorTest.php
  
  Absolute calculations:
  - testAbsoluteWeeklyNextDate()
  - testAbsoluteMonthlyNextDate()
  - testAbsoluteYearlyNextDate()
  - testAbsoluteMultipleDaysNextDate()
  
  Relative calculations:
  - testRelativeWeeklyNextDate()
  - testRelativeDailyNextDate()
  - testRelativeYearlyNextDate()
  
  Edge cases:
  - testDayOfMonthExceedsMonth()  // 31st in April
  - testLeapYearFebruary29()
  - testNonLeapYearFebruary29()
  - testLastDayOfMonth()  // dayOfMonth: -1
  - testWithTimezone()
  - testDSTTransitionSpringForward()
  - testDSTTransitionFallBack()
  - testEndDateRespected()
  - testEndDateExceeded()
  ```

- [ ] **5.8.3** Completion flow tests
  ```php
  // tests/Functional/Api/RecurringTaskApiTest.php
  
  - testCompleteRecurringTaskCreatesNewInstance()
  - testNewInstanceHasCorrectDueDate()
  - testOriginalTaskIdChain()
  - testTagsCopiedToNewInstance()
  - testCompleteForeverNoNewInstance()
  - testAbsoluteRecurrenceFromOriginalDate()
  - testRelativeRecurrenceFromCompletionDate()
  - testUndoRecurringCompletion()
  - testEndDateReachedNoNewInstance()
  ```

- [ ] **5.8.4** Chain query tests
  ```php
  - testQueryByOriginalTaskId()
  - testRecurringHistoryEndpoint()
  - testChainInfoInResponse()
  ```

### Completion Criteria
- [ ] Parser edge cases tested (including yearly, time formats)
- [ ] Calculator accuracy verified (including day-of-month edge cases)
- [ ] Completion flow tested end-to-end
- [ ] Chain tracking tested
- [ ] Undo functionality tested
- [ ] Invalid pattern handling tested

### Files to Create
```
tests/Unit/Service/Recurrence/
├── RecurrenceRuleParserTest.php
└── NextDateCalculatorTest.php

tests/Functional/Api/
└── RecurringTaskApiTest.php
```

---

## Phase 5 Deliverables Checklist

At the end of Phase 5, the following should be complete:

### Database & Entity (Pre-requisites)
- [ ] Task entity has recurrence properties re-added
- [ ] Migration runs successfully
- [ ] RecurrenceType enum created
- [ ] Index on owner_id + is_recurring exists
- [ ] `Task.restoreFromState()` handles recurrence fields

### Parser
- [ ] Recurrence rule parser handles all patterns (daily, weekly, monthly, yearly)
- [ ] Parser supports both 12-hour and 24-hour time formats
- [ ] Parser is lenient with case, abbreviations, whitespace
- [ ] Multiple days with time supported ("every Mon, Wed at 2pm")
- [ ] Original text always preserved in storage
- [ ] InvalidRecurrenceException re-created with exception mapper

### Recurrence Types
- [ ] Absolute recurrence calculates from schedule
- [ ] Relative recurrence calculates from completion

### Date Calculation
- [ ] Day-of-month edge cases handled (31st in short months)
- [ ] Leap year handling correct
- [ ] Last day of month (-1) works correctly
- [ ] Timezone handling correct
- [ ] DST transitions handled
- [ ] End date respected

### Service Layer Integration
- [ ] TaskStateService serializes recurrence fields
- [ ] TaskService has RecurrenceRuleParser and NextDateCalculator dependencies
- [ ] Undo token creation follows TaskUndoService pattern
- [ ] OwnershipChecker used on all mutations

### DTOs Updated
- [ ] CreateTaskRequest has recurrence fields
- [ ] UpdateTaskRequest has recurrence fields
- [ ] TaskResponse includes recurrence fields
- [ ] TaskStatusResult DTO created

### Task Completion
- [ ] Completing recurring task creates new instance
- [ ] original_task_id chain maintained correctly
- [ ] Complete-forever endpoint works
- [ ] End date support implemented
- [ ] Time component preserved
- [ ] Tags copied to new instances
- [ ] Undo works for recurring completion

### Error Handling
- [ ] Invalid patterns rejected with helpful messages
- [ ] Unsupported patterns (hourly, ordinal) have clear errors
- [ ] Invalid dates (Feb 30) caught
- [ ] InvalidRecurrenceExceptionMapper returns proper API error format

### API & Queries
- [ ] Status change returns nextTask for recurring tasks
- [ ] complete-forever endpoint works
- [ ] recurring-history endpoint returns chain with ownership validation
- [ ] Filter by is_recurring works
- [ ] Filter by original_task_id works
- [ ] All endpoints validate ownership

### UI (per UI-PHASE-MODIFICATIONS.md)
- [ ] Recurring indicator displays with tooltip
- [ ] Edit form includes recurrence field
- [ ] Complete forever option in menu

### Testing
- [ ] Parser unit tests comprehensive (30+ tests)
- [ ] Calculator unit tests complete (15+ tests)
- [ ] Integration tests passing (15+ tests)
- [ ] Exception mapper tested
- [ ] All tests passing

---

## Architecture Alignment Summary

When implementing Phase 5, ensure alignment with these patterns established in Phases 1-3:

| Aspect | Pattern to Follow | Example Location |
|--------|-------------------|------------------|
| Service delegation | TaskService → TaskStateService, TaskUndoService | `src/Service/TaskService.php` |
| State serialization | `serializeTaskState()` returns array | `src/Service/TaskStateService.php` |
| State restoration | `Task.restoreFromState()` | `src/Entity/Task.php` |
| Undo token creation | `createUndoToken(UndoAction, entity, state)` | `src/Service/TaskUndoService.php` |
| Exception mapping | `#[AutoconfigureTag('app.exception_mapper')]` | `src/EventListener/ExceptionMapper/Domain/` |
| DTO validation | Symfony constraints in constructor | `src/DTO/CreateTaskRequest.php` |
| DTO serialization | `toArray()` and `fromEntity()` | `src/DTO/TaskResponse.php` |
| Ownership validation | `OwnershipChecker->checkOwnership()` | All service methods |
| API responses | `ResponseFormatter->success()` | All controller methods |

---

## Document History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | Initial | Original plan |
| 2.0 | 2026-01-24 | Updated for Phase 1-3 architecture changes: service refactoring, removed recurrence fields, exception mapper registry, DTO patterns, ownership validation |
