<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\CreateProjectRequest;

class CreateProjectRequestTest extends DtoTestCase
{
    public function testValidMinimalRequest(): void
    {
        $dto = new CreateProjectRequest(name: 'My Project');

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations, $this->formatViolations($violations));
    }

    public function testValidFullRequest(): void
    {
        $dto = new CreateProjectRequest(
            name: 'My Project',
            description: 'Project description',
        );

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations, $this->formatViolations($violations));
    }

    public function testEmptyNameViolation(): void
    {
        $dto = new CreateProjectRequest(name: '');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'name');
    }

    public function testBlankNameViolation(): void
    {
        $dto = new CreateProjectRequest(name: '   ');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'name');
    }

    public function testNameTooLongViolation(): void
    {
        $dto = new CreateProjectRequest(name: $this->generateString(101));

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'name');
    }

    public function testNameAtMaxLengthIsValid(): void
    {
        $dto = new CreateProjectRequest(name: $this->generateString(100));

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'name');
    }

    public function testDescriptionTooLongViolation(): void
    {
        $dto = new CreateProjectRequest(
            name: 'Valid Name',
            description: $this->generateString(501),
        );

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'description');
    }

    public function testDescriptionAtMaxLengthIsValid(): void
    {
        $dto = new CreateProjectRequest(
            name: 'Valid Name',
            description: $this->generateString(500),
        );

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'description');
    }

    public function testNullDescriptionIsValid(): void
    {
        $dto = new CreateProjectRequest(name: 'Valid Name', description: null);

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testFromArrayWithValidData(): void
    {
        $dto = CreateProjectRequest::fromArray([
            'name' => 'Test Project',
            'description' => 'Project description',
        ]);

        $this->assertSame('Test Project', $dto->name);
        $this->assertSame('Project description', $dto->description);
    }

    public function testFromArrayWithMinimalData(): void
    {
        $dto = CreateProjectRequest::fromArray(['name' => 'Test Project']);

        $this->assertSame('Test Project', $dto->name);
        $this->assertNull($dto->description);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $dto = CreateProjectRequest::fromArray([]);

        $this->assertSame('', $dto->name);
        $this->assertNull($dto->description);
    }

    public function testFromArrayTypeCasting(): void
    {
        $dto = CreateProjectRequest::fromArray([
            'name' => 123,
            'description' => 456,
        ]);

        $this->assertSame('123', $dto->name);
        $this->assertSame('456', $dto->description);
    }
}
