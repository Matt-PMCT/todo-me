<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Service\TaskStateService;
use App\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class TaskStateServiceTest extends UnitTestCase
{
    private TaskStateService $service;
    private ProjectRepository&MockObject $projectRepository;
    private TagRepository&MockObject $tagRepository;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->tagRepository = $this->createMock(TagRepository::class);

        $this->service = new TaskStateService(
            $this->projectRepository,
            $this->tagRepository,
        );
    }

    public function testSerializeTaskStateWithMinimalTask(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-123', $user, 'Test Task');

        $state = $this->service->serializeTaskState($task);

        $this->assertSame('task-123', $state['id']);
        $this->assertSame('Test Task', $state['title']);
        $this->assertSame(Task::STATUS_PENDING, $state['status']);
        $this->assertSame(Task::PRIORITY_DEFAULT, $state['priority']);
        $this->assertNull($state['description']);
        $this->assertNull($state['dueDate']);
        $this->assertNull($state['dueTime']);
        $this->assertNull($state['projectId']);
        $this->assertEmpty($state['tagIds']);
    }

    public function testSerializeTaskStateWithFullTask(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user);
        $task = $this->createTaskWithId('task-123', $user, 'Test Task', Task::STATUS_IN_PROGRESS);
        $task->setDescription('Test description');
        $task->setPriority(4);
        $task->setPosition(5);
        $task->setDueDate(new \DateTimeImmutable('2024-12-31'));
        $task->setDueTime(new \DateTimeImmutable('14:30:00'));
        $task->setProject($project);

        // Add tags
        $tag1 = new Tag();
        $this->setEntityId($tag1, 'tag-1');
        $tag1->setName('Tag 1');
        $tag1->setOwner($user);

        $tag2 = new Tag();
        $this->setEntityId($tag2, 'tag-2');
        $tag2->setName('Tag 2');
        $tag2->setOwner($user);

        $task->addTag($tag1);
        $task->addTag($tag2);

        $state = $this->service->serializeTaskState($task);

        $this->assertSame('task-123', $state['id']);
        $this->assertSame('Test Task', $state['title']);
        $this->assertSame('Test description', $state['description']);
        $this->assertSame(Task::STATUS_IN_PROGRESS, $state['status']);
        $this->assertSame(4, $state['priority']);
        $this->assertSame(5, $state['position']);
        $this->assertSame('2024-12-31', $state['dueDate']);
        $this->assertSame('14:30:00', $state['dueTime']);
        $this->assertSame('project-123', $state['projectId']);
        $this->assertContains('tag-1', $state['tagIds']);
        $this->assertContains('tag-2', $state['tagIds']);
    }

    public function testSerializeStatusState(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-123', $user, 'Test Task', Task::STATUS_COMPLETED);
        $task->setCompletedAt(new \DateTimeImmutable('2024-06-15T10:30:00+00:00'));

        $state = $this->service->serializeStatusState($task);

        $this->assertSame(Task::STATUS_COMPLETED, $state['status']);
        $this->assertStringContainsString('2024-06-15', $state['completedAt']);
    }

    public function testSerializeStatusStateWithoutCompletedAt(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-123', $user, 'Test Task', Task::STATUS_PENDING);

        $state = $this->service->serializeStatusState($task);

        $this->assertSame(Task::STATUS_PENDING, $state['status']);
        $this->assertNull($state['completedAt']);
    }

    public function testRestoreTaskFromState(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user);
        $tag = new Tag();
        $this->setEntityId($tag, 'tag-1');
        $tag->setName('Test Tag');
        $tag->setOwner($user);

        $this->projectRepository
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123')
            ->willReturn($project);

        $this->tagRepository
            ->method('findOneByOwnerAndId')
            ->with($user, 'tag-1')
            ->willReturn($tag);

        $state = [
            'title' => 'Restored Task',
            'description' => 'Restored description',
            'status' => Task::STATUS_IN_PROGRESS,
            'priority' => 3,
            'position' => 10,
            'dueDate' => '2024-12-31',
            'dueTime' => '16:00:00',
            'projectId' => 'project-123',
            'tagIds' => ['tag-1'],
            'createdAt' => '2024-01-01T00:00:00+00:00',
        ];

        $task = $this->service->restoreTaskFromState($user, $state);

        $this->assertSame($user, $task->getOwner());
        $this->assertSame('Restored Task', $task->getTitle());
        $this->assertSame('Restored description', $task->getDescription());
        $this->assertSame(Task::STATUS_IN_PROGRESS, $task->getStatus());
        $this->assertSame(3, $task->getPriority());
        $this->assertSame(10, $task->getPosition());
        $this->assertNotNull($task->getDueTime());
        $this->assertSame('16:00:00', $task->getDueTime()->format('H:i:s'));
        $this->assertSame($project, $task->getProject());
        $this->assertCount(1, $task->getTags());
    }

    public function testRestoreTaskFromStateWithCompletedAt(): void
    {
        $user = $this->createUserWithId('user-123');

        $state = [
            'title' => 'Completed Task',
            'status' => Task::STATUS_COMPLETED,
            'priority' => 2,
            'createdAt' => '2024-01-01T00:00:00+00:00',
            'completedAt' => '2024-06-15T10:30:00+00:00',
        ];

        $task = $this->service->restoreTaskFromState($user, $state);

        $this->assertSame(Task::STATUS_COMPLETED, $task->getStatus());
        $this->assertNotNull($task->getCompletedAt());
        $this->assertSame('2024-06-15', $task->getCompletedAt()->format('Y-m-d'));
    }

    public function testApplyStateToTaskUpdatesBasicProperties(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-123', $user, 'Original Title');

        $state = [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'priority' => 4,
        ];

        $this->service->applyStateToTask($task, $state);

        $this->assertSame('Updated Title', $task->getTitle());
        $this->assertSame('Updated description', $task->getDescription());
        $this->assertSame(4, $task->getPriority());
    }

    public function testApplyStateToTaskClearsProject(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user);
        $task = $this->createTaskWithId('task-123', $user, 'Test Task');
        $task->setProject($project);

        $state = [
            'projectId' => null,
        ];

        $this->service->applyStateToTask($task, $state);

        $this->assertNull($task->getProject());
    }

    public function testApplyStateToTaskSkipsNonExistentProject(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-123', $user, 'Test Task');

        $this->projectRepository
            ->method('findOneByOwnerAndId')
            ->with($user, 'non-existent-project')
            ->willReturn(null);

        $state = [
            'projectId' => 'non-existent-project',
        ];

        $this->service->applyStateToTask($task, $state);

        $this->assertNull($task->getProject());
    }

    public function testApplyStateToTaskRestoresTags(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-123', $user, 'Test Task');

        $tag1 = new Tag();
        $this->setEntityId($tag1, 'tag-1');
        $tag1->setName('Tag 1');
        $tag1->setOwner($user);

        $tag2 = new Tag();
        $this->setEntityId($tag2, 'tag-2');
        $tag2->setName('Tag 2');
        $tag2->setOwner($user);

        $this->tagRepository
            ->method('findOneByOwnerAndId')
            ->willReturnMap([
                [$user, 'tag-1', $tag1],
                [$user, 'tag-2', $tag2],
            ]);

        $state = [
            'tagIds' => ['tag-1', 'tag-2'],
        ];

        $this->service->applyStateToTask($task, $state);

        $this->assertCount(2, $task->getTags());
    }

    public function testApplyStateToTaskSkipsNonExistentTags(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-123', $user, 'Test Task');

        $tag1 = new Tag();
        $this->setEntityId($tag1, 'tag-1');
        $tag1->setName('Tag 1');
        $tag1->setOwner($user);

        $this->tagRepository
            ->method('findOneByOwnerAndId')
            ->willReturnMap([
                [$user, 'tag-1', $tag1],
                [$user, 'non-existent-tag', null],
            ]);

        $state = [
            'tagIds' => ['tag-1', 'non-existent-tag'],
        ];

        $this->service->applyStateToTask($task, $state);

        // Only the existing tag should be added
        $this->assertCount(1, $task->getTags());
    }
}
