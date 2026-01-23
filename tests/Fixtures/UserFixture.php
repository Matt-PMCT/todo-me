<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\User;
use App\Service\TokenGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Fixture for creating test users.
 *
 * Creates users with known credentials for testing:
 * - Standard test user
 * - Admin user (same as standard for now)
 * - User without API token
 *
 * All passwords follow the pattern: email prefix + "Password123"
 */
class UserFixture extends Fixture
{
    public const USER_STANDARD_REFERENCE = 'user-standard';
    public const USER_SECONDARY_REFERENCE = 'user-secondary';
    public const USER_NO_TOKEN_REFERENCE = 'user-no-token';

    public const USER_STANDARD_EMAIL = 'test@example.com';
    public const USER_STANDARD_PASSWORD = 'testPassword123';

    public const USER_SECONDARY_EMAIL = 'secondary@example.com';
    public const USER_SECONDARY_PASSWORD = 'secondaryPassword123';

    public const USER_NO_TOKEN_EMAIL = 'notoken@example.com';
    public const USER_NO_TOKEN_PASSWORD = 'notokenPassword123';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenGenerator $tokenGenerator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Standard test user
        $standardUser = $this->createUser(
            self::USER_STANDARD_EMAIL,
            self::USER_STANDARD_PASSWORD,
            true
        );
        $manager->persist($standardUser);
        $this->addReference(self::USER_STANDARD_REFERENCE, $standardUser);

        // Secondary test user (for testing ownership/access control)
        $secondaryUser = $this->createUser(
            self::USER_SECONDARY_EMAIL,
            self::USER_SECONDARY_PASSWORD,
            true
        );
        $manager->persist($secondaryUser);
        $this->addReference(self::USER_SECONDARY_REFERENCE, $secondaryUser);

        // User without API token (for testing token generation)
        $noTokenUser = $this->createUser(
            self::USER_NO_TOKEN_EMAIL,
            self::USER_NO_TOKEN_PASSWORD,
            false
        );
        $manager->persist($noTokenUser);
        $this->addReference(self::USER_NO_TOKEN_REFERENCE, $noTokenUser);

        $manager->flush();
    }

    private function createUser(string $email, string $password, bool $withToken): User
    {
        $user = new User();
        $user->setEmail($email);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPasswordHash($hashedPassword);

        if ($withToken) {
            $user->setApiToken($this->tokenGenerator->generateApiToken());
        }

        return $user;
    }
}
