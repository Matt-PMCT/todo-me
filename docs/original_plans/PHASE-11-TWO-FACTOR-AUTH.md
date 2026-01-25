# Phase 11: Two-Factor Authentication

## Overview
**Goal**: Implement TOTP-based two-factor authentication (2FA) to provide an additional security layer for user accounts, including setup flow, verification, backup codes, and recovery options.

## Revision History
- **2026-01-24**: Initial creation based on comprehensive project review

## Prerequisites
- Phase 10 completed (Email infrastructure required for recovery)
- User entity with authentication system working
- Redis available for temporary token storage

---

## Why This Phase is Important

Two-factor authentication:
- Protects accounts even if passwords are compromised
- Industry standard for security-conscious applications
- Increasingly expected by users
- Required for certain compliance standards (SOC2, etc.)

---

## Sub-Phase 11.1: 2FA Database Schema

### Objective
Add necessary fields to User entity for 2FA support.

### Tasks

- [ ] **11.1.1** Add 2FA fields to User entity
  ```php
  // src/Entity/User.php additions

  #[ORM\Column(type: Types::BOOLEAN, name: 'two_factor_enabled', options: ['default' => false])]
  private bool $twoFactorEnabled = false;

  #[ORM\Column(type: Types::STRING, length: 255, nullable: true, name: 'totp_secret')]
  private ?string $totpSecret = null;

  #[ORM\Column(type: Types::JSON, nullable: true, name: 'backup_codes')]
  private ?array $backupCodes = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'two_factor_enabled_at')]
  private ?\DateTimeImmutable $twoFactorEnabledAt = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'backup_codes_generated_at')]
  private ?\DateTimeImmutable $backupCodesGeneratedAt = null;

  public function isTwoFactorEnabled(): bool
  {
      return $this->twoFactorEnabled;
  }

  public function setTwoFactorEnabled(bool $enabled): self
  {
      $this->twoFactorEnabled = $enabled;
      if ($enabled) {
          $this->twoFactorEnabledAt = new \DateTimeImmutable();
      }
      return $this;
  }

  public function getTotpSecret(): ?string
  {
      return $this->totpSecret;
  }

  public function setTotpSecret(?string $secret): self
  {
      $this->totpSecret = $secret;
      return $this;
  }

  public function getBackupCodes(): ?array
  {
      return $this->backupCodes;
  }

  public function setBackupCodes(?array $codes): self
  {
      $this->backupCodes = $codes;
      if ($codes !== null) {
          $this->backupCodesGeneratedAt = new \DateTimeImmutable();
      }
      return $this;
  }

  public function getTwoFactorEnabledAt(): ?\DateTimeImmutable
  {
      return $this->twoFactorEnabledAt;
  }

  public function getBackupCodesGeneratedAt(): ?\DateTimeImmutable
  {
      return $this->backupCodesGeneratedAt;
  }

  public function getBackupCodesRemaining(): int
  {
      if ($this->backupCodes === null) {
          return 0;
      }
      return count(array_filter($this->backupCodes, fn($code) => !$code['used']));
  }
  ```

- [ ] **11.1.2** Create database migration
  ```php
  // migrations/Version20260124_TwoFactorAuth.php

  public function up(Schema $schema): void
  {
      $this->addSql('ALTER TABLE users ADD two_factor_enabled BOOLEAN NOT NULL DEFAULT FALSE');
      $this->addSql('ALTER TABLE users ADD totp_secret VARCHAR(255) DEFAULT NULL');
      $this->addSql('ALTER TABLE users ADD backup_codes JSONB DEFAULT NULL');
      $this->addSql('ALTER TABLE users ADD two_factor_enabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
      $this->addSql('ALTER TABLE users ADD backup_codes_generated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
  }

  public function down(Schema $schema): void
  {
      $this->addSql('ALTER TABLE users DROP two_factor_enabled');
      $this->addSql('ALTER TABLE users DROP totp_secret');
      $this->addSql('ALTER TABLE users DROP backup_codes');
      $this->addSql('ALTER TABLE users DROP two_factor_enabled_at');
      $this->addSql('ALTER TABLE users DROP backup_codes_generated_at');
  }
  ```

### Completion Criteria
- [ ] User entity has 2FA fields
- [ ] Migration created and tested
- [ ] Backup codes stored as JSON with used flag

---

## Sub-Phase 11.2: TOTP Service

### Objective
Create service for generating and verifying TOTP codes.

### Tasks

- [ ] **11.2.1** Install TOTP library
  ```bash
  composer require spomky-labs/otphp
  ```

- [ ] **11.2.2** Create TotpService
  ```php
  // src/Service/TotpService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\User;
  use OTPHP\TOTP;
  use ParagonIE\ConstantTime\Base32;

  final class TotpService
  {
      private const ISSUER = 'Todo-Me';
      private const DIGITS = 6;
      private const PERIOD = 30;
      private const ALGORITHM = 'sha1';

      public function __construct(
          private readonly string $appName = self::ISSUER,
      ) {}

      /**
       * Generate a new TOTP secret for a user.
       */
      public function generateSecret(): string
      {
          return Base32::encodeUpper(random_bytes(20));
      }

      /**
       * Create a TOTP instance for a user.
       */
      public function createTotp(User $user, string $secret): TOTP
      {
          $totp = TOTP::create($secret);
          $totp->setLabel($user->getEmail());
          $totp->setIssuer($this->appName);
          $totp->setDigits(self::DIGITS);
          $totp->setPeriod(self::PERIOD);

          return $totp;
      }

      /**
       * Generate QR code provisioning URI.
       */
      public function getProvisioningUri(User $user, string $secret): string
      {
          $totp = $this->createTotp($user, $secret);
          return $totp->getProvisioningUri();
      }

      /**
       * Verify a TOTP code.
       */
      public function verifyCode(string $secret, string $code): bool
      {
          $totp = TOTP::create($secret);
          $totp->setDigits(self::DIGITS);
          $totp->setPeriod(self::PERIOD);

          // Allow 1 period of clock drift
          return $totp->verify($code, null, 1);
      }

      /**
       * Get the current TOTP code (for testing).
       */
      public function getCurrentCode(string $secret): string
      {
          $totp = TOTP::create($secret);
          $totp->setDigits(self::DIGITS);
          $totp->setPeriod(self::PERIOD);

          return $totp->now();
      }
  }
  ```

- [ ] **11.2.3** Create BackupCodeService
  ```php
  // src/Service/BackupCodeService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\User;
  use App\Exception\ValidationException;
  use Doctrine\ORM\EntityManagerInterface;

  final class BackupCodeService
  {
      private const CODE_COUNT = 10;
      private const CODE_LENGTH = 8;

      public function __construct(
          private readonly EntityManagerInterface $entityManager,
      ) {}

      /**
       * Generate new backup codes for a user.
       */
      public function generateBackupCodes(User $user): array
      {
          $codes = [];
          $hashedCodes = [];

          for ($i = 0; $i < self::CODE_COUNT; $i++) {
              $code = $this->generateCode();
              $codes[] = $code;
              $hashedCodes[] = [
                  'hash' => password_hash($code, PASSWORD_BCRYPT),
                  'used' => false,
              ];
          }

          $user->setBackupCodes($hashedCodes);
          $this->entityManager->flush();

          return $codes;
      }

      /**
       * Verify and consume a backup code.
       */
      public function verifyBackupCode(User $user, string $code): bool
      {
          $backupCodes = $user->getBackupCodes();

          if ($backupCodes === null) {
              return false;
          }

          foreach ($backupCodes as $index => $storedCode) {
              if ($storedCode['used']) {
                  continue;
              }

              if (password_verify($code, $storedCode['hash'])) {
                  // Mark code as used
                  $backupCodes[$index]['used'] = true;
                  $user->setBackupCodes($backupCodes);
                  $this->entityManager->flush();

                  return true;
              }
          }

          return false;
      }

      /**
       * Check if user has any backup codes remaining.
       */
      public function hasBackupCodesRemaining(User $user): bool
      {
          return $user->getBackupCodesRemaining() > 0;
      }

      private function generateCode(): string
      {
          $chars = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // Excluding I, O for readability
          $code = '';

          for ($i = 0; $i < self::CODE_LENGTH; $i++) {
              $code .= $chars[random_int(0, strlen($chars) - 1)];
          }

          // Format as XXXX-XXXX for readability
          return substr($code, 0, 4) . '-' . substr($code, 4, 4);
      }
  }
  ```

### Completion Criteria
- [ ] TOTP library installed
- [ ] Secret generation working
- [ ] Code verification with clock drift tolerance
- [ ] Backup codes generated and verified
- [ ] Backup codes single-use

---

## Sub-Phase 11.3: 2FA Setup Flow

### Objective
Create the 2FA enablement flow with QR code and verification.

### Tasks

- [ ] **11.3.1** Create TwoFactorService
  ```php
  // src/Service/TwoFactorService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\User;
  use App\Exception\ValidationException;
  use Doctrine\ORM\EntityManagerInterface;
  use Predis\Client as RedisClient;

  final class TwoFactorService
  {
      private const SETUP_TOKEN_PREFIX = '2fa_setup:';
      private const SETUP_TOKEN_TTL = 600; // 10 minutes

      public function __construct(
          private readonly EntityManagerInterface $entityManager,
          private readonly TotpService $totpService,
          private readonly BackupCodeService $backupCodeService,
          private readonly RedisClient $redis,
      ) {}

      /**
       * Initialize 2FA setup - generates secret and returns QR code URI.
       */
      public function initializeSetup(User $user): array
      {
          if ($user->isTwoFactorEnabled()) {
              throw ValidationException::forField('2fa', '2FA is already enabled');
          }

          $secret = $this->totpService->generateSecret();

          // Store secret temporarily in Redis
          $setupToken = bin2hex(random_bytes(16));
          $this->redis->setex(
              self::SETUP_TOKEN_PREFIX . $user->getId(),
              self::SETUP_TOKEN_TTL,
              json_encode(['secret' => $secret, 'token' => $setupToken])
          );

          $provisioningUri = $this->totpService->getProvisioningUri($user, $secret);

          return [
              'setupToken' => $setupToken,
              'secret' => $secret, // For manual entry
              'qrCodeUri' => $provisioningUri,
          ];
      }

      /**
       * Complete 2FA setup by verifying a code.
       */
      public function completeSetup(User $user, string $setupToken, string $code): array
      {
          if ($user->isTwoFactorEnabled()) {
              throw ValidationException::forField('2fa', '2FA is already enabled');
          }

          // Retrieve setup data from Redis
          $setupData = $this->redis->get(self::SETUP_TOKEN_PREFIX . $user->getId());
          if ($setupData === null) {
              throw ValidationException::forField('setupToken', 'Setup session expired. Please start again.');
          }

          $data = json_decode($setupData, true);
          if ($data['token'] !== $setupToken) {
              throw ValidationException::forField('setupToken', 'Invalid setup token');
          }

          // Verify the code
          if (!$this->totpService->verifyCode($data['secret'], $code)) {
              throw ValidationException::forField('code', 'Invalid verification code');
          }

          // Enable 2FA
          $user->setTotpSecret($data['secret']);
          $user->setTwoFactorEnabled(true);

          // Generate backup codes
          $backupCodes = $this->backupCodeService->generateBackupCodes($user);

          // Clean up Redis
          $this->redis->del(self::SETUP_TOKEN_PREFIX . $user->getId());

          $this->entityManager->flush();

          return [
              'enabled' => true,
              'backupCodes' => $backupCodes,
          ];
      }

      /**
       * Disable 2FA for a user.
       */
      public function disable(User $user, string $password): void
      {
          if (!$user->isTwoFactorEnabled()) {
              throw ValidationException::forField('2fa', '2FA is not enabled');
          }

          // Password verification should be done by caller

          $user->setTwoFactorEnabled(false);
          $user->setTotpSecret(null);
          $user->setBackupCodes(null);

          $this->entityManager->flush();
      }

      /**
       * Verify 2FA code during login.
       */
      public function verify(User $user, string $code): bool
      {
          if (!$user->isTwoFactorEnabled()) {
              return true; // No 2FA required
          }

          $secret = $user->getTotpSecret();
          if ($secret === null) {
              return false;
          }

          return $this->totpService->verifyCode($secret, $code);
      }

      /**
       * Verify using backup code.
       */
      public function verifyWithBackupCode(User $user, string $code): bool
      {
          return $this->backupCodeService->verifyBackupCode($user, $code);
      }

      /**
       * Regenerate backup codes.
       */
      public function regenerateBackupCodes(User $user): array
      {
          if (!$user->isTwoFactorEnabled()) {
              throw ValidationException::forField('2fa', '2FA is not enabled');
          }

          return $this->backupCodeService->generateBackupCodes($user);
      }
  }
  ```

- [ ] **11.3.2** Create 2FA setup endpoints
  ```php
  // src/Controller/Api/TwoFactorController.php

  declare(strict_types=1);

  namespace App\Controller\Api;

  use App\Entity\User;
  use App\Exception\ValidationException;
  use App\Service\ResponseFormatter;
  use App\Service\TwoFactorService;
  use App\Service\ValidationHelper;
  use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
  use Symfony\Component\HttpFoundation\JsonResponse;
  use Symfony\Component\HttpFoundation\Request;
  use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
  use Symfony\Component\Routing\Attribute\Route;

  #[Route('/api/v1/2fa', name: 'api_2fa_')]
  final class TwoFactorController extends AbstractController
  {
      public function __construct(
          private readonly TwoFactorService $twoFactorService,
          private readonly ResponseFormatter $responseFormatter,
          private readonly ValidationHelper $validationHelper,
          private readonly UserPasswordHasherInterface $passwordHasher,
      ) {}

      /**
       * Get 2FA status.
       */
      #[Route('/status', name: 'status', methods: ['GET'])]
      public function status(): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          return $this->responseFormatter->success([
              'enabled' => $user->isTwoFactorEnabled(),
              'enabledAt' => $user->getTwoFactorEnabledAt()?->format(\DateTimeInterface::RFC3339),
              'backupCodesRemaining' => $user->getBackupCodesRemaining(),
              'backupCodesGeneratedAt' => $user->getBackupCodesGeneratedAt()?->format(\DateTimeInterface::RFC3339),
          ]);
      }

      /**
       * Initialize 2FA setup.
       */
      #[Route('/setup', name: 'setup', methods: ['POST'])]
      public function setup(): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $result = $this->twoFactorService->initializeSetup($user);

          return $this->responseFormatter->success([
              'setupToken' => $result['setupToken'],
              'secret' => $result['secret'],
              'qrCodeUri' => $result['qrCodeUri'],
              'expiresIn' => 600, // 10 minutes
          ]);
      }

      /**
       * Complete 2FA setup by verifying code.
       */
      #[Route('/setup/verify', name: 'setup_verify', methods: ['POST'])]
      public function verifySetup(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $data = $this->validationHelper->decodeJsonBody($request);
          $setupToken = $data['setupToken'] ?? $data['setup_token'] ?? null;
          $code = $data['code'] ?? null;

          if ($setupToken === null) {
              throw ValidationException::forField('setupToken', 'Setup token is required');
          }
          if ($code === null) {
              throw ValidationException::forField('code', 'Verification code is required');
          }

          $result = $this->twoFactorService->completeSetup($user, $setupToken, $code);

          return $this->responseFormatter->success([
              'enabled' => true,
              'backupCodes' => $result['backupCodes'],
              'message' => 'Two-factor authentication enabled. Save your backup codes securely.',
          ]);
      }

      /**
       * Disable 2FA.
       */
      #[Route('/disable', name: 'disable', methods: ['POST'])]
      public function disable(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $data = $this->validationHelper->decodeJsonBody($request);
          $password = $data['password'] ?? null;

          if ($password === null) {
              throw ValidationException::forField('password', 'Password is required to disable 2FA');
          }

          // Verify password
          if (!$this->passwordHasher->isPasswordValid($user, $password)) {
              throw ValidationException::forField('password', 'Invalid password');
          }

          $this->twoFactorService->disable($user, $password);

          return $this->responseFormatter->success([
              'enabled' => false,
              'message' => 'Two-factor authentication disabled',
          ]);
      }

      /**
       * Regenerate backup codes.
       */
      #[Route('/backup-codes', name: 'backup_codes', methods: ['POST'])]
      public function regenerateBackupCodes(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $data = $this->validationHelper->decodeJsonBody($request);
          $password = $data['password'] ?? null;

          if ($password === null) {
              throw ValidationException::forField('password', 'Password is required');
          }

          // Verify password
          if (!$this->passwordHasher->isPasswordValid($user, $password)) {
              throw ValidationException::forField('password', 'Invalid password');
          }

          $codes = $this->twoFactorService->regenerateBackupCodes($user);

          return $this->responseFormatter->success([
              'backupCodes' => $codes,
              'message' => 'New backup codes generated. Previous codes are now invalid.',
          ]);
      }
  }
  ```

- [ ] **11.3.3** Create 2FA setup UI
  ```twig
  {# templates/settings/2fa-setup.html.twig #}
  {% extends 'settings/layout.html.twig' %}

  {% block settings_content %}
  <div x-data="twoFactorSetup()" class="max-w-lg">
      <div class="bg-white shadow rounded-lg p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">
              Set Up Two-Factor Authentication
          </h2>

          {# Step 1: Scan QR Code #}
          <div x-show="step === 1" class="space-y-4">
              <p class="text-sm text-gray-600">
                  Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
              </p>

              <div class="flex justify-center p-4 bg-gray-50 rounded-lg">
                  <div x-ref="qrcode" class="w-48 h-48"></div>
              </div>

              <div class="text-center">
                  <button @click="showManual = !showManual" type="button"
                          class="text-sm text-indigo-600 hover:text-indigo-500">
                      Can't scan? Enter manually
                  </button>
              </div>

              <div x-show="showManual" x-transition class="p-4 bg-gray-50 rounded-lg">
                  <p class="text-xs text-gray-500 mb-2">Manual entry key:</p>
                  <code class="text-sm font-mono bg-gray-200 px-2 py-1 rounded break-all"
                        x-text="secret"></code>
              </div>

              <button @click="step = 2" type="button"
                      class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md font-semibold">
                  Continue
              </button>
          </div>

          {# Step 2: Verify Code #}
          <div x-show="step === 2" class="space-y-4">
              <p class="text-sm text-gray-600">
                  Enter the 6-digit code from your authenticator app to verify setup.
              </p>

              <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                      Verification Code
                  </label>
                  <input type="text" x-model="code" maxlength="6" pattern="[0-9]{6}"
                         placeholder="000000" autocomplete="one-time-code"
                         class="block w-full text-center text-2xl tracking-widest font-mono
                                rounded-md border-gray-300 shadow-sm
                                focus:border-indigo-500 focus:ring-indigo-500">
              </div>

              <div x-show="error" class="text-sm text-red-600" x-text="error"></div>

              <div class="flex gap-3">
                  <button @click="step = 1" type="button"
                          class="flex-1 bg-gray-100 text-gray-700 py-2 px-4 rounded-md font-semibold">
                      Back
                  </button>
                  <button @click="verifySetup()" type="button" :disabled="code.length !== 6 || verifying"
                          class="flex-1 bg-indigo-600 text-white py-2 px-4 rounded-md font-semibold
                                 disabled:opacity-50">
                      <span x-show="!verifying">Enable 2FA</span>
                      <span x-show="verifying">Verifying...</span>
                  </button>
              </div>
          </div>

          {# Step 3: Backup Codes #}
          <div x-show="step === 3" class="space-y-4">
              <div class="rounded-md bg-green-50 p-4">
                  <div class="flex">
                      <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                      </svg>
                      <p class="ml-3 text-sm text-green-800">
                          Two-factor authentication is now enabled!
                      </p>
                  </div>
              </div>

              <div class="rounded-md bg-yellow-50 p-4">
                  <h3 class="text-sm font-medium text-yellow-800 mb-2">
                      Save Your Backup Codes
                  </h3>
                  <p class="text-sm text-yellow-700 mb-4">
                      If you lose access to your authenticator app, you can use these codes to sign in.
                      Each code can only be used once.
                  </p>

                  <div class="grid grid-cols-2 gap-2 p-4 bg-white rounded border border-yellow-200">
                      <template x-for="code in backupCodes" :key="code">
                          <code class="text-sm font-mono text-center py-1" x-text="code"></code>
                      </template>
                  </div>

                  <div class="mt-4 flex gap-2">
                      <button @click="downloadBackupCodes()" type="button"
                              class="text-sm text-indigo-600 hover:text-indigo-500">
                          Download codes
                      </button>
                      <button @click="copyBackupCodes()" type="button"
                              class="text-sm text-indigo-600 hover:text-indigo-500">
                          Copy to clipboard
                      </button>
                  </div>
              </div>

              <a href="{{ path('app_settings_security') }}"
                 class="block w-full text-center bg-indigo-600 text-white py-2 px-4 rounded-md font-semibold">
                  Done
              </a>
          </div>
      </div>
  </div>
  {% endblock %}

  {% block javascripts %}
  {{ parent() }}
  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
  <script>
  function twoFactorSetup() {
      return {
          step: 1,
          secret: '',
          setupToken: '',
          qrCodeUri: '',
          showManual: false,
          code: '',
          error: '',
          verifying: false,
          backupCodes: [],

          async init() {
              // Initialize setup
              const response = await fetch('/api/v1/2fa/setup', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' }
              });
              const data = await response.json();

              if (data.success) {
                  this.secret = data.data.secret;
                  this.setupToken = data.data.setupToken;
                  this.qrCodeUri = data.data.qrCodeUri;

                  // Generate QR code
                  QRCode.toCanvas(this.$refs.qrcode, this.qrCodeUri, {
                      width: 192,
                      margin: 0
                  });
              }
          },

          async verifySetup() {
              this.verifying = true;
              this.error = '';

              try {
                  const response = await fetch('/api/v1/2fa/setup/verify', {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json' },
                      body: JSON.stringify({
                          setupToken: this.setupToken,
                          code: this.code
                      })
                  });
                  const data = await response.json();

                  if (data.success) {
                      this.backupCodes = data.data.backupCodes;
                      this.step = 3;
                  } else {
                      this.error = data.error?.message || 'Verification failed';
                  }
              } catch (e) {
                  this.error = 'An error occurred';
              } finally {
                  this.verifying = false;
              }
          },

          downloadBackupCodes() {
              const content = 'Todo-Me Backup Codes\n' +
                  'Generated: ' + new Date().toISOString() + '\n\n' +
                  this.backupCodes.join('\n');

              const blob = new Blob([content], { type: 'text/plain' });
              const url = URL.createObjectURL(blob);
              const a = document.createElement('a');
              a.href = url;
              a.download = 'todo-me-backup-codes.txt';
              a.click();
          },

          copyBackupCodes() {
              navigator.clipboard.writeText(this.backupCodes.join('\n'));
          }
      };
  }
  </script>
  {% endblock %}
  ```

### Completion Criteria
- [ ] QR code generated for authenticator apps
- [ ] Manual entry secret available
- [ ] Code verification before enabling
- [ ] Backup codes generated on enable
- [ ] Setup token prevents CSRF

---

## Sub-Phase 11.4: Login 2FA Verification

### Objective
Require 2FA verification during login for users who have it enabled.

### Tasks

- [ ] **11.4.1** Create TwoFactorChallenge entity for pending verifications
  ```php
  // Store pending 2FA challenges in Redis instead of database
  // This is handled by TwoFactorLoginService below
  ```

- [ ] **11.4.2** Create TwoFactorLoginService
  ```php
  // src/Service/TwoFactorLoginService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\User;
  use App\Exception\TwoFactorRequiredException;
  use App\Exception\ValidationException;
  use Predis\Client as RedisClient;

  final class TwoFactorLoginService
  {
      private const CHALLENGE_PREFIX = '2fa_challenge:';
      private const CHALLENGE_TTL = 300; // 5 minutes

      public function __construct(
          private readonly RedisClient $redis,
          private readonly TwoFactorService $twoFactorService,
          private readonly TokenGenerator $tokenGenerator,
      ) {}

      /**
       * Create a 2FA challenge for a user.
       * Called after successful password authentication.
       */
      public function createChallenge(User $user): string
      {
          $challengeToken = $this->tokenGenerator->generateSecureToken();

          $this->redis->setex(
              self::CHALLENGE_PREFIX . $challengeToken,
              self::CHALLENGE_TTL,
              json_encode([
                  'userId' => $user->getId(),
                  'createdAt' => time(),
              ])
          );

          return $challengeToken;
      }

      /**
       * Verify a 2FA challenge.
       */
      public function verifyChallenge(string $challengeToken, string $code): string
      {
          $challengeData = $this->redis->get(self::CHALLENGE_PREFIX . $challengeToken);

          if ($challengeData === null) {
              throw ValidationException::forField('challengeToken', 'Challenge expired or invalid');
          }

          $data = json_decode($challengeData, true);
          $userId = $data['userId'];

          // Get user and verify
          // This should be done through a repository
          // For now, return userId and let caller handle

          return $userId;
      }

      /**
       * Complete 2FA verification.
       */
      public function completeVerification(string $challengeToken): void
      {
          $this->redis->del(self::CHALLENGE_PREFIX . $challengeToken);
      }

      /**
       * Check if user requires 2FA and throw exception if so.
       */
      public function checkAndChallenge(User $user): void
      {
          if ($user->isTwoFactorEnabled()) {
              $challengeToken = $this->createChallenge($user);
              throw new TwoFactorRequiredException($challengeToken);
          }
      }
  }
  ```

- [ ] **11.4.3** Create TwoFactorRequiredException
  ```php
  // src/Exception/TwoFactorRequiredException.php

  declare(strict_types=1);

  namespace App\Exception;

  use Symfony\Component\HttpFoundation\Response;
  use Symfony\Component\HttpKernel\Exception\HttpException;

  final class TwoFactorRequiredException extends HttpException
  {
      public function __construct(
          private readonly string $challengeToken,
      ) {
          parent::__construct(Response::HTTP_FORBIDDEN, 'Two-factor authentication required');
      }

      public function getChallengeToken(): string
      {
          return $this->challengeToken;
      }

      public function getErrorCode(): string
      {
          return 'TWO_FACTOR_REQUIRED';
      }
  }
  ```

- [ ] **11.4.4** Update login flow to handle 2FA
  ```php
  // src/Controller/Api/AuthController.php modifications

  /**
   * Login and get API token.
   */
  #[Route('/token', name: 'token', methods: ['POST'])]
  public function token(Request $request): JsonResponse
  {
      $data = $this->validationHelper->decodeJsonBody($request);
      $email = $data['email'] ?? null;
      $password = $data['password'] ?? null;
      $twoFactorCode = $data['twoFactorCode'] ?? $data['two_factor_code'] ?? null;
      $challengeToken = $data['challengeToken'] ?? $data['challenge_token'] ?? null;

      // ... existing validation ...

      // If this is a 2FA verification
      if ($challengeToken !== null) {
          return $this->verify2faChallenge($challengeToken, $twoFactorCode);
      }

      // Normal login flow
      $user = $this->userRepository->findOneBy(['email' => $email]);

      if ($user === null || !$this->passwordHasher->isPasswordValid($user, $password)) {
          throw ValidationException::forField('credentials', 'Invalid email or password');
      }

      // Check if 2FA is required
      if ($user->isTwoFactorEnabled()) {
          $challenge = $this->twoFactorLoginService->createChallenge($user);

          return $this->responseFormatter->success([
              'twoFactorRequired' => true,
              'challengeToken' => $challenge,
              'message' => 'Please enter your two-factor authentication code',
          ], Response::HTTP_OK);
      }

      // No 2FA, issue token
      return $this->issueToken($user);
  }

  private function verify2faChallenge(string $challengeToken, ?string $code): JsonResponse
  {
      if ($code === null) {
          throw ValidationException::forField('twoFactorCode', '2FA code is required');
      }

      $userId = $this->twoFactorLoginService->verifyChallenge($challengeToken, $code);
      $user = $this->userRepository->find($userId);

      if ($user === null) {
          throw ValidationException::forField('challengeToken', 'Invalid challenge');
      }

      // Verify the 2FA code
      $valid = $this->twoFactorService->verify($user, $code);

      if (!$valid) {
          // Try backup code
          $valid = $this->twoFactorService->verifyWithBackupCode($user, $code);
      }

      if (!$valid) {
          throw ValidationException::forField('twoFactorCode', 'Invalid verification code');
      }

      // Clean up challenge
      $this->twoFactorLoginService->completeVerification($challengeToken);

      return $this->issueToken($user);
  }

  private function issueToken(User $user): JsonResponse
  {
      $token = $this->tokenGenerator->generateApiToken();
      $user->setApiToken($token);
      $user->setApiTokenIssuedAt(new \DateTimeImmutable());
      $user->setApiTokenExpiresAt((new \DateTimeImmutable())->modify('+48 hours'));
      $this->entityManager->flush();

      return $this->responseFormatter->success([
          'token' => $token,
          'expiresAt' => $user->getApiTokenExpiresAt()->format(\DateTimeInterface::RFC3339),
          'user' => [
              'id' => $user->getId(),
              'email' => $user->getEmail(),
          ],
      ]);
  }
  ```

- [ ] **11.4.5** Create 2FA verification UI for login
  ```twig
  {# templates/security/2fa-verify.html.twig #}
  {% extends 'base.html.twig' %}

  {% block body %}
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
      <div class="max-w-md w-full space-y-8">
          <div class="text-center">
              <h2 class="text-3xl font-extrabold text-gray-900">
                  Two-Factor Authentication
              </h2>
              <p class="mt-2 text-sm text-gray-600">
                  Enter the code from your authenticator app
              </p>
          </div>

          <form x-data="twoFactorVerify('{{ challengeToken }}')" @submit.prevent="verify()" class="mt-8 space-y-6">
              <div>
                  <label for="code" class="sr-only">Verification Code</label>
                  <input id="code" type="text" x-model="code" required
                         maxlength="8" pattern="[0-9A-Z\-]{6,9}"
                         placeholder="Enter code" autocomplete="one-time-code"
                         class="appearance-none rounded-md relative block w-full px-3 py-3
                                border border-gray-300 placeholder-gray-500 text-gray-900
                                text-center text-xl font-mono tracking-widest
                                focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
              </div>

              <div x-show="error" x-transition class="text-sm text-red-600 text-center" x-text="error"></div>

              <button type="submit" :disabled="submitting"
                      class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md
                             shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700
                             disabled:opacity-50">
                  <span x-show="!submitting">Verify</span>
                  <span x-show="submitting">Verifying...</span>
              </button>

              <div class="text-center">
                  <button type="button" @click="showBackupInput = !showBackupInput"
                          class="text-sm text-indigo-600 hover:text-indigo-500">
                      Use a backup code instead
                  </button>
              </div>

              <div x-show="showBackupInput" x-transition class="text-center text-sm text-gray-500">
                  <p>Enter one of your 8-character backup codes (e.g., XXXX-XXXX)</p>
              </div>
          </form>
      </div>
  </div>
  {% endblock %}
  ```

### Completion Criteria
- [ ] Login returns challenge token if 2FA enabled
- [ ] Challenge token valid for 5 minutes
- [ ] TOTP code verification works
- [ ] Backup code verification works
- [ ] Session/token issued after successful 2FA

---

## Sub-Phase 11.5: Recovery Flow

### Objective
Allow users to recover access if they lose their authenticator device.

### Tasks

- [ ] **11.5.1** Create recovery endpoint using backup codes
  ```php
  // Already covered in 11.4 - backup codes work during login
  ```

- [ ] **11.5.2** Add account recovery via email (emergency disable)
  ```php
  // src/Controller/Api/TwoFactorController.php additions

  /**
   * Request emergency 2FA disable via email.
   * This is for users who have lost both their authenticator AND backup codes.
   */
  #[Route('/recovery/request', name: 'recovery_request', methods: ['POST'])]
  public function requestRecovery(Request $request): JsonResponse
  {
      $data = $this->validationHelper->decodeJsonBody($request);
      $email = $data['email'] ?? null;

      if ($email === null) {
          throw ValidationException::forField('email', 'Email is required');
      }

      $user = $this->userRepository->findOneBy(['email' => $email]);

      // Always return success to prevent enumeration
      if ($user !== null && $user->isTwoFactorEnabled()) {
          $this->twoFactorRecoveryService->sendRecoveryEmail($user);
      }

      return $this->responseFormatter->success([
          'message' => 'If 2FA is enabled for this account, a recovery email has been sent',
      ]);
  }

  /**
   * Complete 2FA recovery (disable 2FA via email token).
   */
  #[Route('/recovery/complete', name: 'recovery_complete', methods: ['POST'])]
  public function completeRecovery(Request $request): JsonResponse
  {
      $data = $this->validationHelper->decodeJsonBody($request);
      $token = $data['token'] ?? null;

      if ($token === null) {
          throw ValidationException::forField('token', 'Recovery token is required');
      }

      $user = $this->twoFactorRecoveryService->disableViaRecoveryToken($token);

      return $this->responseFormatter->success([
          'message' => 'Two-factor authentication has been disabled. Please sign in and set up 2FA again.',
      ]);
  }
  ```

- [ ] **11.5.3** Create TwoFactorRecoveryService
  ```php
  // src/Service/TwoFactorRecoveryService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\User;
  use App\Exception\ValidationException;
  use App\Repository\UserRepository;
  use Doctrine\ORM\EntityManagerInterface;

  final class TwoFactorRecoveryService
  {
      private const TOKEN_EXPIRY_HOURS = 24;

      public function __construct(
          private readonly EntityManagerInterface $entityManager,
          private readonly UserRepository $userRepository,
          private readonly TokenGenerator $tokenGenerator,
          private readonly EmailService $emailService,
      ) {}

      public function sendRecoveryEmail(User $user): void
      {
          $token = $this->tokenGenerator->generateSecureToken();

          // Store token (reuse password reset fields or create dedicated fields)
          // For simplicity, we'll use a Redis-based approach
          // In production, you might want dedicated database fields

          $this->emailService->send2faRecoveryEmail(
              $user->getEmail(),
              $token,
              $user->getUsername()
          );
      }

      public function disableViaRecoveryToken(string $token): User
      {
          // Validate token and get user
          // This implementation depends on how tokens are stored

          // Disable 2FA
          $user->setTwoFactorEnabled(false);
          $user->setTotpSecret(null);
          $user->setBackupCodes(null);

          // Invalidate all sessions/tokens for security
          $user->setApiToken(null);

          $this->entityManager->flush();

          return $user;
      }
  }
  ```

### Completion Criteria
- [ ] Backup codes can be used for login
- [ ] Emergency recovery via email available
- [ ] Recovery disables 2FA and invalidates sessions
- [ ] No enumeration via recovery endpoint

---

## Sub-Phase 11.6: Testing

### Tasks

- [ ] **11.6.1** TOTP service tests
  ```php
  // tests/Unit/Service/TotpServiceTest.php
  public function testGenerateSecret(): void;
  public function testVerifyValidCode(): void;
  public function testVerifyInvalidCode(): void;
  public function testVerifyWithClockDrift(): void;
  public function testProvisioningUri(): void;
  ```

- [ ] **11.6.2** Backup code tests
  ```php
  // tests/Unit/Service/BackupCodeServiceTest.php
  public function testGenerateBackupCodes(): void;
  public function testVerifyValidBackupCode(): void;
  public function testBackupCodeSingleUse(): void;
  public function testInvalidBackupCode(): void;
  ```

- [ ] **11.6.3** 2FA setup tests
  ```php
  // tests/Functional/Api/TwoFactorSetupApiTest.php
  public function testInitializeSetup(): void;
  public function testCompleteSetupWithValidCode(): void;
  public function testCompleteSetupWithInvalidCode(): void;
  public function testSetupTokenExpiry(): void;
  public function testCannotEnableIfAlreadyEnabled(): void;
  public function testDisable2fa(): void;
  public function testDisableRequiresPassword(): void;
  public function testRegenerateBackupCodes(): void;
  ```

- [ ] **11.6.4** 2FA login tests
  ```php
  // tests/Functional/Api/TwoFactorLoginApiTest.php
  public function testLoginRequires2faWhenEnabled(): void;
  public function testLoginWith2faCode(): void;
  public function testLoginWithBackupCode(): void;
  public function testLoginWithInvalid2faCode(): void;
  public function testChallengeTokenExpiry(): void;
  ```

- [ ] **11.6.5** Recovery tests
  ```php
  // tests/Functional/Api/TwoFactorRecoveryApiTest.php
  public function testRecoveryRequestNoEnumeration(): void;
  public function testRecoveryDisables2fa(): void;
  public function testRecoveryInvalidatesTokens(): void;
  ```

### Completion Criteria
- [ ] All TOTP operations tested
- [ ] Backup codes tested
- [ ] Setup flow tested
- [ ] Login with 2FA tested
- [ ] Recovery flow tested

---

## Phase 11 Deliverables Checklist

### Database
- [ ] User entity has 2FA fields
- [ ] Migration created and applied
- [ ] Backup codes stored securely

### TOTP
- [ ] Secret generation
- [ ] Code verification with drift tolerance
- [ ] QR code provisioning URI

### Backup Codes
- [ ] 10 codes generated
- [ ] Codes are single-use
- [ ] Codes are hashed in storage

### Setup Flow
- [ ] Initialize setup endpoint
- [ ] QR code display
- [ ] Manual entry option
- [ ] Code verification to enable
- [ ] Backup codes shown on enable

### Login Flow
- [ ] Challenge token issued when 2FA required
- [ ] TOTP verification works
- [ ] Backup code verification works
- [ ] Challenge expires after 5 minutes

### Management
- [ ] Status endpoint
- [ ] Disable with password
- [ ] Regenerate backup codes

### Recovery
- [ ] Email-based emergency disable
- [ ] Session invalidation on recovery

### UI
- [ ] Setup wizard with QR code
- [ ] Login verification page
- [ ] Backup codes display and download

### Testing
- [ ] Unit tests for services
- [ ] Functional tests for endpoints

---

## Implementation Order

1. **11.1**: Database schema
2. **11.2**: TOTP and backup code services
3. **11.3**: Setup flow
4. **11.4**: Login verification
5. **11.5**: Recovery flow
6. **11.6**: Testing

---

## Dependencies

```
Sub-Phase 11.1 (Schema) ──► Sub-Phase 11.2 (Services)
                                    │
                    ┌───────────────┴───────────────┐
                    ▼                               ▼
          Sub-Phase 11.3 (Setup)          Sub-Phase 11.4 (Login)
                    │                               │
                    └───────────────┬───────────────┘
                                    ▼
                          Sub-Phase 11.5 (Recovery)
                                    │
                                    ▼
                          Sub-Phase 11.6 (Testing)
```

---

## Security Considerations

1. **Secret Storage**: TOTP secrets encrypted at rest (consider Symfony Secrets)
2. **Backup Codes**: Hashed with bcrypt, single-use
3. **Challenge Tokens**: Short-lived (5 minutes), stored in Redis
4. **Recovery**: Requires email verification, invalidates all sessions
5. **Rate Limiting**: Apply existing rate limits to 2FA endpoints
6. **Timing Attacks**: Use constant-time comparison for code verification

---

## Files Summary

### New Files
```
src/Service/TotpService.php
src/Service/BackupCodeService.php
src/Service/TwoFactorService.php
src/Service/TwoFactorLoginService.php
src/Service/TwoFactorRecoveryService.php
src/Controller/Api/TwoFactorController.php
src/Exception/TwoFactorRequiredException.php
templates/settings/2fa-setup.html.twig
templates/security/2fa-verify.html.twig
templates/emails/2fa-recovery.html.twig
migrations/Version20260124_TwoFactorAuth.php
tests/Unit/Service/TotpServiceTest.php
tests/Unit/Service/BackupCodeServiceTest.php
tests/Functional/Api/TwoFactorSetupApiTest.php
tests/Functional/Api/TwoFactorLoginApiTest.php
tests/Functional/Api/TwoFactorRecoveryApiTest.php
```

### Updated Files
```
src/Entity/User.php
src/Controller/Api/AuthController.php
src/Controller/Web/SecurityController.php
src/Service/EmailService.php
config/services.yaml
composer.json (add otphp dependency)
```
