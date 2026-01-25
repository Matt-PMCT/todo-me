<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for SecurityController (login, register, logout).
 */
class SecurityControllerTest extends ApiTestCase
{
    public function testLoginPageRendersSuccessfully(): void
    {
        $this->client->request('GET', '/login');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorExists('form');
    }

    public function testLoginPageRedirectsToTasksWhenAlreadyAuthenticated(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/login');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/tasks', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testLoginWithValidCredentials(): void
    {
        $user = $this->createUser('weblogin@example.com', 'Password123');

        $crawler = $this->client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'weblogin@example.com',
            '_password' => 'Password123',
        ]);

        $this->client->submit($form);

        // Should redirect after successful login
        $this->assertTrue(
            $this->client->getResponse()->isRedirect(),
            'Expected redirect after successful login'
        );
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $this->createUser('weblogin2@example.com', 'Password123');

        $crawler = $this->client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'weblogin2@example.com',
            '_password' => 'WrongPassword',
        ]);

        $this->client->submit($form);

        // Follow redirect to see error message
        $this->client->followRedirect();

        // Page should contain error message (login error uses red-800 styling)
        $this->assertSelectorExists('.text-red-800, [class*="error"], [class*="alert"]');
    }

    public function testRegisterPageRendersSuccessfully(): void
    {
        $this->client->request('GET', '/register');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorExists('form');
    }

    public function testRegisterPageRedirectsToTasksWhenAlreadyAuthenticated(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/register');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/tasks', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testRegisterWithValidData(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $form = $crawler->selectButton('Create account')->form([
            'email' => 'newwebuser' . uniqid() . '@example.com',
            'password' => 'SecurePassword123',
            'password_confirm' => 'SecurePassword123',
        ]);

        $this->client->submit($form);

        // Should redirect after successful registration
        $this->assertTrue(
            $this->client->getResponse()->isRedirect(),
            'Expected redirect after successful registration'
        );
    }

    public function testRegisterWithMismatchedPasswords(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $form = $crawler->selectButton('Create account')->form([
            'email' => 'mismatch' . uniqid() . '@example.com',
            'password' => 'Password123',
            'password_confirm' => 'DifferentPassword',
        ]);

        $this->client->submit($form);

        // Should show error about mismatched passwords
        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorTextContains('body', 'match');
    }

    public function testRegisterWithEmptyEmail(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $form = $crawler->selectButton('Create account')->form([
            'email' => '',
            'password' => 'Password123',
            'password_confirm' => 'Password123',
        ]);

        $this->client->submit($form);

        // Should show error about required email
        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorTextContains('body', 'required');
    }

    public function testRegisterWithShortPassword(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $form = $crawler->selectButton('Create account')->form([
            'email' => 'shortpwd' . uniqid() . '@example.com',
            'password' => 'short',
            'password_confirm' => 'short',
        ]);

        $this->client->submit($form);

        // Should show error about password length
        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorTextContains('body', '12 character');
    }

    public function testRegisterWithExistingEmail(): void
    {
        $existingUser = $this->createUser('existing@example.com', 'Password123');

        $crawler = $this->client->request('GET', '/register');

        $form = $crawler->selectButton('Create account')->form([
            'email' => 'existing@example.com',
            'password' => 'Password123',
            'password_confirm' => 'Password123',
        ]);

        $this->client->submit($form);

        // Should show error about existing email
        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSelectorTextContains('body', 'already exists');
    }

    public function testLogoutWorks(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/logout');

        // Symfony logout should redirect
        $this->assertTrue($this->client->getResponse()->isRedirect());
    }
}
