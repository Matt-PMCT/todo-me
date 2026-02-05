<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Interface\SyncServiceInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Twig extension that provides sync version as a global variable.
 *
 * Makes `sync_version` and `sync_interval` available in all templates
 * for authenticated users.
 */
final class SyncExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly SyncServiceInterface $syncService,
        private readonly Security $security,
    ) {
    }

    public function getGlobals(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return [
                'sync_version' => 0,
                'sync_interval' => 0,
            ];
        }

        return [
            'sync_version' => $this->syncService->getCurrentVersion($user),
            'sync_interval' => $user->getSyncPollingInterval(),
        ];
    }
}
