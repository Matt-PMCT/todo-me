# Phase 10: Email Infrastructure & Account Security

## Overview
**Goal**: Establish email infrastructure and implement critical account security features including password reset, email verification, password change, and account lockout protection.

## Revision History
- **2026-01-24**: Initial creation based on comprehensive project review

## Prerequisites
- Phases 1-9 completed (or in progress)
- Docker environment functional
- User entity and authentication system working

---

## Why This Phase is Critical

Without email infrastructure:
- Password reset flow (mentioned in Phase 9.8.3) cannot function
- Users who forget passwords are permanently locked out
- No way to verify email addresses (spam/impersonation risk)
- No foundation for notifications (Phase 12)

---

## Sub-Phase 10.1: Email Service Infrastructure

### Objective
Configure Symfony Mailer with support for multiple providers (SMTP, Mailgun, SES).

### Tasks

- [ ] **10.1.1** Install and configure Symfony Mailer
  ```bash
  composer require symfony/mailer
  ```

- [ ] **10.1.2** Create mailer configuration
  ```yaml
  # config/packages/mailer.yaml
  framework:
      mailer:
          dsn: '%env(MAILER_DSN)%'
          envelope:
              sender: '%env(MAIL_FROM_ADDRESS)%'
          headers:
              From: '%env(MAIL_FROM_NAME)% <%env(MAIL_FROM_ADDRESS)%>'
  ```

- [ ] **10.1.3** Add environment variables
  ```bash
  # .env.local.example additions

  ###> symfony/mailer ###
  # SMTP: smtp://user:pass@smtp.example.com:587
  # Mailgun: mailgun+smtp://USERNAME:PASSWORD@default?region=us
  # SES: ses+smtp://ACCESS_KEY:SECRET_KEY@default?region=us-east-1
  MAILER_DSN=smtp://localhost:1025
  MAIL_FROM_ADDRESS=noreply@todo-me.local
  MAIL_FROM_NAME="Todo-Me"
  ###< symfony/mailer ###
  ```

- [ ] **10.1.4** Create EmailService
  ```php
  // src/Service/EmailService.php

  declare(strict_types=1);

  namespace App\Service;

  use Psr\Log\LoggerInterface;
  use Symfony\Bridge\Twig\Mime\TemplatedEmail;
  use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
  use Symfony\Component\Mailer\MailerInterface;
  use Symfony\Component\Mime\Address;

  final class EmailService
  {
      public function __construct(
          private readonly MailerInterface $mailer,
          private readonly LoggerInterface $logger,
          private readonly string $fromAddress,
          private readonly string $fromName,
          private readonly string $appUrl,
      ) {}

      public function sendPasswordResetEmail(string $to, string $token, string $username): void
      {
          $resetUrl = sprintf('%s/reset-password/%s', $this->appUrl, $token);

          $email = (new TemplatedEmail())
              ->from(new Address($this->fromAddress, $this->fromName))
              ->to($to)
              ->subject('Reset Your Password')
              ->htmlTemplate('emails/password-reset.html.twig')
              ->context([
                  'resetUrl' => $resetUrl,
                  'username' => $username,
                  'expiresInMinutes' => 60,
              ]);

          $this->send($email);
      }

      public function sendEmailVerification(string $to, string $token, string $username): void
      {
          $verifyUrl = sprintf('%s/verify-email/%s', $this->appUrl, $token);

          $email = (new TemplatedEmail())
              ->from(new Address($this->fromAddress, $this->fromName))
              ->to($to)
              ->subject('Verify Your Email Address')
              ->htmlTemplate('emails/verify-email.html.twig')
              ->context([
                  'verifyUrl' => $verifyUrl,
                  'username' => $username,
              ]);

          $this->send($email);
      }

      public function sendPasswordChangedNotification(string $to, string $username): void
      {
          $email = (new TemplatedEmail())
              ->from(new Address($this->fromAddress, $this->fromName))
              ->to($to)
              ->subject('Your Password Was Changed')
              ->htmlTemplate('emails/password-changed.html.twig')
              ->context([
                  'username' => $username,
                  'changedAt' => new \DateTimeImmutable(),
              ]);

          $this->send($email);
      }

      public function sendAccountLockedNotification(string $to, string $username, int $unlockMinutes): void
      {
          $email = (new TemplatedEmail())
              ->from(new Address($this->fromAddress, $this->fromName))
              ->to($to)
              ->subject('Account Temporarily Locked')
              ->htmlTemplate('emails/account-locked.html.twig')
              ->context([
                  'username' => $username,
                  'unlockMinutes' => $unlockMinutes,
              ]);

          $this->send($email);
      }

      private function send(TemplatedEmail $email): void
      {
          try {
              $this->mailer->send($email);
          } catch (TransportExceptionInterface $e) {
              $this->logger->error('Failed to send email', [
                  'to' => $email->getTo()[0]->getAddress(),
                  'subject' => $email->getSubject(),
                  'error' => $e->getMessage(),
              ]);
              throw $e;
          }
      }
  }
  ```

- [ ] **10.1.5** Register EmailService in services.yaml
  ```yaml
  # config/services.yaml

  App\Service\EmailService:
      arguments:
          $fromAddress: '%env(MAIL_FROM_ADDRESS)%'
          $fromName: '%env(MAIL_FROM_NAME)%'
          $appUrl: '%env(APP_URL)%'
  ```

- [ ] **10.1.6** Create email templates
  ```twig
  {# templates/emails/base.html.twig #}
  <!DOCTYPE html>
  <html>
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <style>
          body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
          .container { max-width: 600px; margin: 0 auto; padding: 20px; }
          .header { text-align: center; padding: 20px 0; border-bottom: 1px solid #eee; }
          .content { padding: 30px 0; }
          .button { display: inline-block; padding: 12px 24px; background-color: #4f46e5; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; }
          .footer { padding: 20px 0; border-top: 1px solid #eee; font-size: 12px; color: #666; text-align: center; }
      </style>
  </head>
  <body>
      <div class="container">
          <div class="header">
              <h1 style="margin: 0; color: #4f46e5;">Todo-Me</h1>
          </div>
          <div class="content">
              {% block content %}{% endblock %}
          </div>
          <div class="footer">
              <p>This email was sent by Todo-Me. If you didn't request this, you can safely ignore it.</p>
          </div>
      </div>
  </body>
  </html>
  ```

  ```twig
  {# templates/emails/password-reset.html.twig #}
  {% extends 'emails/base.html.twig' %}

  {% block content %}
  <h2>Reset Your Password</h2>
  <p>Hi {{ username }},</p>
  <p>We received a request to reset your password. Click the button below to create a new password:</p>
  <p style="text-align: center; margin: 30px 0;">
      <a href="{{ resetUrl }}" class="button">Reset Password</a>
  </p>
  <p>This link will expire in {{ expiresInMinutes }} minutes.</p>
  <p>If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.</p>
  {% endblock %}
  ```

  ```twig
  {# templates/emails/verify-email.html.twig #}
  {% extends 'emails/base.html.twig' %}

  {% block content %}
  <h2>Verify Your Email Address</h2>
  <p>Hi {{ username }},</p>
  <p>Thanks for signing up! Please verify your email address by clicking the button below:</p>
  <p style="text-align: center; margin: 30px 0;">
      <a href="{{ verifyUrl }}" class="button">Verify Email</a>
  </p>
  <p>If you didn't create an account, you can safely ignore this email.</p>
  {% endblock %}
  ```

  ```twig
  {# templates/emails/password-changed.html.twig #}
  {% extends 'emails/base.html.twig' %}

  {% block content %}
  <h2>Password Changed</h2>
  <p>Hi {{ username }},</p>
  <p>Your password was successfully changed on {{ changedAt|date('F j, Y \\a\\t g:i A') }}.</p>
  <p>If you didn't make this change, please reset your password immediately and contact support.</p>
  {% endblock %}
  ```

  ```twig
  {# templates/emails/account-locked.html.twig #}
  {% extends 'emails/base.html.twig' %}

  {% block content %}
  <h2>Account Temporarily Locked</h2>
  <p>Hi {{ username }},</p>
  <p>Your account has been temporarily locked due to multiple failed login attempts.</p>
  <p>You can try again in {{ unlockMinutes }} minutes, or reset your password if you've forgotten it.</p>
  <p>If this wasn't you, someone may be trying to access your account. Consider changing your password.</p>
  {% endblock %}
  ```

### Completion Criteria
- [ ] Mailer configured and tested
- [ ] EmailService sends templated emails
- [ ] All email templates created
- [ ] Environment variables documented

### Files to Create
```
config/packages/mailer.yaml
src/Service/EmailService.php
templates/emails/base.html.twig
templates/emails/password-reset.html.twig
templates/emails/verify-email.html.twig
templates/emails/password-changed.html.twig
templates/emails/account-locked.html.twig
```

---

## Sub-Phase 10.2: Email Verification

### Objective
Require email verification for new accounts to prevent spam and impersonation.

### Tasks

- [ ] **10.2.1** Add verification fields to User entity
  ```php
  // src/Entity/User.php additions

  #[ORM\Column(type: Types::BOOLEAN, name: 'email_verified', options: ['default' => false])]
  private bool $emailVerified = false;

  #[ORM\Column(type: Types::STRING, length: 64, nullable: true, name: 'email_verification_token')]
  private ?string $emailVerificationToken = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'email_verification_sent_at')]
  private ?\DateTimeImmutable $emailVerificationSentAt = null;

  public function isEmailVerified(): bool
  {
      return $this->emailVerified;
  }

  public function setEmailVerified(bool $verified): self
  {
      $this->emailVerified = $verified;
      return $this;
  }

  public function getEmailVerificationToken(): ?string
  {
      return $this->emailVerificationToken;
  }

  public function setEmailVerificationToken(?string $token): self
  {
      $this->emailVerificationToken = $token;
      return $this;
  }

  public function getEmailVerificationSentAt(): ?\DateTimeImmutable
  {
      return $this->emailVerificationSentAt;
  }

  public function setEmailVerificationSentAt(?\DateTimeImmutable $sentAt): self
  {
      $this->emailVerificationSentAt = $sentAt;
      return $this;
  }
  ```

- [ ] **10.2.2** Create database migration
  ```php
  // migrations/Version20260124_EmailVerification.php

  public function up(Schema $schema): void
  {
      $this->addSql('ALTER TABLE users ADD email_verified BOOLEAN NOT NULL DEFAULT FALSE');
      $this->addSql('ALTER TABLE users ADD email_verification_token VARCHAR(64) DEFAULT NULL');
      $this->addSql('ALTER TABLE users ADD email_verification_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
      $this->addSql('CREATE INDEX idx_users_verification_token ON users (email_verification_token)');

      // Mark existing users as verified (they registered before this feature)
      $this->addSql('UPDATE users SET email_verified = TRUE');
  }
  ```

- [ ] **10.2.3** Create EmailVerificationService
  ```php
  // src/Service/EmailVerificationService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\User;
  use App\Exception\ValidationException;
  use App\Repository\UserRepository;
  use Doctrine\ORM\EntityManagerInterface;

  final class EmailVerificationService
  {
      private const TOKEN_EXPIRY_HOURS = 24;
      private const RESEND_COOLDOWN_MINUTES = 5;

      public function __construct(
          private readonly EntityManagerInterface $entityManager,
          private readonly UserRepository $userRepository,
          private readonly TokenGenerator $tokenGenerator,
          private readonly EmailService $emailService,
      ) {}

      public function sendVerificationEmail(User $user): void
      {
          // Check cooldown
          if ($user->getEmailVerificationSentAt() !== null) {
              $cooldownEnd = $user->getEmailVerificationSentAt()->modify('+' . self::RESEND_COOLDOWN_MINUTES . ' minutes');
              if (new \DateTimeImmutable() < $cooldownEnd) {
                  $remainingSeconds = $cooldownEnd->getTimestamp() - time();
                  throw ValidationException::forField(
                      'email',
                      sprintf('Please wait %d seconds before requesting another verification email', $remainingSeconds)
                  );
              }
          }

          $token = $this->tokenGenerator->generateSecureToken();

          $user->setEmailVerificationToken(hash('sha256', $token));
          $user->setEmailVerificationSentAt(new \DateTimeImmutable());
          $this->entityManager->flush();

          $this->emailService->sendEmailVerification(
              $user->getEmail(),
              $token,
              $user->getUsername()
          );
      }

      public function verifyEmail(string $token): User
      {
          $tokenHash = hash('sha256', $token);
          $user = $this->userRepository->findOneBy(['emailVerificationToken' => $tokenHash]);

          if ($user === null) {
              throw ValidationException::forField('token', 'Invalid or expired verification token');
          }

          // Check expiry
          $expiresAt = $user->getEmailVerificationSentAt()?->modify('+' . self::TOKEN_EXPIRY_HOURS . ' hours');
          if ($expiresAt === null || new \DateTimeImmutable() > $expiresAt) {
              throw ValidationException::forField('token', 'Verification token has expired');
          }

          $user->setEmailVerified(true);
          $user->setEmailVerificationToken(null);
          $user->setEmailVerificationSentAt(null);
          $this->entityManager->flush();

          return $user;
      }

      public function isVerificationRequired(User $user): bool
      {
          return !$user->isEmailVerified();
      }
  }
  ```

- [ ] **10.2.4** Update registration to send verification email
  ```php
  // src/Service/UserService.php modifications

  public function register(RegisterRequest $request): User
  {
      // ... existing validation and user creation ...

      $this->entityManager->persist($user);
      $this->entityManager->flush();

      // Send verification email
      $this->emailVerificationService->sendVerificationEmail($user);

      return $user;
  }
  ```

- [ ] **10.2.5** Create verification endpoints
  ```php
  // src/Controller/Api/AuthController.php additions

  /**
   * Verify email address.
   */
  #[Route('/verify-email/{token}', name: 'verify_email', methods: ['POST'])]
  public function verifyEmail(string $token): JsonResponse
  {
      $user = $this->emailVerificationService->verifyEmail($token);

      return $this->responseFormatter->success([
          'message' => 'Email verified successfully',
          'user' => [
              'id' => $user->getId(),
              'email' => $user->getEmail(),
              'emailVerified' => true,
          ],
      ]);
  }

  /**
   * Resend verification email.
   */
  #[Route('/resend-verification', name: 'resend_verification', methods: ['POST'])]
  public function resendVerification(Request $request): JsonResponse
  {
      $data = $this->validationHelper->decodeJsonBody($request);
      $email = $data['email'] ?? null;

      if ($email === null) {
          throw ValidationException::forField('email', 'Email is required');
      }

      $user = $this->userRepository->findOneBy(['email' => $email]);

      if ($user === null) {
          // Don't reveal if email exists
          return $this->responseFormatter->success([
              'message' => 'If an account exists with this email, a verification link has been sent',
          ]);
      }

      if ($user->isEmailVerified()) {
          return $this->responseFormatter->success([
              'message' => 'Email is already verified',
          ]);
      }

      $this->emailVerificationService->sendVerificationEmail($user);

      return $this->responseFormatter->success([
          'message' => 'If an account exists with this email, a verification link has been sent',
      ]);
  }
  ```

- [ ] **10.2.6** Create web verification page
  ```twig
  {# templates/security/verify-email.html.twig #}
  {% extends 'base.html.twig' %}

  {% block body %}
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
      <div class="max-w-md w-full space-y-8">
          {% if verified %}
          <div class="text-center">
              <svg class="mx-auto h-16 w-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Email Verified!</h2>
              <p class="mt-2 text-sm text-gray-600">Your email has been successfully verified.</p>
              <a href="{{ path('app_login') }}" class="mt-4 inline-block bg-indigo-600 text-white px-6 py-2 rounded-md font-semibold">
                  Sign In
              </a>
          </div>
          {% else %}
          <div class="text-center">
              <svg class="mx-auto h-16 w-16 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
              <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Verification Failed</h2>
              <p class="mt-2 text-sm text-gray-600">{{ error ?? 'Invalid or expired verification link.' }}</p>
              <a href="{{ path('app_login') }}" class="mt-4 inline-block text-indigo-600 font-medium">
                  Back to Sign In
              </a>
          </div>
          {% endif %}
      </div>
  </div>
  {% endblock %}
  ```

### Completion Criteria
- [ ] New users receive verification email
- [ ] Verification token validates correctly
- [ ] Token expiry enforced (24 hours)
- [ ] Resend with cooldown (5 minutes)
- [ ] Existing users marked as verified

### Files to Create/Update
```
src/Entity/User.php (update)
src/Service/EmailVerificationService.php (new)
src/Controller/Api/AuthController.php (update)
src/Controller/Web/SecurityController.php (update)
templates/security/verify-email.html.twig (new)
migrations/Version20260124_EmailVerification.php (new)
```

---

## Sub-Phase 10.3: Password Reset Flow

### Objective
Implement secure password reset via email.

### Tasks

- [ ] **10.3.1** Add password reset fields to User entity
  ```php
  // src/Entity/User.php additions

  #[ORM\Column(type: Types::STRING, length: 64, nullable: true, name: 'password_reset_token')]
  private ?string $passwordResetToken = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'password_reset_expires_at')]
  private ?\DateTimeImmutable $passwordResetExpiresAt = null;

  public function getPasswordResetToken(): ?string
  {
      return $this->passwordResetToken;
  }

  public function setPasswordResetToken(?string $token): self
  {
      $this->passwordResetToken = $token;
      return $this;
  }

  public function getPasswordResetExpiresAt(): ?\DateTimeImmutable
  {
      return $this->passwordResetExpiresAt;
  }

  public function setPasswordResetExpiresAt(?\DateTimeImmutable $expiresAt): self
  {
      $this->passwordResetExpiresAt = $expiresAt;
      return $this;
  }

  public function isPasswordResetTokenValid(): bool
  {
      if ($this->passwordResetToken === null || $this->passwordResetExpiresAt === null) {
          return false;
      }
      return new \DateTimeImmutable() < $this->passwordResetExpiresAt;
  }
  ```

- [ ] **10.3.2** Create database migration
  ```php
  // migrations/Version20260124_PasswordReset.php

  public function up(Schema $schema): void
  {
      $this->addSql('ALTER TABLE users ADD password_reset_token VARCHAR(64) DEFAULT NULL');
      $this->addSql('ALTER TABLE users ADD password_reset_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
      $this->addSql('CREATE INDEX idx_users_password_reset_token ON users (password_reset_token)');
  }
  ```

- [ ] **10.3.3** Create PasswordResetService
  ```php
  // src/Service/PasswordResetService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\User;
  use App\Exception\ValidationException;
  use App\Repository\UserRepository;
  use Doctrine\ORM\EntityManagerInterface;
  use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

  final class PasswordResetService
  {
      private const TOKEN_EXPIRY_MINUTES = 60;

      public function __construct(
          private readonly EntityManagerInterface $entityManager,
          private readonly UserRepository $userRepository,
          private readonly UserPasswordHasherInterface $passwordHasher,
          private readonly TokenGenerator $tokenGenerator,
          private readonly EmailService $emailService,
      ) {}

      /**
       * Request a password reset. Always returns success to prevent email enumeration.
       */
      public function requestReset(string $email): void
      {
          $user = $this->userRepository->findOneBy(['email' => $email]);

          if ($user === null) {
              // Don't reveal if email exists - just return silently
              return;
          }

          $token = $this->tokenGenerator->generateSecureToken();

          $user->setPasswordResetToken(hash('sha256', $token));
          $user->setPasswordResetExpiresAt(
              (new \DateTimeImmutable())->modify('+' . self::TOKEN_EXPIRY_MINUTES . ' minutes')
          );
          $this->entityManager->flush();

          $this->emailService->sendPasswordResetEmail(
              $user->getEmail(),
              $token,
              $user->getUsername()
          );
      }

      /**
       * Reset password using token.
       */
      public function resetPassword(string $token, string $newPassword): User
      {
          $tokenHash = hash('sha256', $token);
          $user = $this->userRepository->findOneBy(['passwordResetToken' => $tokenHash]);

          if ($user === null || !$user->isPasswordResetTokenValid()) {
              throw ValidationException::forField('token', 'Invalid or expired reset token');
          }

          $this->validatePasswordStrength($newPassword);

          $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
          $user->setPasswordHash($hashedPassword);
          $user->setPasswordResetToken(null);
          $user->setPasswordResetExpiresAt(null);

          // Invalidate all API tokens for security
          $user->setApiToken(null);
          $user->setApiTokenIssuedAt(null);
          $user->setApiTokenExpiresAt(null);

          $this->entityManager->flush();

          // Notify user of password change
          $this->emailService->sendPasswordChangedNotification(
              $user->getEmail(),
              $user->getUsername()
          );

          return $user;
      }

      /**
       * Validate token without consuming it (for UI).
       */
      public function validateToken(string $token): bool
      {
          $tokenHash = hash('sha256', $token);
          $user = $this->userRepository->findOneBy(['passwordResetToken' => $tokenHash]);

          return $user !== null && $user->isPasswordResetTokenValid();
      }

      private function validatePasswordStrength(string $password): void
      {
          if (strlen($password) < 12) {
              throw ValidationException::forField('password', 'Password must be at least 12 characters');
          }

          // Check for at least one uppercase, one lowercase, one number
          if (!preg_match('/[A-Z]/', $password)) {
              throw ValidationException::forField('password', 'Password must contain at least one uppercase letter');
          }
          if (!preg_match('/[a-z]/', $password)) {
              throw ValidationException::forField('password', 'Password must contain at least one lowercase letter');
          }
          if (!preg_match('/[0-9]/', $password)) {
              throw ValidationException::forField('password', 'Password must contain at least one number');
          }
      }
  }
  ```

- [ ] **10.3.4** Create password reset endpoints
  ```php
  // src/Controller/Api/AuthController.php additions

  /**
   * Request password reset.
   */
  #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
  public function forgotPassword(Request $request): JsonResponse
  {
      $data = $this->validationHelper->decodeJsonBody($request);
      $email = $data['email'] ?? null;

      if ($email === null) {
          throw ValidationException::forField('email', 'Email is required');
      }

      $this->passwordResetService->requestReset($email);

      // Always return success to prevent email enumeration
      return $this->responseFormatter->success([
          'message' => 'If an account exists with this email, a password reset link has been sent',
      ]);
  }

  /**
   * Reset password with token.
   */
  #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
  public function resetPassword(Request $request): JsonResponse
  {
      $data = $this->validationHelper->decodeJsonBody($request);
      $token = $data['token'] ?? null;
      $password = $data['password'] ?? null;

      if ($token === null) {
          throw ValidationException::forField('token', 'Reset token is required');
      }
      if ($password === null) {
          throw ValidationException::forField('password', 'New password is required');
      }

      $user = $this->passwordResetService->resetPassword($token, $password);

      return $this->responseFormatter->success([
          'message' => 'Password has been reset successfully',
      ]);
  }

  /**
   * Validate reset token (for UI).
   */
  #[Route('/reset-password/validate', name: 'validate_reset_token', methods: ['POST'])]
  public function validateResetToken(Request $request): JsonResponse
  {
      $data = $this->validationHelper->decodeJsonBody($request);
      $token = $data['token'] ?? null;

      if ($token === null) {
          throw ValidationException::forField('token', 'Token is required');
      }

      $valid = $this->passwordResetService->validateToken($token);

      return $this->responseFormatter->success([
          'valid' => $valid,
      ]);
  }
  ```

- [ ] **10.3.5** Create web password reset pages
  ```twig
  {# templates/security/forgot-password.html.twig #}
  {% extends 'base.html.twig' %}

  {% block body %}
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
      <div class="max-w-md w-full space-y-8">
          <div>
              <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                  Reset your password
              </h2>
              <p class="mt-2 text-center text-sm text-gray-600">
                  Enter your email and we'll send you a reset link.
              </p>
          </div>

          <form x-data="forgotPasswordForm()" @submit.prevent="submit()" class="mt-8 space-y-6">
              <div>
                  <label for="email" class="sr-only">Email address</label>
                  <input id="email" name="email" type="email" required x-model="email"
                         class="appearance-none rounded-md relative block w-full px-3 py-2 border
                                border-gray-300 placeholder-gray-500 text-gray-900
                                focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                         placeholder="Email address">
              </div>

              <div x-show="message" x-transition
                   class="rounded-md p-4"
                   :class="success ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
                  <p class="text-sm" x-text="message"></p>
              </div>

              <button type="submit" :disabled="submitting"
                      class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md
                             shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700
                             focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500
                             disabled:opacity-50">
                  <span x-show="!submitting">Send Reset Link</span>
                  <span x-show="submitting">Sending...</span>
              </button>

              <p class="text-center text-sm">
                  <a href="{{ path('app_login') }}" class="text-indigo-600 hover:text-indigo-500">
                      Back to sign in
                  </a>
              </p>
          </form>
      </div>
  </div>
  {% endblock %}
  ```

  ```twig
  {# templates/security/reset-password.html.twig #}
  {% extends 'base.html.twig' %}

  {% block body %}
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
      <div class="max-w-md w-full space-y-8">
          <div>
              <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                  Create new password
              </h2>
          </div>

          <form x-data="resetPasswordForm('{{ token }}')" @submit.prevent="submit()" class="mt-8 space-y-6">
              <div class="space-y-4">
                  <div>
                      <label for="password" class="block text-sm font-medium text-gray-700">
                          New Password
                      </label>
                      <input id="password" type="password" required x-model="password" minlength="12"
                             class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
                                    focus:border-indigo-500 focus:ring-indigo-500">
                      <p class="mt-1 text-xs text-gray-500">
                          At least 12 characters with uppercase, lowercase, and number
                      </p>
                  </div>

                  <div>
                      <label for="confirmPassword" class="block text-sm font-medium text-gray-700">
                          Confirm Password
                      </label>
                      <input id="confirmPassword" type="password" required x-model="confirmPassword"
                             class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
                                    focus:border-indigo-500 focus:ring-indigo-500">
                  </div>
              </div>

              <div x-show="error" x-transition class="rounded-md bg-red-50 p-4">
                  <p class="text-sm text-red-800" x-text="error"></p>
              </div>

              <button type="submit" :disabled="submitting || password !== confirmPassword"
                      class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md
                             shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700
                             disabled:opacity-50">
                  <span x-show="!submitting">Reset Password</span>
                  <span x-show="submitting">Resetting...</span>
              </button>
          </form>
      </div>
  </div>
  {% endblock %}
  ```

### Completion Criteria
- [ ] Password reset request sends email
- [ ] Token validates correctly
- [ ] Token expires after 60 minutes
- [ ] Password changed successfully
- [ ] API tokens invalidated on reset
- [ ] User notified of password change
- [ ] No email enumeration vulnerability

### Files to Create/Update
```
src/Entity/User.php (update)
src/Service/PasswordResetService.php (new)
src/Controller/Api/AuthController.php (update)
src/Controller/Web/SecurityController.php (update)
templates/security/forgot-password.html.twig (new)
templates/security/reset-password.html.twig (new)
migrations/Version20260124_PasswordReset.php (new)
```

---

## Sub-Phase 10.4: Password Change for Authenticated Users

### Objective
Allow authenticated users to change their password.

### Tasks

- [ ] **10.4.1** Create password change endpoint
  ```php
  // src/Controller/Api/UserController.php

  /**
   * Change password for authenticated user.
   */
  #[Route('/me/password', name: 'change_password', methods: ['PATCH'])]
  public function changePassword(Request $request): JsonResponse
  {
      /** @var User $user */
      $user = $this->getUser();

      $data = $this->validationHelper->decodeJsonBody($request);
      $currentPassword = $data['currentPassword'] ?? $data['current_password'] ?? null;
      $newPassword = $data['newPassword'] ?? $data['new_password'] ?? null;

      if ($currentPassword === null) {
          throw ValidationException::forField('currentPassword', 'Current password is required');
      }
      if ($newPassword === null) {
          throw ValidationException::forField('newPassword', 'New password is required');
      }

      // Verify current password
      if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
          throw ValidationException::forField('currentPassword', 'Current password is incorrect');
      }

      // Validate new password strength
      $this->passwordResetService->validatePasswordStrength($newPassword);

      // Update password
      $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
      $user->setPasswordHash($hashedPassword);
      $this->entityManager->flush();

      // Send notification
      $this->emailService->sendPasswordChangedNotification(
          $user->getEmail(),
          $user->getUsername()
      );

      return $this->responseFormatter->success([
          'message' => 'Password changed successfully',
      ]);
  }
  ```

- [ ] **10.4.2** Create ChangePasswordRequest DTO
  ```php
  // src/DTO/ChangePasswordRequest.php

  declare(strict_types=1);

  namespace App\DTO;

  use Symfony\Component\Validator\Constraints as Assert;

  final class ChangePasswordRequest
  {
      public function __construct(
          #[Assert\NotBlank(message: 'Current password is required')]
          public readonly string $currentPassword,

          #[Assert\NotBlank(message: 'New password is required')]
          #[Assert\Length(min: 12, minMessage: 'Password must be at least 12 characters')]
          public readonly string $newPassword,
      ) {}

      public static function fromArray(array $data): self
      {
          return new self(
              currentPassword: $data['currentPassword'] ?? $data['current_password'] ?? '',
              newPassword: $data['newPassword'] ?? $data['new_password'] ?? '',
          );
      }
  }
  ```

### Completion Criteria
- [ ] Current password verified before change
- [ ] New password meets strength requirements
- [ ] User notified via email
- [ ] API response confirms success

---

## Sub-Phase 10.5: Account Lockout Protection

### Objective
Protect against brute-force attacks by temporarily locking accounts after failed attempts.

### Tasks

- [ ] **10.5.1** Add lockout fields to User entity
  ```php
  // src/Entity/User.php additions

  #[ORM\Column(type: Types::INTEGER, name: 'failed_login_attempts', options: ['default' => 0])]
  private int $failedLoginAttempts = 0;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'locked_until')]
  private ?\DateTimeImmutable $lockedUntil = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'last_failed_login_at')]
  private ?\DateTimeImmutable $lastFailedLoginAt = null;

  public function getFailedLoginAttempts(): int
  {
      return $this->failedLoginAttempts;
  }

  public function incrementFailedLoginAttempts(): self
  {
      $this->failedLoginAttempts++;
      $this->lastFailedLoginAt = new \DateTimeImmutable();
      return $this;
  }

  public function resetFailedLoginAttempts(): self
  {
      $this->failedLoginAttempts = 0;
      $this->lockedUntil = null;
      $this->lastFailedLoginAt = null;
      return $this;
  }

  public function getLockedUntil(): ?\DateTimeImmutable
  {
      return $this->lockedUntil;
  }

  public function setLockedUntil(?\DateTimeImmutable $lockedUntil): self
  {
      $this->lockedUntil = $lockedUntil;
      return $this;
  }

  public function isLocked(): bool
  {
      if ($this->lockedUntil === null) {
          return false;
      }
      return new \DateTimeImmutable() < $this->lockedUntil;
  }

  public function getLockoutRemainingSeconds(): int
  {
      if (!$this->isLocked()) {
          return 0;
      }
      return $this->lockedUntil->getTimestamp() - time();
  }
  ```

- [ ] **10.5.2** Create database migration
  ```php
  // migrations/Version20260124_AccountLockout.php

  public function up(Schema $schema): void
  {
      $this->addSql('ALTER TABLE users ADD failed_login_attempts INTEGER NOT NULL DEFAULT 0');
      $this->addSql('ALTER TABLE users ADD locked_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
      $this->addSql('ALTER TABLE users ADD last_failed_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
  }
  ```

- [ ] **10.5.3** Create AccountLockoutService
  ```php
  // src/Service/AccountLockoutService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\User;
  use App\Exception\AccountLockedException;
  use Doctrine\ORM\EntityManagerInterface;

  final class AccountLockoutService
  {
      private const MAX_FAILED_ATTEMPTS = 5;
      private const LOCKOUT_DURATION_MINUTES = 15;
      private const ATTEMPT_WINDOW_MINUTES = 30;

      public function __construct(
          private readonly EntityManagerInterface $entityManager,
          private readonly EmailService $emailService,
      ) {}

      public function checkLockout(User $user): void
      {
          if ($user->isLocked()) {
              $remainingMinutes = (int) ceil($user->getLockoutRemainingSeconds() / 60);
              throw new AccountLockedException(
                  sprintf('Account is locked. Try again in %d minutes.', $remainingMinutes)
              );
          }

          // Reset attempts if outside window
          if ($user->getLastFailedLoginAt() !== null) {
              $windowEnd = $user->getLastFailedLoginAt()->modify('+' . self::ATTEMPT_WINDOW_MINUTES . ' minutes');
              if (new \DateTimeImmutable() > $windowEnd) {
                  $user->resetFailedLoginAttempts();
                  $this->entityManager->flush();
              }
          }
      }

      public function recordFailedAttempt(User $user): void
      {
          $user->incrementFailedLoginAttempts();

          if ($user->getFailedLoginAttempts() >= self::MAX_FAILED_ATTEMPTS) {
              $user->setLockedUntil(
                  (new \DateTimeImmutable())->modify('+' . self::LOCKOUT_DURATION_MINUTES . ' minutes')
              );

              // Notify user
              $this->emailService->sendAccountLockedNotification(
                  $user->getEmail(),
                  $user->getUsername(),
                  self::LOCKOUT_DURATION_MINUTES
              );
          }

          $this->entityManager->flush();
      }

      public function recordSuccessfulLogin(User $user): void
      {
          if ($user->getFailedLoginAttempts() > 0) {
              $user->resetFailedLoginAttempts();
              $this->entityManager->flush();
          }
      }
  }
  ```

- [ ] **10.5.4** Create AccountLockedException
  ```php
  // src/Exception/AccountLockedException.php

  declare(strict_types=1);

  namespace App\Exception;

  use Symfony\Component\HttpFoundation\Response;
  use Symfony\Component\HttpKernel\Exception\HttpException;

  final class AccountLockedException extends HttpException
  {
      public function __construct(string $message = 'Account is temporarily locked')
      {
          parent::__construct(Response::HTTP_TOO_MANY_REQUESTS, $message);
      }

      public function getErrorCode(): string
      {
          return 'ACCOUNT_LOCKED';
      }
  }
  ```

- [ ] **10.5.5** Integrate with authentication
  ```php
  // src/Security/LoginAuthenticationHandler.php

  declare(strict_types=1);

  namespace App\Security;

  use App\Service\AccountLockoutService;
  use Symfony\Component\Security\Http\Event\LoginFailureEvent;
  use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
  use Symfony\Component\EventDispatcher\EventSubscriberInterface;

  final class LoginAuthenticationHandler implements EventSubscriberInterface
  {
      public function __construct(
          private readonly AccountLockoutService $lockoutService,
      ) {}

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
          if ($user instanceof \App\Entity\User) {
              $this->lockoutService->recordSuccessfulLogin($user);
          }
      }

      public function onLoginFailure(LoginFailureEvent $event): void
      {
          // Get user from failed authentication attempt
          $passport = $event->getPassport();
          if ($passport !== null) {
              $userBadge = $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class);
              if ($userBadge !== null) {
                  $user = $userBadge->getUser();
                  if ($user instanceof \App\Entity\User) {
                      $this->lockoutService->recordFailedAttempt($user);
                  }
              }
          }
      }
  }
  ```

### Completion Criteria
- [ ] Failed attempts tracked
- [ ] Account locked after 5 failures
- [ ] Lockout duration is 15 minutes
- [ ] Attempts reset after 30 minutes of no failures
- [ ] User notified when locked
- [ ] Successful login resets counter

---

## Sub-Phase 10.6: Enhanced Password Policy

### Objective
Enforce stronger password requirements for security.

### Tasks

- [ ] **10.6.1** Create PasswordPolicyValidator
  ```php
  // src/Validator/PasswordPolicyValidator.php

  declare(strict_types=1);

  namespace App\Validator;

  use App\Exception\ValidationException;

  final class PasswordPolicyValidator
  {
      private const MIN_LENGTH = 12;
      private const COMMON_PASSWORDS = [
          'password123', 'password1234', '123456789012', 'qwerty123456',
          // Add more common passwords
      ];

      public function validate(string $password, ?string $email = null, ?string $username = null): void
      {
          $errors = [];

          // Length check
          if (strlen($password) < self::MIN_LENGTH) {
              $errors[] = sprintf('Password must be at least %d characters', self::MIN_LENGTH);
          }

          // Uppercase check
          if (!preg_match('/[A-Z]/', $password)) {
              $errors[] = 'Password must contain at least one uppercase letter';
          }

          // Lowercase check
          if (!preg_match('/[a-z]/', $password)) {
              $errors[] = 'Password must contain at least one lowercase letter';
          }

          // Number check
          if (!preg_match('/[0-9]/', $password)) {
              $errors[] = 'Password must contain at least one number';
          }

          // Special character check (optional but recommended)
          // if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
          //     $errors[] = 'Password must contain at least one special character';
          // }

          // Common password check
          if (in_array(strtolower($password), self::COMMON_PASSWORDS, true)) {
              $errors[] = 'This password is too common. Please choose a different password.';
          }

          // Check if password contains email or username
          if ($email !== null && str_contains(strtolower($password), strtolower(explode('@', $email)[0]))) {
              $errors[] = 'Password cannot contain your email address';
          }
          if ($username !== null && str_contains(strtolower($password), strtolower($username))) {
              $errors[] = 'Password cannot contain your username';
          }

          if (!empty($errors)) {
              throw ValidationException::forField('password', implode('. ', $errors));
          }
      }

      public function getRequirements(): array
      {
          return [
              'minLength' => self::MIN_LENGTH,
              'requireUppercase' => true,
              'requireLowercase' => true,
              'requireNumber' => true,
              'requireSpecial' => false,
          ];
      }
  }
  ```

- [ ] **10.6.2** Add password requirements endpoint
  ```php
  // src/Controller/Api/AuthController.php

  /**
   * Get password requirements.
   */
  #[Route('/password-requirements', name: 'password_requirements', methods: ['GET'])]
  public function getPasswordRequirements(): JsonResponse
  {
      return $this->responseFormatter->success([
          'requirements' => $this->passwordPolicyValidator->getRequirements(),
      ]);
  }
  ```

- [ ] **10.6.3** Update registration to use policy
  ```php
  // Update UserService::register() and PasswordResetService::resetPassword()
  // to use PasswordPolicyValidator
  ```

### Completion Criteria
- [ ] 12+ character minimum enforced
- [ ] Uppercase, lowercase, number required
- [ ] Common passwords blocked
- [ ] Password cannot contain email/username
- [ ] Requirements exposed via API

---

## Sub-Phase 10.7: Testing

### Tasks

- [ ] **10.7.1** Email service tests
  ```php
  // tests/Unit/Service/EmailServiceTest.php
  public function testSendPasswordResetEmail(): void;
  public function testSendEmailVerification(): void;
  public function testSendPasswordChangedNotification(): void;
  public function testSendAccountLockedNotification(): void;
  ```

- [ ] **10.7.2** Email verification tests
  ```php
  // tests/Functional/Api/EmailVerificationApiTest.php
  public function testVerifyEmailWithValidToken(): void;
  public function testVerifyEmailWithExpiredToken(): void;
  public function testVerifyEmailWithInvalidToken(): void;
  public function testResendVerificationRespectsCooldown(): void;
  public function testResendVerificationNoEmailEnumeration(): void;
  ```

- [ ] **10.7.3** Password reset tests
  ```php
  // tests/Functional/Api/PasswordResetApiTest.php
  public function testForgotPasswordSendsEmail(): void;
  public function testForgotPasswordNoEmailEnumeration(): void;
  public function testResetPasswordWithValidToken(): void;
  public function testResetPasswordWithExpiredToken(): void;
  public function testResetPasswordInvalidatesApiTokens(): void;
  public function testResetPasswordSendsNotification(): void;
  public function testValidateTokenEndpoint(): void;
  ```

- [ ] **10.7.4** Password change tests
  ```php
  // tests/Functional/Api/PasswordChangeApiTest.php
  public function testChangePasswordSuccess(): void;
  public function testChangePasswordWrongCurrentPassword(): void;
  public function testChangePasswordWeakPassword(): void;
  public function testChangePasswordSendsNotification(): void;
  ```

- [ ] **10.7.5** Account lockout tests
  ```php
  // tests/Functional/Api/AccountLockoutApiTest.php
  public function testAccountLocksAfterFiveFailures(): void;
  public function testAccountUnlocksAfterDuration(): void;
  public function testSuccessfulLoginResetsCounter(): void;
  public function testLockoutNotificationSent(): void;
  ```

- [ ] **10.7.6** Password policy tests
  ```php
  // tests/Unit/Validator/PasswordPolicyValidatorTest.php
  public function testMinimumLength(): void;
  public function testRequiresUppercase(): void;
  public function testRequiresLowercase(): void;
  public function testRequiresNumber(): void;
  public function testRejectsCommonPasswords(): void;
  public function testRejectsPasswordContainingEmail(): void;
  ```

### Completion Criteria
- [ ] All email sending tested
- [ ] Verification flow tested
- [ ] Password reset flow tested
- [ ] Password change tested
- [ ] Account lockout tested
- [ ] Password policy tested

---

## Phase 10 Deliverables Checklist

### Email Infrastructure
- [ ] Symfony Mailer configured
- [ ] EmailService with templated emails
- [ ] All email templates created
- [ ] Environment variables documented

### Email Verification
- [ ] Verification required for new users
- [ ] Verification token with 24h expiry
- [ ] Resend with 5-minute cooldown
- [ ] Web verification page
- [ ] API endpoints working

### Password Reset
- [ ] Reset request endpoint
- [ ] Reset execution endpoint
- [ ] Token validation endpoint
- [ ] 60-minute token expiry
- [ ] API tokens invalidated on reset
- [ ] Notification sent after reset
- [ ] No email enumeration

### Password Change
- [ ] Current password verified
- [ ] New password strength validated
- [ ] Notification sent after change

### Account Lockout
- [ ] 5 failed attempts triggers lockout
- [ ] 15-minute lockout duration
- [ ] 30-minute attempt window
- [ ] Notification sent on lockout
- [ ] Successful login resets counter

### Password Policy
- [ ] 12+ characters required
- [ ] Uppercase, lowercase, number required
- [ ] Common passwords blocked
- [ ] Requirements API endpoint

### Testing
- [ ] All services unit tested
- [ ] All endpoints functionally tested
- [ ] Security flows tested

---

## Implementation Order

1. **10.1**: Email Infrastructure (foundation)
2. **10.3**: Password Reset (highest user value)
3. **10.2**: Email Verification
4. **10.4**: Password Change
5. **10.5**: Account Lockout
6. **10.6**: Enhanced Password Policy
7. **10.7**: Testing

---

## Dependencies

```
Sub-Phase 10.1 (Email) ──► Sub-Phase 10.2 (Verification)
         │                           │
         │                           ▼
         └──────────────► Sub-Phase 10.3 (Reset)
                                     │
                                     ▼
                          Sub-Phase 10.4 (Change)
                                     │
                                     ▼
                          Sub-Phase 10.5 (Lockout)
                                     │
                                     ▼
                          Sub-Phase 10.6 (Policy)
                                     │
                                     ▼
                          Sub-Phase 10.7 (Testing)
```

---

## Security Considerations

1. **Token Security**: All tokens are hashed before storage (SHA-256)
2. **No Email Enumeration**: Password reset always returns success
3. **Rate Limiting**: Existing rate limiting applies to auth endpoints
4. **Token Expiry**: All tokens have appropriate expiry times
5. **Notification on Change**: Users notified of security-relevant changes
6. **API Token Invalidation**: Password reset invalidates all API tokens

---

## Files Summary

### New Files
```
config/packages/mailer.yaml
src/Service/EmailService.php
src/Service/EmailVerificationService.php
src/Service/PasswordResetService.php
src/Service/AccountLockoutService.php
src/Exception/AccountLockedException.php
src/Validator/PasswordPolicyValidator.php
src/Security/LoginAuthenticationHandler.php
src/DTO/ChangePasswordRequest.php
templates/emails/base.html.twig
templates/emails/password-reset.html.twig
templates/emails/verify-email.html.twig
templates/emails/password-changed.html.twig
templates/emails/account-locked.html.twig
templates/security/forgot-password.html.twig
templates/security/reset-password.html.twig
templates/security/verify-email.html.twig
migrations/Version20260124_EmailVerification.php
migrations/Version20260124_PasswordReset.php
migrations/Version20260124_AccountLockout.php
tests/Unit/Service/EmailServiceTest.php
tests/Unit/Validator/PasswordPolicyValidatorTest.php
tests/Functional/Api/EmailVerificationApiTest.php
tests/Functional/Api/PasswordResetApiTest.php
tests/Functional/Api/PasswordChangeApiTest.php
tests/Functional/Api/AccountLockoutApiTest.php
```

### Updated Files
```
src/Entity/User.php
src/Service/UserService.php
src/Controller/Api/AuthController.php
src/Controller/Web/SecurityController.php
config/services.yaml
.env.local.example
```
