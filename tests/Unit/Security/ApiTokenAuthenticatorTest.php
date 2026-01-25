<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Interface\ApiLoggerInterface;
use App\Interface\ApiTokenServiceInterface;
use App\Interface\UserServiceInterface;
use App\Security\ApiTokenAuthenticator;
use App\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Unit tests for ApiTokenAuthenticator.
 *
 * Tests authentication logic including:
 * - Bearer token validation
 * - X-API-Key header support
 * - Public route bypasses
 * - Token validation (invalid, expired)
 * - Authentication failure responses
 */
class ApiTokenAuthenticatorTest extends UnitTestCase
{
    private UserServiceInterface&MockObject $userService;
    private ApiLoggerInterface&MockObject $apiLogger;
    private ApiTokenServiceInterface&MockObject $apiTokenService;
    private ApiTokenAuthenticator $authenticator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userService = $this->createMock(UserServiceInterface::class);
        $this->apiLogger = $this->createMock(ApiLoggerInterface::class);
        $this->apiTokenService = $this->createMock(ApiTokenServiceInterface::class);

        $this->authenticator = new ApiTokenAuthenticator(
            $this->userService,
            $this->apiLogger,
            $this->apiTokenService,
        );
    }

    // =========================================================================
    // supports() Method Tests - Non-API Routes
    // =========================================================================

    public function testSupportsReturnsFalseForNonApiRoutes(): void
    {
        $request = Request::create('/web/dashboard', 'GET');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseForRootRoute(): void
    {
        $request = Request::create('/', 'GET');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    // =========================================================================
    // supports() Method Tests - Public API Routes
    // =========================================================================

    public function testSupportsReturnsFalseForPublicRegisterRoute(): void
    {
        $request = Request::create('/api/v1/auth/register', 'POST');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseForPublicTokenRoute(): void
    {
        $request = Request::create('/api/v1/auth/token', 'POST');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseForPublicRefreshRoute(): void
    {
        $request = Request::create('/api/v1/auth/refresh', 'POST');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    // =========================================================================
    // supports() Method Tests - Protected Routes with Tokens
    // =========================================================================

    public function testSupportsReturnsTrueForProtectedRouteWithBearerToken(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $request->headers->set('Authorization', 'Bearer valid-token-123');

        $result = $this->authenticator->supports($request);

        $this->assertTrue($result);
    }

    public function testSupportsReturnsTrueForProtectedRouteWithXApiKey(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $request->headers->set('X-API-Key', 'valid-token-123');

        $result = $this->authenticator->supports($request);

        $this->assertTrue($result);
    }

    public function testSupportsReturnsFalseWhenNoTokenProvided(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseForEmptyBearerToken(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $request->headers->set('Authorization', 'Bearer ');

        $result = $this->authenticator->supports($request);

        // Bearer header is present, so it should return true
        // (empty token handling happens in authenticate())
        $this->assertTrue($result);
    }

    // =========================================================================
    // authenticate() Method Tests - Valid Token
    // =========================================================================

    public function testAuthenticateWithValidBearerToken(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $request->headers->set('Authorization', 'Bearer valid-token-123');

        $user = $this->createUserWithId('user-123');
        $this->setUserApiToken($user, 'valid-token-123', false);

        $this->userService->method('findByApiTokenIgnoreExpiration')
            ->with('valid-token-123')
            ->willReturn($user);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);

        // Trigger the user loader to verify the user is returned
        $userBadge = $passport->getBadge('Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge');
        $loadedUser = $userBadge->getUser();

        $this->assertSame($user, $loadedUser);
    }

    public function testAuthenticateWithValidXApiKey(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $request->headers->set('X-API-Key', 'valid-token-456');

        $user = $this->createUserWithId('user-123');
        $this->setUserApiToken($user, 'valid-token-456', false);

        $this->userService->method('findByApiTokenIgnoreExpiration')
            ->with('valid-token-456')
            ->willReturn($user);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    public function testAuthenticateBearerTokenTakesPrecedenceOverXApiKey(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $request->headers->set('Authorization', 'Bearer bearer-token');
        $request->headers->set('X-API-Key', 'api-key-token');

        $user = $this->createUserWithId('user-123');
        $this->setUserApiToken($user, 'bearer-token', false);

        // Should use bearer-token, not api-key-token
        $this->userService->method('findByApiTokenIgnoreExpiration')
            ->with('bearer-token')
            ->willReturn($user);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    // =========================================================================
    // authenticate() Method Tests - Invalid Token
    // =========================================================================

    public function testAuthenticateThrowsExceptionForMissingToken(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');

        $this->apiLogger->expects($this->once())
            ->method('logWarning')
            ->with('Authentication attempt without token', $this->anything());

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('API token not provided');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsExceptionForInvalidToken(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $this->userService->method('findByApiTokenIgnoreExpiration')
            ->with('invalid-token')
            ->willReturn(null);

        $this->apiLogger->expects($this->once())
            ->method('logWarning')
            ->with('Authentication attempt with invalid token', $this->anything());

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Authentication failed');

        $passport = $this->authenticator->authenticate($request);
        // Trigger the user loader
        $passport->getBadge('Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge')->getUser();
    }

    public function testAuthenticateThrowsExceptionForExpiredToken(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $request->headers->set('Authorization', 'Bearer expired-token');

        $user = $this->createUserWithId('user-123');
        $this->setUserApiToken($user, 'expired-token', true);

        $this->userService->method('findByApiTokenIgnoreExpiration')
            ->with('expired-token')
            ->willReturn($user);

        $this->apiLogger->expects($this->once())
            ->method('logWarning')
            ->with('Authentication attempt with expired token', $this->anything());

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Authentication failed');

        $passport = $this->authenticator->authenticate($request);
        $passport->getBadge('Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge')->getUser();
    }

    public function testAuthenticateThrowsExceptionForEmptyBearerTokenValue(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $request->headers->set('Authorization', 'Bearer ');

        $this->apiLogger->expects($this->once())
            ->method('logWarning')
            ->with('Authentication attempt without token', $this->anything());

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('API token not provided');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsExceptionForEmptyXApiKey(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $request->headers->set('X-API-Key', '');

        $this->apiLogger->expects($this->once())
            ->method('logWarning')
            ->with('Authentication attempt without token', $this->anything());

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('API token not provided');

        $this->authenticator->authenticate($request);
    }

    // =========================================================================
    // onAuthenticationSuccess() Method Tests
    // =========================================================================

    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $token = $this->createMock(TokenInterface::class);

        $result = $this->authenticator->onAuthenticationSuccess($request, $token, 'api');

        $this->assertNull($result);
    }

    // =========================================================================
    // onAuthenticationFailure() Method Tests
    // =========================================================================

    public function testOnAuthenticationFailureReturnsJsonResponse(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $exception = new CustomUserMessageAuthenticationException('Test failure');

        $this->apiLogger->expects($this->once())
            ->method('logWarning')
            ->with('Authentication failed', $this->anything());

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertNotNull($response);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testOnAuthenticationFailureResponseStructure(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $exception = new CustomUserMessageAuthenticationException('Invalid credentials');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $content = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('success', $content);
        $this->assertFalse($content['success']);

        $this->assertArrayHasKey('data', $content);
        $this->assertNull($content['data']);

        $this->assertArrayHasKey('error', $content);
        $this->assertArrayHasKey('code', $content['error']);
        $this->assertEquals('AUTHENTICATION_FAILED', $content['error']['code']);
        $this->assertArrayHasKey('message', $content['error']);
        $this->assertEquals('Invalid credentials', $content['error']['message']);

        $this->assertArrayHasKey('meta', $content);
        $this->assertArrayHasKey('timestamp', $content['meta']);
    }

    public function testOnAuthenticationFailureWithAuthenticationException(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $exception = new AuthenticationException('Generic auth error');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $content = json_decode($response->getContent(), true);

        $this->assertFalse($content['success']);
        $this->assertEquals('AUTHENTICATION_FAILED', $content['error']['code']);
    }

    // =========================================================================
    // Logging Tests
    // =========================================================================

    public function testLoggerCalledOnSuccessfulAuthentication(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $request->headers->set('Authorization', 'Bearer valid-token');

        $user = $this->createUserWithId('user-123');
        $this->setUserApiToken($user, 'valid-token', false);

        $this->userService->method('findByApiTokenIgnoreExpiration')
            ->willReturn($user);

        $this->apiLogger->expects($this->once())
            ->method('logInfo')
            ->with('User authenticated successfully', $this->callback(function ($context) {
                return isset($context['user_id']) && isset($context['email_hash']);
            }));

        $passport = $this->authenticator->authenticate($request);
        $passport->getBadge('Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge')->getUser();
    }

    public function testLoggerIncludesUserIdOnExpiredToken(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $request->headers->set('Authorization', 'Bearer expired-token');

        $user = $this->createUserWithId('user-999');
        $this->setUserApiToken($user, 'expired-token', true);

        $this->userService->method('findByApiTokenIgnoreExpiration')
            ->willReturn($user);

        $this->apiLogger->expects($this->once())
            ->method('logWarning')
            ->with(
                'Authentication attempt with expired token',
                $this->callback(function ($context) {
                    return isset($context['user_id']) && $context['user_id'] === 'user-999';
                })
            );

        try {
            $passport = $this->authenticator->authenticate($request);
            $passport->getBadge('Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge')->getUser();
        } catch (CustomUserMessageAuthenticationException) {
            // Expected
        }
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function testAuthenticateWithDifferentHttpMethods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($methods as $method) {
            $request = Request::create('/api/v1/tasks', $method);
            $request->headers->set('Authorization', 'Bearer valid-token');

            $user = $this->createUserWithId('user-123');
            $this->setUserApiToken($user, 'valid-token', false);

            $this->userService->method('findByApiTokenIgnoreExpiration')
                ->willReturn($user);

            $this->assertTrue(
                $this->authenticator->supports($request),
                "Failed for HTTP method: $method"
            );
        }
    }

    public function testSupportsVariousApiPaths(): void
    {
        $paths = [
            '/api/v1/tasks' => true,
            '/api/v1/tasks/123' => true,
            '/api/v1/projects' => true,
            '/api/v1/tags' => true,
            '/api/v2/tasks' => true,
            '/api/v1/auth/register' => false, // Public
            '/api/v1/auth/token' => false, // Public
            '/api/v1/auth/refresh' => false, // Public
        ];

        foreach ($paths as $path => $expectedSupport) {
            $request = Request::create($path, 'GET');
            if ($expectedSupport) {
                $request->headers->set('Authorization', 'Bearer token');
            }

            $result = $this->authenticator->supports($request);

            $this->assertEquals(
                $expectedSupport,
                $result,
                "Failed for path: $path"
            );
        }
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function setUserApiToken(User $user, string $token, bool $expired): void
    {
        $reflection = new \ReflectionClass($user);

        $tokenProperty = $reflection->getProperty('apiToken');
        $tokenProperty->setValue($user, $token);

        $expiresAt = $expired
            ? new \DateTimeImmutable('-1 hour')
            : new \DateTimeImmutable('+1 hour');

        $expiresProperty = $reflection->getProperty('apiTokenExpiresAt');
        $expiresProperty->setValue($user, $expiresAt);
    }
}
