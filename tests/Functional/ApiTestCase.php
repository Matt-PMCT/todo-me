<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use App\Service\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base test case for API functional tests.
 *
 * Provides helper methods for:
 * - Creating authenticated clients
 * - Creating test users, tasks, and projects
 * - Making API requests
 * - Asserting API responses
 */
abstract class ApiTestCase extends WebTestCase
{
    protected ?EntityManagerInterface $entityManager = null;
    protected ?KernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        // Disable kernel reboot between requests to maintain database transaction
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Begin transaction for test isolation
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback transaction to reset database state
        if ($this->entityManager !== null && $this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollBack();
        }

        $this->entityManager = null;
        $this->client = null;

        parent::tearDown();
    }

    /**
     * Creates a KernelBrowser authenticated with the given user's API token.
     */
    protected function createAuthenticatedClient(User $user): KernelBrowser
    {
        $client = static::createClient();
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $user->getApiToken());

        return $client;
    }

    /**
     * Creates a KernelBrowser authenticated using X-API-Key header.
     */
    protected function createAuthenticatedClientWithApiKey(User $user): KernelBrowser
    {
        $client = static::createClient();
        $client->setServerParameter('HTTP_X_API_KEY', $user->getApiToken());

        return $client;
    }

    /**
     * Creates a test user with the given email and password.
     *
     * @param string $email
     * @param string $password
     * @param bool $withToken Whether to generate an API token
     * @return User
     */
    protected function createUser(
        string $email = 'test@example.com',
        string $password = 'password123',
        bool $withToken = true
    ): User {
        $user = new User();
        $user->setEmail($email);

        // Generate username from email (using part before @ plus random suffix)
        $emailPrefix = explode('@', $email)[0];
        $user->setUsername($emailPrefix . '_' . substr(md5(uniqid()), 0, 8));

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPasswordHash($hashedPassword);

        if ($withToken) {
            /** @var TokenGenerator $tokenGenerator */
            $tokenGenerator = static::getContainer()->get(TokenGenerator::class);
            $user->setApiToken($tokenGenerator->generateApiToken());
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Creates a test project for the given user.
     */
    protected function createProject(
        User $owner,
        string $name = 'Test Project',
        ?string $description = null,
        bool $isArchived = false
    ): Project {
        $project = new Project();
        $project->setOwner($owner);
        $project->setName($name);
        $project->setDescription($description);
        $project->setIsArchived($isArchived);

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    /**
     * Creates a test task for the given user.
     */
    protected function createTask(
        User $owner,
        string $title = 'Test Task',
        ?string $description = null,
        string $status = Task::STATUS_PENDING,
        int $priority = Task::PRIORITY_DEFAULT,
        ?Project $project = null,
        ?\DateTimeImmutable $dueDate = null
    ): Task {
        $task = new Task();
        $task->setOwner($owner);
        $task->setTitle($title);
        $task->setDescription($description);
        $task->setStatus($status);
        $task->setPriority($priority);
        $task->setProject($project);
        $task->setDueDate($dueDate);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * Creates a test tag for the given user.
     */
    protected function createTag(
        User $owner,
        string $name = 'Test Tag',
        string $color = '#FF0000'
    ): Tag {
        $tag = new Tag();
        $tag->setOwner($owner);
        $tag->setName($name);
        $tag->setColor($color);

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return $tag;
    }

    /**
     * Makes an API request and returns the response.
     *
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @param array<string, mixed>|null $data Request body data
     * @param array<string, string> $headers Additional headers
     * @param KernelBrowser|null $client Client to use (defaults to $this->client)
     * @return Response
     */
    protected function apiRequest(
        string $method,
        string $uri,
        ?array $data = null,
        array $headers = [],
        ?KernelBrowser $client = null
    ): Response {
        $client = $client ?? $this->client;

        $serverHeaders = ['CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $key => $value) {
            $serverHeaders['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        $content = $data !== null ? json_encode($data) : null;

        $client->request($method, $uri, [], [], $serverHeaders, $content);

        return $client->getResponse();
    }

    /**
     * Makes an authenticated API request.
     *
     * @param User $user The user to authenticate as
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @param array<string, mixed>|null $data Request body data
     * @param array<string, string> $headers Additional headers
     * @return Response
     */
    protected function authenticatedApiRequest(
        User $user,
        string $method,
        string $uri,
        ?array $data = null,
        array $headers = []
    ): Response {
        $headers['Authorization'] = 'Bearer ' . $user->getApiToken();

        return $this->apiRequest($method, $uri, $data, $headers);
    }

    /**
     * Asserts that the response is valid JSON.
     */
    protected function assertJsonResponse(Response $response): array
    {
        $this->assertJson($response->getContent());

        return json_decode($response->getContent(), true);
    }

    /**
     * Asserts that the response contains the expected error code.
     */
    protected function assertErrorCode(Response $response, string $expectedCode): void
    {
        $data = $this->assertJsonResponse($response);

        $this->assertFalse($data['success'] ?? true, 'Expected response to indicate failure');
        $this->assertArrayHasKey('error', $data, 'Expected response to have error field');
        $this->assertArrayHasKey('code', $data['error'], 'Expected error to have code field');
        $this->assertEquals($expectedCode, $data['error']['code'], 'Expected error code to match');
    }

    /**
     * Asserts that the response indicates success.
     */
    protected function assertSuccessResponse(Response $response): array
    {
        $data = $this->assertJsonResponse($response);

        $this->assertTrue($data['success'] ?? false, 'Expected response to indicate success');
        $this->assertArrayHasKey('data', $data, 'Expected response to have data field');

        return $data;
    }

    /**
     * Gets the response data from a successful response.
     */
    protected function getResponseData(Response $response): array
    {
        $json = $this->assertJsonResponse($response);

        return $json['data'] ?? [];
    }

    /**
     * Gets the response meta from a response.
     */
    protected function getResponseMeta(Response $response): array
    {
        $json = $this->assertJsonResponse($response);

        return $json['meta'] ?? [];
    }

    /**
     * Gets the response error from an error response.
     */
    protected function getResponseError(Response $response): array
    {
        $json = $this->assertJsonResponse($response);

        return $json['error'] ?? [];
    }

    /**
     * Asserts rate limit headers are present in the response.
     */
    protected function assertRateLimitHeaders(Response $response): void
    {
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit'),
            'Expected X-RateLimit-Limit header'
        );
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Remaining'),
            'Expected X-RateLimit-Remaining header'
        );
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Reset'),
            'Expected X-RateLimit-Reset header'
        );
    }

    /**
     * Asserts the response has the expected HTTP status code.
     */
    protected function assertResponseStatusCode(int $expected, Response $response): void
    {
        $this->assertEquals(
            $expected,
            $response->getStatusCode(),
            sprintf(
                'Expected status code %d, got %d. Response: %s',
                $expected,
                $response->getStatusCode(),
                $response->getContent()
            )
        );
    }

    /**
     * Refreshes an entity from the database.
     *
     * @template T of object
     * @param T $entity
     * @return T
     */
    protected function refreshEntity(object $entity): object
    {
        $this->entityManager->refresh($entity);

        return $entity;
    }

    /**
     * Clears the entity manager cache.
     */
    protected function clearEntityManager(): void
    {
        $this->entityManager->clear();
    }
}
