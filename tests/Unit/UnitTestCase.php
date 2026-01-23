<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Base class for unit tests providing common helper methods.
 */
abstract class UnitTestCase extends TestCase
{
    /**
     * Creates a mock User entity with a specific ID.
     */
    protected function createUserWithId(string $id = 'user-123', string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPasswordHash('hashed_password');
        $user->setUsername('testuser_' . substr($id, 0, 8));
        $this->setEntityId($user, $id);

        return $user;
    }

    /**
     * Creates a mock Task entity with a specific ID.
     */
    protected function createTaskWithId(
        string $id = 'task-123',
        ?User $owner = null,
        string $title = 'Test Task',
        string $status = Task::STATUS_PENDING,
    ): Task {
        $task = new Task();
        $task->setTitle($title);
        $task->setStatus($status);
        $task->setPriority(Task::PRIORITY_DEFAULT);

        if ($owner !== null) {
            $task->setOwner($owner);
        }

        $this->setEntityId($task, $id);

        return $task;
    }

    /**
     * Creates a mock Project entity with a specific ID.
     */
    protected function createProjectWithId(
        string $id = 'project-123',
        ?User $owner = null,
        string $name = 'Test Project',
    ): Project {
        $project = new Project();
        $project->setName($name);

        if ($owner !== null) {
            $project->setOwner($owner);
        }

        $this->setEntityId($project, $id);

        return $project;
    }

    /**
     * Sets the ID property on an entity using reflection.
     */
    protected function setEntityId(object $entity, string $id): void
    {
        $reflection = new ReflectionProperty($entity, 'id');
        $reflection->setValue($entity, $id);
    }

    /**
     * Creates a mock object for the given class.
     *
     * @template T of object
     * @param class-string<T> $className
     * @return T&MockObject
     */
    protected function createMockFor(string $className): MockObject
    {
        return $this->createMock($className);
    }

    /**
     * Generates a valid UUID v4 for testing.
     */
    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }
}
