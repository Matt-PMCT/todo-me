<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Functional\ApiTestCase;

/**
 * Functional tests for HomeController.
 */
class HomeControllerTest extends ApiTestCase
{
    public function testHomeRedirectsToLoginWhenNotAuthenticated(): void
    {
        $this->client->request('GET', '/');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testHomeRedirectsToTaskListWhenAuthenticated(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/tasks', $this->client->getResponse()->headers->get('Location') ?? '');
    }
}
