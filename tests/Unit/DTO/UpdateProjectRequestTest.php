<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\UpdateProjectRequest;

class UpdateProjectRequestTest extends DtoTestCase
{
    public function testEmptyRequestIsValid(): void
    {
        $dto = new UpdateProjectRequest();

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations, $this->formatViolations($violations));
    }

    public function testNameOnlyUpdate(): void
    {
        $dto = new UpdateProjectRequest(name: 'Updated Name');

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations);
        $this->assertSame('Updated Name', $dto->name);
        $this->assertNull($dto->description);
    }

    public function testDescriptionOnlyUpdate(): void
    {
        $dto = new UpdateProjectRequest(description: 'Updated Description');

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations);
        $this->assertNull($dto->name);
        $this->assertSame('Updated Description', $dto->description);
    }

    public function testFullUpdate(): void
    {
        $dto = new UpdateProjectRequest(
            name: 'Updated Name',
            description: 'Updated Description',
        );

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations);
        $this->assertSame('Updated Name', $dto->name);
        $this->assertSame('Updated Description', $dto->description);
    }

    public function testNameTooLongViolation(): void
    {
        $dto = new UpdateProjectRequest(name: $this->generateString(101));

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'name');
    }

    public function testNameAtMaxLengthIsValid(): void
    {
        $dto = new UpdateProjectRequest(name: $this->generateString(100));

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'name');
    }

    public function testDescriptionTooLongViolation(): void
    {
        $dto = new UpdateProjectRequest(description: $this->generateString(501));

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'description');
    }

    public function testDescriptionAtMaxLengthIsValid(): void
    {
        $dto = new UpdateProjectRequest(description: $this->generateString(500));

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'description');
    }

    public function testHasChangesReturnsFalseForEmptyRequest(): void
    {
        $dto = new UpdateProjectRequest();

        $this->assertFalse($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForName(): void
    {
        $dto = new UpdateProjectRequest(name: 'New Name');

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForDescription(): void
    {
        $dto = new UpdateProjectRequest(description: 'New Description');

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForBothFields(): void
    {
        $dto = new UpdateProjectRequest(name: 'New Name', description: 'New Description');

        $this->assertTrue($dto->hasChanges());
    }

    public function testFromArrayWithValidData(): void
    {
        $dto = UpdateProjectRequest::fromArray([
            'name' => 'Updated Project',
            'description' => 'Updated description',
        ]);

        $this->assertSame('Updated Project', $dto->name);
        $this->assertSame('Updated description', $dto->description);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $dto = UpdateProjectRequest::fromArray([]);

        $this->assertNull($dto->name);
        $this->assertNull($dto->description);
    }

    public function testFromArrayWithNameOnly(): void
    {
        $dto = UpdateProjectRequest::fromArray(['name' => 'Updated Name']);

        $this->assertSame('Updated Name', $dto->name);
        $this->assertNull($dto->description);
    }

    public function testFromArrayWithDescriptionOnly(): void
    {
        $dto = UpdateProjectRequest::fromArray(['description' => 'Updated Description']);

        $this->assertNull($dto->name);
        $this->assertSame('Updated Description', $dto->description);
    }

    public function testFromArrayWithNullDescription(): void
    {
        // When description key exists but is null, it should be handled
        $dto = UpdateProjectRequest::fromArray([
            'name' => 'Test',
            'description' => null,
        ]);

        $this->assertSame('Test', $dto->name);
        $this->assertNull($dto->description);
    }

    public function testFromArrayTypeCasting(): void
    {
        $dto = UpdateProjectRequest::fromArray([
            'name' => 123,
            'description' => 456,
        ]);

        $this->assertSame('123', $dto->name);
        $this->assertSame('456', $dto->description);
    }
}
