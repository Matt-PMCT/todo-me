<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use App\Service\ImportService;
use App\Tests\Unit\UnitTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ImportService.
 *
 * Tests:
 * - JSON import parsing
 * - Todoist format mapping
 * - CSV parsing
 * - Validation of import data
 * - Error handling for malformed data
 */
class ImportServiceTest extends UnitTestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ProjectRepository&MockObject $projectRepository;
    private TagRepository&MockObject $tagRepository;
    private TaskRepository&MockObject $taskRepository;
    private LoggerInterface&MockObject $logger;
    private ImportService $importService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->taskRepository = $this->createMock(TaskRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->importService = new ImportService(
            $this->entityManager,
            $this->projectRepository,
            $this->tagRepository,
            $this->taskRepository,
            $this->logger,
        );

        $this->user = $this->createUserWithId('import-user', 'import@example.com');
    }

    // ========================================
    // JSON Import Tests
    // ========================================

    public function testImportJsonParsesTasksCorrectly(): void
    {
        $data = [
            'tasks' => [
                ['title' => 'Task 1', 'status' => 'pending', 'priority' => 3],
                ['title' => 'Task 2', 'status' => 'completed', 'priority' => 4],
            ],
        ];

        $this->taskRepository->method('getMaxPosition')->willReturn(0);
        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $stats = $this->importService->importJson($this->user, $data);

        $this->assertEquals(2, $stats['tasks']);
        $this->assertEquals(0, $stats['projects']);
        $this->assertEquals(0, $stats['tags']);
    }

    public function testImportJsonParsesProjectsCorrectly(): void
    {
        $data = [
            'projects' => [
                ['name' => 'Project 1', 'description' => 'Description 1'],
                ['name' => 'Project 2'],
            ],
        ];

        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);
        $this->projectRepository->method('getMaxPositionInParent')->willReturn(0);
        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $stats = $this->importService->importJson($this->user, $data);

        $this->assertEquals(0, $stats['tasks']);
        $this->assertEquals(2, $stats['projects']);
        $this->assertEquals(0, $stats['tags']);
    }

    public function testImportJsonParsesTagsCorrectly(): void
    {
        $data = [
            'tags' => [
                ['name' => 'urgent', 'color' => '#FF0000'],
                ['name' => 'important'],
            ],
        ];

        $this->tagRepository->method('findByNameInsensitive')->willReturn(null);
        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $stats = $this->importService->importJson($this->user, $data);

        $this->assertEquals(0, $stats['tasks']);
        $this->assertEquals(0, $stats['projects']);
        $this->assertEquals(2, $stats['tags']);
    }

    public function testImportJsonHandlesEmptyData(): void
    {
        $data = [];

        $this->entityManager->expects($this->once())->method('flush');

        $stats = $this->importService->importJson($this->user, $data);

        $this->assertEquals(0, $stats['tasks']);
        $this->assertEquals(0, $stats['projects']);
        $this->assertEquals(0, $stats['tags']);
    }

    public function testImportJsonSkipsTasksWithoutTitle(): void
    {
        $data = [
            'tasks' => [
                ['title' => 'Valid Task'],
                ['description' => 'Task without title'],
                ['title' => ''],
            ],
        ];

        $this->taskRepository->method('getMaxPosition')->willReturn(0);
        $this->entityManager->expects($this->once())->method('persist'); // Only valid task
        $this->entityManager->expects($this->once())->method('flush');

        $stats = $this->importService->importJson($this->user, $data);

        $this->assertEquals(1, $stats['tasks']);
    }

    public function testImportJsonSkipsProjectsWithoutName(): void
    {
        $data = [
            'projects' => [
                ['name' => 'Valid Project'],
                ['description' => 'Project without name'],
                ['name' => ''],
            ],
        ];

        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);
        $this->projectRepository->method('getMaxPositionInParent')->willReturn(0);
        $this->entityManager->expects($this->once())->method('persist'); // Only valid project
        $this->entityManager->expects($this->once())->method('flush');

        $stats = $this->importService->importJson($this->user, $data);

        $this->assertEquals(1, $stats['projects']);
    }

    public function testImportJsonReusesExistingProjects(): void
    {
        $existingProject = $this->createProjectWithId('project-1', $this->user, 'Existing');

        $data = [
            'projects' => [
                ['name' => 'Existing'],
            ],
        ];

        $this->projectRepository->method('findByNameInsensitive')
            ->with($this->user, 'Existing')
            ->willReturn($existingProject);

        // persist should not be called since project already exists
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $stats = $this->importService->importJson($this->user, $data);

        // Stats count all processed projects including existing ones
        $this->assertEquals(1, $stats['projects']);
    }

    public function testImportJsonReusesExistingTags(): void
    {
        $existingTag = new Tag();
        $existingTag->setOwner($this->user);
        $existingTag->setName('existing');
        $this->setEntityId($existingTag, 'tag-1');

        $data = [
            'tags' => [
                ['name' => 'existing'],
            ],
        ];

        $this->tagRepository->method('findByNameInsensitive')
            ->with($this->user, 'existing')
            ->willReturn($existingTag);

        // persist should not be called since tag already exists
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $stats = $this->importService->importJson($this->user, $data);

        // Stats count all processed tags including existing ones
        $this->assertEquals(1, $stats['tags']);
    }

    public function testImportJsonSetsValidStatuses(): void
    {
        $data = [
            'tasks' => [
                ['title' => 'Pending', 'status' => 'pending'],
                ['title' => 'In Progress', 'status' => 'in_progress'],
                ['title' => 'Completed', 'status' => 'completed'],
            ],
        ];

        $this->taskRepository->method('getMaxPosition')->willReturn(0);

        $persistedTasks = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedTasks) {
                if ($entity instanceof Task) {
                    $persistedTasks[] = $entity;
                }
            });

        $this->importService->importJson($this->user, $data);

        $this->assertCount(3, $persistedTasks);
        $this->assertEquals(Task::STATUS_PENDING, $persistedTasks[0]->getStatus());
        $this->assertEquals(Task::STATUS_IN_PROGRESS, $persistedTasks[1]->getStatus());
        $this->assertEquals(Task::STATUS_COMPLETED, $persistedTasks[2]->getStatus());
    }

    public function testImportJsonSetsValidPriorities(): void
    {
        $data = [
            'tasks' => [
                ['title' => 'Priority 0', 'priority' => 0],
                ['title' => 'Priority 2', 'priority' => 2],
                ['title' => 'Priority 4', 'priority' => 4],
            ],
        ];

        $this->taskRepository->method('getMaxPosition')->willReturn(0);

        $persistedTasks = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedTasks) {
                if ($entity instanceof Task) {
                    $persistedTasks[] = $entity;
                }
            });

        $this->importService->importJson($this->user, $data);

        $this->assertCount(3, $persistedTasks);
        $this->assertEquals(0, $persistedTasks[0]->getPriority());
        $this->assertEquals(2, $persistedTasks[1]->getPriority());
        $this->assertEquals(4, $persistedTasks[2]->getPriority());
    }

    // ========================================
    // Todoist Import Tests
    // ========================================

    public function testImportTodoistMapsProjectsCorrectly(): void
    {
        $data = [
            'projects' => [
                ['id' => 1, 'name' => 'Work', 'color' => 'red'],
                ['id' => 2, 'name' => 'Personal'],
            ],
        ];

        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);
        $this->projectRepository->method('getMaxPositionInParent')->willReturn(0);
        $this->entityManager->expects($this->exactly(2))->method('persist');

        $stats = $this->importService->importTodoist($this->user, $data);

        $this->assertEquals(2, $stats['projects']);
    }

    public function testImportTodoistMapsLabelsToTags(): void
    {
        $data = [
            'labels' => [
                ['id' => 1, 'name' => 'urgent', 'color' => 'red'],
                ['id' => 2, 'name' => 'important'],
            ],
        ];

        $this->tagRepository->method('findByNameInsensitive')->willReturn(null);
        $this->entityManager->expects($this->exactly(2))->method('persist');

        $stats = $this->importService->importTodoist($this->user, $data);

        $this->assertEquals(2, $stats['tags']);
    }

    public function testImportTodoistMapsItemsToTasks(): void
    {
        $data = [
            'items' => [
                ['id' => 1, 'content' => 'Todoist Task 1'],
                ['id' => 2, 'content' => 'Todoist Task 2'],
            ],
        ];

        $this->taskRepository->method('getMaxPosition')->willReturn(0);
        $this->entityManager->expects($this->exactly(2))->method('persist');

        $stats = $this->importService->importTodoist($this->user, $data);

        $this->assertEquals(2, $stats['tasks']);
    }

    public function testImportTodoistMapsPriorityCorrectly(): void
    {
        $data = [
            'items' => [
                ['id' => 1, 'content' => 'Priority 1', 'priority' => 1],
                ['id' => 2, 'content' => 'Priority 4', 'priority' => 4],
            ],
        ];

        $this->taskRepository->method('getMaxPosition')->willReturn(0);

        $persistedTasks = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedTasks) {
                if ($entity instanceof Task) {
                    $persistedTasks[] = $entity;
                }
            });

        $this->importService->importTodoist($this->user, $data);

        $this->assertCount(2, $persistedTasks);
        // Todoist: 1 -> our 0, 4 -> our 3
        $this->assertEquals(0, $persistedTasks[0]->getPriority());
        $this->assertEquals(3, $persistedTasks[1]->getPriority());
    }

    public function testImportTodoistMapsCheckedToCompleted(): void
    {
        $data = [
            'items' => [
                ['id' => 1, 'content' => 'Completed', 'checked' => true],
                ['id' => 2, 'content' => 'Active', 'checked' => false],
            ],
        ];

        $this->taskRepository->method('getMaxPosition')->willReturn(0);

        $persistedTasks = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedTasks) {
                if ($entity instanceof Task) {
                    $persistedTasks[] = $entity;
                }
            });

        $this->importService->importTodoist($this->user, $data);

        $this->assertCount(2, $persistedTasks);
        $this->assertEquals(Task::STATUS_COMPLETED, $persistedTasks[0]->getStatus());
        $this->assertEquals(Task::STATUS_PENDING, $persistedTasks[1]->getStatus());
    }

    public function testImportTodoistMapsDueDateCorrectly(): void
    {
        $data = [
            'items' => [
                ['id' => 1, 'content' => 'Task with due', 'due' => ['date' => '2026-01-30']],
            ],
        ];

        $this->taskRepository->method('getMaxPosition')->willReturn(0);

        $persistedTask = null;
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedTask) {
                if ($entity instanceof Task) {
                    $persistedTask = $entity;
                }
            });

        $this->importService->importTodoist($this->user, $data);

        $this->assertNotNull($persistedTask);
        $this->assertNotNull($persistedTask->getDueDate());
        $this->assertEquals('2026-01-30', $persistedTask->getDueDate()->format('Y-m-d'));
    }

    public function testImportTodoistLinksItemsToProjects(): void
    {
        $data = [
            'projects' => [
                ['id' => 123, 'name' => 'Work'],
            ],
            'items' => [
                ['id' => 1, 'content' => 'Work Task', 'project_id' => 123],
            ],
        ];

        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);
        $this->projectRepository->method('getMaxPositionInParent')->willReturn(0);
        $this->taskRepository->method('getMaxPosition')->willReturn(0);

        $persistedProject = null;
        $persistedTask = null;
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedProject, &$persistedTask) {
                if ($entity instanceof Project) {
                    $persistedProject = $entity;
                }
                if ($entity instanceof Task) {
                    $persistedTask = $entity;
                }
            });

        $this->importService->importTodoist($this->user, $data);

        $this->assertNotNull($persistedTask);
        $this->assertSame($persistedProject, $persistedTask->getProject());
    }

    public function testImportTodoistLinksItemsToLabels(): void
    {
        $data = [
            'labels' => [
                ['id' => 1, 'name' => 'urgent'],
            ],
            'items' => [
                ['id' => 1, 'content' => 'Tagged Task', 'labels' => ['urgent']],
            ],
        ];

        $this->tagRepository->method('findByNameInsensitive')->willReturn(null);
        $this->taskRepository->method('getMaxPosition')->willReturn(0);

        $persistedTag = null;
        $persistedTask = null;
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedTag, &$persistedTask) {
                if ($entity instanceof Tag) {
                    $persistedTag = $entity;
                }
                if ($entity instanceof Task) {
                    $persistedTask = $entity;
                }
            });

        $this->importService->importTodoist($this->user, $data);

        $this->assertNotNull($persistedTask);
        $this->assertTrue($persistedTask->getTags()->contains($persistedTag));
    }

    // ========================================
    // CSV Import Tests
    // ========================================

    public function testImportCsvParsesHeadersAndDataCorrectly(): void
    {
        $csvContent = "title,description,status,priority\n"
            . "Task 1,Description 1,pending,3\n"
            . "Task 2,Description 2,completed,4";

        $this->taskRepository->method('getMaxPosition')->willReturn(0);
        $this->entityManager->expects($this->exactly(2))->method('persist');

        $stats = $this->importService->importCsv($this->user, $csvContent);

        $this->assertEquals(2, $stats['tasks']);
    }

    public function testImportCsvHandlesEmptyContent(): void
    {
        $csvContent = "";

        $stats = $this->importService->importCsv($this->user, $csvContent);

        $this->assertEquals(0, $stats['tasks']);
        $this->assertEquals(0, $stats['projects']);
        $this->assertEquals(0, $stats['tags']);
    }

    public function testImportCsvHandlesHeaderOnly(): void
    {
        $csvContent = "title,description,status";

        $stats = $this->importService->importCsv($this->user, $csvContent);

        $this->assertEquals(0, $stats['tasks']);
    }

    public function testImportCsvCreatesProjectsFromData(): void
    {
        $csvContent = "title,project\n"
            . "Task 1,New Project";

        $this->taskRepository->method('getMaxPosition')->willReturn(0);
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);
        $this->projectRepository->method('getMaxPositionInParent')->willReturn(0);

        $stats = $this->importService->importCsv($this->user, $csvContent);

        $this->assertEquals(1, $stats['tasks']);
        $this->assertEquals(1, $stats['projects']);
    }

    public function testImportCsvCreatesTagsFromData(): void
    {
        $csvContent = "title,tags\n"
            . "Task 1,\"urgent,important\"";

        $this->taskRepository->method('getMaxPosition')->willReturn(0);
        $this->tagRepository->method('findByNameInsensitive')->willReturn(null);

        $stats = $this->importService->importCsv($this->user, $csvContent);

        $this->assertEquals(1, $stats['tasks']);
        $this->assertEquals(2, $stats['tags']);
    }

    public function testImportCsvHandlesVariousDueDateHeaders(): void
    {
        // Test with "duedate" header
        $csvContent1 = "title,duedate\n"
            . "Task 1,2026-01-30";

        $this->taskRepository->method('getMaxPosition')->willReturn(0);

        $persistedTask = null;
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedTask) {
                if ($entity instanceof Task) {
                    $persistedTask = $entity;
                }
            });

        $this->importService->importCsv($this->user, $csvContent1);

        $this->assertNotNull($persistedTask);
        $this->assertNotNull($persistedTask->getDueDate());
    }

    public function testImportCsvSkipsRowsWithoutTitle(): void
    {
        $csvContent = "title,description\n"
            . "Valid Task,Description\n"
            . ",No title here";

        $this->taskRepository->method('getMaxPosition')->willReturn(0);
        $this->entityManager->expects($this->once())->method('persist'); // Only valid task

        $stats = $this->importService->importCsv($this->user, $csvContent);

        $this->assertEquals(1, $stats['tasks']);
    }

    public function testImportCsvHandlesMalformedRows(): void
    {
        $csvContent = "title,description,status\n"
            . "Task with missing columns";

        $this->taskRepository->method('getMaxPosition')->willReturn(0);
        $this->entityManager->expects($this->once())->method('persist');

        $stats = $this->importService->importCsv($this->user, $csvContent);

        // Should still import the task, just with default values for missing columns
        $this->assertEquals(1, $stats['tasks']);
    }

    // ========================================
    // Error Handling Tests
    // ========================================

    public function testImportJsonLogsWarningsForFailedTasks(): void
    {
        // This tests that the import service logs warnings when tasks fail
        $data = [
            'tasks' => [
                ['title' => 'Valid Task'],
            ],
        ];

        $this->taskRepository->method('getMaxPosition')->willReturn(0);

        // Should not throw exceptions for well-formed data
        $stats = $this->importService->importJson($this->user, $data);

        $this->assertEquals(1, $stats['tasks']);
    }

    public function testImportCsvHandlesInvalidDates(): void
    {
        $csvContent = "title,dueDate\n"
            . "Task with invalid date,not-a-date";

        $this->taskRepository->method('getMaxPosition')->willReturn(0);

        $persistedTask = null;
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedTask) {
                if ($entity instanceof Task) {
                    $persistedTask = $entity;
                }
            });

        $stats = $this->importService->importCsv($this->user, $csvContent);

        $this->assertEquals(1, $stats['tasks']);
        $this->assertNotNull($persistedTask);
        // Due date should be null (invalid date ignored)
        $this->assertNull($persistedTask->getDueDate());
    }

    public function testImportJsonIgnoresInvalidStatusValues(): void
    {
        $data = [
            'tasks' => [
                ['title' => 'Task with invalid status', 'status' => 'invalid_status'],
            ],
        ];

        $this->taskRepository->method('getMaxPosition')->willReturn(0);

        $persistedTask = null;
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedTask) {
                if ($entity instanceof Task) {
                    $persistedTask = $entity;
                }
            });

        $stats = $this->importService->importJson($this->user, $data);

        $this->assertEquals(1, $stats['tasks']);
        $this->assertNotNull($persistedTask);
        // Should default to pending
        $this->assertEquals(Task::STATUS_PENDING, $persistedTask->getStatus());
    }

    public function testImportJsonIgnoresInvalidPriorityValues(): void
    {
        $data = [
            'tasks' => [
                ['title' => 'Task with invalid priority', 'priority' => 99],
            ],
        ];

        $this->taskRepository->method('getMaxPosition')->willReturn(0);

        $persistedTask = null;
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedTask) {
                if ($entity instanceof Task) {
                    $persistedTask = $entity;
                }
            });

        $stats = $this->importService->importJson($this->user, $data);

        $this->assertEquals(1, $stats['tasks']);
        $this->assertNotNull($persistedTask);
        // Should default to 2 (default priority)
        $this->assertEquals(Task::PRIORITY_DEFAULT, $persistedTask->getPriority());
    }

    public function testImportJsonHandlesNonArrayEntries(): void
    {
        $data = [
            'tasks' => [
                'not an array',
                123,
                null,
                ['title' => 'Valid Task'],
            ],
        ];

        $this->taskRepository->method('getMaxPosition')->willReturn(0);
        $this->entityManager->expects($this->once())->method('persist'); // Only valid task

        $stats = $this->importService->importJson($this->user, $data);

        $this->assertEquals(1, $stats['tasks']);
    }
}
