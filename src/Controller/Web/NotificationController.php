<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for notification web pages.
 */
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    #[Route('/notifications', name: 'app_notifications', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get filter parameter
        $filter = $request->query->get('filter', 'all');

        // Get pagination parameters
        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * self::ITEMS_PER_PAGE;

        // Get notifications based on filter
        if ($filter === 'unread') {
            $notifications = $this->notificationRepository->findUnreadByOwner($user, self::ITEMS_PER_PAGE);
            $totalCount = $this->notificationRepository->countUnreadByOwner($user);
        } else {
            $notifications = $this->notificationRepository->findByOwner($user, self::ITEMS_PER_PAGE, $offset);
            $totalCount = $this->notificationRepository->countByOwner($user);
        }

        // Calculate pagination
        $totalPages = (int) ceil($totalCount / self::ITEMS_PER_PAGE);
        $unreadCount = $this->notificationRepository->countUnreadByOwner($user);

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
            'filter' => $filter,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/settings/notifications', name: 'app_settings_notifications', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('settings/notifications.html.twig');
    }
}
