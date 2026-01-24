# Phase 12: Notifications & Reminders

## Overview
**Goal**: Implement a comprehensive notification system including due date reminders, email notifications, browser push notifications, and an in-app notification center. This is a critical feature for any todo application.

## Revision History
- **2026-01-24**: Initial creation based on comprehensive project review

## Prerequisites
- Phase 10 completed (Email infrastructure required)
- Symfony Messenger component configured
- Redis available for queue storage
- Task entity with due dates functional

---

## Why This Phase is Critical

A todo application without reminders is fundamentally incomplete:
- Users forget about tasks without proactive reminders
- Due dates are meaningless without notification
- Competing apps all have robust notification systems
- User engagement depends on timely reminders

---

## Sub-Phase 12.1: Notification Entity & Infrastructure

### Objective
Create the database schema and core infrastructure for notifications.

### Tasks

- [ ] **12.1.1** Create Notification entity
  ```php
  // src/Entity/Notification.php

  declare(strict_types=1);

  namespace App\Entity;

  use App\Interface\UserOwnedInterface;
  use Doctrine\DBAL\Types\Types;
  use Doctrine\ORM\Mapping as ORM;

  #[ORM\Entity(repositoryClass: 'App\Repository\NotificationRepository')]
  #[ORM\Table(name: 'notifications')]
  #[ORM\Index(columns: ['owner_id', 'read_at'], name: 'idx_notifications_owner_read')]
  #[ORM\Index(columns: ['owner_id', 'created_at'], name: 'idx_notifications_owner_created')]
  class Notification implements UserOwnedInterface
  {
      public const TYPE_TASK_DUE_SOON = 'task_due_soon';
      public const TYPE_TASK_OVERDUE = 'task_overdue';
      public const TYPE_TASK_DUE_TODAY = 'task_due_today';
      public const TYPE_RECURRING_CREATED = 'recurring_created';
      public const TYPE_SYSTEM = 'system';

      public const CHANNEL_IN_APP = 'in_app';
      public const CHANNEL_EMAIL = 'email';
      public const CHANNEL_PUSH = 'push';

      #[ORM\Id]
      #[ORM\Column(type: 'guid')]
      #[ORM\GeneratedValue(strategy: 'CUSTOM')]
      #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
      private ?string $id = null;

      #[ORM\ManyToOne(targetEntity: User::class)]
      #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
      private User $owner;

      #[ORM\Column(type: Types::STRING, length: 50)]
      private string $type;

      #[ORM\Column(type: Types::STRING, length: 255)]
      private string $title;

      #[ORM\Column(type: Types::TEXT, nullable: true)]
      private ?string $body = null;

      #[ORM\Column(type: Types::STRING, length: 50, nullable: true, name: 'entity_type')]
      private ?string $entityType = null;

      #[ORM\Column(type: Types::GUID, nullable: true, name: 'entity_id')]
      private ?string $entityId = null;

      #[ORM\Column(type: Types::JSON, nullable: true)]
      private ?array $data = null;

      #[ORM\Column(type: Types::JSON, name: 'channels_sent')]
      private array $channelsSent = [];

      #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at')]
      private \DateTimeImmutable $createdAt;

      #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'read_at')]
      private ?\DateTimeImmutable $readAt = null;

      public function __construct()
      {
          $this->createdAt = new \DateTimeImmutable();
      }

      public function getId(): ?string
      {
          return $this->id;
      }

      public function getOwner(): User
      {
          return $this->owner;
      }

      public function setOwner(User $owner): self
      {
          $this->owner = $owner;
          return $this;
      }

      public function getType(): string
      {
          return $this->type;
      }

      public function setType(string $type): self
      {
          $this->type = $type;
          return $this;
      }

      public function getTitle(): string
      {
          return $this->title;
      }

      public function setTitle(string $title): self
      {
          $this->title = $title;
          return $this;
      }

      public function getBody(): ?string
      {
          return $this->body;
      }

      public function setBody(?string $body): self
      {
          $this->body = $body;
          return $this;
      }

      public function getEntityType(): ?string
      {
          return $this->entityType;
      }

      public function setEntityType(?string $entityType): self
      {
          $this->entityType = $entityType;
          return $this;
      }

      public function getEntityId(): ?string
      {
          return $this->entityId;
      }

      public function setEntityId(?string $entityId): self
      {
          $this->entityId = $entityId;
          return $this;
      }

      public function getData(): ?array
      {
          return $this->data;
      }

      public function setData(?array $data): self
      {
          $this->data = $data;
          return $this;
      }

      public function getChannelsSent(): array
      {
          return $this->channelsSent;
      }

      public function addChannelSent(string $channel): self
      {
          if (!in_array($channel, $this->channelsSent, true)) {
              $this->channelsSent[] = $channel;
          }
          return $this;
      }

      public function getCreatedAt(): \DateTimeImmutable
      {
          return $this->createdAt;
      }

      public function getReadAt(): ?\DateTimeImmutable
      {
          return $this->readAt;
      }

      public function setReadAt(?\DateTimeImmutable $readAt): self
      {
          $this->readAt = $readAt;
          return $this;
      }

      public function isRead(): bool
      {
          return $this->readAt !== null;
      }

      public function markAsRead(): self
      {
          if ($this->readAt === null) {
              $this->readAt = new \DateTimeImmutable();
          }
          return $this;
      }
  }
  ```

- [ ] **12.1.2** Create NotificationRepository
  ```php
  // src/Repository/NotificationRepository.php

  declare(strict_types=1);

  namespace App\Repository;

  use App\Entity\Notification;
  use App\Entity\User;
  use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
  use Doctrine\Persistence\ManagerRegistry;

  /**
   * @extends ServiceEntityRepository<Notification>
   */
  final class NotificationRepository extends ServiceEntityRepository
  {
      public function __construct(ManagerRegistry $registry)
      {
          parent::__construct($registry, Notification::class);
      }

      public function findUnreadByOwner(User $owner, int $limit = 50): array
      {
          return $this->createQueryBuilder('n')
              ->where('n.owner = :owner')
              ->andWhere('n.readAt IS NULL')
              ->setParameter('owner', $owner)
              ->orderBy('n.createdAt', 'DESC')
              ->setMaxResults($limit)
              ->getQuery()
              ->getResult();
      }

      public function findByOwner(User $owner, int $limit = 50, int $offset = 0): array
      {
          return $this->createQueryBuilder('n')
              ->where('n.owner = :owner')
              ->setParameter('owner', $owner)
              ->orderBy('n.createdAt', 'DESC')
              ->setFirstResult($offset)
              ->setMaxResults($limit)
              ->getQuery()
              ->getResult();
      }

      public function countUnreadByOwner(User $owner): int
      {
          return (int) $this->createQueryBuilder('n')
              ->select('COUNT(n.id)')
              ->where('n.owner = :owner')
              ->andWhere('n.readAt IS NULL')
              ->setParameter('owner', $owner)
              ->getQuery()
              ->getSingleScalarResult();
      }

      public function markAllAsReadByOwner(User $owner): int
      {
          return $this->createQueryBuilder('n')
              ->update()
              ->set('n.readAt', ':now')
              ->where('n.owner = :owner')
              ->andWhere('n.readAt IS NULL')
              ->setParameter('owner', $owner)
              ->setParameter('now', new \DateTimeImmutable())
              ->getQuery()
              ->execute();
      }

      public function deleteOldNotifications(\DateTimeImmutable $before): int
      {
          return $this->createQueryBuilder('n')
              ->delete()
              ->where('n.createdAt < :before')
              ->andWhere('n.readAt IS NOT NULL')
              ->setParameter('before', $before)
              ->getQuery()
              ->execute();
      }
  }
  ```

- [ ] **12.1.3** Create database migration
  ```php
  // migrations/Version20260124_Notifications.php

  public function up(Schema $schema): void
  {
      $this->addSql('CREATE TABLE notifications (
          id UUID NOT NULL,
          owner_id UUID NOT NULL,
          type VARCHAR(50) NOT NULL,
          title VARCHAR(255) NOT NULL,
          body TEXT DEFAULT NULL,
          entity_type VARCHAR(50) DEFAULT NULL,
          entity_id UUID DEFAULT NULL,
          data JSONB DEFAULT NULL,
          channels_sent JSONB NOT NULL DEFAULT \'[]\',
          created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
          read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
          PRIMARY KEY(id)
      )');

      $this->addSql('CREATE INDEX idx_notifications_owner_read ON notifications (owner_id, read_at)');
      $this->addSql('CREATE INDEX idx_notifications_owner_created ON notifications (owner_id, created_at DESC)');
      $this->addSql('ALTER TABLE notifications ADD CONSTRAINT fk_notifications_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
  }
  ```

### Completion Criteria
- [ ] Notification entity created
- [ ] Repository with common queries
- [ ] Migration applied
- [ ] Indexes for performance

---

## Sub-Phase 12.2: User Notification Preferences

### Objective
Allow users to configure their notification preferences.

### Tasks

- [ ] **12.2.1** Add notification settings to User entity
  ```php
  // src/Entity/User.php - settings JSON structure

  /*
   * User settings JSON structure for notifications:
   * {
   *   "notifications": {
   *     "email": {
   *       "enabled": true,
   *       "taskDueSoon": true,
   *       "taskOverdue": true,
   *       "dailyDigest": false
   *     },
   *     "push": {
   *       "enabled": true,
   *       "taskDueSoon": true,
   *       "taskOverdue": true
   *     },
   *     "inApp": {
   *       "enabled": true
   *     },
   *     "quietHours": {
   *       "enabled": false,
   *       "start": "22:00",
   *       "end": "08:00"
   *     },
   *     "reminders": {
   *       "defaultBefore": [1440, 60],  // minutes: 1 day, 1 hour
   *       "dueTodayTime": "08:00"       // When to send "due today" notifications
   *     }
   *   },
   *   "timezone": "America/New_York"
   * }
   */

  public function getNotificationSettings(): array
  {
      $settings = $this->settings ?? [];
      return $settings['notifications'] ?? $this->getDefaultNotificationSettings();
  }

  public function setNotificationSettings(array $notificationSettings): self
  {
      $settings = $this->settings ?? [];
      $settings['notifications'] = $notificationSettings;
      $this->settings = $settings;
      return $this;
  }

  public function getDefaultNotificationSettings(): array
  {
      return [
          'email' => [
              'enabled' => true,
              'taskDueSoon' => true,
              'taskOverdue' => true,
              'dailyDigest' => false,
          ],
          'push' => [
              'enabled' => true,
              'taskDueSoon' => true,
              'taskOverdue' => true,
          ],
          'inApp' => [
              'enabled' => true,
          ],
          'quietHours' => [
              'enabled' => false,
              'start' => '22:00',
              'end' => '08:00',
          ],
          'reminders' => [
              'defaultBefore' => [1440, 60], // 1 day, 1 hour
              'dueTodayTime' => '08:00',
          ],
      ];
  }

  public function isNotificationEnabled(string $channel, string $type): bool
  {
      $settings = $this->getNotificationSettings();
      return ($settings[$channel]['enabled'] ?? true)
          && ($settings[$channel][$type] ?? true);
  }

  public function isInQuietHours(\DateTimeImmutable $time): bool
  {
      $settings = $this->getNotificationSettings();
      $quietHours = $settings['quietHours'] ?? [];

      if (!($quietHours['enabled'] ?? false)) {
          return false;
      }

      $timezone = new \DateTimeZone($this->getTimezone());
      $localTime = $time->setTimezone($timezone);
      $currentTime = $localTime->format('H:i');

      $start = $quietHours['start'] ?? '22:00';
      $end = $quietHours['end'] ?? '08:00';

      // Handle overnight quiet hours
      if ($start > $end) {
          return $currentTime >= $start || $currentTime < $end;
      }

      return $currentTime >= $start && $currentTime < $end;
  }
  ```

- [ ] **12.2.2** Create NotificationPreferencesRequest DTO
  ```php
  // src/DTO/NotificationPreferencesRequest.php

  declare(strict_types=1);

  namespace App\DTO;

  use Symfony\Component\Validator\Constraints as Assert;

  final class NotificationPreferencesRequest
  {
      public function __construct(
          #[Assert\Type('array')]
          public readonly ?array $email = null,

          #[Assert\Type('array')]
          public readonly ?array $push = null,

          #[Assert\Type('array')]
          public readonly ?array $inApp = null,

          #[Assert\Type('array')]
          public readonly ?array $quietHours = null,

          #[Assert\Type('array')]
          public readonly ?array $reminders = null,
      ) {}

      public static function fromArray(array $data): self
      {
          return new self(
              email: $data['email'] ?? null,
              push: $data['push'] ?? null,
              inApp: $data['inApp'] ?? $data['in_app'] ?? null,
              quietHours: $data['quietHours'] ?? $data['quiet_hours'] ?? null,
              reminders: $data['reminders'] ?? null,
          );
      }

      public function toArray(): array
      {
          return array_filter([
              'email' => $this->email,
              'push' => $this->push,
              'inApp' => $this->inApp,
              'quietHours' => $this->quietHours,
              'reminders' => $this->reminders,
          ], fn($v) => $v !== null);
      }
  }
  ```

- [ ] **12.2.3** Create notification preferences endpoint
  ```php
  // src/Controller/Api/NotificationController.php

  declare(strict_types=1);

  namespace App\Controller\Api;

  use App\DTO\NotificationPreferencesRequest;
  use App\Entity\Notification;
  use App\Entity\User;
  use App\Repository\NotificationRepository;
  use App\Service\ResponseFormatter;
  use App\Service\ValidationHelper;
  use Doctrine\ORM\EntityManagerInterface;
  use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
  use Symfony\Component\HttpFoundation\JsonResponse;
  use Symfony\Component\HttpFoundation\Request;
  use Symfony\Component\HttpFoundation\Response;
  use Symfony\Component\Routing\Attribute\Route;

  #[Route('/api/v1/notifications', name: 'api_notifications_')]
  final class NotificationController extends AbstractController
  {
      public function __construct(
          private readonly NotificationRepository $notificationRepository,
          private readonly EntityManagerInterface $entityManager,
          private readonly ResponseFormatter $responseFormatter,
          private readonly ValidationHelper $validationHelper,
      ) {}

      /**
       * Get notification preferences.
       */
      #[Route('/preferences', name: 'get_preferences', methods: ['GET'])]
      public function getPreferences(): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          return $this->responseFormatter->success([
              'preferences' => $user->getNotificationSettings(),
          ]);
      }

      /**
       * Update notification preferences.
       */
      #[Route('/preferences', name: 'update_preferences', methods: ['PATCH'])]
      public function updatePreferences(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $data = $this->validationHelper->decodeJsonBody($request);
          $prefsRequest = NotificationPreferencesRequest::fromArray($data);

          $currentSettings = $user->getNotificationSettings();
          $newSettings = array_replace_recursive($currentSettings, $prefsRequest->toArray());

          $user->setNotificationSettings($newSettings);
          $this->entityManager->flush();

          return $this->responseFormatter->success([
              'preferences' => $user->getNotificationSettings(),
          ]);
      }
  }
  ```

### Completion Criteria
- [ ] Notification settings in user preferences
- [ ] Per-channel enable/disable
- [ ] Per-type enable/disable
- [ ] Quiet hours configuration
- [ ] Default reminder times

---

## Sub-Phase 12.3: Notification Service

### Objective
Create the core notification service that dispatches to multiple channels.

### Tasks

- [ ] **12.3.1** Create NotificationService
  ```php
  // src/Service/NotificationService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\Notification;
  use App\Entity\Task;
  use App\Entity\User;
  use App\Message\SendEmailNotification;
  use App\Message\SendPushNotification;
  use Doctrine\ORM\EntityManagerInterface;
  use Symfony\Component\Messenger\MessageBusInterface;

  final class NotificationService
  {
      public function __construct(
          private readonly EntityManagerInterface $entityManager,
          private readonly MessageBusInterface $messageBus,
      ) {}

      /**
       * Create a task due soon notification.
       */
      public function notifyTaskDueSoon(Task $task, int $minutesBefore): void
      {
          $user = $task->getOwner();

          if ($user->isInQuietHours(new \DateTimeImmutable())) {
              return; // Skip during quiet hours
          }

          $notification = $this->createNotification(
              $user,
              Notification::TYPE_TASK_DUE_SOON,
              sprintf('Task due in %s', $this->formatDuration($minutesBefore)),
              sprintf('"%s" is due %s', $task->getTitle(), $this->formatDueTime($task)),
              'task',
              $task->getId(),
              ['taskId' => $task->getId(), 'minutesBefore' => $minutesBefore]
          );

          $this->dispatch($notification);
      }

      /**
       * Create a task overdue notification.
       */
      public function notifyTaskOverdue(Task $task): void
      {
          $user = $task->getOwner();

          $notification = $this->createNotification(
              $user,
              Notification::TYPE_TASK_OVERDUE,
              'Task overdue',
              sprintf('"%s" was due %s', $task->getTitle(), $this->formatDueTime($task)),
              'task',
              $task->getId(),
              ['taskId' => $task->getId()]
          );

          $this->dispatch($notification);
      }

      /**
       * Create a task due today notification.
       */
      public function notifyTaskDueToday(Task $task): void
      {
          $user = $task->getOwner();

          $notification = $this->createNotification(
              $user,
              Notification::TYPE_TASK_DUE_TODAY,
              'Task due today',
              sprintf('"%s" is due today', $task->getTitle()),
              'task',
              $task->getId(),
              ['taskId' => $task->getId()]
          );

          $this->dispatch($notification);
      }

      /**
       * Create and persist a notification.
       */
      private function createNotification(
          User $user,
          string $type,
          string $title,
          string $body,
          ?string $entityType = null,
          ?string $entityId = null,
          ?array $data = null
      ): Notification {
          $notification = new Notification();
          $notification->setOwner($user);
          $notification->setType($type);
          $notification->setTitle($title);
          $notification->setBody($body);
          $notification->setEntityType($entityType);
          $notification->setEntityId($entityId);
          $notification->setData($data);

          $this->entityManager->persist($notification);
          $this->entityManager->flush();

          // Always create in-app notification
          $notification->addChannelSent(Notification::CHANNEL_IN_APP);

          return $notification;
      }

      /**
       * Dispatch notification to enabled channels.
       */
      private function dispatch(Notification $notification): void
      {
          $user = $notification->getOwner();
          $type = $this->getTypeKey($notification->getType());

          // Email notification
          if ($user->isNotificationEnabled('email', $type)) {
              $this->messageBus->dispatch(new SendEmailNotification($notification->getId()));
              $notification->addChannelSent(Notification::CHANNEL_EMAIL);
          }

          // Push notification
          if ($user->isNotificationEnabled('push', $type)) {
              $this->messageBus->dispatch(new SendPushNotification($notification->getId()));
              $notification->addChannelSent(Notification::CHANNEL_PUSH);
          }

          $this->entityManager->flush();
      }

      private function getTypeKey(string $type): string
      {
          return match ($type) {
              Notification::TYPE_TASK_DUE_SOON => 'taskDueSoon',
              Notification::TYPE_TASK_OVERDUE => 'taskOverdue',
              Notification::TYPE_TASK_DUE_TODAY => 'taskDueSoon',
              default => 'system',
          };
      }

      private function formatDuration(int $minutes): string
      {
          if ($minutes < 60) {
              return sprintf('%d minutes', $minutes);
          }
          if ($minutes < 1440) {
              $hours = (int) floor($minutes / 60);
              return sprintf('%d hour%s', $hours, $hours > 1 ? 's' : '');
          }
          $days = (int) floor($minutes / 1440);
          return sprintf('%d day%s', $days, $days > 1 ? 's' : '');
      }

      private function formatDueTime(Task $task): string
      {
          $dueDate = $task->getDueDate();
          if ($dueDate === null) {
              return '';
          }

          $now = new \DateTimeImmutable();
          $diff = $dueDate->diff($now);

          if ($dueDate < $now) {
              return $dueDate->format('M j') . ' (overdue)';
          }

          if ($diff->days === 0) {
              $dueTime = $task->getDueTime();
              return $dueTime ? 'today at ' . $dueTime->format('g:i A') : 'today';
          }

          return $dueDate->format('M j');
      }
  }
  ```

- [ ] **12.3.2** Create message classes for async dispatch
  ```php
  // src/Message/SendEmailNotification.php

  declare(strict_types=1);

  namespace App\Message;

  final class SendEmailNotification
  {
      public function __construct(
          public readonly string $notificationId,
      ) {}
  }
  ```

  ```php
  // src/Message/SendPushNotification.php

  declare(strict_types=1);

  namespace App\Message;

  final class SendPushNotification
  {
      public function __construct(
          public readonly string $notificationId,
      ) {}
  }
  ```

- [ ] **12.3.3** Create message handlers
  ```php
  // src/MessageHandler/SendEmailNotificationHandler.php

  declare(strict_types=1);

  namespace App\MessageHandler;

  use App\Message\SendEmailNotification;
  use App\Repository\NotificationRepository;
  use App\Service\EmailService;
  use Symfony\Component\Messenger\Attribute\AsMessageHandler;

  #[AsMessageHandler]
  final class SendEmailNotificationHandler
  {
      public function __construct(
          private readonly NotificationRepository $notificationRepository,
          private readonly EmailService $emailService,
      ) {}

      public function __invoke(SendEmailNotification $message): void
      {
          $notification = $this->notificationRepository->find($message->notificationId);

          if ($notification === null) {
              return;
          }

          $user = $notification->getOwner();

          $this->emailService->sendNotificationEmail(
              $user->getEmail(),
              $notification->getTitle(),
              $notification->getBody(),
              $notification->getEntityType(),
              $notification->getEntityId()
          );
      }
  }
  ```

  ```php
  // src/MessageHandler/SendPushNotificationHandler.php

  declare(strict_types=1);

  namespace App\MessageHandler;

  use App\Message\SendPushNotification;
  use App\Repository\NotificationRepository;
  use App\Service\PushNotificationService;
  use Symfony\Component\Messenger\Attribute\AsMessageHandler;

  #[AsMessageHandler]
  final class SendPushNotificationHandler
  {
      public function __construct(
          private readonly NotificationRepository $notificationRepository,
          private readonly PushNotificationService $pushService,
      ) {}

      public function __invoke(SendPushNotification $message): void
      {
          $notification = $this->notificationRepository->find($message->notificationId);

          if ($notification === null) {
              return;
          }

          $this->pushService->send($notification);
      }
  }
  ```

- [ ] **12.3.4** Configure Symfony Messenger
  ```yaml
  # config/packages/messenger.yaml

  framework:
      messenger:
          transports:
              async:
                  dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                  retry_strategy:
                      max_retries: 3
                      delay: 1000
                      multiplier: 2
                      max_delay: 60000

              notifications:
                  dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                  options:
                      queue_name: notifications

          routing:
              'App\Message\SendEmailNotification': notifications
              'App\Message\SendPushNotification': notifications
  ```

### Completion Criteria
- [ ] NotificationService creates notifications
- [ ] Async dispatch via Messenger
- [ ] Email handler sends emails
- [ ] Push handler dispatches push
- [ ] Quiet hours respected

---

## Sub-Phase 12.4: Reminder Scheduler

### Objective
Create a scheduled job that checks for upcoming due dates and sends reminders.

### Tasks

- [ ] **12.4.1** Create ReminderSchedulerService
  ```php
  // src/Service/ReminderSchedulerService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\Task;
  use App\Repository\TaskRepository;
  use Psr\Log\LoggerInterface;

  final class ReminderSchedulerService
  {
      private const REMINDER_INTERVALS = [
          1440, // 1 day before
          60,   // 1 hour before
          15,   // 15 minutes before
      ];

      public function __construct(
          private readonly TaskRepository $taskRepository,
          private readonly NotificationService $notificationService,
          private readonly ReminderTrackingService $trackingService,
          private readonly LoggerInterface $logger,
      ) {}

      /**
       * Process reminders for all users.
       * Should be run every 15 minutes via cron.
       */
      public function processReminders(): array
      {
          $stats = [
              'processed' => 0,
              'reminders_sent' => 0,
              'overdue_sent' => 0,
          ];

          // Get tasks due within the next 24 hours
          $now = new \DateTimeImmutable();
          $endWindow = $now->modify('+1 day');

          $tasks = $this->taskRepository->findTasksDueBetween($now, $endWindow);

          foreach ($tasks as $task) {
              $stats['processed']++;

              if ($this->shouldSendReminder($task)) {
                  $this->sendReminder($task);
                  $stats['reminders_sent']++;
              }
          }

          // Process overdue tasks (check once per hour)
          if ((int) $now->format('i') < 15) {
              $overdueTasks = $this->taskRepository->findOverdueTasks();
              foreach ($overdueTasks as $task) {
                  if ($this->shouldSendOverdueReminder($task)) {
                      $this->notificationService->notifyTaskOverdue($task);
                      $this->trackingService->markOverdueSent($task);
                      $stats['overdue_sent']++;
                  }
              }
          }

          $this->logger->info('Reminder processing complete', $stats);

          return $stats;
      }

      /**
       * Process "due today" notifications.
       * Should be run once daily, typically in the morning.
       */
      public function processDueTodayNotifications(): array
      {
          $stats = ['sent' => 0];

          $tasks = $this->taskRepository->findTasksDueToday();

          foreach ($tasks as $task) {
              if (!$this->trackingService->hasDueTodaySent($task)) {
                  $this->notificationService->notifyTaskDueToday($task);
                  $this->trackingService->markDueTodaySent($task);
                  $stats['sent']++;
              }
          }

          return $stats;
      }

      private function shouldSendReminder(Task $task): bool
      {
          $dueDate = $task->getDueDate();
          if ($dueDate === null) {
              return false;
          }

          $now = new \DateTimeImmutable();
          $minutesUntilDue = ($dueDate->getTimestamp() - $now->getTimestamp()) / 60;

          // Get user's reminder preferences
          $user = $task->getOwner();
          $settings = $user->getNotificationSettings();
          $intervals = $settings['reminders']['defaultBefore'] ?? self::REMINDER_INTERVALS;

          foreach ($intervals as $interval) {
              // Check if we're within 15 minutes of this reminder interval
              if (abs($minutesUntilDue - $interval) < 15) {
                  if (!$this->trackingService->hasReminderSent($task, $interval)) {
                      return true;
                  }
              }
          }

          return false;
      }

      private function sendReminder(Task $task): void
      {
          $dueDate = $task->getDueDate();
          $now = new \DateTimeImmutable();
          $minutesUntilDue = (int) (($dueDate->getTimestamp() - $now->getTimestamp()) / 60);

          // Find the closest reminder interval
          $user = $task->getOwner();
          $settings = $user->getNotificationSettings();
          $intervals = $settings['reminders']['defaultBefore'] ?? self::REMINDER_INTERVALS;

          $closestInterval = $intervals[0];
          foreach ($intervals as $interval) {
              if (abs($minutesUntilDue - $interval) < abs($minutesUntilDue - $closestInterval)) {
                  $closestInterval = $interval;
              }
          }

          $this->notificationService->notifyTaskDueSoon($task, $closestInterval);
          $this->trackingService->markReminderSent($task, $closestInterval);
      }

      private function shouldSendOverdueReminder(Task $task): bool
      {
          return !$this->trackingService->hasOverdueSent($task);
      }
  }
  ```

- [ ] **12.4.2** Create ReminderTrackingService (Redis-based)
  ```php
  // src/Service/ReminderTrackingService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\Task;
  use Predis\Client as RedisClient;

  final class ReminderTrackingService
  {
      private const PREFIX = 'reminder:';
      private const TTL = 86400 * 7; // 7 days

      public function __construct(
          private readonly RedisClient $redis,
      ) {}

      public function hasReminderSent(Task $task, int $minutesBefore): bool
      {
          $key = $this->getReminderKey($task, $minutesBefore);
          return $this->redis->exists($key) === 1;
      }

      public function markReminderSent(Task $task, int $minutesBefore): void
      {
          $key = $this->getReminderKey($task, $minutesBefore);
          $this->redis->setex($key, self::TTL, '1');
      }

      public function hasOverdueSent(Task $task): bool
      {
          $key = self::PREFIX . 'overdue:' . $task->getId();
          return $this->redis->exists($key) === 1;
      }

      public function markOverdueSent(Task $task): void
      {
          $key = self::PREFIX . 'overdue:' . $task->getId();
          $this->redis->setex($key, self::TTL, '1');
      }

      public function hasDueTodaySent(Task $task): bool
      {
          $key = self::PREFIX . 'duetoday:' . $task->getId() . ':' . date('Y-m-d');
          return $this->redis->exists($key) === 1;
      }

      public function markDueTodaySent(Task $task): void
      {
          $key = self::PREFIX . 'duetoday:' . $task->getId() . ':' . date('Y-m-d');
          $this->redis->setex($key, self::TTL, '1');
      }

      public function clearReminders(Task $task): void
      {
          $pattern = self::PREFIX . '*:' . $task->getId() . '*';
          $keys = $this->redis->keys($pattern);
          if (!empty($keys)) {
              $this->redis->del($keys);
          }
      }

      private function getReminderKey(Task $task, int $minutesBefore): string
      {
          $dueDate = $task->getDueDate()?->format('Y-m-d');
          return self::PREFIX . "before:{$task->getId()}:{$dueDate}:{$minutesBefore}";
      }
  }
  ```

- [ ] **12.4.3** Create console command for scheduler
  ```php
  // src/Command/ProcessRemindersCommand.php

  declare(strict_types=1);

  namespace App\Command;

  use App\Service\ReminderSchedulerService;
  use Symfony\Component\Console\Attribute\AsCommand;
  use Symfony\Component\Console\Command\Command;
  use Symfony\Component\Console\Input\InputInterface;
  use Symfony\Component\Console\Input\InputOption;
  use Symfony\Component\Console\Output\OutputInterface;
  use Symfony\Component\Console\Style\SymfonyStyle;

  #[AsCommand(
      name: 'app:reminders:process',
      description: 'Process task reminders and send notifications',
  )]
  final class ProcessRemindersCommand extends Command
  {
      public function __construct(
          private readonly ReminderSchedulerService $schedulerService,
      ) {
          parent::__construct();
      }

      protected function configure(): void
      {
          $this->addOption('due-today', null, InputOption::VALUE_NONE, 'Process due-today notifications');
      }

      protected function execute(InputInterface $input, OutputInterface $output): int
      {
          $io = new SymfonyStyle($input, $output);

          if ($input->getOption('due-today')) {
              $stats = $this->schedulerService->processDueTodayNotifications();
              $io->success(sprintf('Due today notifications sent: %d', $stats['sent']));
          } else {
              $stats = $this->schedulerService->processReminders();
              $io->success(sprintf(
                  'Processed: %d tasks, Reminders: %d, Overdue: %d',
                  $stats['processed'],
                  $stats['reminders_sent'],
                  $stats['overdue_sent']
              ));
          }

          return Command::SUCCESS;
      }
  }
  ```

- [ ] **12.4.4** Add TaskRepository methods
  ```php
  // src/Repository/TaskRepository.php additions

  public function findTasksDueBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): array
  {
      return $this->createQueryBuilder('t')
          ->where('t.dueDate >= :start')
          ->andWhere('t.dueDate <= :end')
          ->andWhere('t.status != :completed')
          ->setParameter('start', $start->format('Y-m-d'))
          ->setParameter('end', $end->format('Y-m-d'))
          ->setParameter('completed', Task::STATUS_COMPLETED)
          ->getQuery()
          ->getResult();
  }

  public function findTasksDueToday(): array
  {
      $today = new \DateTimeImmutable();

      return $this->createQueryBuilder('t')
          ->where('t.dueDate = :today')
          ->andWhere('t.status != :completed')
          ->setParameter('today', $today->format('Y-m-d'))
          ->setParameter('completed', Task::STATUS_COMPLETED)
          ->getQuery()
          ->getResult();
  }

  public function findOverdueTasks(): array
  {
      $today = new \DateTimeImmutable();

      return $this->createQueryBuilder('t')
          ->where('t.dueDate < :today')
          ->andWhere('t.status != :completed')
          ->setParameter('today', $today->format('Y-m-d'))
          ->setParameter('completed', Task::STATUS_COMPLETED)
          ->getQuery()
          ->getResult();
  }
  ```

- [ ] **12.4.5** Configure cron schedule
  ```bash
  # Add to crontab or scheduler configuration

  # Process reminders every 15 minutes
  */15 * * * * php /path/to/bin/console app:reminders:process

  # Process "due today" notifications at 8 AM
  0 8 * * * php /path/to/bin/console app:reminders:process --due-today

  # Process Symfony Messenger queue
  * * * * * php /path/to/bin/console messenger:consume notifications --time-limit=60 --limit=100
  ```

### Completion Criteria
- [ ] Scheduler checks tasks every 15 minutes
- [ ] Reminders sent at configured intervals
- [ ] Overdue notifications sent once
- [ ] Due today notifications sent in morning
- [ ] Redis tracking prevents duplicates

---

## Sub-Phase 12.5: Push Notifications (Web Push API)

### Objective
Implement browser push notifications for real-time alerts.

### Tasks

- [ ] **12.5.1** Install Web Push library
  ```bash
  composer require minishlink/web-push
  ```

- [ ] **12.5.2** Create PushSubscription entity
  ```php
  // src/Entity/PushSubscription.php

  declare(strict_types=1);

  namespace App\Entity;

  use App\Interface\UserOwnedInterface;
  use Doctrine\DBAL\Types\Types;
  use Doctrine\ORM\Mapping as ORM;

  #[ORM\Entity]
  #[ORM\Table(name: 'push_subscriptions')]
  #[ORM\Index(columns: ['owner_id'], name: 'idx_push_subscriptions_owner')]
  #[ORM\UniqueConstraint(columns: ['endpoint'])]
  class PushSubscription implements UserOwnedInterface
  {
      #[ORM\Id]
      #[ORM\Column(type: 'guid')]
      #[ORM\GeneratedValue(strategy: 'CUSTOM')]
      #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
      private ?string $id = null;

      #[ORM\ManyToOne(targetEntity: User::class)]
      #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
      private User $owner;

      #[ORM\Column(type: Types::TEXT)]
      private string $endpoint;

      #[ORM\Column(type: Types::STRING, length: 255, name: 'public_key')]
      private string $publicKey;

      #[ORM\Column(type: Types::STRING, length: 255, name: 'auth_token')]
      private string $authToken;

      #[ORM\Column(type: Types::STRING, length: 255, nullable: true, name: 'user_agent')]
      private ?string $userAgent = null;

      #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at')]
      private \DateTimeImmutable $createdAt;

      public function __construct()
      {
          $this->createdAt = new \DateTimeImmutable();
      }

      // Getters and setters...
  }
  ```

- [ ] **12.5.3** Create PushNotificationService
  ```php
  // src/Service/PushNotificationService.php

  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\Notification;
  use App\Entity\PushSubscription;
  use App\Repository\PushSubscriptionRepository;
  use Minishlink\WebPush\Subscription;
  use Minishlink\WebPush\WebPush;
  use Psr\Log\LoggerInterface;

  final class PushNotificationService
  {
      private WebPush $webPush;

      public function __construct(
          private readonly PushSubscriptionRepository $subscriptionRepository,
          private readonly LoggerInterface $logger,
          string $vapidPublicKey,
          string $vapidPrivateKey,
          string $vapidSubject,
      ) {
          $auth = [
              'VAPID' => [
                  'subject' => $vapidSubject,
                  'publicKey' => $vapidPublicKey,
                  'privateKey' => $vapidPrivateKey,
              ],
          ];

          $this->webPush = new WebPush($auth);
      }

      public function send(Notification $notification): void
      {
          $user = $notification->getOwner();
          $subscriptions = $this->subscriptionRepository->findBy(['owner' => $user]);

          if (empty($subscriptions)) {
              return;
          }

          $payload = json_encode([
              'title' => $notification->getTitle(),
              'body' => $notification->getBody(),
              'icon' => '/images/icon-192.png',
              'badge' => '/images/badge-72.png',
              'data' => [
                  'notificationId' => $notification->getId(),
                  'entityType' => $notification->getEntityType(),
                  'entityId' => $notification->getEntityId(),
                  'url' => $this->getNotificationUrl($notification),
              ],
          ]);

          foreach ($subscriptions as $pushSubscription) {
              $subscription = Subscription::create([
                  'endpoint' => $pushSubscription->getEndpoint(),
                  'publicKey' => $pushSubscription->getPublicKey(),
                  'authToken' => $pushSubscription->getAuthToken(),
              ]);

              $this->webPush->queueNotification($subscription, $payload);
          }

          foreach ($this->webPush->flush() as $report) {
              if (!$report->isSuccess()) {
                  $this->logger->warning('Push notification failed', [
                      'endpoint' => $report->getEndpoint(),
                      'reason' => $report->getReason(),
                  ]);

                  // Remove invalid subscriptions
                  if ($report->isSubscriptionExpired()) {
                      $this->removeSubscription($report->getEndpoint());
                  }
              }
          }
      }

      public function subscribe(
          \App\Entity\User $user,
          string $endpoint,
          string $publicKey,
          string $authToken,
          ?string $userAgent = null
      ): PushSubscription {
          // Check for existing subscription
          $existing = $this->subscriptionRepository->findOneBy(['endpoint' => $endpoint]);
          if ($existing !== null) {
              return $existing;
          }

          $subscription = new PushSubscription();
          $subscription->setOwner($user);
          $subscription->setEndpoint($endpoint);
          $subscription->setPublicKey($publicKey);
          $subscription->setAuthToken($authToken);
          $subscription->setUserAgent($userAgent);

          $this->subscriptionRepository->save($subscription);

          return $subscription;
      }

      public function unsubscribe(string $endpoint): void
      {
          $this->removeSubscription($endpoint);
      }

      private function removeSubscription(string $endpoint): void
      {
          $subscription = $this->subscriptionRepository->findOneBy(['endpoint' => $endpoint]);
          if ($subscription !== null) {
              $this->subscriptionRepository->remove($subscription);
          }
      }

      private function getNotificationUrl(Notification $notification): string
      {
          if ($notification->getEntityType() === 'task' && $notification->getEntityId()) {
              return '/tasks/' . $notification->getEntityId();
          }
          return '/';
      }
  }
  ```

- [ ] **12.5.4** Create push subscription endpoints
  ```php
  // src/Controller/Api/PushController.php

  declare(strict_types=1);

  namespace App\Controller\Api;

  use App\Entity\User;
  use App\Service\PushNotificationService;
  use App\Service\ResponseFormatter;
  use App\Service\ValidationHelper;
  use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
  use Symfony\Component\HttpFoundation\JsonResponse;
  use Symfony\Component\HttpFoundation\Request;
  use Symfony\Component\Routing\Attribute\Route;

  #[Route('/api/v1/push', name: 'api_push_')]
  final class PushController extends AbstractController
  {
      public function __construct(
          private readonly PushNotificationService $pushService,
          private readonly ResponseFormatter $responseFormatter,
          private readonly ValidationHelper $validationHelper,
          private readonly string $vapidPublicKey,
      ) {}

      /**
       * Get VAPID public key for push subscription.
       */
      #[Route('/vapid-key', name: 'vapid_key', methods: ['GET'])]
      public function getVapidKey(): JsonResponse
      {
          return $this->responseFormatter->success([
              'publicKey' => $this->vapidPublicKey,
          ]);
      }

      /**
       * Subscribe to push notifications.
       */
      #[Route('/subscribe', name: 'subscribe', methods: ['POST'])]
      public function subscribe(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $data = $this->validationHelper->decodeJsonBody($request);

          $subscription = $this->pushService->subscribe(
              $user,
              $data['endpoint'],
              $data['keys']['p256dh'],
              $data['keys']['auth'],
              $request->headers->get('User-Agent')
          );

          return $this->responseFormatter->success([
              'subscribed' => true,
              'id' => $subscription->getId(),
          ]);
      }

      /**
       * Unsubscribe from push notifications.
       */
      #[Route('/unsubscribe', name: 'unsubscribe', methods: ['POST'])]
      public function unsubscribe(Request $request): JsonResponse
      {
          $data = $this->validationHelper->decodeJsonBody($request);

          $this->pushService->unsubscribe($data['endpoint']);

          return $this->responseFormatter->success([
              'unsubscribed' => true,
          ]);
      }
  }
  ```

- [ ] **12.5.5** Create service worker for push
  ```javascript
  // public/sw.js

  self.addEventListener('push', function(event) {
      if (!event.data) {
          return;
      }

      const data = event.data.json();

      const options = {
          body: data.body,
          icon: data.icon || '/images/icon-192.png',
          badge: data.badge || '/images/badge-72.png',
          vibrate: [100, 50, 100],
          data: data.data,
          actions: [
              { action: 'open', title: 'Open' },
              { action: 'dismiss', title: 'Dismiss' }
          ]
      };

      event.waitUntil(
          self.registration.showNotification(data.title, options)
      );
  });

  self.addEventListener('notificationclick', function(event) {
      event.notification.close();

      if (event.action === 'dismiss') {
          return;
      }

      const url = event.notification.data?.url || '/';

      event.waitUntil(
          clients.matchAll({ type: 'window', includeUncontrolled: true })
              .then(function(clientList) {
                  // Focus existing window if available
                  for (const client of clientList) {
                      if (client.url === url && 'focus' in client) {
                          return client.focus();
                      }
                  }
                  // Open new window
                  if (clients.openWindow) {
                      return clients.openWindow(url);
                  }
              })
      );
  });
  ```

- [ ] **12.5.6** Create push subscription JavaScript
  ```javascript
  // assets/js/push-notifications.js

  async function subscribeToPushNotifications() {
      if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
          console.log('Push notifications not supported');
          return false;
      }

      try {
          // Register service worker
          const registration = await navigator.serviceWorker.register('/sw.js');

          // Get VAPID public key
          const response = await fetch('/api/v1/push/vapid-key');
          const { data } = await response.json();
          const vapidPublicKey = data.publicKey;

          // Subscribe to push
          const subscription = await registration.pushManager.subscribe({
              userVisibleOnly: true,
              applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
          });

          // Send subscription to server
          await fetch('/api/v1/push/subscribe', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(subscription.toJSON())
          });

          console.log('Push notification subscription successful');
          return true;

      } catch (error) {
          console.error('Push subscription failed:', error);
          return false;
      }
  }

  function urlBase64ToUint8Array(base64String) {
      const padding = '='.repeat((4 - base64String.length % 4) % 4);
      const base64 = (base64String + padding)
          .replace(/-/g, '+')
          .replace(/_/g, '/');

      const rawData = window.atob(base64);
      const outputArray = new Uint8Array(rawData.length);

      for (let i = 0; i < rawData.length; ++i) {
          outputArray[i] = rawData.charCodeAt(i);
      }
      return outputArray;
  }

  // Check and request permission
  async function requestNotificationPermission() {
      if (Notification.permission === 'granted') {
          return true;
      }

      if (Notification.permission === 'denied') {
          return false;
      }

      const permission = await Notification.requestPermission();
      return permission === 'granted';
  }
  ```

### Completion Criteria
- [ ] VAPID keys configured
- [ ] Push subscriptions stored
- [ ] Service worker registered
- [ ] Push notifications delivered
- [ ] Invalid subscriptions cleaned up

---

## Sub-Phase 12.6: Notification API & UI

### Objective
Create API endpoints and UI for viewing and managing notifications.

### Tasks

- [ ] **12.6.1** Complete NotificationController
  ```php
  // src/Controller/Api/NotificationController.php additions

  /**
   * List notifications.
   */
  #[Route('', name: 'list', methods: ['GET'])]
  public function list(Request $request): JsonResponse
  {
      /** @var User $user */
      $user = $this->getUser();

      $limit = min((int) $request->query->get('limit', 50), 100);
      $offset = (int) $request->query->get('offset', 0);
      $unreadOnly = $request->query->getBoolean('unread_only', false);

      if ($unreadOnly) {
          $notifications = $this->notificationRepository->findUnreadByOwner($user, $limit);
      } else {
          $notifications = $this->notificationRepository->findByOwner($user, $limit, $offset);
      }

      $unreadCount = $this->notificationRepository->countUnreadByOwner($user);

      return $this->responseFormatter->success([
          'notifications' => array_map(fn($n) => $this->formatNotification($n), $notifications),
          'unreadCount' => $unreadCount,
      ]);
  }

  /**
   * Get unread count.
   */
  #[Route('/unread-count', name: 'unread_count', methods: ['GET'])]
  public function unreadCount(): JsonResponse
  {
      /** @var User $user */
      $user = $this->getUser();

      $count = $this->notificationRepository->countUnreadByOwner($user);

      return $this->responseFormatter->success([
          'count' => $count,
      ]);
  }

  /**
   * Mark notification as read.
   */
  #[Route('/{id}/read', name: 'mark_read', methods: ['POST'])]
  public function markAsRead(string $id): JsonResponse
  {
      /** @var User $user */
      $user = $this->getUser();

      $notification = $this->notificationRepository->find($id);

      if ($notification === null || $notification->getOwner() !== $user) {
          return $this->responseFormatter->error(
              'Notification not found',
              'NOT_FOUND',
              Response::HTTP_NOT_FOUND
          );
      }

      $notification->markAsRead();
      $this->entityManager->flush();

      return $this->responseFormatter->success([
          'read' => true,
      ]);
  }

  /**
   * Mark all notifications as read.
   */
  #[Route('/read-all', name: 'mark_all_read', methods: ['POST'])]
  public function markAllAsRead(): JsonResponse
  {
      /** @var User $user */
      $user = $this->getUser();

      $count = $this->notificationRepository->markAllAsReadByOwner($user);

      return $this->responseFormatter->success([
          'markedRead' => $count,
      ]);
  }

  private function formatNotification(Notification $notification): array
  {
      return [
          'id' => $notification->getId(),
          'type' => $notification->getType(),
          'title' => $notification->getTitle(),
          'body' => $notification->getBody(),
          'entityType' => $notification->getEntityType(),
          'entityId' => $notification->getEntityId(),
          'read' => $notification->isRead(),
          'createdAt' => $notification->getCreatedAt()->format(\DateTimeInterface::RFC3339),
          'readAt' => $notification->getReadAt()?->format(\DateTimeInterface::RFC3339),
      ];
  }
  ```

- [ ] **12.6.2** Create notification dropdown UI
  ```twig
  {# templates/components/notification-dropdown.html.twig #}
  <div x-data="notificationDropdown()" @click.away="open = false" class="relative">
      {# Bell icon button #}
      <button @click="toggle()" class="relative p-2 text-gray-400 hover:text-gray-500">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>

          {# Unread badge #}
          <span x-show="unreadCount > 0"
                x-text="unreadCount > 99 ? '99+' : unreadCount"
                class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1
                       text-xs font-bold leading-none text-white bg-red-600 rounded-full">
          </span>
      </button>

      {# Dropdown panel #}
      <div x-show="open" x-transition
           class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 z-50">

          {# Header #}
          <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
              <button @click="markAllRead()" x-show="unreadCount > 0"
                      class="text-xs text-indigo-600 hover:text-indigo-800">
                  Mark all read
              </button>
          </div>

          {# Notification list #}
          <div class="max-h-96 overflow-y-auto">
              <template x-if="notifications.length === 0">
                  <div class="px-4 py-8 text-center text-sm text-gray-500">
                      No notifications
                  </div>
              </template>

              <template x-for="notification in notifications" :key="notification.id">
                  <a :href="getNotificationUrl(notification)"
                     @click="markRead(notification)"
                     :class="{ 'bg-indigo-50': !notification.read }"
                     class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-100">
                      <div class="flex items-start gap-3">
                          <div :class="getIconClass(notification.type)"
                               class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center">
                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path x-bind:d="getIconPath(notification.type)"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                              </svg>
                          </div>
                          <div class="flex-1 min-w-0">
                              <p class="text-sm font-medium text-gray-900 truncate"
                                 x-text="notification.title"></p>
                              <p class="text-xs text-gray-500 truncate"
                                 x-text="notification.body"></p>
                              <p class="text-xs text-gray-400 mt-1"
                                 x-text="formatTime(notification.createdAt)"></p>
                          </div>
                      </div>
                  </a>
              </template>
          </div>

          {# Footer #}
          <div class="px-4 py-3 border-t border-gray-100">
              <a href="{{ path('app_notifications') }}"
                 class="text-sm text-indigo-600 hover:text-indigo-800">
                  View all notifications
              </a>
          </div>
      </div>
  </div>
  ```

- [ ] **12.6.3** Create notification JavaScript
  ```javascript
  // assets/js/notifications.js

  function notificationDropdown() {
      return {
          open: false,
          notifications: [],
          unreadCount: 0,
          loading: false,

          init() {
              this.fetchUnreadCount();
              // Poll for new notifications every 60 seconds
              setInterval(() => this.fetchUnreadCount(), 60000);
          },

          toggle() {
              this.open = !this.open;
              if (this.open) {
                  this.fetchNotifications();
              }
          },

          async fetchNotifications() {
              this.loading = true;
              try {
                  const response = await fetch('/api/v1/notifications?limit=10');
                  const data = await response.json();
                  this.notifications = data.data.notifications;
                  this.unreadCount = data.data.unreadCount;
              } finally {
                  this.loading = false;
              }
          },

          async fetchUnreadCount() {
              const response = await fetch('/api/v1/notifications/unread-count');
              const data = await response.json();
              this.unreadCount = data.data.count;
          },

          async markRead(notification) {
              if (notification.read) return;

              await fetch(`/api/v1/notifications/${notification.id}/read`, {
                  method: 'POST'
              });
              notification.read = true;
              this.unreadCount = Math.max(0, this.unreadCount - 1);
          },

          async markAllRead() {
              await fetch('/api/v1/notifications/read-all', {
                  method: 'POST'
              });
              this.notifications.forEach(n => n.read = true);
              this.unreadCount = 0;
          },

          getNotificationUrl(notification) {
              if (notification.entityType === 'task' && notification.entityId) {
                  return `/tasks/${notification.entityId}`;
              }
              return '#';
          },

          getIconClass(type) {
              switch (type) {
                  case 'task_due_soon':
                      return 'bg-yellow-100 text-yellow-600';
                  case 'task_overdue':
                      return 'bg-red-100 text-red-600';
                  case 'task_due_today':
                      return 'bg-blue-100 text-blue-600';
                  default:
                      return 'bg-gray-100 text-gray-600';
              }
          },

          getIconPath(type) {
              switch (type) {
                  case 'task_overdue':
                      return 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
                  default:
                      return 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
              }
          },

          formatTime(dateString) {
              const date = new Date(dateString);
              const now = new Date();
              const diff = now - date;

              if (diff < 60000) return 'Just now';
              if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
              if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
              return date.toLocaleDateString();
          }
      };
  }
  ```

### Completion Criteria
- [ ] Notification list endpoint
- [ ] Unread count endpoint
- [ ] Mark as read (single/all)
- [ ] Dropdown UI in header
- [ ] Real-time count updates
- [ ] Full notification page

---

## Sub-Phase 12.7: Testing

### Tasks

- [ ] **12.7.1** Notification service tests
  ```php
  // tests/Unit/Service/NotificationServiceTest.php
  public function testCreateTaskDueSoonNotification(): void;
  public function testCreateTaskOverdueNotification(): void;
  public function testQuietHoursRespected(): void;
  public function testNotificationDispatchedToEnabledChannels(): void;
  ```

- [ ] **12.7.2** Reminder scheduler tests
  ```php
  // tests/Unit/Service/ReminderSchedulerServiceTest.php
  public function testProcessRemindersForUpcomingTasks(): void;
  public function testRemindersNotDuplicated(): void;
  public function testOverdueNotificationsSentOnce(): void;
  public function testDueTodayNotifications(): void;
  public function testRespectUserReminderPreferences(): void;
  ```

- [ ] **12.7.3** Push notification tests
  ```php
  // tests/Unit/Service/PushNotificationServiceTest.php
  public function testSubscribe(): void;
  public function testUnsubscribe(): void;
  public function testSendNotification(): void;
  public function testInvalidSubscriptionRemoved(): void;
  ```

- [ ] **12.7.4** API tests
  ```php
  // tests/Functional/Api/NotificationApiTest.php
  public function testListNotifications(): void;
  public function testUnreadCount(): void;
  public function testMarkAsRead(): void;
  public function testMarkAllAsRead(): void;
  public function testUpdatePreferences(): void;
  ```

### Completion Criteria
- [ ] All services unit tested
- [ ] API endpoints functionally tested
- [ ] Scheduler tested
- [ ] Push notifications tested

---

## Phase 12 Deliverables Checklist

### Database
- [ ] Notification entity
- [ ] PushSubscription entity
- [ ] Migrations applied

### User Preferences
- [ ] Notification settings in user entity
- [ ] Per-channel enable/disable
- [ ] Per-type enable/disable
- [ ] Quiet hours configuration
- [ ] Reminder interval configuration

### Notification Service
- [ ] Create notifications for task events
- [ ] Async dispatch via Messenger
- [ ] Email notifications sent
- [ ] Push notifications sent
- [ ] Quiet hours respected

### Reminder Scheduler
- [ ] Checks tasks every 15 minutes
- [ ] Sends reminders at configured intervals
- [ ] Overdue notifications sent once
- [ ] Due today notifications sent daily
- [ ] Deduplication via Redis

### Push Notifications
- [ ] VAPID keys configured
- [ ] Subscriptions stored
- [ ] Service worker registered
- [ ] Push delivery working

### API
- [ ] List notifications
- [ ] Unread count
- [ ] Mark as read
- [ ] Update preferences
- [ ] Push subscribe/unsubscribe

### UI
- [ ] Notification dropdown in header
- [ ] Unread badge count
- [ ] Full notifications page
- [ ] Preferences UI

### Testing
- [ ] Services unit tested
- [ ] API functionally tested
- [ ] Scheduler tested

---

## Implementation Order

1. **12.1**: Entity & infrastructure
2. **12.2**: User preferences
3. **12.3**: Notification service
4. **12.4**: Reminder scheduler
5. **12.5**: Push notifications
6. **12.6**: API & UI
7. **12.7**: Testing

---

## Files Summary

### New Files
```
src/Entity/Notification.php
src/Entity/PushSubscription.php
src/Repository/NotificationRepository.php
src/Repository/PushSubscriptionRepository.php
src/Service/NotificationService.php
src/Service/ReminderSchedulerService.php
src/Service/ReminderTrackingService.php
src/Service/PushNotificationService.php
src/Controller/Api/NotificationController.php
src/Controller/Api/PushController.php
src/Command/ProcessRemindersCommand.php
src/Message/SendEmailNotification.php
src/Message/SendPushNotification.php
src/MessageHandler/SendEmailNotificationHandler.php
src/MessageHandler/SendPushNotificationHandler.php
src/DTO/NotificationPreferencesRequest.php
templates/components/notification-dropdown.html.twig
templates/notifications/index.html.twig
templates/emails/notification.html.twig
public/sw.js
assets/js/notifications.js
assets/js/push-notifications.js
migrations/Version20260124_Notifications.php
migrations/Version20260124_PushSubscriptions.php
config/packages/messenger.yaml
tests/Unit/Service/NotificationServiceTest.php
tests/Unit/Service/ReminderSchedulerServiceTest.php
tests/Unit/Service/PushNotificationServiceTest.php
tests/Functional/Api/NotificationApiTest.php
```

### Updated Files
```
src/Entity/User.php
src/Repository/TaskRepository.php
src/Service/EmailService.php
config/services.yaml
.env.local.example (VAPID keys, MESSENGER_TRANSPORT_DSN)
templates/base.html.twig (notification dropdown)
```
