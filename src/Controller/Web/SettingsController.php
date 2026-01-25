<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\DTO\UserSettingsRequest;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for user settings pages.
 */
#[Route('/settings', name: 'app_settings_')]
#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_settings_profile');
    }

    #[Route('/profile', name: 'profile', methods: ['GET'])]
    public function profile(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('settings/profile.html.twig', [
            'settings' => $user->getSettingsWithDefaults(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'dateFormats' => UserSettingsRequest::VALID_DATE_FORMATS,
        ]);
    }

    #[Route('/security', name: 'security', methods: ['GET'])]
    public function security(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('settings/security.html.twig', [
            'twoFactorEnabled' => $user->isTwoFactorEnabled(),
            'backupCodesRemaining' => $user->getBackupCodesRemaining(),
        ]);
    }

    #[Route('/api-tokens', name: 'api_tokens', methods: ['GET'])]
    public function apiTokens(): Response
    {
        return $this->render('settings/api-tokens.html.twig');
    }

    #[Route('/data', name: 'data', methods: ['GET'])]
    public function data(): Response
    {
        return $this->render('settings/data.html.twig');
    }
}
