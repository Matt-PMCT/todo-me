# Phase 2: Natural Language Parsing

## Overview
**Duration**: Week 2-3  
**Goal**: Implement intelligent natural language parsing for task entry, including date detection, project hashtag matching, tag recognition, and priority parsing with real-time visual feedback.

## Prerequisites
- Phase 1 completed
- All database tables functional
- Basic task CRUD working

---

## Sub-Phase 2.1: Date Parsing Library Integration

### Objective
Create a robust date parsing service that handles natural language dates.

### Tasks

- [ ] **2.1.1** Install and configure Carbon for date manipulation
  ```bash
  composer require nesbot/carbon
  ```

- [ ] **2.1.2** Create DateParserService
  ```php
  // src/Service/Parser/DateParserService.php
  
  Methods:
  - parse(string $input, ?string $timezone = 'UTC', ?string $dateFormat = 'MDY'): ?DateParseResult
  - setUserTimezone(string $timezone): void
  - setStartOfWeek(int $day): void  // 0=Sunday, 1=Monday
  - setDateFormat(string $format): void  // 'MDY', 'DMY', 'YMD'
  
  DateParseResult:
  - date: DateTimeImmutable
  - originalText: string
  - startPosition: int
  - endPosition: int
  - hasTime: bool
  ```

- [ ] **2.1.3** Implement relative date parsing
  ```php
  Supported patterns:
  - "today" → current date in user timezone
  - "tomorrow" → current date + 1 day
  - "yesterday" → current date - 1 day
  - "next week" → Monday of next week
  - "next month" → 1st of next month
  - "in X days" → current + X days (X = 1-365)
  - "in X weeks" → current + X weeks
  - "in X months" → current + X months
  ```

- [ ] **2.1.4** Implement day name parsing
  ```php
  Supported patterns:
  - "Monday", "Mon" → next occurrence of Monday
  - "Tuesday", "Tue", etc.
  - "next Monday" → Monday of next week
  - "this Monday" → Monday of current week (or next if passed)
  ```

- [ ] **2.1.5** Implement absolute date parsing with format awareness
  ```php
  Supported formats (based on user date_format preference):
  
  MDY format (US default):
  - "Jan 23", "January 23"
  - "1/23", "01/23"
  - "1/23/26", "01/23/2026"
  
  DMY format (EU):
  - "23 Jan", "23 January"
  - "23/1", "23/01"
  - "23/1/26", "23/01/2026"
  
  YMD format (ISO):
  - "2026-01-23" (always unambiguous)
  
  AMBIGUOUS FORMAT HANDLING:
  When a date like "1/2/25" or "3/4" is encountered:
  - Use user's date_format setting from User::getDateFormat()
  - MDY setting: "1/2/25" → January 2, 2025
  - DMY setting: "1/2/25" → February 1, 2025
  - If no user setting available, default to MDY (US format)
  
  UNAMBIGUOUS FORMATS (always parsed regardless of setting):
  - Month names: "Jan 23", "23 January" → unambiguous
  - ISO format: "2026-01-23" → unambiguous
  - Day > 12: "15/1" → always January 15 (day cannot be 15 in month position)
  ```

- [ ] **2.1.6** Implement time parsing
  ```php
  Supported patterns:
  - "at 2pm", "at 2:30pm"
  - "at 14:00", "at 14:30"
  - "2pm", "2:30pm"
  - Combined: "tomorrow at 2pm"
  ```

- [ ] **2.1.7** Define default time behavior
  ```php
  DEFAULT TIME FOR DATES WITHOUT TIME:
  
  When a date is parsed without an explicit time component:
  - Default to start of day: 00:00:00 in user's timezone
  - Store due_time as NULL (not 00:00:00)
  - due_date contains the date, due_time is separate field
  
  Examples:
  - "tomorrow" → due_date: 2026-01-24, due_time: NULL
  - "tomorrow at 2pm" → due_date: 2026-01-24, due_time: 14:00:00
  - "Jan 30" → due_date: 2026-01-30, due_time: NULL
  
  UI BEHAVIOR:
  - Tasks with due_time: NULL show only the date
  - Tasks with due_time: set show date + time
  - Sorting: tasks without time sort to start of day
  ```

- [ ] **2.1.8** Create timezone handling utility
  ```php
  // src/Service/Parser/TimezoneHelper.php
  
  Methods:
  - resolveToUtc(DateTimeImmutable $date, string $userTimezone): DateTimeImmutable
  - resolveFromUtc(DateTimeImmutable $date, string $userTimezone): DateTimeImmutable
  - getStartOfDay(string $timezone): DateTimeImmutable
  ```

### Completion Criteria
- [ ] All relative date patterns recognized
- [ ] All absolute date formats parsed correctly
- [ ] Ambiguous dates resolved using user's date_format setting
- [ ] Time parsing works with and without dates
- [ ] Dates without times default to NULL due_time
- [ ] Timezone conversion accurate (including DST)
- [ ] 50+ unit tests for date parsing

### Files to Create
```
src/Service/Parser/
├── DateParserService.php
├── TimezoneHelper.php
└── DateParseResult.php

tests/Unit/Parser/
└── DateParserServiceTest.php
```

---

## Sub-Phase 2.2: Project Hashtag Detection

### Objective
Implement project name detection using # hashtag syntax with autocomplete support.

### Tasks

- [ ] **2.2.1** Create ProjectParserService
  ```php
  // src/Service/Parser/ProjectParserService.php
  
  Methods:
  - parse(string $input, int $userId): ?ProjectParseResult
  - getAutocomplete(string $partial, int $userId): array
  
  ProjectParseResult:
  - project: Project
  - originalText: string (including #)
  - startPosition: int
  - endPosition: int
  - matchedName: string
  ```

- [ ] **2.2.2** Implement hashtag detection regex
  ```php
  Pattern: /#([a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*)/
  
  Examples:
  - "#work" → matches "work"
  - "#Work" → matches "Work" (case-insensitive lookup)
  - "#Parent/Child" → matches nested project
  - "#my-project" → matches with hyphens
  - "#project_name" → matches with underscores
  ```

- [ ] **2.2.3** Implement case-insensitive project matching
  ```php
  // ProjectRepository
  - findByNameInsensitive(string $name, int $userId): ?Project
  
  Behavior:
  - "#work" matches project named "Work"
  - "#WORK" matches project named "work"
  - Exact match preferred over partial
  ```

- [ ] **2.2.4** Implement nested project parsing
  ```php
  Syntax: #Parent/Child
  
  Behavior:
  - Find project named "Parent"
  - Find child project named "Child" under Parent
  - Both must exist and belong to user
  - Return the Child project for task assignment
  
  Error handling:
  - If Parent doesn't exist → no match
  - If Child doesn't exist under Parent → no match
  ```

- [ ] **2.2.5** Create autocomplete API endpoint
  ```php
  // src/Controller/Api/AutocompleteController.php
  
  GET /api/v1/autocomplete/projects?q={query}
  
  Response:
  {
    "data": [
      {
        "id": 5,
        "name": "Work",
        "fullPath": "Work",
        "color": "#e74c3c",
        "parent": null
      },
      {
        "id": 10,
        "name": "Meetings",
        "fullPath": "Work/Meetings",
        "color": "#3498db",
        "parent": { "id": 5, "name": "Work" }
      }
    ]
  }
  ```

### Completion Criteria
- [ ] Hashtag projects detected in text
- [ ] Case-insensitive matching works
- [ ] Nested project syntax works
- [ ] Autocomplete returns matching projects
- [ ] Non-existent projects cause parsing failure (not auto-create)

### Files to Create
```
src/Service/Parser/
├── ProjectParserService.php
└── ProjectParseResult.php

src/Controller/Api/
└── AutocompleteController.php

tests/Unit/Parser/
└── ProjectParserServiceTest.php
```

---

## Sub-Phase 2.3: Tag Detection and Auto-Creation

### Objective
Implement tag detection using @ symbol with automatic tag creation.

### Tasks

- [ ] **2.3.1** Create TagParserService
  ```php
  // src/Service/Parser/TagParserService.php
  
  Methods:
  - parse(string $input, int $userId): array<TagParseResult>
  - getAutocomplete(string $partial, int $userId): array
  
  TagParseResult:
  - tag: Tag
  - originalText: string (including @)
  - startPosition: int
  - endPosition: int
  - wasCreated: bool
  ```

- [ ] **2.3.2** Implement tag detection regex
  ```php
  Pattern: /@([a-zA-Z0-9_-]+)/
  
  Examples:
  - "@urgent" → tag "urgent"
  - "@work" → tag "work"
  - "@high-priority" → tag "high-priority"
  ```

- [ ] **2.3.3** Implement tag auto-creation
  ```php
  Behavior:
  - Check if tag exists (case-insensitive)
  - If exists → return existing tag
  - If not exists → create new tag with default color
  - Store tag names lowercase for consistency
  
  // TagService
  - findOrCreate(string $name, int $userId): Tag
  ```

- [ ] **2.3.4** Implement multiple tag support
  ```php
  Input: "Task @urgent @work @personal"
  
  Result:
  - Array of 3 TagParseResult objects
  - Each with position information
  - All unique (duplicates ignored)
  ```

- [ ] **2.3.5** Create tag autocomplete endpoint
  ```php
  GET /api/v1/autocomplete/tags?q={query}
  
  Response:
  {
    "data": [
      { "id": 1, "name": "urgent", "color": "#f39c12" },
      { "id": 2, "name": "work", "color": "#3498db" }
    ]
  }
  ```

### Completion Criteria
- [ ] @ tags detected in text
- [ ] Multiple tags per input supported
- [ ] Tags auto-created when new
- [ ] Case-insensitive matching
- [ ] Autocomplete functional

### Files to Create
```
src/Service/Parser/
├── TagParserService.php
└── TagParseResult.php

src/Service/
└── TagService.php

tests/Unit/Parser/
└── TagParserServiceTest.php
```

---

## Sub-Phase 2.4: Priority Parsing

### Objective
Implement priority detection using p0-p4 syntax.

### Tasks

- [ ] **2.4.1** Create PriorityParserService
  ```php
  // src/Service/Parser/PriorityParserService.php
  
  Methods:
  - parse(string $input): ?PriorityParseResult
  
  PriorityParseResult:
  - priority: int (0-4)
  - originalText: string
  - startPosition: int
  - endPosition: int
  ```

- [ ] **2.4.2** Implement priority detection
  ```php
  Pattern: /\bp([0-4])\b/i
  
  Mapping:
  - p0 → 0 (None)
  - p1 → 1 (Low)
  - p2 → 2 (Medium)
  - p3 → 3 (High)
  - p4 → 4 (Urgent)
  
  Case-insensitive: p3, P3, p3 all valid
  ```

- [ ] **2.4.3** Handle invalid priority gracefully
  ```php
  Behavior:
  - "p5" → ignored (warning logged)
  - "p10" → ignored
  - "priority" → not matched (requires p prefix + number)
  - Only first valid priority used if multiple present
  ```

### Completion Criteria
- [ ] p0-p4 patterns recognized
- [ ] Invalid priorities ignored with warning
- [ ] Position tracking accurate
- [ ] Only one priority per input

### Files to Create
```
src/Service/Parser/
├── PriorityParserService.php
└── PriorityParseResult.php

tests/Unit/Parser/
└── PriorityParserServiceTest.php
```

---

## Sub-Phase 2.5: Combined Natural Language Parser

### Objective
Create a unified parser that combines all parsing services with clear multi-match and error-handling semantics.

### Tasks

- [ ] **2.5.1** Create NaturalLanguageParserService
  ```php
  // src/Service/Parser/NaturalLanguageParserService.php
  
  Methods:
  - parse(string $input, int $userId): TaskParseResult
  
  TaskParseResult:
  - title: string (cleaned, metadata removed)
  - date: ?DateTimeImmutable
  - time: ?string
  - project: ?Project
  - tags: array<Tag>
  - priority: ?int
  - highlights: array<ParseHighlight>
  - warnings: array<string>
  ```

- [ ] **2.5.2** Define multi-match behavior (FIRST WINS)
  ```php
  MULTI-MATCH RULES:
  
  When multiple instances of the same type appear in input:
  
  DATES - First valid date wins:
  - "Meet tomorrow then next Monday" → uses "tomorrow"
  - "Call Jan 15 about Jan 20 meeting" → uses "Jan 15"
  - Subsequent dates remain in title text
  - Warning added: "Multiple dates found, using first: 'tomorrow'"
  
  PROJECTS - First valid project wins:
  - "Task #work about #personal stuff" → assigns to #work
  - Only first hashtag is removed from title
  - Second hashtag remains as literal text in title
  - Warning added: "Multiple projects found, using first: '#work'"
  
  PRIORITIES - First valid priority wins:
  - "Task p3 p4 urgent" → priority = 3
  - Second priority remains in title
  - Warning added: "Multiple priorities found, using first: 'p3'"
  
  TAGS - All valid tags are used:
  - "Task @urgent @work @personal" → all 3 tags attached
  - Tags are the ONLY type that supports multiple values
  - Duplicate tags are ignored (no warning)
  
  RATIONALE:
  - Users typically put the most important metadata first
  - Consistent behavior is easier to understand
  - Warnings help users correct unintentional duplicates
  ```

- [ ] **2.5.3** Define error-handling strategy (INDEPENDENT PARSING)
  ```php
  ERROR HANDLING STRATEGY:
  
  Each parser component operates INDEPENDENTLY:
  - A failed component does NOT block other components
  - Each component reports its own warnings
  - Task creation proceeds with successfully parsed values
  
  FAILURE SCENARIOS:
  
  Project not found:
  - "#nonexistent" → project = null, hashtag stays in title
  - Warning: "Project 'nonexistent' not found"
  - Other parsing continues normally
  
  Invalid date format:
  - "Meet on 35/13/2026" → date = null, text stays in title
  - Warning: "Could not parse date from '35/13/2026'"
  - Other parsing continues normally
  
  Invalid priority:
  - "Task p7" → priority = null, "p7" stays in title
  - Warning: "Invalid priority 'p7' (must be p0-p4)"
  - Other parsing continues normally
  
  COMBINED EXAMPLE:
  Input: "Review #nonexistent @urgent tomorrow p7"
  Result:
  - title: "Review #nonexistent p7"
  - project: null (not found)
  - tags: [urgent] (created/found)
  - date: 2026-01-24 (tomorrow)
  - priority: null (p7 invalid)
  - warnings: [
      "Project 'nonexistent' not found",
      "Invalid priority 'p7' (must be p0-p4)"
    ]
  
  CRITICAL: The task is still created with partial data.
  Only a completely empty title after parsing is an error.
  ```

- [ ] **2.5.4** Implement title extraction
  ```php
  Process:
  1. Parse all metadata (date, project, tags, priority)
  2. Remove ONLY successfully matched metadata from input
  3. Failed matches remain in the title
  4. Trim whitespace and collapse multiple spaces
  5. Result is the clean title
  
  Example:
  Input: "Review proposal #work @urgent tomorrow p3"
  Title: "Review proposal"
  
  Example with partial failure:
  Input: "Review proposal #fake @urgent tomorrow p3"
  Title: "Review proposal #fake" (project not found, stays in title)
  ```

- [ ] **2.5.5** Implement highlight generation
  ```php
  ParseHighlight:
  - type: 'date' | 'project' | 'tag' | 'priority'
  - text: string
  - startPosition: int
  - endPosition: int
  - value: mixed (parsed value)
  - valid: bool (false if parse failed)
  
  Used by frontend for visual highlighting
  - Valid highlights: colored appropriately
  - Invalid highlights: shown with error styling (red underline)
  ```

- [ ] **2.5.6** Handle edge cases
  ```php
  Edge cases:
  - "# not a project" → # followed by space is not project
  - "@ not a tag" → @ followed by space is not tag
  - "Meeting #work" → project at end of input
  - "Review #work/meetings doc" → nested project mid-input
  - Empty result after parsing → error (title required)
  - Whitespace-only title → error (title required)
  ```

- [ ] **2.5.7** Create API endpoint for parsing
  ```php
  POST /api/v1/parse
  {
    "input": "Review proposal #work @urgent tomorrow p3"
  }
  
  Response:
  {
    "data": {
      "title": "Review proposal",
      "due_date": "2026-01-24",
      "due_time": null,
      "project": { "id": 5, "name": "work" },
      "tags": [{ "id": 10, "name": "urgent" }],
      "priority": 3,
      "highlights": [
        { "type": "project", "text": "#work", "start": 16, "end": 21, "valid": true },
        { "type": "tag", "text": "@urgent", "start": 22, "end": 29, "valid": true },
        { "type": "date", "text": "tomorrow", "start": 30, "end": 38, "valid": true },
        { "type": "priority", "text": "p3", "start": 39, "end": 41, "valid": true }
      ],
      "warnings": []
    }
  }
  
  Response with warnings:
  {
    "data": {
      "title": "Review proposal #fake",
      "due_date": "2026-01-24",
      "due_time": null,
      "project": null,
      "tags": [{ "id": 10, "name": "urgent" }],
      "priority": null,
      "highlights": [
        { "type": "project", "text": "#fake", "start": 16, "end": 21, "valid": false },
        { "type": "tag", "text": "@urgent", "start": 22, "end": 29, "valid": true },
        { "type": "date", "text": "tomorrow", "start": 30, "end": 38, "valid": true },
        { "type": "priority", "text": "p7", "start": 39, "end": 41, "valid": false }
      ],
      "warnings": [
        "Project 'fake' not found",
        "Invalid priority 'p7' (must be p0-p4)"
      ]
    }
  }
  ```

### Completion Criteria
- [ ] All individual parsers integrated
- [ ] Title correctly extracted
- [ ] First-wins behavior for dates, projects, priorities
- [ ] All tags collected (multiple allowed)
- [ ] Failed components don't block others
- [ ] Warnings returned for all parsing issues
- [ ] Highlights accurate for UI (including invalid markers)
- [ ] Edge cases handled gracefully
- [ ] API endpoint functional

### Files to Create
```
src/Service/Parser/
├── NaturalLanguageParserService.php
├── TaskParseResult.php
└── ParseHighlight.php

src/Controller/Api/
└── ParseController.php

tests/Unit/Parser/
└── NaturalLanguageParserServiceTest.php
```

---

## Sub-Phase 2.6: Task API with Natural Language Support

### Objective
Update task creation API to support natural language input.

### Tasks

- [ ] **2.6.1** Update TaskController for natural language
  ```php
  POST /api/v1/tasks?parse_natural_language=true
  
  Request:
  {
    "input_text": "Review proposal #work @urgent tomorrow p3"
  }
  
  Behavior:
  - Parse input_text through NaturalLanguageParserService
  - Create task with parsed values
  - Return created task with parsed highlights and warnings
  ```

- [ ] **2.6.2** Support both structured and natural language
  ```php
  // Structured input (recommended for AI agents)
  POST /api/v1/tasks
  {
    "title": "Review proposal",
    "project_id": 5,
    "tags": ["urgent"],
    "due_date": "2026-01-24",
    "priority": 3
  }
  
  // Natural language input
  POST /api/v1/tasks?parse_natural_language=true
  {
    "input_text": "Review proposal #work @urgent tomorrow p3"
  }
  ```

- [ ] **2.6.3** Update TaskService to handle both modes
  ```php
  // src/Service/TaskService.php
  
  Methods:
  - createFromStructured(User $user, TaskCreateDTO $dto): Task
  - createFromNaturalLanguage(User $user, string $input): TaskCreationResult
  
  TaskCreationResult:
  - task: Task
  - parseResult: TaskParseResult
  - warnings: array<string>
  ```

- [ ] **2.6.4** Implement reschedule with natural language
  ```php
  PATCH /api/v1/tasks/{id}/reschedule
  {
    "due_date": "next Monday"  // Natural language supported
  }
  
  or
  
  {
    "due_date": "2026-01-27"   // ISO format also supported
  }
  ```

### Completion Criteria
- [ ] Both input modes work
- [ ] Natural language creates correct task
- [ ] Warnings returned for parsing issues
- [ ] Reschedule accepts natural language dates

### Files Updated/Created
```
src/Controller/Api/TaskController.php (updated)
src/Service/TaskService.php (updated)
src/DTO/TaskCreationResult.php (new)

tests/Functional/Api/TaskApiTest.php (updated)
tests/Functional/Api/NaturalLanguageTaskTest.php (new)
```

---

## Sub-Phase 2.7: Frontend Real-Time Parsing

### Objective
Implement real-time parsing feedback in the Quick Add UI.

### Tasks

- [ ] **2.7.1** Create Quick Add component JavaScript
  ```javascript
  // assets/js/quick-add.js
  
  Features:
  - Listen to input events
  - Debounce parsing requests (150ms)
  - Call /api/v1/parse endpoint
  - Display highlights inline
  ```

- [ ] **2.7.2** Implement inline highlighting
  ```javascript
  // Visual feedback for parsed elements
  // Reference: docs/UI-DESIGN-SYSTEM.md Section 6.2

  Highlight types and styling (Tailwind classes):
  - Date:     bg-blue-100 text-blue-700 rounded px-1
  - Project:  bg-{project-color}-100 text-{project-color}-700 rounded-full px-2 py-0.5
  - Tag:      bg-gray-100 text-gray-700 rounded-full px-2 py-0.5
  - Priority: bg-yellow-100 text-yellow-700 rounded px-1
  - Invalid:  border-b-2 border-red-400 (underline style, NOT background)

  Implementation:
  - Use contenteditable div or overlay technique
  - Position highlights based on character offsets
  - Show warnings in tooltip or below input (text-xs text-red-500)
  - Valid highlights get colored appropriately
  - Invalid highlights use red underline to indicate parse failure
  ```

- [ ] **2.7.3** Implement project autocomplete dropdown
  ```javascript
  // When user types #
  - Show autocomplete dropdown
  - Filter as they type
  - Allow selection via click or Enter
  - Insert selected project as chip

  // AUTOCOMPLETE DROPDOWN STYLING (per UI-DESIGN-SYSTEM.md):
  // - Position: absolute, z-10, below input
  // - Container: rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5
  // - Items: px-4 py-2 text-sm text-gray-700 hover:bg-gray-100
  // - Selected/focused: bg-indigo-50 text-indigo-700
  // - Project color indicator: w-3 h-3 rounded-full inline-block mr-2
  // - Hierarchy indent: pl-4 per nesting level
  // - Max height: max-h-60 overflow-y-auto
  // - Alpine.js: x-show with x-transition for smooth open/close
  ```

- [ ] **2.7.4** Implement tag autocomplete dropdown
  ```javascript
  // When user types @
  - Show autocomplete dropdown
  - Filter existing tags
  - Allow new tag creation
  - Insert as chip
  ```

- [ ] **2.7.5** Implement keyboard navigation
  ```javascript
  Shortcuts in Quick Add:
  - Enter: Submit task
  - Escape: Clear input
  - Arrow keys: Navigate autocomplete
  - Tab: Select autocomplete item
  ```

- [ ] **2.7.6** Create Quick Add Twig template
  ```twig
  {# templates/components/quick-add.html.twig #}

  - Input field with placeholder
  - Autocomplete dropdown container
  - Parsed metadata display area
  - Submit button
  - Keyboard shortcut hint

  {# QUICK ADD STYLING (per UI-DESIGN-SYSTEM.md Section 6.2): #}
  {# - Container: bg-white shadow rounded-lg p-4 (card pattern) #}
  {# - Input: text-base (larger than standard for prominence) #}
  {#   w-full border-0 focus:ring-0 placeholder:text-gray-400 #}
  {# - Placeholder text: "What needs to be done?" #}
  {# - Button: Primary button style (bg-indigo-600 hover:bg-indigo-700 #}
  {#   text-white font-semibold py-2 px-4 rounded-md) #}
  {#   inline-flex items-center with plus icon (w-5 h-5 mr-1) #}
  {# - Keyboard hint: text-xs text-gray-400 mt-2 #}
  {#   "Press Enter to add, Esc to clear" #}
  {# - Alpine.js: x-data for form state, @keydown handlers #}
  ```

- [ ] **2.7.7** Implement click-to-remove for parsed elements
  ```javascript
  Behavior:
  - Click on highlighted date → remove date assignment
  - Click on project chip → remove project
  - Click on tag chip → remove tag
  - Click on priority → remove priority
  - Update input text accordingly
  ```

- [ ] **2.7.8** Apply UI Design System standards to Quick Add
  - Reference `docs/UI-DESIGN-SYSTEM.md` for all styling decisions
  - Use established color tokens (Section 2)
  - Follow component specifications (Section 5)
  - Implement proper transitions (Section 9)
  - Verify accessibility compliance (Section 10)
  - Ensure WCAG 2.1 AA contrast ratios
  - Test keyboard navigation for all interactive elements

### Completion Criteria
- [ ] Real-time highlighting as user types
- [ ] Autocomplete for projects shows matching results
- [ ] Autocomplete for tags shows existing + new option
- [ ] Chips can be removed by clicking
- [ ] Task created correctly on submit
- [ ] Keyboard shortcuts working

### Files to Create
```
assets/js/
├── quick-add.js
├── autocomplete.js
└── highlight.js

assets/css/
└── quick-add.css

templates/components/
└── quick-add.html.twig
```

---

## Sub-Phase 2.8: Parsing Test Suite

### Objective
Create comprehensive test coverage for all parsing logic.

### Tasks

- [ ] **2.8.1** Date parser tests (50+ cases)
  ```php
  // tests/Unit/Parser/DateParserServiceTest.php
  
  Test categories:
  - Relative dates (today, tomorrow, next week, in X days)
  - Day names (Mon, Monday, next Monday)
  - Absolute dates (Jan 23, 1/23, 2026-01-23)
  - Times (2pm, 14:00, at 2:30pm)
  - Combined (tomorrow at 2pm)
  - Edge cases (leap year, month boundaries)
  - Invalid inputs (graceful failure)
  - Timezone handling (UTC, America/Los_Angeles, etc.)
  - Default time behavior (no time → NULL due_time)
  - Ambiguous date format handling (MDY vs DMY)
  
  Specific ambiguous format tests:
  - testAmbiguousDateWithMDYSetting() // "1/2/25" → Jan 2
  - testAmbiguousDateWithDMYSetting() // "1/2/25" → Feb 1
  - testUnambiguousDateIgnoresSetting() // "Jan 2" → Jan 2 regardless
  - testDayGreaterThan12() // "15/1" → Jan 15 always
  ```

- [ ] **2.8.2** Project parser tests
  ```php
  // tests/Unit/Parser/ProjectParserServiceTest.php
  
  Test cases:
  - Basic hashtag (#work)
  - Case insensitive (#Work, #WORK)
  - With hyphens (#my-project)
  - With underscores (#my_project)
  - Nested projects (#Parent/Child)
  - Non-existent project (no match, warning returned)
  - Multiple projects in input (first one used, warning returned)
  - At various positions in text
  ```

- [ ] **2.8.3** Tag parser tests
  ```php
  // tests/Unit/Parser/TagParserServiceTest.php
  
  Test cases:
  - Single tag (@urgent)
  - Multiple tags (@urgent @work)
  - New tag creation
  - Existing tag matching
  - Case normalization
  - Invalid tag patterns
  - Duplicate tags (ignored, no warning)
  ```

- [ ] **2.8.4** Priority parser tests
  ```php
  // tests/Unit/Parser/PriorityParserServiceTest.php
  
  Test cases:
  - Each priority level (p0-p4)
  - Case insensitive (P3, p3)
  - Invalid priorities (p5, p10)
  - No priority
  - Multiple priorities (first wins, warning returned)
  ```

- [ ] **2.8.5** Combined parser integration tests
  ```php
  // tests/Unit/Parser/NaturalLanguageParserServiceTest.php
  
  Test cases:
  - Full input with all metadata
  - Partial inputs (only some metadata)
  - Title extraction
  - Position tracking
  - Edge cases from all sub-parsers
  
  Multi-match tests:
  - testMultipleDatesUsesFirst()
  - testMultipleProjectsUsesFirst()
  - testMultiplePrioritiesUsesFirst()
  - testMultipleTagsUsesAll()
  - testMultiMatchWarnings()
  
  Error isolation tests:
  - testInvalidProjectDoesNotBlockDate()
  - testInvalidPriorityDoesNotBlockTags()
  - testMultipleFailuresAllReported()
  - testPartialSuccessCreatesTask()
  ```

### Completion Criteria
- [ ] 50+ date parser tests
- [ ] 20+ project parser tests
- [ ] 15+ tag parser tests
- [ ] 10+ priority parser tests
- [ ] 20+ integration tests
- [ ] Multi-match behavior fully tested
- [ ] Error isolation fully tested
- [ ] Ambiguous date format handling tested
- [ ] 100% coverage on parser services

### Files to Create
```
tests/Unit/Parser/
├── DateParserServiceTest.php
├── ProjectParserServiceTest.php
├── TagParserServiceTest.php
├── PriorityParserServiceTest.php
└── NaturalLanguageParserServiceTest.php

tests/Functional/Api/
└── NaturalLanguageTaskTest.php
```

---

## Phase 2 Deliverables Checklist

At the end of Phase 2, the following should be complete:

### Parsing Services
- [ ] Date parser handles all relative and absolute formats
- [ ] Ambiguous dates resolved using user's date_format setting
- [ ] Dates without times default to NULL due_time
- [ ] Project hashtag detection with nested support
- [ ] Tag detection with auto-creation
- [ ] Priority p0-p4 parsing

### Combined Parser
- [ ] Combined parser extracts all metadata
- [ ] First-wins behavior for dates, projects, priorities
- [ ] All tags collected (multiple allowed)
- [ ] Failed components don't block others (independent parsing)
- [ ] Warnings returned for all parsing issues

### API
- [ ] API supports natural language task creation
- [ ] Both structured and natural language modes work
- [ ] Warnings included in responses

### Frontend
- [ ] Real-time parsing in Quick Add UI
- [ ] Autocomplete for projects and tags
- [ ] Visual highlighting of parsed elements (including invalid markers)

### Testing
- [ ] 100+ parser unit tests passing
- [ ] All edge cases documented and tested
- [ ] Multi-match and error isolation thoroughly tested
