# Test-Driven Bug Fixing Patterns

## Why Test First?

Writing the test before the fix provides critical benefits:

1. **Proves the bug exists**: If your test passes without changes, you haven't reproduced the bug
2. **Documents the expected behavior**: The test serves as executable documentation
3. **Prevents regression**: The test remains in the suite forever
4. **Clarifies understanding**: Writing the test forces you to understand the bug deeply
5. **Validates the fix**: You know exactly when the bug is fixed

## Bug Reproduction Patterns

### Pattern 1: Direct Method Test

For bugs in a specific method:

```php
/**
 * @covers \App\Service\TagService::validateTagOperation
 * Summary: Reproduces Issue #436 - Tag removal blocked incorrectly.
 *
 * Bug: Removal operations were checking canBeClosedOut() which fails
 *      when the tag has active applications.
 * Expected: Removal should allow tags with active applications.
 */
public function testIssue436_TagRemovalAllowsActiveApplications(): void
{
    // ARRANGE: Create tag with active application
    $tag = $this->createTagWithActiveApplication();

    // Verify pre-condition: tag cannot be closed out
    $this->assertFalse($tag->canBeClosedOut());

    // ACT: Attempt removal operation
    $result = $this->service->validateTagOperation($tag, 'remove');

    // ASSERT: Removal should be allowed
    $this->assertTrue($result->isValid());
}
```

### Pattern 2: Integration Test with Database

For bugs involving database queries:

```php
/**
 * @covers \App\Repository\TagApplicationRepository::findActiveForLogEntry
 * Summary: Reproduces Issue #436 - PartiallyRemoved status not included.
 *
 * Bug: Query only looked for 'Applied' status, missing PartiallyRemoved.
 * Expected: Both Applied and PartiallyRemoved should be returned.
 */
public function testIssue436_FindActiveIncludesPartiallyRemoved(): void
{
    // ARRANGE: Create applications with both statuses
    $appliedApp = $this->createTagApplication('Applied');
    $partialApp = $this->createTagApplication('PartiallyRemoved');
    $removedApp = $this->createTagApplication('Removed');

    $this->entityManager->flush();

    // ACT: Query for active applications
    $results = $this->repository->findActiveForLogEntry($logEntry);

    // ASSERT: Should include both Applied and PartiallyRemoved
    $this->assertCount(2, $results);
    $this->assertContains($appliedApp, $results);
    $this->assertContains($partialApp, $results);
    $this->assertNotContains($removedApp, $results);
}
```

### Pattern 3: Controller/API Test

For bugs in HTTP endpoints:

```php
/**
 * @covers \App\Controller\TagController::removeTag
 * Summary: Reproduces Issue #436 - API returns 400 for valid removal.
 *
 * Bug: API endpoint rejected valid tag removal requests.
 * Expected: Return 200 and process the removal.
 */
public function testIssue436_RemoveTagApiAcceptsActiveTag(): void
{
    // ARRANGE: Login and create tag with application
    $client = static::createClient();
    $client->loginUser($this->testUser);
    $tag = $this->createTagWithActiveApplication();

    // ACT: Call the remove endpoint
    $client->request('POST', '/api/tag/remove', [
        'tagId' => $tag->getId(),
    ]);

    // ASSERT: Should succeed
    $this->assertResponseIsSuccessful();
    $response = json_decode($client->getResponse()->getContent(), true);
    $this->assertTrue($response['success']);
}
```

### Pattern 4: Edge Case Test

For bugs triggered by specific conditions:

```php
/**
 * @covers \App\Service\DateTimeService::convertToLocal
 * Summary: Reproduces Issue #XXX - DST transition handled incorrectly.
 *
 * Bug: Times during "fall back" hour were converted incorrectly.
 * Expected: Ambiguous times should use the standard time interpretation.
 */
public function testIssueXXX_DstFallBackAmbiguousTime(): void
{
    // ARRANGE: Use known DST transition time
    // Nov 3, 2024 1:30 AM occurs twice (DST and Standard)
    $utc = new DateTime('2024-11-03 06:30:00', new DateTimeZone('UTC'));

    // ACT: Convert to local
    $local = $this->service->convertToLocal($utc);

    // ASSERT: Should use standard time (second occurrence)
    $this->assertEquals('01:30:00', $local->format('H:i:s'));
    $this->assertEquals('CST', $local->format('T'));
}
```

## Non-Testable Bug Handling

### Categories of Non-Testable Bugs

1. **Environment-specific**
   - Server configuration issues
   - Permission problems
   - Network connectivity
   - External service availability

2. **UI/Visual**
   - CSS rendering issues
   - Layout problems
   - Browser-specific display bugs
   - Animation/transition issues

3. **Timing-dependent**
   - Race conditions
   - Timeout issues
   - Async operation ordering

4. **Hardware-dependent**
   - Device-specific issues
   - Performance on specific hardware

### User Communication Template

When a bug cannot be tested, use this template:

```
## Test Assessment for Issue #XXX

**Bug Type**: [Environment/UI/Timing/Hardware]

**Why Testing is Difficult**:
[Explain the specific reason - be precise]

**Options**:

1. **Proceed without automated test**
   - Risk: Bug could regress without detection
   - Mitigation: Document manual verification steps

2. **Write partial test**
   - Coverage: [What can be tested]
   - Gap: [What cannot be tested]

3. **Defer fix pending more information**
   - Need: [What additional info would help]

**Recommendation**: [Your suggestion with reasoning]

Please confirm how you'd like to proceed.
```

## Root Cause Analysis Techniques

### 5 Whys Example

```
Issue: Users see "Tag cannot be closed out" when trying to remove a tag

Why 1: The validation function returns false
  → Check: SubEntryDisplayService line 120, canBeClosedOut() returns false

Why 2: canBeClosedOut() returns false because tag has active applications
  → Check: AppliedTag::canBeClosedOut() checks for active applications

Why 3: But for REMOVAL, having active applications is expected
  → Insight: The validation doesn't distinguish operation types

Why 4: The validation was added for closeout operations only
  → Check: Git history shows validation added for closeout feature

Why 5: When removal was added, the validation wasn't updated
  → Root Cause: Missing operation-type check in validation logic

Fix: Add check for template field type before applying validation
```

### Pattern Recognition

After finding a bug, search for similar patterns:

```bash
# Find similar validation patterns
grep -rn "canBeClosedOut" src/

# Find similar status checks
grep -rn "status = 'Applied'" src/

# Find similar early-return patterns
grep -rn "return.*getReturnArrayAsJSON" src/
```

Document all similar code locations and verify they handle the case correctly.

## Test Naming Conventions

Use descriptive names that include:
- Issue number (if applicable)
- What is being tested
- Expected outcome

```php
// Good names
testIssue436_TagRemovalAllowsActiveApplications
testIssue436_PartiallyRemovedIncludedInActiveQuery
testValidateTagOperation_RejectsNullTag
testFindActiveForLogEntry_ExcludesArchivedApplications

// Bad names (too vague)
testTagRemoval
testValidation
testQuery
```

## Test Structure Template

```php
<?php

namespace App\Tests\[Category];

use App\Entity\...;
use App\Service\...;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class [ServiceName]BugfixTest extends KernelTestCase
{
    private [ServiceType] $service;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->service = $container->get([ServiceClass]::class);
        $this->entityManager = $container->get('doctrine')->getManager();
    }

    /**
     * @covers \App\[Path]\[Class]::[method]
     * Summary: Reproduces Issue #XXX - [brief description].
     *
     * Bug: [What was happening wrong]
     * Expected: [What should happen]
     */
    public function testIssueXXX_DescriptiveName(): void
    {
        // ARRANGE

        // ACT

        // ASSERT
    }

    // Helper methods for test setup
    private function createTestEntity(): Entity
    {
        // ...
    }
}
```
