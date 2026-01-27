<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Issue #55: Validates APP_SECRET is properly configured.
 *
 * In production, rejects known insecure default values.
 * In dev mode, logs a warning if using insecure defaults.
 */
final class AppSecretValidator implements EventSubscriberInterface
{
    private const INSECURE_SECRETS = [
        'CHANGE_ME_TO_A_SECURE_SECRET_KEY',
        'change_me',
        'changeme',
        'secret',
        'app_secret',
        'your_secret',
        'test',
        'dev',
        'development',
    ];

    private bool $validated = false;

    public function __construct(
        private readonly string $appSecret,
        private readonly string $environment,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
            ConsoleEvents::COMMAND => ['onConsoleCommand', 256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->validate();
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->validated) {
            return;
        }

        $this->validated = true;

        $isInsecure = $this->isInsecureSecret($this->appSecret);

        if ($this->environment === 'prod' && $isInsecure) {
            throw new \RuntimeException(
                'APP_SECRET is set to an insecure default value. '
                .'Please set a secure, random value in your .env.local file. '
                .'You can generate one using: php -r "echo bin2hex(random_bytes(32));"'
            );
        }

        if ($isInsecure) {
            $this->logger->warning(
                'APP_SECRET is set to an insecure default value. '
                .'While acceptable in development, please change this before deploying to production.',
                ['environment' => $this->environment]
            );
        }
    }

    private function isInsecureSecret(string $secret): bool
    {
        // Check against known insecure defaults (case-insensitive)
        foreach (self::INSECURE_SECRETS as $insecure) {
            if (strcasecmp($secret, $insecure) === 0) {
                return true;
            }
        }

        // Check if secret is too short (less than 32 characters)
        if (strlen($secret) < 32) {
            return true;
        }

        return false;
    }
}
