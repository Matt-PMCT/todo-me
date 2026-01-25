<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $appUrl,
        private readonly string $mailFromAddress,
        private readonly string $mailFromName,
    ) {
    }

    public function sendPasswordResetEmail(User $user, string $token): void
    {
        $resetUrl = $this->appUrl . '/reset-password?token=' . $token;

        $html = $this->twig->render('email/password-reset.html.twig', [
            'user' => $user,
            'resetUrl' => $resetUrl,
            'expiresInMinutes' => 60,
        ]);

        $this->sendEmail($user->getEmail(), 'Reset Your Password', $html);
    }

    public function sendEmailVerification(User $user, string $token): void
    {
        $verifyUrl = $this->appUrl . '/verify-email/' . $token;

        $html = $this->twig->render('email/verify-email.html.twig', [
            'user' => $user,
            'verifyUrl' => $verifyUrl,
            'expiresInHours' => 24,
        ]);

        $this->sendEmail($user->getEmail(), 'Verify Your Email Address', $html);
    }

    public function sendPasswordChangedNotification(User $user): void
    {
        $html = $this->twig->render('email/password-changed.html.twig', [
            'user' => $user,
            'changedAt' => new \DateTimeImmutable(),
        ]);

        $this->sendEmail($user->getEmail(), 'Your Password Has Been Changed', $html);
    }

    public function sendAccountLockedNotification(User $user, int $lockoutMinutes): void
    {
        $html = $this->twig->render('email/account-locked.html.twig', [
            'user' => $user,
            'lockoutMinutes' => $lockoutMinutes,
        ]);

        $this->sendEmail($user->getEmail(), 'Your Account Has Been Locked', $html);
    }

    public function send2faRecoveryEmail(User $user, string $token): void
    {
        $recoveryUrl = $this->appUrl . '/2fa-recovery?token=' . $token;

        $html = $this->twig->render('email/2fa-recovery.html.twig', [
            'user' => $user,
            'recoveryUrl' => $recoveryUrl,
            'expiresInHours' => 24,
        ]);

        $this->sendEmail($user->getEmail(), 'Disable Two-Factor Authentication', $html);
    }

    private function sendEmail(string $to, string $subject, string $html): void
    {
        $email = (new Email())
            ->from($this->mailFromName . ' <' . $this->mailFromAddress . '>')
            ->to($to)
            ->subject($subject)
            ->html($html);

        $this->mailer->send($email);
    }
}
