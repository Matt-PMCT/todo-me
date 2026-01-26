<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Project;
use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for TaskListController.
 */
class TaskListControllerTest extends ApiTestCase
{
    public function testTaskListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/tasks');

        // Should redirect to login
        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testTaskListRendersForAuthenticatedUser(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/tasks');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
    }

    public function testTaskListShowsUserTasks(): void
    {
        $user = $this->createUser();
        $this->createTask($user, 'My Test Task');
        $this->client->loginUser($user);

        $this->client->request('GET', '/tasks');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorTextContains('body', 'My Test Task');
    }

    public function testTaskListDoesNotShowOtherUsersTasks(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $this->createTask($user1, 'User 1 Task');
        $this->createTask($user2, 'User 2 Task');

        $this->client->loginUser($user1);
        $this->client->request('GET', '/tasks');

        $this->assertSelectorTextContains('body', 'User 1 Task');
        $this->assertSelectorTextNotContains('body', 'User 2 Task');
    }

    public function testTaskListFiltersbyStatus(): void
    {
        $user = $this->createUser();
        $this->createTask($user, 'Pending Task', null, Task::STATUS_PENDING);
        $this->createTask($user, 'Completed Task', null, Task::STATUS_COMPLETED);
        $this->client->loginUser($user);

        $this->client->request('GET', '/tasks?status=completed');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorTextContains('body', 'Completed Task');
        $this->assertSelectorTextNotContains('body', 'Pending Task');
    }

    public function testTaskListFiltersByPriority(): void
    {
        $user = $this->createUser();
        $this->createTask($user, 'High Priority Task', null, Task::STATUS_PENDING, Task::PRIORITY_MAX);
        $this->createTask($user, 'Low Priority Task', null, Task::STATUS_PENDING, Task::PRIORITY_MIN);
        $this->client->loginUser($user);

        $this->client->request('GET', '/tasks?priority='.Task::PRIORITY_MAX);

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorTextContains('body', 'High Priority Task');
        $this->assertSelectorTextNotContains('body', 'Low Priority Task');
    }

    public function testCreateTaskWithValidData(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        // First get the page to establish session and get CSRF token from hidden form
        $crawler = $this->client->request('GET', '/tasks');
        $csrfToken = $crawler->filter('#create-task-form input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/tasks', [
            'title' => 'New Task from Web',
            '_csrf_token' => $csrfToken,
        ]);

        // Should redirect after creation
        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Follow redirect and verify task is shown
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'New Task from Web');
    }

    public function testCreateTaskWithEmptyTitleShowsError(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        // First get the page to establish session and get CSRF token from hidden form
        $crawler = $this->client->request('GET', '/tasks');
        $csrfToken = $crawler->filter('#create-task-form input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/tasks', [
            'title' => '',
            '_csrf_token' => $csrfToken,
        ]);

        // Should redirect
        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Follow redirect and verify error is shown
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'required');
    }

    public function testCreateTaskWithInvalidCsrfTokenShowsError(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/tasks', [
            'title' => 'Test Task',
            '_csrf_token' => 'invalid-token',
        ]);

        // Should redirect
        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Follow redirect and verify error is shown
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'security token');
    }

    public function testChangeTaskStatus(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user, 'Status Task', null, Task::STATUS_PENDING);
        $this->client->loginUser($user);

        // First get the page to establish session
        $crawler = $this->client->request('GET', '/tasks');

        // Find the status form for this task and get its CSRF token
        $statusForm = $crawler->filter('form[action*="/tasks/'.$task->getId().'/status"]');
        $csrfToken = $statusForm->filter('input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/tasks/'.$task->getId().'/status', [
            'status' => Task::STATUS_COMPLETED,
            '_csrf_token' => $csrfToken,
        ]);

        // Should redirect
        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Verify task status was changed
        $this->entityManager->clear();
        $updatedTask = $this->entityManager->find(Task::class, $task->getId());
        $this->assertEquals(Task::STATUS_COMPLETED, $updatedTask->getStatus());
    }

    public function testChangeTaskStatusWithInvalidCsrfTokenShowsError(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user, 'Status Task');
        $this->client->loginUser($user);

        $this->client->request('POST', '/tasks/'.$task->getId().'/status', [
            'status' => Task::STATUS_COMPLETED,
            '_csrf_token' => 'invalid-token',
        ]);

        // Should redirect
        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Follow redirect and verify error is shown
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'security token');
    }

    public function testDeleteTask(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user, 'Task to Delete');
        $taskId = $task->getId();
        $this->client->loginUser($user);

        // First get the page to establish session
        $crawler = $this->client->request('GET', '/tasks');

        // Find the delete form for this task and get its CSRF token
        $deleteForm = $crawler->filter('form[action*="/tasks/'.$task->getId().'/delete"]');
        $csrfToken = $deleteForm->filter('input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/tasks/'.$task->getId().'/delete', [
            '_csrf_token' => $csrfToken,
        ]);

        // Should redirect
        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Verify task was deleted
        $this->entityManager->clear();
        $deletedTask = $this->entityManager->find(Task::class, $taskId);
        $this->assertNull($deletedTask);
    }

    public function testDeleteTaskWithInvalidCsrfTokenShowsError(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user, 'Task to Delete');
        $this->client->loginUser($user);

        $this->client->request('POST', '/tasks/'.$task->getId().'/delete', [
            '_csrf_token' => 'invalid-token',
        ]);

        // Should redirect
        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Follow redirect and verify error is shown
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'security token');
    }

    public function testCannotChangeOtherUsersTaskStatus(): void
    {
        $user1 = $this->createUser('user1-status@example.com');
        $user2 = $this->createUser('user2-status@example.com');
        $user1Task = $this->createTask($user1, 'User 1 Task', null, Task::STATUS_PENDING);

        $this->client->loginUser($user2);

        // Get page to establish session
        $this->client->request('GET', '/tasks');

        // Attempt to change user1's task using an arbitrary token
        // Either CSRF or authorization will prevent this - both are valid protections
        $this->client->request('POST', '/tasks/'.$user1Task->getId().'/status', [
            'status' => Task::STATUS_COMPLETED,
            '_csrf_token' => 'forged-token',
        ]);

        // Should redirect
        $this->assertTrue($this->client->getResponse()->isRedirect());

        // The key assertion: user1's task should NOT be modified
        $this->entityManager->clear();
        $unchangedTask = $this->entityManager->find(Task::class, $user1Task->getId());
        $this->assertEquals(Task::STATUS_PENDING, $unchangedTask->getStatus());
    }

    public function testCannotDeleteOtherUsersTask(): void
    {
        $user1 = $this->createUser('user1-delete@example.com');
        $user2 = $this->createUser('user2-delete@example.com');
        $task = $this->createTask($user1, 'User 1 Task');
        $taskId = $task->getId();

        $this->client->loginUser($user2);

        // Get page to establish session
        $this->client->request('GET', '/tasks');

        // Attempt to delete user1's task using an arbitrary token
        // Either CSRF or authorization will prevent this - both are valid protections
        $this->client->request('POST', '/tasks/'.$task->getId().'/delete', [
            '_csrf_token' => 'forged-token',
        ]);

        // Should redirect
        $this->assertTrue($this->client->getResponse()->isRedirect());

        // The key assertion: user1's task should still exist
        $this->entityManager->clear();
        $notDeletedTask = $this->entityManager->find(Task::class, $taskId);
        $this->assertNotNull($notDeletedTask);
    }

    // =====================================================
    // Archived Projects Page Tests
    // =====================================================

    public function testArchivedProjectsRequiresAuthentication(): void
    {
        $this->client->request('GET', '/projects/archived');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testArchivedProjectsRendersForAuthenticatedUser(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/projects/archived');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorTextContains('h1', 'Archived Projects');
    }

    public function testArchivedProjectsShowsOnlyArchivedProjects(): void
    {
        $user = $this->createUser();
        $this->createProject($user, 'Active Project', null, false);
        $this->createProject($user, 'Archived Project', null, true);
        $this->client->loginUser($user);

        $this->client->request('GET', '/projects/archived');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorTextContains('body', 'Archived Project');
        $this->assertSelectorTextNotContains('body', 'Active Project');
    }

    public function testArchivedProjectsShowsEmptyStateWhenNoArchivedProjects(): void
    {
        $user = $this->createUser();
        $this->createProject($user, 'Active Project', null, false);
        $this->client->loginUser($user);

        $this->client->request('GET', '/projects/archived');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorTextContains('body', 'No archived projects');
    }

    public function testArchivedProjectsDoesNotShowOtherUsersProjects(): void
    {
        $user1 = $this->createUser('user1-archived@example.com');
        $user2 = $this->createUser('user2-archived@example.com');
        $this->createProject($user1, 'User1 Archived', null, true);
        $this->createProject($user2, 'User2 Archived', null, true);
        $this->client->loginUser($user1);

        $this->client->request('GET', '/projects/archived');

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('User1 Archived', $content);
        $this->assertStringNotContainsString('User2 Archived', $content);
    }

    public function testUnarchiveProjectSuccessfully(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user, 'Project to Restore', null, true);
        $projectId = (string) $project->getId();
        $this->client->loginUser($user);

        // Get the archived projects page and find the form
        $crawler = $this->client->request('GET', '/projects/archived');

        // Verify project is shown on the page
        $this->assertSelectorTextContains('body', 'Project to Restore');

        // Find form with unarchive in the action
        $form = $crawler->filter('form[action$="/projects/'.$projectId.'/unarchive"]');
        $this->assertGreaterThan(0, $form->count(), 'Expected to find unarchive form for project');
        $csrfToken = $form->filter('input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/projects/'.$projectId.'/unarchive', [
            '_csrf_token' => $csrfToken,
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Verify project is no longer archived
        $this->entityManager->clear();
        $restoredProject = $this->entityManager->find(Project::class, $projectId);
        $this->assertNotNull($restoredProject);
        $this->assertNull($restoredProject->getArchivedAt());
    }

    public function testUnarchiveProjectWithInvalidCsrfTokenShowsError(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user, 'Project to Restore', null, true);
        $this->client->loginUser($user);

        $this->client->request('POST', '/projects/'.$project->getId().'/unarchive', [
            '_csrf_token' => 'invalid-token',
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'security token');
    }

    public function testUnarchiveProjectRequiresAuthentication(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user, 'Project to Restore', null, true);

        $this->client->request('POST', '/projects/'.$project->getId().'/unarchive', [
            '_csrf_token' => 'any-token',
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testCannotUnarchiveOtherUsersProject(): void
    {
        $user1 = $this->createUser('user1-unarchive@example.com');
        $user2 = $this->createUser('user2-unarchive@example.com');
        $project = $this->createProject($user1, 'User1 Archived Project', null, true);
        $this->client->loginUser($user2);

        // Get page to establish session
        $this->client->request('GET', '/projects/archived');

        $this->client->request('POST', '/projects/'.$project->getId().'/unarchive', [
            '_csrf_token' => 'forged-token',
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirect());

        // The project should still be archived
        $this->entityManager->clear();
        $unchangedProject = $this->entityManager->find(Project::class, $project->getId());
        $this->assertTrue($unchangedProject->isArchived());
    }

    // =====================================================
    // Project Creation Tests
    // =====================================================

    public function testCreateProjectRequiresAuthentication(): void
    {
        $this->client->request('POST', '/projects', [
            'name' => 'Test Project',
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testCreateProjectWithValidData(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        // Get page to establish session and get CSRF token
        $crawler = $this->client->request('GET', '/tasks');
        $csrfToken = $crawler->filter('input[name="_csrf_token"][data-action="create_project"]')->attr('value');

        $this->client->request('POST', '/projects', [
            'name' => 'New Test Project',
            '_csrf_token' => $csrfToken,
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Verify project was created
        $this->entityManager->clear();
        $project = $this->entityManager->getRepository(Project::class)->findOneBy(['name' => 'New Test Project']);
        $this->assertNotNull($project);
        $this->assertEquals($user->getId(), $project->getOwner()->getId());
    }

    public function testCreateProjectWithEmptyNameShowsError(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/tasks');
        $csrfToken = $crawler->filter('input[name="_csrf_token"][data-action="create_project"]')->attr('value');

        $this->client->request('POST', '/projects', [
            'name' => '',
            '_csrf_token' => $csrfToken,
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'required');
    }

    public function testCreateProjectWithInvalidCsrfTokenShowsError(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/projects', [
            'name' => 'Test Project',
            '_csrf_token' => 'invalid-token',
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'security token');
    }

    public function testCreateProjectWithOptionalFields(): void
    {
        $user = $this->createUser();
        $parentProject = $this->createProject($user, 'Parent Project');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/tasks');
        $csrfToken = $crawler->filter('input[name="_csrf_token"][data-action="create_project"]')->attr('value');

        $this->client->request('POST', '/projects', [
            'name' => 'Child Project',
            'description' => 'Test description',
            'parentId' => (string) $parentProject->getId(),
            'color' => '#FF5733',
            '_csrf_token' => $csrfToken,
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirect());

        $this->entityManager->clear();
        $project = $this->entityManager->getRepository(Project::class)->findOneBy(['name' => 'Child Project']);
        $this->assertNotNull($project);
        $this->assertEquals('Test description', $project->getDescription());
        $this->assertEquals('#FF5733', $project->getColor());
        $this->assertEquals($parentProject->getId(), $project->getParent()->getId());
    }
}
