<?php

declare(strict_types=1);

namespace App\Service;

use Predis\Client;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class RedisService
{
    private ?ClientInterface $client = null;
    private bool $connected = false;

    public function __construct(
        private readonly string $redisUrl,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get the Redis client, lazily initializing the connection.
     */
    private function getClient(): ?ClientInterface
    {
        if ($this->client === null) {
            try {
                $this->client = new Client($this->redisUrl, [
                    'exceptions' => true,
                ]);
                // Test the connection
                $this->client->ping();
                $this->connected = true;
                $this->logger->debug('Redis connection established');
            } catch (Throwable $e) {
                $this->logger->error('Failed to connect to Redis', [
                    'error' => $e->getMessage(),
                    'url' => $this->redisUrl,
                ]);
                $this->client = null;
                $this->connected = false;
            }
        }

        return $this->client;
    }

    /**
     * Check if Redis is connected.
     */
    public function isConnected(): bool
    {
        $this->getClient();
        return $this->connected;
    }

    /**
     * Get a value from Redis.
     */
    public function get(string $key): ?string
    {
        try {
            $client = $this->getClient();
            if ($client === null) {
                return null;
            }

            $value = $client->get($key);
            $this->logger->debug('Redis GET', ['key' => $key, 'found' => $value !== null]);

            return $value;
        } catch (Throwable $e) {
            $this->logger->error('Redis GET failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Set a value in Redis.
     *
     * @param string $key The key to set
     * @param string $value The value to set
     * @param int $ttl Time to live in seconds (0 = no expiration)
     */
    public function set(string $key, string $value, int $ttl = 0): bool
    {
        try {
            $client = $this->getClient();
            if ($client === null) {
                return false;
            }

            if ($ttl > 0) {
                $result = $client->setex($key, $ttl, $value);
            } else {
                $result = $client->set($key, $value);
            }

            $success = $result !== null && (string) $result === 'OK';
            $this->logger->debug('Redis SET', [
                'key' => $key,
                'ttl' => $ttl,
                'success' => $success,
            ]);

            return $success;
        } catch (Throwable $e) {
            $this->logger->error('Redis SET failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete a key from Redis.
     */
    public function delete(string $key): bool
    {
        try {
            $client = $this->getClient();
            if ($client === null) {
                return false;
            }

            $result = $client->del([$key]);
            $success = $result >= 1;
            $this->logger->debug('Redis DEL', ['key' => $key, 'success' => $success]);

            return $success;
        } catch (Throwable $e) {
            $this->logger->error('Redis DEL failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if a key exists in Redis.
     */
    public function exists(string $key): bool
    {
        try {
            $client = $this->getClient();
            if ($client === null) {
                return false;
            }

            $exists = $client->exists($key) > 0;
            $this->logger->debug('Redis EXISTS', ['key' => $key, 'exists' => $exists]);

            return $exists;
        } catch (Throwable $e) {
            $this->logger->error('Redis EXISTS failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Increment a key's value.
     * If the key does not exist, it is set to 0 before performing the operation.
     */
    public function increment(string $key): int
    {
        try {
            $client = $this->getClient();
            if ($client === null) {
                return 0;
            }

            $result = $client->incr($key);
            $this->logger->debug('Redis INCR', ['key' => $key, 'value' => $result]);

            return (int) $result;
        } catch (Throwable $e) {
            $this->logger->error('Redis INCR failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Set a timeout on a key.
     */
    public function expire(string $key, int $ttl): bool
    {
        try {
            $client = $this->getClient();
            if ($client === null) {
                return false;
            }

            $result = $client->expire($key, $ttl);
            $success = $result === 1;
            $this->logger->debug('Redis EXPIRE', [
                'key' => $key,
                'ttl' => $ttl,
                'success' => $success,
            ]);

            return $success;
        } catch (Throwable $e) {
            $this->logger->error('Redis EXPIRE failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Set a JSON-encoded value in Redis.
     *
     * @param string $key The key to set
     * @param array $data The data to JSON-encode and store
     * @param int $ttl Time to live in seconds (0 = no expiration)
     */
    public function setJson(string $key, array $data, int $ttl = 0): bool
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            return $this->set($key, $json, $ttl);
        } catch (Throwable $e) {
            $this->logger->error('Redis setJson failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get and decode a JSON value from Redis.
     */
    public function getJson(string $key): ?array
    {
        try {
            $value = $this->get($key);
            if ($value === null) {
                return null;
            }

            $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : null;
        } catch (Throwable $e) {
            $this->logger->error('Redis getJson failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
