<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

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

        $this->client->request('GET', '/tasks?priority=' . Task::PRIORITY_MAX);

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorTextContains('body', 'High Priority Task');
        $this->assertSelectorTextNotContains('body', 'Low Priority Task');
    }

    public function testCreateTaskWithValidData(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        // First get the page to establish session and get CSRF token
        $crawler = $this->client->request('GET', '/tasks');
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');

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

        // First get the page to establish session and get CSRF token
        $crawler = $this->client->request('GET', '/tasks');
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');

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
        $statusForm = $crawler->filter('form[action*="/tasks/' . $task->getId() . '/status"]');
        $csrfToken = $statusForm->filter('input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/tasks/' . $task->getId() . '/status', [
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

        $this->client->request('POST', '/tasks/' . $task->getId() . '/status', [
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
        $deleteForm = $crawler->filter('form[action*="/tasks/' . $task->getId() . '/delete"]');
        $csrfToken = $deleteForm->filter('input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/tasks/' . $task->getId() . '/delete', [
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

        $this->client->request('POST', '/tasks/' . $task->getId() . '/delete', [
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
        $task = $this->createTask($user1, 'User 1 Task');

        $this->client->loginUser($user2);

        // First get the page to establish session - user2 won't see user1's task
        $this->client->request('GET', '/tasks');

        // Generate a CSRF token manually for this task (which user2 doesn't own)
        $container = static::getContainer();
        /** @var CsrfTokenManagerInterface $csrfManager */
        $csrfManager = $container->get('security.csrf.token_manager');
        $csrfToken = $csrfManager->getToken('task_status_' . $task->getId())->getValue();

        $this->client->request('POST', '/tasks/' . $task->getId() . '/status', [
            'status' => Task::STATUS_COMPLETED,
            '_csrf_token' => $csrfToken,
        ]);

        // Should redirect
        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Follow redirect - should show error
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Failed');
    }

    public function testCannotDeleteOtherUsersTask(): void
    {
        $user1 = $this->createUser('user1-delete@example.com');
        $user2 = $this->createUser('user2-delete@example.com');
        $task = $this->createTask($user1, 'User 1 Task');
        $taskId = $task->getId();

        $this->client->loginUser($user2);

        // First get the page to establish session - user2 won't see user1's task
        $this->client->request('GET', '/tasks');

        // Generate a CSRF token manually for this task (which user2 doesn't own)
        $container = static::getContainer();
        /** @var CsrfTokenManagerInterface $csrfManager */
        $csrfManager = $container->get('security.csrf.token_manager');
        $csrfToken = $csrfManager->getToken('delete_task_' . $task->getId())->getValue();

        $this->client->request('POST', '/tasks/' . $task->getId() . '/delete', [
            '_csrf_token' => $csrfToken,
        ]);

        // Should redirect
        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Verify task still exists
        $this->entityManager->clear();
        $notDeletedTask = $this->entityManager->find(Task::class, $taskId);
        $this->assertNotNull($notDeletedTask);
    }
}
