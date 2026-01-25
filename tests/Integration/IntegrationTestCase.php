<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use App\Service\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base test case for integration tests.
 *
 * Provides database access with transaction isolation for test cleanup.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    protected ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

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
        parent::tearDown();
    }

    /**
     * Creates a test user.
     */
    protected function createUser(string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setUsername(str_replace(['@', '.'], '_', $email).'_'.substr(md5(uniqid()), 0, 6));

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $hashedPassword = $passwordHasher->hashPassword($user, 'password123');
        $user->setPasswordHash($hashedPassword);

        /** @var TokenGenerator $tokenGenerator */
        $tokenGenerator = static::getContainer()->get(TokenGenerator::class);
        $user->setApiToken($tokenGenerator->generateApiToken());
        $user->setApiTokenIssuedAt(new \DateTimeImmutable());
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('+48 hours'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Creates a test task.
     */
    protected function createTask(
        User $owner,
        string $title,
        string $status = Task::STATUS_PENDING,
        int $priority = Task::PRIORITY_DEFAULT,
        ?Project $project = null,
        ?\DateTimeImmutable $dueDate = null
    ): Task {
        $task = new Task();
        $task->setOwner($owner);
        $task->setTitle($title);
        $task->setStatus($status);
        $task->setPriority($priority);
        $task->setProject($project);
        $task->setDueDate($dueDate);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * Creates a test project.
     */
    protected function createProject(
        User $owner,
        string $name,
        bool $isArchived = false,
        ?Project $parent = null
    ): Project {
        $project = new Project();
        $project->setOwner($owner);
        $project->setName($name);
        $project->setIsArchived($isArchived);
        $project->setParent($parent);

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    /**
     * Creates a test tag.
     */
    protected function createTag(User $owner, string $name): Tag
    {
        $tag = new Tag();
        $tag->setOwner($owner);
        $tag->setName($name);

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return $tag;
    }
}
