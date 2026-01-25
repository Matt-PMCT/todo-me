<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Helper service for extracting API tokens from requests.
 */
final class TokenHelper
{
    private const BEARER_PREFIX = 'Bearer ';
    private const API_KEY_HEADER = 'X-API-Key';

    /**
     * Extracts the API token from the request headers.
     *
     * Checks Authorization header for Bearer token first,
     * then falls back to X-API-Key header.
     */
    public function extractFromRequest(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization', '');
        if (str_starts_with($authHeader, self::BEARER_PREFIX)) {
            $token = substr($authHeader, strlen(self::BEARER_PREFIX));
            return $token !== '' ? $token : null;
        }

        $apiKey = $request->headers->get(self::API_KEY_HEADER);
        if ($apiKey !== null && $apiKey !== '') {
            return $apiKey;
        }

        return null;
    }
}
