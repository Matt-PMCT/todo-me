<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\DTO\UserSettingsRequest;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the User Settings API endpoints.
 *
 * Tests:
 * - GET /api/v1/users/me/settings - Get user settings
 * - PATCH /api/v1/users/me/settings - Update user settings
 * - Validation errors for invalid values
 * - Unauthenticated access
 */
class UserSettingsApiTest extends ApiTestCase
{
    // ========================================
    // Get Settings Tests
    // ========================================

    public function testGetSettingsReturnsDefaultsForNewUser(): void
    {
        $user = $this->createUser('settings-new@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/users/me/settings'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('settings', $data);
        $settings = $data['settings'];

        // Check that defaults are applied
        $this->assertArrayHasKey('timezone', $settings);
        $this->assertArrayHasKey('date_format', $settings);
        $this->assertArrayHasKey('start_of_week', $settings);
    }

    public function testGetSettingsUnauthenticated(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/users/me/settings');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Update Settings Tests
    // ========================================

    public function testUpdateSettingsTimezone(): void
    {
        $user = $this->createUser('settings-timezone@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/users/me/settings',
            ['timezone' => 'America/New_York']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('America/New_York', $data['settings']['timezone']);
    }

    public function testUpdateSettingsDateFormat(): void
    {
        $user = $this->createUser('settings-dateformat@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/users/me/settings',
            ['dateFormat' => 'DMY']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('DMY', $data['settings']['date_format']);
    }

    public function testUpdateSettingsStartOfWeek(): void
    {
        $user = $this->createUser('settings-startofweek@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/users/me/settings',
            ['startOfWeek' => 1]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals(1, $data['settings']['start_of_week']);
    }

    public function testUpdateMultipleSettings(): void
    {
        $user = $this->createUser('settings-multi@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/users/me/settings',
            [
                'timezone' => 'Europe/London',
                'dateFormat' => 'YMD',
                'startOfWeek' => 1,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Europe/London', $data['settings']['timezone']);
        $this->assertEquals('YMD', $data['settings']['date_format']);
        $this->assertEquals(1, $data['settings']['start_of_week']);
    }

    public function testSettingsPersist(): void
    {
        $user = $this->createUser('settings-persist@example.com', 'Password123');

        // Update settings
        $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/users/me/settings',
            ['timezone' => 'Asia/Tokyo']
        );

        // Get settings again
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/users/me/settings'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Asia/Tokyo', $data['settings']['timezone']);
    }

    // ========================================
    // Validation Error Tests
    // ========================================

    public function testUpdateSettingsInvalidTimezone(): void
    {
        $user = $this->createUser('settings-invalid-tz@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/users/me/settings',
            ['timezone' => 'Invalid/Timezone']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testUpdateSettingsInvalidDateFormat(): void
    {
        $user = $this->createUser('settings-invalid-df@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/users/me/settings',
            ['dateFormat' => 'INVALID']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testUpdateSettingsInvalidStartOfWeek(): void
    {
        $user = $this->createUser('settings-invalid-sow@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/users/me/settings',
            ['startOfWeek' => 5]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testUpdateSettingsUnauthenticated(): void
    {
        $response = $this->apiRequest(
            'PATCH',
            '/api/v1/users/me/settings',
            ['timezone' => 'UTC']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Valid Timezone Tests
    // ========================================

    public function testAllValidTimezones(): void
    {
        $user = $this->createUser('settings-tz-valid@example.com', 'Password123');

        // Test a subset of valid PHP timezones to verify Symfony's Timezone constraint works
        $testTimezones = [
            'UTC',
            'America/New_York',
            'Europe/London',
            'Asia/Tokyo',
            'Australia/Sydney',
            'Pacific/Auckland', // Additional timezone not in old hardcoded list
            'Africa/Cairo', // Additional timezone not in old hardcoded list
        ];

        foreach ($testTimezones as $tz) {
            $response = $this->authenticatedApiRequest(
                $user,
                'PATCH',
                '/api/v1/users/me/settings',
                ['timezone' => $tz]
            );

            $this->assertResponseStatusCode(Response::HTTP_OK, $response, "Timezone {$tz} should be valid");
        }
    }

    public function testAllValidDateFormats(): void
    {
        $user = $this->createUser('settings-formats@example.com', 'Password123');

        foreach (['MDY', 'DMY', 'YMD'] as $format) {
            $response = $this->authenticatedApiRequest(
                $user,
                'PATCH',
                '/api/v1/users/me/settings',
                ['dateFormat' => $format]
            );

            $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        }
    }

    public function testAllValidStartOfWeekValues(): void
    {
        $user = $this->createUser('settings-sow-valid@example.com', 'Password123');

        foreach ([0, 1] as $value) {
            $response = $this->authenticatedApiRequest(
                $user,
                'PATCH',
                '/api/v1/users/me/settings',
                ['startOfWeek' => $value]
            );

            $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        }
    }
}
