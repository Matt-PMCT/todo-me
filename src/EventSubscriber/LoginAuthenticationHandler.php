<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AccountLockoutService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginAuthenticationHandler implements EventSubscriberInterface
{
    public function __construct(
        private readonly AccountLockoutService $accountLockoutService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $this->accountLockoutService->recordSuccessfulLogin($user);
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $passport = $event->getPassport();
        if ($passport === null) {
            return;
        }

        $userBadge = $passport->getBadge(UserBadge::class);
        if ($userBadge === null) {
            return;
        }

        $email = $userBadge->getUserIdentifier();
        $user = $this->userRepository->findByEmail($email);

        if ($user !== null) {
            $this->accountLockoutService->recordFailedAttempt($user);
        }
    }
}
