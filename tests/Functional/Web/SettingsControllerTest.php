<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Functional\ApiTestCase;

/**
 * Functional tests for SettingsController.
 */
class SettingsControllerTest extends ApiTestCase
{
    public function testSettingsIndexRedirectsToLoginWhenNotAuthenticated(): void
    {
        $this->client->request('GET', '/settings');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testSettingsIndexRedirectsToProfileWhenAuthenticated(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/settings');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/settings/profile', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testProfilePageRedirectsToLoginWhenNotAuthenticated(): void
    {
        $this->client->request('GET', '/settings/profile');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testProfilePageReturns200WhenAuthenticated(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/settings/profile');

        $this->assertResponseIsSuccessful();
    }

    public function testSecurityPageRedirectsToLoginWhenNotAuthenticated(): void
    {
        $this->client->request('GET', '/settings/security');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testSecurityPageReturns200WhenAuthenticated(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/settings/security');

        $this->assertResponseIsSuccessful();
    }

    public function testApiTokensPageRedirectsToLoginWhenNotAuthenticated(): void
    {
        $this->client->request('GET', '/settings/api-tokens');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testApiTokensPageReturns200WhenAuthenticated(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/settings/api-tokens');

        $this->assertResponseIsSuccessful();
    }

    public function testDataPageRedirectsToLoginWhenNotAuthenticated(): void
    {
        $this->client->request('GET', '/settings/data');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testDataPageReturns200WhenAuthenticated(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/settings/data');

        $this->assertResponseIsSuccessful();
    }
}
