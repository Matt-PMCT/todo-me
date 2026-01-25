<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Health API endpoints.
 */
class HealthApiTest extends ApiTestCase
{
    public function testHealthCheckReturnsHealthyStatus(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/health');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $json = $this->assertJsonResponse($response);

        $this->assertEquals('healthy', $json['status']);
        $this->assertArrayHasKey('services', $json);
        $this->assertArrayHasKey('database', $json['services']);
        $this->assertArrayHasKey('redis', $json['services']);
        $this->assertArrayHasKey('timestamp', $json);
    }

    public function testHealthCheckServicesStatus(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/health');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $json = $this->assertJsonResponse($response);

        // Database should be healthy in test environment
        $this->assertEquals('healthy', $json['services']['database']);
    }

    public function testLivenessProbeReturnsOk(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/health/live');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $json = $this->assertJsonResponse($response);

        $this->assertEquals('ok', $json['status']);
    }

    public function testReadinessProbeReturnsReady(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/health/ready');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $json = $this->assertJsonResponse($response);

        $this->assertEquals('ready', $json['status']);
    }

    public function testHealthCheckDoesNotRequireAuthentication(): void
    {
        // Health check should work without authentication
        $response = $this->apiRequest('GET', '/api/v1/health');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
    }

    public function testLivenessProbeDoesNotRequireAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/health/live');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
    }

    public function testReadinessProbeDoesNotRequireAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/health/ready');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
    }

    public function testHealthCheckTimestampIsValid(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/health');

        $json = $this->assertJsonResponse($response);

        $this->assertArrayHasKey('timestamp', $json);

        // Verify timestamp is valid RFC3339 format
        $timestamp = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339, $json['timestamp']);
        $this->assertNotFalse($timestamp);
    }
}
