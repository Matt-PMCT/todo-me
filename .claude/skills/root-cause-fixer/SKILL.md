---
name: root-cause-fixer
description: Test-driven bug fixing workflow that reproduces issues with failing tests before implementing fixes. Use when fixing bugs, resolving defects, addressing reported issues, or when asked to fix something that is broken. Enforces the principle of "test first, fix second" to prevent regressions.
---

# Root Cause Fixer

A systematic approach to fixing bugs using test-driven development principles. The core philosophy: **never fix a bug without first proving it exists through a failing test**.

## Workflow Overview

```
1. UNDERSTAND   →  Gather information about the bug
2. REPRODUCE    →  Write a failing test that demonstrates the bug
3. ANALYZE      →  Identify root cause and similar patterns
4. FIX          →  Implement the minimal fix
5. VERIFY       →  Run the test to confirm the fix works
6. SCAN         →  Check for similar issues elsewhere
```

## Phase 1: Understand the Bug

Before writing any code, gather complete information:

1. **Get the bug report details**
   - Error messages (exact text)
   - Steps to reproduce
   - Expected vs actual behavior
   - Affected code paths

2. **Locate the relevant code**
   - Use Grep/Glob to find related files
   - Read the code to understand the flow
   - Identify the specific function/method involved

3. **Classify the bug type**
   | Type | Example | Testable? |
   |------|---------|-----------|
   | Logic error | Wrong condition, missing case | Yes |
   | Data issue | Invalid state, missing validation | Yes |
   | Integration | API mismatch, wrong parameters | Yes |
   | Race condition | Timing-dependent failures | Difficult |
   | Environment | Config, permissions, deployment | Difficult |
   | UI/Visual | Display issues, CSS problems | Difficult |

## Phase 2: Write the Failing Test

**This is the most critical phase. Do not skip it.**

### Determine Test Feasibility

Before writing the test, assess if the bug is testable:

**TESTABLE** - Proceed with test:
- Logic errors in services, entities, or repositories
- Validation failures
- Incorrect calculations or data transformations
- Missing or incorrect business rules
- API response errors

**NOT EASILY TESTABLE** - Alert user and request guidance:
- Environment-specific issues (deployment, config)
- Timing/race conditions
- UI rendering problems
- Third-party service failures
- Issues requiring real user interaction

### If Test Cannot Be Written

**STOP and inform the user with this message:**

```
I cannot write an automated test for this issue because:

[Reason: e.g., "This is a UI rendering issue that requires visual inspection"]

Options:
1. Proceed with fix without test (higher regression risk)
2. Write a partial test covering related logic
3. Document manual test steps for verification

Which approach would you prefer?
```

Wait for user response before proceeding.

### Writing the Failing Test

1. **Create test in appropriate location:**
   - Entity tests: `tests/Entity/`
   - Service tests: `tests/Service/`
   - Repository tests: `tests/Repository/`
   - Controller tests: `tests/Controller/`

2. **Name the test descriptively:**
   ```php
   public function testIssue123_TagRemovalBlockedWhenActiveApplications(): void
   ```

3. **Structure the test:**
   ```php
   /**
    * @covers \App\Service\SomeService::methodName
    * Summary: Reproduces Issue #123 - [brief description].
    *
    * Bug: [Describe what was happening wrong]
    * Expected: [What should happen]
    */
   public function testIssue123_DescriptiveName(): void
   {
       // ARRANGE: Set up the conditions that trigger the bug

       // ACT: Perform the action that causes the bug

       // ASSERT: Verify the expected (fixed) behavior
   }
   ```

4. **Run the test - it MUST fail:**
   ```bash
   php bin/phpunit --filter testIssue123
   ```

   If the test passes, either:
   - The bug doesn't exist in the tested code path
   - The test doesn't correctly reproduce the issue
   - Investigate further before proceeding

## Phase 3: Analyze Root Cause

With the failing test in place, analyze the root cause:

### 5 Whys Technique

Ask "why" repeatedly to find the true root cause:

```
Bug: Tag removal is blocked with "active applications" error
Why? → The validation checks canBeClosedOut() for all tag operations
Why? → The code doesn't distinguish between closeout and removal
Why? → The preSelectedTagValue validation was added without field type check
Why? → When the feature was added, only closeout existed
Root Cause: Validation logic needs to check template field type
```

### Look for Patterns

- Are there similar validation checks elsewhere?
- Is this pattern repeated in other files?
- Could other code paths have the same issue?

Note any similar issues found for Phase 6.

## Phase 4: Implement the Fix

Apply the minimal change to fix the issue:

### Fix Principles

1. **Minimal change**: Fix only what's broken, nothing more
2. **No scope creep**: Don't refactor unrelated code
3. **Preserve behavior**: Other code paths should work identically
4. **Add comments**: Explain non-obvious fixes with issue references

### Fix Pattern

```php
// Issue #123: Only apply closeout validation for tagCloseout templates
// For tagRemove, having active applications is expected
if ($tagFieldType === 'tagCloseout' && !$appliedTag->canBeClosedOut()) {
    // Error handling
}
```

## Phase 5: Verify the Fix

1. **Run the specific test:**
   ```bash
   php bin/phpunit --filter testIssue123
   ```

   The test MUST now pass.

2. **Run related tests:**
   ```bash
   php bin/phpunit tests/Service/RelevantServiceTest.php
   ```

3. **Run full test suite:**
   ```bash
   php bin/phpunit > test_output.txt 2>&1
   ```

   Review output for any regressions.

## Phase 6: Scan for Similar Issues

After fixing, actively look for similar problems:

1. **Search for similar patterns:**
   ```bash
   # Find similar validation logic
   grep -r "canBeClosedOut" src/

   # Find similar status checks
   grep -r "status = 'Applied'" src/Repository/
   ```

2. **Check for consistency:**
   - Do other methods handle the same case correctly?
   - Are there related entity methods that need updating?
   - Are translations/messages consistent?

3. **Report findings:**
   If similar issues found, report to user:
   ```
   Found potential similar issues:
   - File X, line Y: Same pattern, may need same fix
   - File Z, line W: Related validation, verify behavior
   ```

## Quick Reference

### Test Location by Bug Type

| Bug Location | Test Location |
|--------------|---------------|
| Entity method | `tests/Entity/` |
| Service logic | `tests/Service/` |
| Repository query | `tests/Repository/` |
| Controller/API | `tests/Controller/` |
| Display/rendering | `tests/Service/Display/` |

### Common Test Commands

```bash
# Run single test
php bin/phpunit --filter testMethodName

# Run test file
php bin/phpunit tests/Path/To/TestFile.php

# Run with coverage (if configured)
php bin/phpunit --coverage-text

# Full suite to file
php bin/phpunit > test_output.txt 2>&1
```

### Issue Reference Pattern

Always reference the issue in:
- Test method docblock
- Code comments at fix location
- Commit message

```php
// Issue #123: Brief explanation of the fix
```

## When to Use This Skill

- User reports a bug or error
- MantisBT issue needs fixing
- Test is failing unexpectedly
- User says something "isn't working" or "is broken"
- Unexpected behavior is discovered

## Related Skills

- Testing patterns: `.claude/skills/testing/SKILL.md`
- MantisBT integration: `.claude/skills/mantisbt/SKILL.md`
- Commit workflow: `.claude/skills/commit-workflow/SKILL.md`
