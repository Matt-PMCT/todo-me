<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;

final class PasswordChangeApiTest extends ApiTestCase
{
    public function testChangePasswordSuccess(): void
    {
        $user = $this->createUser('changepass@test.com', 'OldPassword123!');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/auth/me/password',
            [
                'current_password' => 'OldPassword123!',
                'new_password' => 'NewSecurePass456!',
            ]
        );

        $this->assertSuccessResponse($response);
        $data = $this->getResponseData($response);
        $this->assertStringContainsString('changed', $data['message']);
    }

    public function testChangePasswordWithIncorrectCurrentPassword(): void
    {
        $user = $this->createUser('wrongpass@test.com', 'CorrectPass123!');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/auth/me/password',
            [
                'current_password' => 'WrongPassword123!',
                'new_password' => 'NewSecurePass456!',
            ]
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertErrorCode($response, 'INVALID_PASSWORD');
    }

    public function testChangePasswordWithWeakNewPassword(): void
    {
        $user = $this->createUser('weaknew@test.com', 'StrongPass123!');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/auth/me/password',
            [
                'current_password' => 'StrongPass123!',
                'new_password' => 'weak',
            ]
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testChangePasswordRequiresAuth(): void
    {
        $response = $this->apiRequest('PATCH', '/api/v1/auth/me/password', [
            'current_password' => 'anything',
            'new_password' => 'NewSecurePass456!',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
