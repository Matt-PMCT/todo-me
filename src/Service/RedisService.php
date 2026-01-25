<?php

declare(strict_types=1);

namespace App\Service;

use Predis\Client;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;

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
            } catch (\Throwable $e) {
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
     * Ping Redis to check connectivity.
     *
     * @return bool True if Redis responds, false otherwise
     */
    public function ping(): bool
    {
        try {
            $client = $this->getClient();
            if ($client === null) {
                return false;
            }

            $result = $client->ping();

            return $result === 'PONG' || (string) $result === 'PONG';
        } catch (\Throwable $e) {
            $this->logger->error('Redis PING failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete all keys matching a pattern.
     * Uses SCAN to avoid blocking Redis with KEYS command.
     *
     * @param string $pattern The pattern to match (e.g., "user:123:*")
     *
     * @return int Number of keys deleted
     */
    public function deletePattern(string $pattern): int
    {
        try {
            $client = $this->getClient();
            if ($client === null) {
                return 0;
            }

            $deleted = 0;
            $cursor = '0';

            do {
                $result = $client->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 100]);
                $cursor = $result[0];
                $keys = $result[1];

                if (!empty($keys)) {
                    $deleted += $client->del($keys);
                }
            } while ($cursor !== '0');

            $this->logger->debug('Redis DELETEPATTERN', [
                'pattern' => $pattern,
                'deleted' => $deleted,
            ]);

            return $deleted;
        } catch (\Throwable $e) {
            $this->logger->error('Redis DELETEPATTERN failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
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
        } catch (\Throwable $e) {
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
     * @param string $key   The key to set
     * @param string $value The value to set
     * @param int    $ttl   Time to live in seconds (0 = no expiration)
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
     * @param string $key  The key to set
     * @param array  $data The data to JSON-encode and store
     * @param int    $ttl  Time to live in seconds (0 = no expiration)
     */
    public function setJson(string $key, array $data, int $ttl = 0): bool
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);

            return $this->set($key, $json, $ttl);
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            $this->logger->error('Redis getJson failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Atomically get a value and delete it (one-shot consume pattern).
     *
     * Uses a Lua script to ensure the operation is atomic, preventing race
     * conditions where concurrent requests could both read the same value
     * before either deletes it.
     *
     * @param string $key The key to get and delete
     *
     * @return string|null The value if key existed, null otherwise
     */
    public function getAndDelete(string $key): ?string
    {
        try {
            $client = $this->getClient();
            if ($client === null) {
                return null;
            }

            // Lua script: GET and DEL atomically
            // This ensures only one consumer can get the value
            $script = <<<'LUA'
                local value = redis.call('GET', KEYS[1])
                if value then
                    redis.call('DEL', KEYS[1])
                end
                return value
            LUA;

            $result = $client->eval($script, 1, $key);

            $this->logger->debug('Redis GETDEL (Lua)', [
                'key' => $key,
                'found' => $result !== null,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Redis GETDEL failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Atomically get a JSON value and delete the key.
     *
     * Combines getAndDelete() with JSON decoding for consuming one-time-use
     * JSON data like undo tokens.
     *
     * @param string $key The key to get and delete
     *
     * @return array|null The decoded JSON data if key existed and was valid JSON, null otherwise
     */
    public function getJsonAndDelete(string $key): ?array
    {
        try {
            $value = $this->getAndDelete($key);
            if ($value === null) {
                return null;
            }

            $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            $this->logger->error('Redis getJsonAndDelete failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get all keys matching a pattern.
     * Uses SCAN to avoid blocking Redis with KEYS command.
     *
     * @param string $pattern The pattern to match (e.g., "user:123:*")
     *
     * @return string[] Array of matching keys
     */
    public function keys(string $pattern): array
    {
        try {
            $client = $this->getClient();
            if ($client === null) {
                return [];
            }

            $allKeys = [];
            $cursor = '0';

            do {
                $result = $client->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 100]);
                $cursor = $result[0];
                $keys = $result[1];

                if (!empty($keys)) {
                    $allKeys = array_merge($allKeys, $keys);
                }
            } while ($cursor !== '0');

            $this->logger->debug('Redis KEYS (SCAN)', [
                'pattern' => $pattern,
                'count' => count($allKeys),
            ]);

            return $allKeys;
        } catch (\Throwable $e) {
            $this->logger->error('Redis KEYS failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
