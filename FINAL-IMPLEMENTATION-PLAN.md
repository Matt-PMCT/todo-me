# Final Implementation Plan - Phase 8 & 9 Completion

**Project:** todo-me
**Objective:** Complete all deferred Phase 8 items + Phase 9 documentation to achieve 100% completion
**Current Status:** Phase 8 at 60% completion, Phase 9 at 95% completion (4,474 tests passing)
**Target:** Both phases at 100% completion with production-ready polish features

---

## Executive Summary

This plan addresses all incomplete Phase 8 items identified during comprehensive phase reviews. Work is organized into 7 implementation groups with estimated effort of 10-12 development days total.

**Key Decision:** Performance regression testing will use dedicated benchmarking tools (not PHPUnit) as recommended by PHP testing best practices.

---

## Implementation Groups

### Group A: Critical Fixes (Priority: CRITICAL, Est: 2 hours)

**Objective:** Fix failing and skipped tests to achieve 100% test pass rate

**Tasks:**
1. **Fix SearchApiTest Type Mismatch**
   - File: `tests/Functional/Api/SearchApiTest.php:462`
   - Issue: `searchTimeMs` returns int, expects float
   - Fix: Cast to float in SearchService or SearchResponse
   - Verify: Run `vendor/bin/phpunit tests/Functional/Api/SearchApiTest.php`

2. **Resume Skipped Undo Token Test**
   - File: `tests/Functional/Api/AuthorizationEdgeCasesTest.php`
   - Issue: "Undo token not returned from delete"
   - Investigation: Determine if undo token should be returned on delete
   - Action: Either fix implementation or remove test if not applicable

**Verification:**
```bash
vendor/bin/phpunit tests/Functional/Api/SearchApiTest.php
vendor/bin/phpunit tests/Functional/Api/AuthorizationEdgeCasesTest.php
vendor/bin/phpunit --testdox-text var/test-results.txt
# Expect: 4,474 tests passing, 0 failures, 0 skipped
```

---

### Group B: Keyboard Shortcuts (Priority: HIGH, Est: 2-3 days)

**Objective:** Implement remaining 10 keyboard shortcuts for complete navigation

**Current State:** 6/16 shortcuts implemented in `assets/js/keyboard-shortcuts.js`

**Tasks:**

1. **Task Navigation Shortcuts**
   - `n` - Focus quick add / new task
   - `j` - Navigate to next task (down arrow)
   - `k` - Navigate to previous task (up arrow)
   - Implementation: Add task selection state to Alpine.js store
   - Visual: Highlight selected task with ring-2 ring-indigo-500

2. **Task Action Shortcuts**
   - `c` - Mark selected task complete
   - `e` - Edit selected task (open modal)
   - `Delete`/`Backspace` - Delete selected task (with confirmation)

3. **Quick Date Shortcuts**
   - `t` - Set due date to today
   - `y` - Set due date to tomorrow

4. **Priority Shortcuts**
   - `1` - Set priority to 1 (highest)
   - `2` - Set priority to 2
   - `3` - Set priority to 3
   - `4` - Set priority to 4 (lowest)

**Files to Modify:**
- `assets/js/keyboard-shortcuts.js` - Add new shortcuts
- `templates/components/keyboard-help-modal.html.twig` - Document new shortcuts
- `assets/app.js` - Initialize task selection state
- `templates/task/_task_item.html.twig` - Add selection visual state

**Verification:**
- Manual testing of all 16 shortcuts
- Test modal interactions (escape to close)
- Test navigation with j/k keys
- Test task actions with keyboard only
- Verify accessibility (focus visible, aria labels)

---

### Group C: Subtask UI (Priority: HIGH, Est: 2-3 days)

**Objective:** Complete subtask user interface for full subtask management

**Current State:** Backend 100% complete, UI 30% complete (basic display only)

**Tasks:**

1. **Subtask Count Badges**
   - Location: `templates/task/_task_item.html.twig`
   - Display: Badge showing "3 subtasks" or "2/3 complete"
   - Position: After task title, before due date
   - Styling: `text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full`

2. **Expandable Subtask List**
   - Add expand/collapse button to task cards with subtasks
   - Icon: chevron-down when collapsed, chevron-up when expanded
   - Alpine.js state: `x-data="{ showSubtasks: false }"`
   - Load subtasks on expand via AJAX: `GET /api/v1/tasks/{id}/subtasks`
   - Display: Indented list with checkboxes

3. **Inline Subtask Creation**
   - Add "+ Add subtask" button inside expanded subtask list
   - Inline form with title input
   - Submit: `POST /api/v1/tasks/{id}/subtasks`
   - Auto-refresh subtask list on success

4. **Subtask Progress Indicator**
   - Visual: Progress bar showing completion percentage
   - Position: Below task title when subtasks exist
   - Calculation: completedSubtasks / totalSubtasks * 100
   - Styling: `bg-gray-200 h-1 rounded-full` with `bg-green-500` fill

**Files to Modify:**
- `templates/task/_task_item.html.twig` - Add badges, expand button, progress bar
- `templates/task/_subtask_item.html.twig` - Enhance subtask display
- Create: `templates/task/_subtask_form.html.twig` - Inline creation form
- `assets/js/subtasks.js` - AJAX loading and form handling

**Verification:**
- Test count badge displays correctly
- Test expand/collapse animation
- Test inline subtask creation
- Test progress bar updates on completion
- Test cascade delete (parent deletes all subtasks)

---

### Group D: Mobile Enhancements (Priority: HIGH, Est: 2-3 days)

**Objective:** Add mobile-specific interactions for better UX on touch devices

**Current State:** Mobile responsive (80%), missing gesture support

**Tasks:**

1. **Swipe Gestures**
   - Library: Use Hammer.js or vanilla Touch Events
   - Swipe Right: Mark task complete (with animation)
   - Swipe Left: Show action menu (edit, delete, reschedule)
   - Visual feedback: Transform translateX during swipe
   - Threshold: 50px swipe distance
   - Cancel: Snap back if under threshold

2. **Bottom Navigation Bar**
   - Fixed position bottom bar on mobile (<md screens)
   - Buttons: Home, Today, Projects, Add Task (center), Search
   - Active state: Highlight current route
   - Icons: Heroicons or similar
   - Z-index: Above content, below modals

3. **Pull-to-Refresh (Optional)**
   - Implement on task list page
   - Visual: Spinner or loading animation
   - Action: Reload task list data
   - Library: Use CSS scroll-snap or vanilla implementation

**Files to Create:**
- `assets/js/gestures.js` - Swipe gesture handling
- `templates/partials/mobile-nav.html.twig` - Bottom navigation bar

**Files to Modify:**
- `templates/base.html.twig` - Include mobile nav, gesture scripts
- `templates/task/list.html.twig` - Add pull-to-refresh support
- `assets/app.js` - Initialize gesture handlers

**Verification:**
- Test swipe right to complete
- Test swipe left for menu
- Test bottom navigation on mobile viewport
- Test pull-to-refresh (if implemented)
- Verify no interference with scrolling

---

### Group E: Code Quality & Analysis (Priority: MEDIUM, Est: 1-2 days)

**Objective:** Add automated code quality checks to CI/CD pipeline

**Tasks:**

1. **PHPStan Static Analysis**
   - Install: `composer require --dev phpstan/phpstan`
   - Config: Create `phpstan.neon` with level 6
   - Paths: `src/`, exclude `var/`, `vendor/`
   - Baseline: Generate baseline for existing issues
   - CI: Add to `.github/workflows/ci.yml`

2. **PHP-CS-Fixer Code Style**
   - Install: `composer require --dev friendsofphp/php-cs-fixer`
   - Config: Create `.php-cs-fixer.php` with PSR-12 + Symfony rules
   - Dry run: Check style violations
   - Fix: Auto-format on commit (optional pre-commit hook)
   - CI: Add to workflow as check (not auto-fix)

3. **UI Design System Audit**
   - Manual review: Check all templates against `docs/UI-DESIGN-SYSTEM.md`
   - Color tokens: Verify indigo-600 primary, semantic colors
   - Typography: Verify text-sm default, font-semibold buttons
   - Spacing: Verify 4px base unit (Tailwind scale)
   - Components: Verify all follow design system specs
   - Document violations in `docs/UI-AUDIT-REPORT.md`
   - Fix critical violations

**Files to Create:**
- `phpstan.neon` - PHPStan configuration
- `.php-cs-fixer.php` - Code style configuration
- `docs/UI-AUDIT-REPORT.md` - Audit findings

**Files to Modify:**
- `.github/workflows/ci.yml` - Add PHPStan and PHP-CS-Fixer jobs
- `composer.json` - Add dev dependencies
- Various template files (based on audit findings)

**Verification:**
```bash
vendor/bin/phpstan analyse src/
vendor/bin/php-cs-fixer fix --dry-run --diff
# Review UI-AUDIT-REPORT.md for violations
```

---

### Group F: Performance Monitoring (Priority: MEDIUM, Est: 1-2 days)

**Objective:** Add performance monitoring WITHOUT using PHPUnit

**Rationale:** PHPUnit is designed for unit/functional testing, not performance benchmarking. Use specialized tools instead.

**Approach: Lightweight Performance Monitoring**

**Tasks:**

1. **Database Query Logging**
   - Enable Symfony Profiler in dev: Already enabled
   - Add slow query logging in production
   - Config: `doctrine.yaml` with `logging: true`
   - Threshold: Log queries > 100ms
   - Location: `var/log/slow_queries.log`

2. **API Response Time Tracking**
   - Enhance `ApiLogger` service
   - Log response times for all endpoints
   - Format: `{endpoint} {method} {duration_ms} {status}`
   - Monitor: Identify slow endpoints (>500ms)

3. **Simple Benchmarking Script**
   - Create: `bin/benchmark.php`
   - Tests: Critical operations (search, filter, create task, project tree)
   - Output: Execution times, memory usage
   - Run manually, not in CI
   - Document baseline performance

4. **Optional: Load Testing Setup**
   - Tool: k6 (https://k6.io/) or Apache JMeter
   - Script: `tests/load/basic-load-test.js`
   - Scenarios: Login, create tasks, search, filter
   - Run: Manual execution, not automated
   - Document: Expected vs actual performance

**Files to Create:**
- `bin/benchmark.php` - Benchmarking script
- `tests/load/basic-load-test.js` - k6 load test (optional)
- `docs/PERFORMANCE-BASELINE.md` - Performance documentation

**Files to Modify:**
- `config/packages/doctrine.yaml` - Enable slow query logging
- `src/Service/ApiLogger.php` - Enhance with timing details
- `config/packages/monolog.yaml` - Add slow query log channel

**Verification:**
```bash
php bin/benchmark.php
# Review var/log/slow_queries.log
# Optional: k6 run tests/load/basic-load-test.js
```

---

### Group G: Documentation & Polish (Priority: LOW, Est: 1 day)

**Objective:** Complete remaining documentation gaps (Phase 8 & Phase 9)

**Tasks:**

1. **Complete Phase 9 OpenAPI Attributes (95% → 100%)**
   - Add OpenAPI attributes to 3 controllers:
     - `src/Controller/Api/NotificationController.php`
     - `src/Controller/Api/PushController.php`
     - `src/Controller/Api/TwoFactorController.php`
   - Add `#[OA\Get]`, `#[OA\Post]`, `#[OA\Patch]`, `#[OA\Delete]` as needed
   - Document: Request schemas, response schemas, security requirements
   - Verify: Check `/api/v1/docs` Swagger UI shows all endpoints

2. **Keyboard Shortcuts Reference**
   - Create: `docs/KEYBOARD-SHORTCUTS.md`
   - Document: All 16 shortcuts with descriptions
   - Include: Screenshot of help modal

3. **Subtask Feature Guide**
   - Create: `docs/features/SUBTASKS.md`
   - Document: How to create, manage, complete subtasks
   - Include: API endpoints, UI usage, limitations

4. **Mobile Features Guide**
   - Create: `docs/features/MOBILE.md`
   - Document: Swipe gestures, bottom nav, responsive design
   - Include: Screenshots on mobile viewport

5. **Phase 8 & 9 Completion Document**
   - Update: `docs/PROGRESS.md`
   - Mark Phase 8 as 100% complete
   - Mark Phase 9 as 100% complete (from 95%)
   - Document completion date and final metrics

**Files to Create:**
- `docs/KEYBOARD-SHORTCUTS.md`
- `docs/features/SUBTASKS.md`
- `docs/features/MOBILE.md`

**Files to Modify:**
- `docs/PROGRESS.md`
- `README.md` (add links to new docs)

**Verification:**
- Read all new documentation
- Verify links work
- Check markdown formatting

---

## Implementation Order

Recommended sequence for maximum efficiency:

1. **Day 1: Critical Fixes (Group A)**
   - Fix failing test (30 min)
   - Resume skipped test (1.5 hours)
   - Run full test suite verification

2. **Days 2-3: Keyboard Shortcuts (Group B)**
   - Implement navigation shortcuts (day 1)
   - Implement action shortcuts (day 2)
   - Test and document

3. **Days 4-5: Subtask UI (Group C)**
   - Count badges and progress bars (day 1)
   - Expandable lists and inline forms (day 2)
   - Test and refine

4. **Days 6-7: Mobile Enhancements (Group D)**
   - Swipe gestures (day 1)
   - Bottom navigation (day 2)
   - Test on multiple devices

5. **Days 8-9: Code Quality (Group E)**
   - PHPStan setup and fixes (day 1)
   - PHP-CS-Fixer and UI audit (day 2)

6. **Day 10: Performance Monitoring (Group F)**
   - Query logging and benchmarks
   - Optional: Load test setup

7. **Day 11: Documentation (Group G)**
   - Write feature guides
   - Update progress tracking

**Estimated Total: 10-12 development days**

---

## Critical Files

### Backend (No Changes Expected)
- Subtask system: Complete
- Keyboard shortcuts backend: N/A (frontend only)
- Mobile backend: Complete

### Frontend (Primary Changes)
- `assets/js/keyboard-shortcuts.js` - Expand shortcuts
- `assets/js/gestures.js` - NEW: Swipe handling
- `assets/js/subtasks.js` - NEW: Subtask UI logic
- `templates/task/_task_item.html.twig` - Badges, expand, selection state
- `templates/task/_subtask_item.html.twig` - Enhanced display
- `templates/partials/mobile-nav.html.twig` - NEW: Bottom nav
- `templates/components/keyboard-help-modal.html.twig` - Update shortcuts

### Configuration
- `.github/workflows/ci.yml` - Add PHPStan, PHP-CS-Fixer
- `phpstan.neon` - NEW: Static analysis config
- `.php-cs-fixer.php` - NEW: Code style config
- `config/packages/doctrine.yaml` - Slow query logging

### Documentation
- `docs/KEYBOARD-SHORTCUTS.md` - NEW
- `docs/features/SUBTASKS.md` - NEW
- `docs/features/MOBILE.md` - NEW
- `docs/PERFORMANCE-BASELINE.md` - NEW
- `docs/UI-AUDIT-REPORT.md` - NEW
- `docs/PROGRESS.md` - UPDATE

---

## Testing Strategy

### Unit Tests
- No new unit tests required (backend complete)

### Functional Tests
- Fix 2 existing tests (Group A)
- No new API tests (APIs complete)

### Manual Testing
- Keyboard shortcuts: Full 16-shortcut walkthrough
- Subtask UI: Create, expand, complete, delete flow
- Mobile gestures: Swipe interactions on touch device
- Bottom nav: Navigation on mobile viewport
- UI audit: Visual inspection of all templates

### Integration Testing
- Full test suite after each group
- Regression testing before final sign-off

---

## Success Criteria

Phase 8 & 9 will be 100% complete when:

- ✅ All 4,474+ tests passing (0 failures, 0 skipped)
- ✅ All 16 keyboard shortcuts functional
- ✅ Subtask UI fully interactive (badges, expand, create, progress)
- ✅ Mobile swipe gestures working
- ✅ Bottom navigation bar implemented
- ✅ PHPStan and PHP-CS-Fixer in CI pipeline
- ✅ UI design system audit complete
- ✅ Performance monitoring in place
- ✅ Phase 9: OpenAPI attributes on all 3 controllers (NotificationController, PushController, TwoFactorController)
- ✅ All documentation complete
- ✅ Manual QA sign-off on all features

---

## Risk Mitigation

### Risk: Keyboard shortcuts conflict with browser/OS shortcuts
**Mitigation:** Test on Windows, Mac, Linux; document conflicts; allow disable option

### Risk: Swipe gestures interfere with scrolling
**Mitigation:** Use gesture thresholds; test extensively; provide disable option

### Risk: PHPStan/PHP-CS-Fixer introduce many violations
**Mitigation:** Start with baseline; fix incrementally; set realistic level (6 not 9)

### Risk: Performance monitoring adds overhead
**Mitigation:** Only enable slow query logging in production; benchmarks run manually

---

## Notes

- **Performance Testing Decision:** Not using PHPUnit for performance tests. Using dedicated benchmarking scripts and optional load testing tools instead. This follows PHP testing best practices.

- **Mobile Testing:** Requires physical device or browser dev tools for gesture testing. Recommend testing on iOS Safari, Android Chrome.

- **UI Design Audit:** Manual process. Use `docs/UI-DESIGN-SYSTEM.md` as checklist. Focus on critical violations only.

- **Deployment:** Can deploy after Group A-D completion (high priority items). Groups E-G are quality improvements, not blockers.

---

## End State

After completing this plan, Phase 8 & 9 will be at 100% completion with:
- Zero test failures
- Complete keyboard navigation (16/16 shortcuts)
- Full subtask UI (badges, expand, inline creation, progress)
- Mobile gesture support (swipe, bottom nav)
- Code quality automation (PHPStan, PHP-CS-Fixer)
- Performance monitoring (slow query logging, benchmarks)
- Complete API documentation (100% OpenAPI coverage)
- Complete feature documentation

**The application will be fully polished, 100% documented, and production-ready with all 13 phases complete.**
