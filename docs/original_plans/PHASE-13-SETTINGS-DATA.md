# Phase 13: User Settings & Data Management

## Overview
**Goal**: Create a comprehensive user settings interface, implement timezone support, session management, API token management UI, data import functionality, and activity logging.

## Revision History
- **2026-01-24**: Initial creation based on comprehensive project review

## Prerequisites
- Phase 10 completed (Email infrastructure)
- Phase 11 completed (2FA for security settings)
- Phase 12 completed (Notification preferences)
- User entity with settings JSON field

---

## Why This Phase is Important

User settings and data management are essential for:
- **Timezone support**: Due dates displayed incorrectly without user timezone
- **Account control**: Users need to manage their security settings
- **Data portability**: GDPR requires data export; import enables migration
- **Transparency**: Activity logs show what happened and when

---

## Sub-Phase 13.1: User Settings Page

### Objective
Create a comprehensive settings page with navigation sidebar.

### Tasks

- [ ] **13.1.1** Create SettingsController
  ```php
  // src/Controller/Web/SettingsController.php

  declare(strict_types=1);

  namespace App\Controller\Web;

  use App\Entity\User;
  use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
  use Symfony\Component\HttpFoundation\Response;
  use Symfony\Component\Routing\Attribute\Route;
  use Symfony\Component\Security\Http\Attribute\IsGranted;

  #[Route('/settings', name: 'app_settings_')]
  #[IsGranted('IS_AUTHENTICATED_FULLY')]
  final class SettingsController extends AbstractController
  {
      #[Route('', name: 'index')]
      public function index(): Response
      {
          return $this->redirectToRoute('app_settings_profile');
      }

      #[Route('/profile', name: 'profile')]
      public function profile(): Response
      {
          return $this->render('settings/profile.html.twig');
      }

      #[Route('/security', name: 'security')]
      public function security(): Response
      {
          /** @var User $user */
          $user = $this->getUser();

          return $this->render('settings/security.html.twig', [
              'is2faEnabled' => $user->isTwoFactorEnabled(),
              'backupCodesRemaining' => $user->getBackupCodesRemaining(),
          ]);
      }

      #[Route('/notifications', name: 'notifications')]
      public function notifications(): Response
      {
          /** @var User $user */
          $user = $this->getUser();

          return $this->render('settings/notifications.html.twig', [
              'preferences' => $user->getNotificationSettings(),
          ]);
      }

      #[Route('/api-tokens', name: 'api_tokens')]
      public function apiTokens(): Response
      {
          return $this->render('settings/api-tokens.html.twig');
      }

      #[Route('/data', name: 'data')]
      public function data(): Response
      {
          return $this->render('settings/data.html.twig');
      }

      #[Route('/2fa/setup', name: '2fa_setup')]
      public function twoFactorSetup(): Response
      {
          /** @var User $user */
          $user = $this->getUser();

          if ($user->isTwoFactorEnabled()) {
              return $this->redirectToRoute('app_settings_security');
          }

          return $this->render('settings/2fa-setup.html.twig');
      }
  }
  ```

- [ ] **13.1.2** Create settings layout template
  ```twig
  {# templates/settings/layout.html.twig #}
  {% extends 'base.html.twig' %}

  {% block body %}
  <div class="min-h-screen bg-gray-50">
      <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
          <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">

              {# Sidebar Navigation #}
              <aside class="py-6 px-2 sm:px-6 lg:col-span-3 lg:py-0 lg:px-0">
                  <nav class="space-y-1">
                      <a href="{{ path('app_settings_profile') }}"
                         class="{% if app.request.attributes.get('_route') == 'app_settings_profile' %}
                                    bg-gray-100 text-indigo-700
                                {% else %}
                                    text-gray-600 hover:bg-gray-50 hover:text-gray-900
                                {% endif %}
                                group flex items-center px-3 py-2 text-sm font-medium rounded-md">
                          <svg class="flex-shrink-0 -ml-1 mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                          </svg>
                          Profile
                      </a>

                      <a href="{{ path('app_settings_security') }}"
                         class="{% if app.request.attributes.get('_route') starts with 'app_settings_security' or app.request.attributes.get('_route') starts with 'app_settings_2fa' %}
                                    bg-gray-100 text-indigo-700
                                {% else %}
                                    text-gray-600 hover:bg-gray-50 hover:text-gray-900
                                {% endif %}
                                group flex items-center px-3 py-2 text-sm font-medium rounded-md">
                          <svg class="flex-shrink-0 -ml-1 mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                          </svg>
                          Security
                      </a>

                      <a href="{{ path('app_settings_notifications') }}"
                         class="{% if app.request.attributes.get('_route') == 'app_settings_notifications' %}
                                    bg-gray-100 text-indigo-700
                                {% else %}
                                    text-gray-600 hover:bg-gray-50 hover:text-gray-900
                                {% endif %}
                                group flex items-center px-3 py-2 text-sm font-medium rounded-md">
                          <svg class="flex-shrink-0 -ml-1 mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                          </svg>
                          Notifications
                      </a>

                      <a href="{{ path('app_settings_api_tokens') }}"
                         class="{% if app.request.attributes.get('_route') == 'app_settings_api_tokens' %}
                                    bg-gray-100 text-indigo-700
                                {% else %}
                                    text-gray-600 hover:bg-gray-50 hover:text-gray-900
                                {% endif %}
                                group flex items-center px-3 py-2 text-sm font-medium rounded-md">
                          <svg class="flex-shrink-0 -ml-1 mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                          </svg>
                          API Tokens
                      </a>

                      <a href="{{ path('app_settings_data') }}"
                         class="{% if app.request.attributes.get('_route') == 'app_settings_data' %}
                                    bg-gray-100 text-indigo-700
                                {% else %}
                                    text-gray-600 hover:bg-gray-50 hover:text-gray-900
                                {% endif %}
                                group flex items-center px-3 py-2 text-sm font-medium rounded-md">
                          <svg class="flex-shrink-0 -ml-1 mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                          </svg>
                          Data & Privacy
                      </a>
                  </nav>
              </aside>

              {# Main content #}
              <main class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">
                  {% block settings_content %}{% endblock %}
              </main>
          </div>
      </div>
  </div>
  {% endblock %}
  ```

- [ ] **13.1.3** Create profile settings page
  ```twig
  {# templates/settings/profile.html.twig #}
  {% extends 'settings/layout.html.twig' %}

  {% block settings_content %}
  <div x-data="profileSettings()" class="space-y-6">
      {# Profile Information #}
      <div class="bg-white shadow rounded-lg">
          <div class="px-4 py-5 sm:p-6">
              <h2 class="text-lg font-medium text-gray-900 mb-4">Profile Information</h2>

              <form @submit.prevent="saveProfile()" class="space-y-4">
                  <div>
                      <label class="block text-sm font-medium text-gray-700">Email</label>
                      <input type="email" disabled value="{{ app.user.email }}"
                             class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm sm:text-sm">
                      <p class="mt-1 text-xs text-gray-500">Email cannot be changed</p>
                  </div>

                  <div>
                      <label class="block text-sm font-medium text-gray-700">Display Name</label>
                      <input type="text" x-model="username" minlength="3" maxlength="50"
                             class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
                                    focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                  </div>

                  <div x-show="profileError" class="text-sm text-red-600" x-text="profileError"></div>
                  <div x-show="profileSuccess" class="text-sm text-green-600" x-text="profileSuccess"></div>

                  <button type="submit" :disabled="savingProfile"
                          class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-semibold
                                 hover:bg-indigo-500 disabled:opacity-50">
                      <span x-show="!savingProfile">Save Changes</span>
                      <span x-show="savingProfile">Saving...</span>
                  </button>
              </form>
          </div>
      </div>

      {# Regional Settings #}
      <div class="bg-white shadow rounded-lg">
          <div class="px-4 py-5 sm:p-6">
              <h2 class="text-lg font-medium text-gray-900 mb-4">Regional Settings</h2>

              <form @submit.prevent="saveRegional()" class="space-y-4">
                  <div>
                      <label class="block text-sm font-medium text-gray-700">Timezone</label>
                      <select x-model="timezone"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
                                     focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                          {% for tz in timezones %}
                          <option value="{{ tz }}">{{ tz }}</option>
                          {% endfor %}
                      </select>
                      <p class="mt-1 text-xs text-gray-500">
                          Used for due date reminders and display
                      </p>
                  </div>

                  <div>
                      <label class="block text-sm font-medium text-gray-700">Date Format</label>
                      <select x-model="dateFormat"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
                                     focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                          <option value="MDY">MM/DD/YYYY (US)</option>
                          <option value="DMY">DD/MM/YYYY (European)</option>
                          <option value="YMD">YYYY-MM-DD (ISO)</option>
                      </select>
                  </div>

                  <div>
                      <label class="block text-sm font-medium text-gray-700">Start of Week</label>
                      <select x-model="startOfWeek"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
                                     focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                          <option value="0">Sunday</option>
                          <option value="1">Monday</option>
                      </select>
                  </div>

                  <div x-show="regionalError" class="text-sm text-red-600" x-text="regionalError"></div>
                  <div x-show="regionalSuccess" class="text-sm text-green-600" x-text="regionalSuccess"></div>

                  <button type="submit" :disabled="savingRegional"
                          class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-semibold
                                 hover:bg-indigo-500 disabled:opacity-50">
                      <span x-show="!savingRegional">Save Changes</span>
                      <span x-show="savingRegional">Saving...</span>
                  </button>
              </form>
          </div>
      </div>
  </div>

  <script>
  function profileSettings() {
      return {
          username: '{{ app.user.username }}',
          timezone: '{{ app.user.timezone ?? 'UTC' }}',
          dateFormat: '{{ app.user.settings.date_format ?? 'MDY' }}',
          startOfWeek: '{{ app.user.settings.start_of_week ?? 0 }}',
          savingProfile: false,
          savingRegional: false,
          profileError: '',
          profileSuccess: '',
          regionalError: '',
          regionalSuccess: '',

          async saveProfile() {
              this.savingProfile = true;
              this.profileError = '';
              this.profileSuccess = '';

              try {
                  const response = await fetch('/api/v1/users/me/settings', {
                      method: 'PATCH',
                      headers: { 'Content-Type': 'application/json' },
                      body: JSON.stringify({ username: this.username })
                  });

                  const data = await response.json();

                  if (data.success) {
                      this.profileSuccess = 'Profile updated successfully';
                  } else {
                      this.profileError = data.error?.message || 'Failed to update profile';
                  }
              } catch (e) {
                  this.profileError = 'An error occurred';
              } finally {
                  this.savingProfile = false;
              }
          },

          async saveRegional() {
              this.savingRegional = true;
              this.regionalError = '';
              this.regionalSuccess = '';

              try {
                  const response = await fetch('/api/v1/users/me/settings', {
                      method: 'PATCH',
                      headers: { 'Content-Type': 'application/json' },
                      body: JSON.stringify({
                          timezone: this.timezone,
                          dateFormat: this.dateFormat,
                          startOfWeek: parseInt(this.startOfWeek)
                      })
                  });

                  const data = await response.json();

                  if (data.success) {
                      this.regionalSuccess = 'Settings updated successfully';
                  } else {
                      this.regionalError = data.error?.message || 'Failed to update settings';
                  }
              } catch (e) {
                  this.regionalError = 'An error occurred';
              } finally {
                  this.savingRegional = false;
              }
          }
      };
  }
  </script>
  {% endblock %}
  ```

- [ ] **13.1.4** Add timezone to User entity
  ```php
  // src/Entity/User.php additions

  public function getTimezone(): string
  {
      return $this->settings['timezone'] ?? 'UTC';
  }

  public function setTimezone(string $timezone): self
  {
      $settings = $this->settings ?? [];
      $settings['timezone'] = $timezone;
      $this->settings = $settings;
      return $this;
  }

  public function getDateFormat(): string
  {
      return $this->settings['date_format'] ?? 'MDY';
  }

  public function getStartOfWeek(): int
  {
      return $this->settings['start_of_week'] ?? 0;
  }
  ```

- [ ] **13.1.5** Create UserSettingsRequest DTO
  ```php
  // src/DTO/UserSettingsRequest.php

  declare(strict_types=1);

  namespace App\DTO;

  use Symfony\Component\Validator\Constraints as Assert;

  final class UserSettingsRequest
  {
      public function __construct(
          #[Assert\Length(min: 3, max: 50)]
          public readonly ?string $username = null,

          #[Assert\Timezone]
          public readonly ?string $timezone = null,

          #[Assert\Choice(choices: ['MDY', 'DMY', 'YMD'])]
          public readonly ?string $dateFormat = null,

          #[Assert\Choice(choices: [0, 1])]
          public readonly ?int $startOfWeek = null,
      ) {}

      public static function fromArray(array $data): self
      {
          return new self(
              username: $data['username'] ?? null,
              timezone: $data['timezone'] ?? null,
              dateFormat: $data['dateFormat'] ?? $data['date_format'] ?? null,
              startOfWeek: isset($data['startOfWeek']) || isset($data['start_of_week'])
                  ? (int) ($data['startOfWeek'] ?? $data['start_of_week'])
                  : null,
          );
      }
  }
  ```

- [ ] **13.1.6** Create settings API endpoint
  ```php
  // src/Controller/Api/UserController.php

  declare(strict_types=1);

  namespace App\Controller\Api;

  use App\DTO\UserSettingsRequest;
  use App\Entity\User;
  use App\Service\ResponseFormatter;
  use App\Service\ValidationHelper;
  use Doctrine\ORM\EntityManagerInterface;
  use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
  use Symfony\Component\HttpFoundation\JsonResponse;
  use Symfony\Component\HttpFoundation\Request;
  use Symfony\Component\Routing\Attribute\Route;
  use Symfony\Component\Validator\Validator\ValidatorInterface;

  #[Route('/api/v1/users', name: 'api_users_')]
  final class UserController extends AbstractController
  {
      public function __construct(
          private readonly EntityManagerInterface $entityManager,
          private readonly ResponseFormatter $responseFormatter,
          private readonly ValidationHelper $validationHelper,
          private readonly ValidatorInterface $validator,
      ) {}

      /**
       * Get current user profile.
       */
      #[Route('/me', name: 'me', methods: ['GET'])]
      public function me(): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          return $this->responseFormatter->success([
              'id' => $user->getId(),
              'email' => $user->getEmail(),
              'username' => $user->getUsername(),
              'emailVerified' => $user->isEmailVerified(),
              'twoFactorEnabled' => $user->isTwoFactorEnabled(),
              'settings' => $user->getSettingsWithDefaults(),
              'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::RFC3339),
          ]);
      }

      /**
       * Update user settings.
       */
      #[Route('/me/settings', name: 'update_settings', methods: ['PATCH'])]
      public function updateSettings(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $data = $this->validationHelper->decodeJsonBody($request);
          $settingsRequest = UserSettingsRequest::fromArray($data);

          $errors = $this->validator->validate($settingsRequest);
          if (count($errors) > 0) {
              return $this->responseFormatter->validationError($errors);
          }

          // Update username if provided
          if ($settingsRequest->username !== null) {
              $user->setUsername($settingsRequest->username);
          }

          // Update settings
          $settings = $user->getSettings() ?? [];

          if ($settingsRequest->timezone !== null) {
              $settings['timezone'] = $settingsRequest->timezone;
          }
          if ($settingsRequest->dateFormat !== null) {
              $settings['date_format'] = $settingsRequest->dateFormat;
          }
          if ($settingsRequest->startOfWeek !== null) {
              $settings['start_of_week'] = $settingsRequest->startOfWeek;
          }

          $user->setSettings($settings);
          $this->entityManager->flush();

          return $this->responseFormatter->success([
              'settings' => $user->getSettingsWithDefaults(),
          ]);
      }
  }
  ```

### Completion Criteria
- [ ] Settings page with navigation
- [ ] Profile editing (username)
- [ ] Timezone selection
- [ ] Date format preference
- [ ] Week start preference
- [ ] API endpoints working

---

## Sub-Phase 13.2: Session Management

### Objective
Allow users to view and manage active sessions.

### Tasks

- [ ] **13.2.1** Create UserSession entity
  ```php
  // src/Entity/UserSession.php

  declare(strict_types=1);

  namespace App\Entity;

  use App\Interface\UserOwnedInterface;
  use Doctrine\DBAL\Types\Types;
  use Doctrine\ORM\Mapping as ORM;

  #[ORM\Entity(repositoryClass: 'App\Repository\UserSessionRepository')]
  #[ORM\Table(name: 'user_sessions')]
  #[ORM\Index(columns: ['owner_id'], name: 'idx_user_sessions_owner')]
  #[ORM\Index(columns: ['token_hash'], name: 'idx_user_sessions_token')]
  class UserSession implements UserOwnedInterface
  {
      #[ORM\Id]
      #[ORM\Column(type: 'guid')]
      #[ORM\GeneratedValue(strategy: 'CUSTOM')]
      #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
      private ?string $id = null;

      #[ORM\ManyToOne(targetEntity: User::class)]
      #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
      private User $owner;

      #[ORM\Column(type: Types::STRING, length: 64, name: 'token_hash')]
      private string $tokenHash;

      #[ORM\Column(type: Types::STRING, length: 500, nullable: true, name: 'user_agent')]
      private ?string $userAgent = null;

      #[ORM\Column(type: Types::STRING, length: 45, nullable: true, name: 'ip_address')]
      private ?string $ipAddress = null;

      #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
      private ?string $device = null;

      #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
      private ?string $browser = null;

      #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
      private ?string $location = null;

      #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at')]
      private \DateTimeImmutable $createdAt;

      #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'last_active_at')]
      private \DateTimeImmutable $lastActiveAt;

      public function __construct()
      {
          $this->createdAt = new \DateTimeImmutable();
          $this->lastActiveAt = new \DateTimeImmutable();
      }

      // Getters and setters...

      public function getId(): ?string { return $this->id; }
      public function getOwner(): User { return $this->owner; }
      public function setOwner(User $owner): self { $this->owner = $owner; return $this; }
      public function getTokenHash(): string { return $this->tokenHash; }
      public function setTokenHash(string $hash): self { $this->tokenHash = $hash; return $this; }
      public function getUserAgent(): ?string { return $this->userAgent; }
      public function setUserAgent(?string $ua): self { $this->userAgent = $ua; return $this; }
      public function getIpAddress(): ?string { return $this->ipAddress; }
      public function setIpAddress(?string $ip): self { $this->ipAddress = $ip; return $this; }
      public function getDevice(): ?string { return $this->device; }
      public function setDevice(?string $device): self { $this->device = $device; return $this; }
      public function getBrowser(): ?string { return $this->browser; }
      public function setBrowser(?string $browser): self { $this->browser = $browser; return $this; }
      public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
      public function getLastActiveAt(): \DateTimeImmutable { return $this->lastActiveAt; }
      public function setLastActiveAt(\DateTimeImmutable $at): self { $this->lastActiveAt = $at; return $this; }
  }
  ```

- [ ] **13.2.2** Create SessionService
  ```php
  // src/Service/SessionService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\User;
  use App\Entity\UserSession;
  use App\Repository\UserSessionRepository;
  use Doctrine\ORM\EntityManagerInterface;
  use Symfony\Component\HttpFoundation\Request;

  final class SessionService
  {
      public function __construct(
          private readonly EntityManagerInterface $entityManager,
          private readonly UserSessionRepository $sessionRepository,
      ) {}

      public function createSession(User $user, string $token, Request $request): UserSession
      {
          $session = new UserSession();
          $session->setOwner($user);
          $session->setTokenHash(hash('sha256', $token));
          $session->setUserAgent($request->headers->get('User-Agent'));
          $session->setIpAddress($request->getClientIp());

          $userAgent = $request->headers->get('User-Agent', '');
          $session->setDevice($this->parseDevice($userAgent));
          $session->setBrowser($this->parseBrowser($userAgent));

          $this->entityManager->persist($session);
          $this->entityManager->flush();

          return $session;
      }

      public function updateLastActive(string $token): void
      {
          $tokenHash = hash('sha256', $token);
          $session = $this->sessionRepository->findOneBy(['tokenHash' => $tokenHash]);

          if ($session !== null) {
              $session->setLastActiveAt(new \DateTimeImmutable());
              $this->entityManager->flush();
          }
      }

      public function getSessions(User $user): array
      {
          return $this->sessionRepository->findBy(
              ['owner' => $user],
              ['lastActiveAt' => 'DESC']
          );
      }

      public function revokeSession(User $user, string $sessionId): bool
      {
          $session = $this->sessionRepository->find($sessionId);

          if ($session === null || $session->getOwner()->getId() !== $user->getId()) {
              return false;
          }

          $this->entityManager->remove($session);
          $this->entityManager->flush();

          return true;
      }

      public function revokeAllSessions(User $user, ?string $exceptTokenHash = null): int
      {
          return $this->sessionRepository->revokeAllForUser($user, $exceptTokenHash);
      }

      public function isCurrentSession(UserSession $session, string $currentToken): bool
      {
          return $session->getTokenHash() === hash('sha256', $currentToken);
      }

      private function parseDevice(string $userAgent): string
      {
          if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent)) {
              if (preg_match('/iPad/i', $userAgent)) {
                  return 'Tablet';
              }
              return 'Mobile';
          }
          return 'Desktop';
      }

      private function parseBrowser(string $userAgent): string
      {
          if (preg_match('/Firefox/i', $userAgent)) return 'Firefox';
          if (preg_match('/Chrome/i', $userAgent)) return 'Chrome';
          if (preg_match('/Safari/i', $userAgent)) return 'Safari';
          if (preg_match('/Edge/i', $userAgent)) return 'Edge';
          return 'Unknown';
      }
  }
  ```

- [ ] **13.2.3** Create session management endpoints
  ```php
  // src/Controller/Api/SessionController.php

  declare(strict_types=1);

  namespace App\Controller\Api;

  use App\Entity\User;
  use App\Service\ResponseFormatter;
  use App\Service\SessionService;
  use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
  use Symfony\Component\HttpFoundation\JsonResponse;
  use Symfony\Component\HttpFoundation\Request;
  use Symfony\Component\HttpFoundation\Response;
  use Symfony\Component\Routing\Attribute\Route;

  #[Route('/api/v1/sessions', name: 'api_sessions_')]
  final class SessionController extends AbstractController
  {
      public function __construct(
          private readonly SessionService $sessionService,
          private readonly ResponseFormatter $responseFormatter,
      ) {}

      /**
       * List active sessions.
       */
      #[Route('', name: 'list', methods: ['GET'])]
      public function list(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $currentToken = $this->extractToken($request);
          $sessions = $this->sessionService->getSessions($user);

          return $this->responseFormatter->success([
              'sessions' => array_map(fn($s) => [
                  'id' => $s->getId(),
                  'device' => $s->getDevice(),
                  'browser' => $s->getBrowser(),
                  'ipAddress' => $s->getIpAddress(),
                  'lastActiveAt' => $s->getLastActiveAt()->format(\DateTimeInterface::RFC3339),
                  'createdAt' => $s->getCreatedAt()->format(\DateTimeInterface::RFC3339),
                  'isCurrent' => $this->sessionService->isCurrentSession($s, $currentToken),
              ], $sessions),
          ]);
      }

      /**
       * Revoke a specific session.
       */
      #[Route('/{id}', name: 'revoke', methods: ['DELETE'])]
      public function revoke(string $id): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $revoked = $this->sessionService->revokeSession($user, $id);

          if (!$revoked) {
              return $this->responseFormatter->error(
                  'Session not found',
                  'NOT_FOUND',
                  Response::HTTP_NOT_FOUND
              );
          }

          return $this->responseFormatter->success([
              'message' => 'Session revoked',
          ]);
      }

      /**
       * Revoke all other sessions.
       */
      #[Route('/revoke-others', name: 'revoke_others', methods: ['POST'])]
      public function revokeOthers(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $currentToken = $this->extractToken($request);
          $currentHash = hash('sha256', $currentToken);

          $count = $this->sessionService->revokeAllSessions($user, $currentHash);

          return $this->responseFormatter->success([
              'message' => sprintf('%d session(s) revoked', $count),
          ]);
      }

      private function extractToken(Request $request): string
      {
          $auth = $request->headers->get('Authorization', '');
          if (str_starts_with($auth, 'Bearer ')) {
              return substr($auth, 7);
          }
          return $request->headers->get('X-API-Key', '');
      }
  }
  ```

- [ ] **13.2.4** Create session management UI
  ```twig
  {# In templates/settings/security.html.twig - add sessions section #}

  {# Active Sessions #}
  <div x-data="sessionsManager()" class="bg-white shadow rounded-lg">
      <div class="px-4 py-5 sm:p-6">
          <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-medium text-gray-900">Active Sessions</h2>
              <button @click="revokeAll()" x-show="sessions.length > 1"
                      class="text-sm text-red-600 hover:text-red-800">
                  Sign out all other devices
              </button>
          </div>

          <ul class="divide-y divide-gray-200">
              <template x-for="session in sessions" :key="session.id">
                  <li class="py-4 flex items-center justify-between">
                      <div class="flex items-center gap-3">
                          <div class="flex-shrink-0">
                              <svg x-show="session.device === 'Desktop'" class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                              </svg>
                              <svg x-show="session.device === 'Mobile'" class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                              </svg>
                          </div>
                          <div>
                              <p class="text-sm font-medium text-gray-900">
                                  <span x-text="session.browser"></span> on <span x-text="session.device"></span>
                              </p>
                              <p class="text-xs text-gray-500">
                                  <span x-text="session.ipAddress"></span> ·
                                  Last active <span x-text="formatDate(session.lastActiveAt)"></span>
                              </p>
                          </div>
                      </div>
                      <div class="flex items-center gap-2">
                          <span x-show="session.isCurrent"
                                class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">
                              This device
                          </span>
                          <button x-show="!session.isCurrent" @click="revoke(session.id)"
                                  class="text-sm text-red-600 hover:text-red-800">
                              Revoke
                          </button>
                      </div>
                  </li>
              </template>
          </ul>
      </div>
  </div>
  ```

### Completion Criteria
- [ ] Sessions tracked on login
- [ ] Sessions list shows device/browser/IP
- [ ] Individual session revocation
- [ ] Revoke all other sessions
- [ ] Current session marked

---

## Sub-Phase 13.3: API Token Management UI

### Objective
Allow users to create and manage multiple named API tokens.

### Tasks

- [ ] **13.3.1** Create ApiToken entity (for multiple tokens)
  ```php
  // src/Entity/ApiToken.php

  declare(strict_types=1);

  namespace App\Entity;

  use App\Interface\UserOwnedInterface;
  use Doctrine\DBAL\Types\Types;
  use Doctrine\ORM\Mapping as ORM;

  #[ORM\Entity(repositoryClass: 'App\Repository\ApiTokenRepository')]
  #[ORM\Table(name: 'api_tokens')]
  #[ORM\Index(columns: ['owner_id'], name: 'idx_api_tokens_owner')]
  #[ORM\Index(columns: ['token_hash'], name: 'idx_api_tokens_hash')]
  class ApiToken implements UserOwnedInterface
  {
      #[ORM\Id]
      #[ORM\Column(type: 'guid')]
      #[ORM\GeneratedValue(strategy: 'CUSTOM')]
      #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
      private ?string $id = null;

      #[ORM\ManyToOne(targetEntity: User::class)]
      #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
      private User $owner;

      #[ORM\Column(type: Types::STRING, length: 100)]
      private string $name;

      #[ORM\Column(type: Types::STRING, length: 64, name: 'token_hash', unique: true)]
      private string $tokenHash;

      #[ORM\Column(type: Types::STRING, length: 8, name: 'token_prefix')]
      private string $tokenPrefix;

      #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'expires_at')]
      private ?\DateTimeImmutable $expiresAt = null;

      #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at')]
      private \DateTimeImmutable $createdAt;

      #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'last_used_at')]
      private ?\DateTimeImmutable $lastUsedAt = null;

      #[ORM\Column(type: Types::JSON, nullable: true)]
      private ?array $scopes = null;

      public function __construct()
      {
          $this->createdAt = new \DateTimeImmutable();
      }

      // Getters and setters...

      public function isExpired(): bool
      {
          if ($this->expiresAt === null) {
              return false;
          }
          return new \DateTimeImmutable() > $this->expiresAt;
      }

      public function isValid(): bool
      {
          return !$this->isExpired();
      }
  }
  ```

- [ ] **13.3.2** Create ApiTokenController
  ```php
  // src/Controller/Api/ApiTokenController.php

  declare(strict_types=1);

  namespace App\Controller\Api;

  use App\Entity\ApiToken;
  use App\Entity\User;
  use App\Repository\ApiTokenRepository;
  use App\Service\ResponseFormatter;
  use App\Service\TokenGenerator;
  use App\Service\ValidationHelper;
  use Doctrine\ORM\EntityManagerInterface;
  use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
  use Symfony\Component\HttpFoundation\JsonResponse;
  use Symfony\Component\HttpFoundation\Request;
  use Symfony\Component\HttpFoundation\Response;
  use Symfony\Component\Routing\Attribute\Route;

  #[Route('/api/v1/api-tokens', name: 'api_tokens_')]
  final class ApiTokenController extends AbstractController
  {
      public function __construct(
          private readonly ApiTokenRepository $tokenRepository,
          private readonly EntityManagerInterface $entityManager,
          private readonly ResponseFormatter $responseFormatter,
          private readonly ValidationHelper $validationHelper,
          private readonly TokenGenerator $tokenGenerator,
      ) {}

      /**
       * List API tokens.
       */
      #[Route('', name: 'list', methods: ['GET'])]
      public function list(): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $tokens = $this->tokenRepository->findBy(['owner' => $user], ['createdAt' => 'DESC']);

          return $this->responseFormatter->success([
              'tokens' => array_map(fn($t) => [
                  'id' => $t->getId(),
                  'name' => $t->getName(),
                  'prefix' => $t->getTokenPrefix() . '...',
                  'expiresAt' => $t->getExpiresAt()?->format(\DateTimeInterface::RFC3339),
                  'lastUsedAt' => $t->getLastUsedAt()?->format(\DateTimeInterface::RFC3339),
                  'createdAt' => $t->getCreatedAt()->format(\DateTimeInterface::RFC3339),
                  'isExpired' => $t->isExpired(),
              ], $tokens),
          ]);
      }

      /**
       * Create a new API token.
       */
      #[Route('', name: 'create', methods: ['POST'])]
      public function create(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $data = $this->validationHelper->decodeJsonBody($request);
          $name = $data['name'] ?? 'API Token';
          $expiresInDays = $data['expiresInDays'] ?? $data['expires_in_days'] ?? null;

          // Generate token
          $plainToken = $this->tokenGenerator->generateApiToken();
          $tokenPrefix = substr($plainToken, 0, 8);
          $tokenHash = hash('sha256', $plainToken);

          $apiToken = new ApiToken();
          $apiToken->setOwner($user);
          $apiToken->setName($name);
          $apiToken->setTokenHash($tokenHash);
          $apiToken->setTokenPrefix($tokenPrefix);

          if ($expiresInDays !== null && $expiresInDays > 0) {
              $expiresAt = (new \DateTimeImmutable())->modify("+{$expiresInDays} days");
              $apiToken->setExpiresAt($expiresAt);
          }

          $this->entityManager->persist($apiToken);
          $this->entityManager->flush();

          return $this->responseFormatter->created([
              'id' => $apiToken->getId(),
              'name' => $apiToken->getName(),
              'token' => $plainToken,
              'expiresAt' => $apiToken->getExpiresAt()?->format(\DateTimeInterface::RFC3339),
              'warning' => 'This token will only be shown once. Save it securely.',
          ]);
      }

      /**
       * Revoke an API token.
       */
      #[Route('/{id}', name: 'revoke', methods: ['DELETE'])]
      public function revoke(string $id): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $token = $this->tokenRepository->find($id);

          if ($token === null || $token->getOwner()->getId() !== $user->getId()) {
              return $this->responseFormatter->error(
                  'Token not found',
                  'NOT_FOUND',
                  Response::HTTP_NOT_FOUND
              );
          }

          $this->entityManager->remove($token);
          $this->entityManager->flush();

          return $this->responseFormatter->success([
              'message' => 'Token revoked',
          ]);
      }
  }
  ```

- [ ] **13.3.3** Create API tokens UI (see Phase 12 plan for template)

### Completion Criteria
- [ ] Multiple named tokens supported
- [ ] Token creation with expiry
- [ ] Token shown only once
- [ ] Token revocation
- [ ] Last used tracking

---

## Sub-Phase 13.4: Data Import

### Objective
Allow users to import data from JSON, CSV, and popular services.

### Tasks

- [ ] **13.4.1** Create ImportService
  ```php
  // src/Service/ImportService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\Project;
  use App\Entity\Task;
  use App\Entity\Tag;
  use App\Entity\User;
  use Doctrine\ORM\EntityManagerInterface;
  use Psr\Log\LoggerInterface;

  final class ImportService
  {
      public function __construct(
          private readonly EntityManagerInterface $entityManager,
          private readonly LoggerInterface $logger,
      ) {}

      /**
       * Import from JSON format.
       */
      public function importFromJson(User $user, array $data): array
      {
          $stats = ['projects' => 0, 'tasks' => 0, 'tags' => 0, 'errors' => []];

          $this->entityManager->beginTransaction();

          try {
              // Import tags first
              $tagMap = [];
              foreach ($data['tags'] ?? [] as $tagData) {
                  $tag = $this->importTag($user, $tagData);
                  if ($tag !== null) {
                      $tagMap[$tagData['id'] ?? $tagData['name']] = $tag;
                      $stats['tags']++;
                  }
              }

              // Import projects
              $projectMap = [];
              foreach ($data['projects'] ?? [] as $projectData) {
                  $project = $this->importProject($user, $projectData);
                  if ($project !== null) {
                      $projectMap[$projectData['id'] ?? $projectData['name']] = $project;
                      $stats['projects']++;
                  }
              }

              // Import tasks
              foreach ($data['tasks'] ?? [] as $taskData) {
                  $task = $this->importTask($user, $taskData, $projectMap, $tagMap);
                  if ($task !== null) {
                      $stats['tasks']++;
                  }
              }

              $this->entityManager->flush();
              $this->entityManager->commit();

          } catch (\Throwable $e) {
              $this->entityManager->rollback();
              $stats['errors'][] = $e->getMessage();
              $this->logger->error('Import failed', ['error' => $e->getMessage()]);
          }

          return $stats;
      }

      /**
       * Import from Todoist export format.
       */
      public function importFromTodoist(User $user, array $data): array
      {
          $transformed = $this->transformTodoistData($data);
          return $this->importFromJson($user, $transformed);
      }

      /**
       * Import from CSV.
       */
      public function importFromCsv(User $user, string $csv): array
      {
          $lines = str_getcsv($csv, "\n");
          $headers = str_getcsv(array_shift($lines));

          $tasks = [];
          foreach ($lines as $line) {
              if (empty(trim($line))) continue;
              $values = str_getcsv($line);
              if (count($values) !== count($headers)) continue;
              $tasks[] = array_combine($headers, $values);
          }

          return $this->importFromJson($user, ['tasks' => $tasks]);
      }

      private function importTag(User $user, array $data): ?Tag
      {
          $name = $data['name'] ?? null;
          if ($name === null) return null;

          $tag = new Tag();
          $tag->setOwner($user);
          $tag->setName($name);
          $tag->setColor($data['color'] ?? '#6366f1');

          $this->entityManager->persist($tag);
          return $tag;
      }

      private function importProject(User $user, array $data): ?Project
      {
          $name = $data['name'] ?? null;
          if ($name === null) return null;

          $project = new Project();
          $project->setOwner($user);
          $project->setName($name);
          $project->setDescription($data['description'] ?? null);
          $project->setColor($data['color'] ?? '#6366f1');

          $this->entityManager->persist($project);
          return $project;
      }

      private function importTask(User $user, array $data, array $projectMap, array $tagMap): ?Task
      {
          $title = $data['title'] ?? $data['content'] ?? null;
          if ($title === null) return null;

          $task = new Task();
          $task->setOwner($user);
          $task->setTitle($title);
          $task->setDescription($data['description'] ?? null);
          $task->setStatus($this->mapStatus($data['status'] ?? $data['checked'] ?? null));
          $task->setPriority($this->mapPriority($data['priority'] ?? null));

          if (isset($data['dueDate']) || isset($data['due_date']) || isset($data['due'])) {
              $dueDate = $data['dueDate'] ?? $data['due_date'] ?? $data['due']['date'] ?? null;
              if ($dueDate) {
                  $task->setDueDate(new \DateTimeImmutable($dueDate));
              }
          }

          // Link to project
          $projectRef = $data['projectId'] ?? $data['project_id'] ?? $data['project'] ?? null;
          if ($projectRef !== null && isset($projectMap[$projectRef])) {
              $task->setProject($projectMap[$projectRef]);
          }

          // Link to tags
          $taskTags = $data['tags'] ?? $data['labels'] ?? [];
          foreach ($taskTags as $tagRef) {
              if (isset($tagMap[$tagRef])) {
                  $task->addTag($tagMap[$tagRef]);
              }
          }

          $this->entityManager->persist($task);
          return $task;
      }

      private function mapStatus($status): string
      {
          if ($status === true || $status === 1 || $status === 'completed') {
              return Task::STATUS_COMPLETED;
          }
          return Task::STATUS_PENDING;
      }

      private function mapPriority($priority): int
      {
          if ($priority === null) return Task::PRIORITY_DEFAULT;

          // Todoist uses 1-4 (4 is highest)
          if (is_numeric($priority)) {
              return min(max((int) $priority, 1), 4);
          }

          return match (strtolower((string) $priority)) {
              'urgent', 'highest' => 4,
              'high' => 3,
              'medium', 'normal' => 2,
              'low' => 1,
              default => Task::PRIORITY_DEFAULT,
          };
      }

      private function transformTodoistData(array $data): array
      {
          $transformed = ['projects' => [], 'tasks' => [], 'tags' => []];

          // Labels -> Tags
          foreach ($data['labels'] ?? [] as $label) {
              $transformed['tags'][] = [
                  'id' => $label['id'],
                  'name' => $label['name'],
                  'color' => $label['color'] ?? null,
              ];
          }

          // Projects
          foreach ($data['projects'] ?? [] as $project) {
              $transformed['projects'][] = [
                  'id' => $project['id'],
                  'name' => $project['name'],
                  'color' => $project['color'] ?? null,
              ];
          }

          // Items -> Tasks
          foreach ($data['items'] ?? [] as $item) {
              $transformed['tasks'][] = [
                  'title' => $item['content'],
                  'description' => $item['description'] ?? null,
                  'status' => $item['checked'] ? 'completed' : 'pending',
                  'priority' => $item['priority'] ?? 1,
                  'dueDate' => $item['due']['date'] ?? null,
                  'projectId' => $item['project_id'] ?? null,
                  'tags' => $item['labels'] ?? [],
              ];
          }

          return $transformed;
      }
  }
  ```

- [ ] **13.4.2** Create import endpoints
  ```php
  // src/Controller/Api/ImportController.php

  declare(strict_types=1);

  namespace App\Controller\Api;

  use App\Entity\User;
  use App\Service\ImportService;
  use App\Service\ResponseFormatter;
  use App\Service\ValidationHelper;
  use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
  use Symfony\Component\HttpFoundation\File\UploadedFile;
  use Symfony\Component\HttpFoundation\JsonResponse;
  use Symfony\Component\HttpFoundation\Request;
  use Symfony\Component\HttpFoundation\Response;
  use Symfony\Component\Routing\Attribute\Route;

  #[Route('/api/v1/import', name: 'api_import_')]
  final class ImportController extends AbstractController
  {
      public function __construct(
          private readonly ImportService $importService,
          private readonly ResponseFormatter $responseFormatter,
          private readonly ValidationHelper $validationHelper,
      ) {}

      /**
       * Import from JSON.
       */
      #[Route('/json', name: 'json', methods: ['POST'])]
      public function importJson(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $data = $this->validationHelper->decodeJsonBody($request);
          $stats = $this->importService->importFromJson($user, $data);

          return $this->responseFormatter->success([
              'message' => 'Import completed',
              'stats' => $stats,
          ]);
      }

      /**
       * Import from Todoist.
       */
      #[Route('/todoist', name: 'todoist', methods: ['POST'])]
      public function importTodoist(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $data = $this->validationHelper->decodeJsonBody($request);
          $stats = $this->importService->importFromTodoist($user, $data);

          return $this->responseFormatter->success([
              'message' => 'Todoist import completed',
              'stats' => $stats,
          ]);
      }

      /**
       * Import from CSV file.
       */
      #[Route('/csv', name: 'csv', methods: ['POST'])]
      public function importCsv(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          /** @var UploadedFile|null $file */
          $file = $request->files->get('file');

          if ($file === null) {
              return $this->responseFormatter->error(
                  'No file uploaded',
                  'FILE_REQUIRED',
                  Response::HTTP_BAD_REQUEST
              );
          }

          $csv = file_get_contents($file->getPathname());
          $stats = $this->importService->importFromCsv($user, $csv);

          return $this->responseFormatter->success([
              'message' => 'CSV import completed',
              'stats' => $stats,
          ]);
      }
  }
  ```

- [ ] **13.4.3** Create import UI (in data settings page)

### Completion Criteria
- [ ] JSON import working
- [ ] Todoist format supported
- [ ] CSV import working
- [ ] Import statistics returned
- [ ] Transactions with rollback

---

## Sub-Phase 13.5: Activity Log

### Objective
Track and display user activity for transparency.

### Tasks

- [ ] **13.5.1** Create ActivityLog entity
  ```php
  // src/Entity/ActivityLog.php

  declare(strict_types=1);

  namespace App\Entity;

  use App\Interface\UserOwnedInterface;
  use Doctrine\DBAL\Types\Types;
  use Doctrine\ORM\Mapping as ORM;

  #[ORM\Entity(repositoryClass: 'App\Repository\ActivityLogRepository')]
  #[ORM\Table(name: 'activity_logs')]
  #[ORM\Index(columns: ['owner_id', 'created_at'], name: 'idx_activity_logs_owner_created')]
  #[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_activity_logs_entity')]
  class ActivityLog implements UserOwnedInterface
  {
      public const ACTION_CREATE = 'create';
      public const ACTION_UPDATE = 'update';
      public const ACTION_DELETE = 'delete';
      public const ACTION_COMPLETE = 'complete';
      public const ACTION_ARCHIVE = 'archive';

      #[ORM\Id]
      #[ORM\Column(type: 'guid')]
      #[ORM\GeneratedValue(strategy: 'CUSTOM')]
      #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
      private ?string $id = null;

      #[ORM\ManyToOne(targetEntity: User::class)]
      #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
      private User $owner;

      #[ORM\Column(type: Types::STRING, length: 20)]
      private string $action;

      #[ORM\Column(type: Types::STRING, length: 50, name: 'entity_type')]
      private string $entityType;

      #[ORM\Column(type: Types::GUID, name: 'entity_id')]
      private string $entityId;

      #[ORM\Column(type: Types::STRING, length: 255, name: 'entity_title')]
      private string $entityTitle;

      #[ORM\Column(type: Types::JSON, nullable: true)]
      private ?array $changes = null;

      #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at')]
      private \DateTimeImmutable $createdAt;

      public function __construct()
      {
          $this->createdAt = new \DateTimeImmutable();
      }

      // Getters and setters...
  }
  ```

- [ ] **13.5.2** Create ActivityLogService
  ```php
  // src/Service/ActivityLogService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\ActivityLog;
  use App\Entity\Project;
  use App\Entity\Task;
  use App\Entity\User;
  use App\Repository\ActivityLogRepository;
  use Doctrine\ORM\EntityManagerInterface;

  final class ActivityLogService
  {
      public function __construct(
          private readonly EntityManagerInterface $entityManager,
          private readonly ActivityLogRepository $repository,
      ) {}

      public function logTaskAction(Task $task, string $action, ?array $changes = null): void
      {
          $log = new ActivityLog();
          $log->setOwner($task->getOwner());
          $log->setAction($action);
          $log->setEntityType('task');
          $log->setEntityId($task->getId());
          $log->setEntityTitle($task->getTitle());
          $log->setChanges($changes);

          $this->entityManager->persist($log);
          // Don't flush - let the main operation flush
      }

      public function logProjectAction(Project $project, string $action, ?array $changes = null): void
      {
          $log = new ActivityLog();
          $log->setOwner($project->getOwner());
          $log->setAction($action);
          $log->setEntityType('project');
          $log->setEntityId($project->getId());
          $log->setEntityTitle($project->getName());
          $log->setChanges($changes);

          $this->entityManager->persist($log);
      }

      public function getRecentActivity(User $user, int $limit = 50): array
      {
          return $this->repository->findBy(
              ['owner' => $user],
              ['createdAt' => 'DESC'],
              $limit
          );
      }

      public function getActivityForEntity(string $entityType, string $entityId): array
      {
          return $this->repository->findBy(
              ['entityType' => $entityType, 'entityId' => $entityId],
              ['createdAt' => 'DESC']
          );
      }
  }
  ```

- [ ] **13.5.3** Integrate with TaskService and ProjectService
  ```php
  // Add to TaskService

  public function create(CreateTaskRequest $request): Task
  {
      // ... existing creation logic ...

      $this->activityLogService->logTaskAction($task, ActivityLog::ACTION_CREATE);
      $this->entityManager->flush();

      return $task;
  }

  public function update(Task $task, UpdateTaskRequest $request): Task
  {
      $changes = $this->calculateChanges($task, $request);

      // ... existing update logic ...

      if (!empty($changes)) {
          $this->activityLogService->logTaskAction($task, ActivityLog::ACTION_UPDATE, $changes);
      }
      $this->entityManager->flush();

      return $task;
  }

  private function calculateChanges(Task $task, UpdateTaskRequest $request): array
  {
      $changes = [];

      if ($request->title !== null && $request->title !== $task->getTitle()) {
          $changes['title'] = ['from' => $task->getTitle(), 'to' => $request->title];
      }
      if ($request->status !== null && $request->status !== $task->getStatus()) {
          $changes['status'] = ['from' => $task->getStatus(), 'to' => $request->status];
      }
      // ... other fields ...

      return $changes;
  }
  ```

- [ ] **13.5.4** Create activity endpoint
  ```php
  // src/Controller/Api/ActivityController.php

  #[Route('/api/v1/activity', name: 'api_activity_')]
  final class ActivityController extends AbstractController
  {
      #[Route('', name: 'list', methods: ['GET'])]
      public function list(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $limit = min((int) $request->query->get('limit', 50), 100);
          $activity = $this->activityLogService->getRecentActivity($user, $limit);

          return $this->responseFormatter->success([
              'activity' => array_map(fn($a) => [
                  'id' => $a->getId(),
                  'action' => $a->getAction(),
                  'entityType' => $a->getEntityType(),
                  'entityId' => $a->getEntityId(),
                  'entityTitle' => $a->getEntityTitle(),
                  'changes' => $a->getChanges(),
                  'createdAt' => $a->getCreatedAt()->format(\DateTimeInterface::RFC3339),
              ], $activity),
          ]);
      }
  }
  ```

### Completion Criteria
- [ ] Activity logged for task/project CRUD
- [ ] Changes tracked (before/after)
- [ ] Activity feed endpoint
- [ ] Per-entity history

---

## Sub-Phase 13.6: Testing

### Tasks

- [ ] **13.6.1** Settings tests
  ```php
  // tests/Functional/Api/UserSettingsApiTest.php
  public function testGetSettings(): void;
  public function testUpdateTimezone(): void;
  public function testUpdateDateFormat(): void;
  public function testInvalidTimezone(): void;
  ```

- [ ] **13.6.2** Session tests
  ```php
  // tests/Functional/Api/SessionApiTest.php
  public function testListSessions(): void;
  public function testRevokeSession(): void;
  public function testRevokeOtherSessions(): void;
  public function testCannotRevokeOtherUserSession(): void;
  ```

- [ ] **13.6.3** API token tests
  ```php
  // tests/Functional/Api/ApiTokenApiTest.php
  public function testListTokens(): void;
  public function testCreateToken(): void;
  public function testCreateTokenWithExpiry(): void;
  public function testRevokeToken(): void;
  public function testTokenShownOnlyOnce(): void;
  ```

- [ ] **13.6.4** Import tests
  ```php
  // tests/Unit/Service/ImportServiceTest.php
  public function testImportFromJson(): void;
  public function testImportFromTodoist(): void;
  public function testImportFromCsv(): void;
  public function testImportRollbackOnError(): void;
  ```

- [ ] **13.6.5** Activity log tests
  ```php
  // tests/Unit/Service/ActivityLogServiceTest.php
  public function testLogTaskCreate(): void;
  public function testLogTaskUpdate(): void;
  public function testChangesTracked(): void;
  ```

### Completion Criteria
- [ ] Settings API tested
- [ ] Session management tested
- [ ] API tokens tested
- [ ] Import tested
- [ ] Activity logging tested

---

## Phase 13 Deliverables Checklist

### Settings Page
- [ ] Navigation sidebar
- [ ] Profile settings
- [ ] Timezone selection
- [ ] Date format preference
- [ ] Week start preference

### Session Management
- [ ] Sessions tracked
- [ ] Session list UI
- [ ] Individual revocation
- [ ] Revoke all others
- [ ] Current session marked

### API Token Management
- [ ] Multiple tokens
- [ ] Named tokens
- [ ] Expiry options
- [ ] Last used tracking
- [ ] Token revocation

### Data Import
- [ ] JSON import
- [ ] Todoist format
- [ ] CSV import
- [ ] Transaction rollback

### Activity Log
- [ ] Task actions logged
- [ ] Project actions logged
- [ ] Changes tracked
- [ ] Activity feed API

### Testing
- [ ] All features tested

---

## Implementation Order

1. **13.1**: Settings page & timezone
2. **13.2**: Session management
3. **13.3**: API token management
4. **13.4**: Data import
5. **13.5**: Activity log
6. **13.6**: Testing

---

## Files Summary

### New Files
```
src/Controller/Web/SettingsController.php
src/Controller/Api/UserController.php
src/Controller/Api/SessionController.php
src/Controller/Api/ApiTokenController.php
src/Controller/Api/ImportController.php
src/Controller/Api/ActivityController.php
src/Entity/UserSession.php
src/Entity/ApiToken.php
src/Entity/ActivityLog.php
src/Repository/UserSessionRepository.php
src/Repository/ApiTokenRepository.php
src/Repository/ActivityLogRepository.php
src/Service/SessionService.php
src/Service/ImportService.php
src/Service/ActivityLogService.php
src/DTO/UserSettingsRequest.php
templates/settings/layout.html.twig
templates/settings/profile.html.twig
templates/settings/security.html.twig
templates/settings/notifications.html.twig
templates/settings/api-tokens.html.twig
templates/settings/data.html.twig
migrations/Version20260124_UserSessions.php
migrations/Version20260124_ApiTokens.php
migrations/Version20260124_ActivityLogs.php
```

### Updated Files
```
src/Entity/User.php
src/Service/TaskService.php
src/Service/ProjectService.php
```
