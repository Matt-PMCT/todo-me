<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserSession;
use App\Exception\EntityNotFoundException;
use App\Exception\ForbiddenException;
use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service for managing user login sessions.
 */
final class SessionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserSessionRepository $sessionRepository,
    ) {
    }

    /**
     * Creates a new session record for a user login.
     *
     * @param User $user The user
     * @param string $apiToken The plain API token (will be hashed)
     * @param Request $request The current request
     * @return UserSession The created session
     */
    public function createSession(User $user, string $apiToken, Request $request): UserSession
    {
        $session = new UserSession();
        $session->setOwner($user);
        $session->setTokenHash(hash('sha256', $apiToken));
        $session->setUserAgent($this->truncateUserAgent($request->headers->get('User-Agent')));
        $session->setIpAddress($request->getClientIp());
        $session->setDevice($this->parseDevice($request->headers->get('User-Agent')));
        $session->setBrowser($this->parseBrowser($request->headers->get('User-Agent')));

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    /**
     * Lists all sessions for a user.
     *
     * @param User $user The user
     * @return UserSession[]
     */
    public function listSessions(User $user): array
    {
        return $this->sessionRepository->findByOwner($user);
    }

    /**
     * Revokes a specific session.
     *
     * @param User $user The user (for ownership verification)
     * @param string $sessionId The session ID to revoke
     * @throws EntityNotFoundException If session not found
     * @throws ForbiddenException If user doesn't own the session
     */
    public function revokeSession(User $user, string $sessionId): void
    {
        $session = $this->sessionRepository->find($sessionId);

        if ($session === null) {
            throw EntityNotFoundException::forResource('Session', $sessionId);
        }

        if ($session->getOwner()?->getId() !== $user->getId()) {
            throw ForbiddenException::notOwner('Session');
        }

        $this->entityManager->remove($session);
        $this->entityManager->flush();
    }

    /**
     * Revokes all sessions for a user except the current one.
     *
     * @param User $user The user
     * @param string $currentToken The current API token (plain text)
     * @return int Number of sessions revoked
     */
    public function revokeOtherSessions(User $user, string $currentToken): int
    {
        $currentTokenHash = hash('sha256', $currentToken);

        return $this->sessionRepository->deleteByOwnerExcept($user, $currentTokenHash);
    }

    /**
     * Updates the last active timestamp for a session.
     *
     * @param string $tokenHash SHA256 hash of the API token
     */
    public function updateLastActive(string $tokenHash): void
    {
        $this->sessionRepository->updateLastActive($tokenHash);
    }

    /**
     * Finds a session by token hash.
     *
     * @param string $tokenHash SHA256 hash of the API token
     * @return UserSession|null
     */
    public function findByTokenHash(string $tokenHash): ?UserSession
    {
        return $this->sessionRepository->findByTokenHash($tokenHash);
    }

    /**
     * Deletes the session associated with a token.
     *
     * @param string $apiToken The plain API token
     */
    public function deleteSessionByToken(string $apiToken): void
    {
        $tokenHash = hash('sha256', $apiToken);
        $session = $this->sessionRepository->findByTokenHash($tokenHash);

        if ($session !== null) {
            $this->entityManager->remove($session);
            $this->entityManager->flush();
        }
    }

    /**
     * Parses the device type from user agent.
     *
     * @param string|null $userAgent The user agent string
     * @return string|null Device type (Mobile, Tablet, Desktop)
     */
    private function parseDevice(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        $userAgentLower = strtolower($userAgent);

        // Check for mobile devices first
        if (
            str_contains($userAgentLower, 'mobile') ||
            str_contains($userAgentLower, 'iphone') ||
            str_contains($userAgentLower, 'android') && str_contains($userAgentLower, 'mobile')
        ) {
            return 'Mobile';
        }

        // Check for tablets
        if (
            str_contains($userAgentLower, 'tablet') ||
            str_contains($userAgentLower, 'ipad') ||
            str_contains($userAgentLower, 'android') && !str_contains($userAgentLower, 'mobile')
        ) {
            return 'Tablet';
        }

        // Check for desktop platforms
        if (
            str_contains($userAgentLower, 'windows') ||
            str_contains($userAgentLower, 'macintosh') ||
            str_contains($userAgentLower, 'mac os') ||
            str_contains($userAgentLower, 'linux') && !str_contains($userAgentLower, 'android')
        ) {
            return 'Desktop';
        }

        return 'Unknown';
    }

    /**
     * Parses the browser name from user agent.
     *
     * @param string|null $userAgent The user agent string
     * @return string|null Browser name
     */
    private function parseBrowser(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        $userAgentLower = strtolower($userAgent);

        // Order matters - check more specific browsers first
        if (str_contains($userAgentLower, 'edg/') || str_contains($userAgentLower, 'edge/')) {
            return 'Edge';
        }

        if (str_contains($userAgentLower, 'opr/') || str_contains($userAgentLower, 'opera')) {
            return 'Opera';
        }

        if (str_contains($userAgentLower, 'chrome') && !str_contains($userAgentLower, 'chromium')) {
            return 'Chrome';
        }

        if (str_contains($userAgentLower, 'chromium')) {
            return 'Chromium';
        }

        if (str_contains($userAgentLower, 'safari') && !str_contains($userAgentLower, 'chrome')) {
            return 'Safari';
        }

        if (str_contains($userAgentLower, 'firefox')) {
            return 'Firefox';
        }

        if (str_contains($userAgentLower, 'msie') || str_contains($userAgentLower, 'trident/')) {
            return 'Internet Explorer';
        }

        return 'Unknown';
    }

    /**
     * Truncates user agent to fit database column.
     *
     * @param string|null $userAgent The user agent string
     * @return string|null Truncated user agent
     */
    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return mb_substr($userAgent, 0, 500);
    }
}
