<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\SessionApiAuthenticator;
use App\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Unit tests for SessionApiAuthenticator.
 *
 * Tests session-based authentication for web UI API requests including:
 * - Route detection (API vs non-API)
 * - Token header detection (defer to ApiTokenAuthenticator)
 * - Session validation
 * - CSRF token validation for state-changing requests
 * - User identifier extraction from session token
 */
class SessionApiAuthenticatorTest extends UnitTestCase
{
    private CsrfTokenManagerInterface&MockObject $csrfTokenManager;
    private SessionApiAuthenticator $authenticator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->authenticator = new SessionApiAuthenticator($this->csrfTokenManager);
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
    // supports() Method Tests - Token Headers Present (defer to ApiTokenAuthenticator)
    // =========================================================================

    public function testSupportsReturnsFalseWhenAuthorizationHeaderPresent(): void
    {
        $request = $this->createApiRequestWithSession('/api/v1/tasks', 'GET', $this->createSerializedToken());
        $request->headers->set('Authorization', 'Bearer some-token');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseWhenXApiKeyHeaderPresent(): void
    {
        $request = $this->createApiRequestWithSession('/api/v1/tasks', 'GET', $this->createSerializedToken());
        $request->headers->set('X-API-Key', 'some-api-key');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    // =========================================================================
    // supports() Method Tests - No Session
    // =========================================================================

    public function testSupportsReturnsFalseWhenNoSession(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseWhenSessionHasNoSecurityMain(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    // =========================================================================
    // supports() Method Tests - Valid Session
    // =========================================================================

    public function testSupportsReturnsTrueForApiRouteWithValidSession(): void
    {
        $request = $this->createApiRequestWithSession('/api/v1/tasks', 'GET', $this->createSerializedToken());

        $result = $this->authenticator->supports($request);

        $this->assertTrue($result);
    }

    // =========================================================================
    // authenticate() Method Tests - GET/HEAD Requests (no CSRF required)
    // =========================================================================

    public function testAuthenticateSucceedsForGetRequestWithoutCsrfToken(): void
    {
        $request = $this->createApiRequestWithSession('/api/v1/tasks', 'GET', $this->createSerializedToken('user@example.com'));

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $userBadge = $passport->getBadge('Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge');
        $this->assertSame('user@example.com', $userBadge->getUserIdentifier());
    }

    public function testAuthenticateSucceedsForHeadRequestWithoutCsrfToken(): void
    {
        $request = $this->createApiRequestWithSession('/api/v1/tasks', 'HEAD', $this->createSerializedToken('user@example.com'));

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $userBadge = $passport->getBadge('Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge');
        $this->assertSame('user@example.com', $userBadge->getUserIdentifier());
    }

    // =========================================================================
    // authenticate() Method Tests - State-Changing Requests with Valid CSRF
    // =========================================================================

    public function testAuthenticateSucceedsForPostWithValidCsrfToken(): void
    {
        $request = $this->createApiRequestWithSession(
            '/api/v1/tasks',
            'POST',
            $this->createSerializedToken('user@example.com'),
            'valid-csrf-token'
        );
        $this->csrfTokenManager->method('isTokenValid')
            ->with($this->callback(fn (CsrfToken $token) => $token->getId() === 'api' && $token->getValue() === 'valid-csrf-token'))
            ->willReturn(true);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $userBadge = $passport->getBadge('Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge');
        $this->assertSame('user@example.com', $userBadge->getUserIdentifier());
    }

    public function testAuthenticateSucceedsForPutWithValidCsrfToken(): void
    {
        $request = $this->createApiRequestWithSession(
            '/api/v1/tasks/123',
            'PUT',
            $this->createSerializedToken('user@example.com'),
            'valid-csrf-token'
        );
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    public function testAuthenticateSucceedsForPatchWithValidCsrfToken(): void
    {
        $request = $this->createApiRequestWithSession(
            '/api/v1/tasks/123',
            'PATCH',
            $this->createSerializedToken('user@example.com'),
            'valid-csrf-token'
        );
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    public function testAuthenticateSucceedsForDeleteWithValidCsrfToken(): void
    {
        $request = $this->createApiRequestWithSession(
            '/api/v1/tasks/123',
            'DELETE',
            $this->createSerializedToken('user@example.com'),
            'valid-csrf-token'
        );
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    // =========================================================================
    // authenticate() Method Tests - CSRF Failures
    // =========================================================================

    public function testAuthenticateThrowsForPostWithMissingCsrfToken(): void
    {
        $request = $this->createApiRequestWithSession(
            '/api/v1/tasks',
            'POST',
            $this->createSerializedToken('user@example.com')
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('CSRF token missing');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsForPostWithEmptyCsrfToken(): void
    {
        $request = $this->createApiRequestWithSession(
            '/api/v1/tasks',
            'POST',
            $this->createSerializedToken('user@example.com'),
            ''
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('CSRF token missing');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsForPostWithInvalidCsrfToken(): void
    {
        $request = $this->createApiRequestWithSession(
            '/api/v1/tasks',
            'POST',
            $this->createSerializedToken('user@example.com'),
            'invalid-csrf-token'
        );
        $this->csrfTokenManager->method('isTokenValid')->willReturn(false);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid CSRF token');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsForPutWithMissingCsrfToken(): void
    {
        $request = $this->createApiRequestWithSession(
            '/api/v1/tasks/123',
            'PUT',
            $this->createSerializedToken('user@example.com')
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('CSRF token missing');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsForDeleteWithInvalidCsrfToken(): void
    {
        $request = $this->createApiRequestWithSession(
            '/api/v1/tasks/123',
            'DELETE',
            $this->createSerializedToken('user@example.com'),
            'bad-token'
        );
        $this->csrfTokenManager->method('isTokenValid')->willReturn(false);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid CSRF token');

        $this->authenticator->authenticate($request);
    }

    // =========================================================================
    // authenticate() Method Tests - Session Token Handling
    // =========================================================================

    public function testAuthenticateThrowsWhenSessionTokenIsNull(): void
    {
        $request = $this->createApiRequestWithSession('/api/v1/tasks', 'GET', null);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No session token found');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsWhenSessionTokenIsNotTokenInterface(): void
    {
        $request = $this->createApiRequestWithSession('/api/v1/tasks', 'GET', serialize('not-a-token'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid session token');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsWhenUserIdentifierIsEmpty(): void
    {
        $request = $this->createApiRequestWithSession('/api/v1/tasks', 'GET', $this->createSerializedToken(''));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No user identifier in session');

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

    public function testOnAuthenticationFailureReturnsNull(): void
    {
        $request = Request::create('/api/v1/tasks', 'GET');
        $exception = new AuthenticationException('Test failure');

        $result = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertNull($result);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Creates an API request with a session containing the security token.
     */
    private function createApiRequestWithSession(
        string $path,
        string $method,
        ?string $serializedToken,
        ?string $csrfToken = null,
    ): Request {
        $request = Request::create($path, $method);

        $session = new Session(new MockArraySessionStorage());
        if ($serializedToken !== null) {
            $session->set('_security_main', $serializedToken);
        }
        $request->setSession($session);

        if ($csrfToken !== null) {
            $request->headers->set('X-CSRF-Token', $csrfToken);
        }

        return $request;
    }

    /**
     * Creates a serialized mock TokenInterface with the given user identifier.
     */
    private function createSerializedToken(string $userIdentifier = 'user@example.com'): string
    {
        // Create a simple serializable token class for testing
        return serialize(new TestSessionToken($userIdentifier));
    }
}

/**
 * Simple token implementation for testing session serialization.
 */
class TestSessionToken implements TokenInterface
{
    private string $userIdentifier;

    public function __construct(string $userIdentifier)
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function __toString(): string
    {
        return $this->userIdentifier;
    }

    public function getRoleNames(): array
    {
        return ['ROLE_USER'];
    }

    public function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
    {
        return null;
    }

    public function setUser(\Symfony\Component\Security\Core\User\UserInterface $user): void
    {
    }

    public function eraseCredentials(): void
    {
    }

    public function getAttributes(): array
    {
        return [];
    }

    public function setAttributes(array $attributes): void
    {
    }

    public function hasAttribute(string $name): bool
    {
        return false;
    }

    public function getAttribute(string $name): mixed
    {
        return null;
    }

    public function setAttribute(string $name, mixed $value): void
    {
    }

    public function __serialize(): array
    {
        return ['userIdentifier' => $this->userIdentifier];
    }

    public function __unserialize(array $data): void
    {
        $this->userIdentifier = $data['userIdentifier'];
    }
}
