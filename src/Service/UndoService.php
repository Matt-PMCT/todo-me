<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\UndoAction;
use App\ValueObject\UndoToken;
use Psr\Log\LoggerInterface;

class UndoService
{
    private const KEY_PREFIX = 'undo';
    private const DEFAULT_TTL = 60;

    public function __construct(
        private readonly RedisService $redisService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create an undo token for a given action.
     *
     * @param string $userId The user ID to scope the token to
     * @param string $action The action type (delete, update, status_change, archive)
     * @param string $entityType The type of entity (e.g., 'todo', 'category')
     * @param string $entityId The entity ID
     * @param array $previousState The previous state of the entity for restoration
     * @param int $ttl Time to live in seconds (default 60)
     */
    public function createUndoToken(
        string $userId,
        string $action,
        string $entityType,
        string $entityId,
        array $previousState,
        int $ttl = self::DEFAULT_TTL
    ): ?UndoToken {
        // Validate the action
        if (!UndoAction::isValid($action)) {
            $this->logger->warning('Invalid undo action attempted', [
                'action' => $action,
                'userId' => $userId,
                'entityType' => $entityType,
                'entityId' => $entityId,
            ]);
            return null;
        }

        $token = UndoToken::create(
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            previousState: $previousState,
            userId: $userId,
            ttl: $ttl
        );

        $key = $this->buildKey($userId, $token->token);
        $success = $this->redisService->setJson($key, $token->toArray(), $ttl);

        if (!$success) {
            $this->logger->error('Failed to store undo token in Redis', [
                'userId' => $userId,
                'token' => $token->token,
                'action' => $action,
                'entityType' => $entityType,
                'entityId' => $entityId,
            ]);
            return null;
        }

        $this->logger->info('Undo token created', [
            'userId' => $userId,
            'token' => $token->token,
            'action' => $action,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'ttl' => $ttl,
        ]);

        return $token;
    }

    /**
     * Get an undo token without consuming it.
     *
     * @param string $userId The user ID
     * @param string $token The token string
     */
    public function getUndoToken(string $userId, string $token): ?UndoToken
    {
        $key = $this->buildKey($userId, $token);
        $data = $this->redisService->getJson($key);

        if ($data === null) {
            $this->logger->debug('Undo token not found', [
                'userId' => $userId,
                'token' => $token,
            ]);
            return null;
        }

        try {
            $undoToken = UndoToken::fromArray($data);

            if ($undoToken->isExpired()) {
                $this->logger->debug('Undo token has expired', [
                    'userId' => $userId,
                    'token' => $token,
                ]);
                // Clean up expired token
                $this->redisService->delete($key);
                return null;
            }

            return $undoToken;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to deserialize undo token', [
                'userId' => $userId,
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Consume an undo token (get and delete - one-time use).
     *
     * @param string $userId The user ID
     * @param string $token The token string
     */
    public function consumeUndoToken(string $userId, string $token): ?UndoToken
    {
        $undoToken = $this->getUndoToken($userId, $token);

        if ($undoToken === null) {
            return null;
        }

        // Delete the token after retrieval (one-time use)
        $key = $this->buildKey($userId, $token);
        $deleted = $this->redisService->delete($key);

        if (!$deleted) {
            $this->logger->warning('Failed to delete consumed undo token', [
                'userId' => $userId,
                'token' => $token,
            ]);
            // Still return the token even if deletion failed
        }

        $this->logger->info('Undo token consumed', [
            'userId' => $userId,
            'token' => $token,
            'action' => $undoToken->action,
            'entityType' => $undoToken->entityType,
            'entityId' => $undoToken->entityId,
        ]);

        return $undoToken;
    }

    /**
     * Check if a valid (non-expired) token exists.
     *
     * @param string $userId The user ID
     * @param string $token The token string
     */
    public function hasValidToken(string $userId, string $token): bool
    {
        return $this->getUndoToken($userId, $token) !== null;
    }

    /**
     * Build the Redis key for an undo token.
     */
    private function buildKey(string $userId, string $token): string
    {
        return sprintf('%s:%s:%s', self::KEY_PREFIX, $userId, $token);
    }
}
