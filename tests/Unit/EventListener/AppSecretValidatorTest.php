<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\AppSecretValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class AppSecretValidatorTest extends TestCase
{
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createValidator(string $secret, string $environment): AppSecretValidator
    {
        return new AppSecretValidator($secret, $environment, $this->logger);
    }

    private function createMainRequestEvent(): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function createSubRequestEvent(): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        return new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);
    }

    private function createConsoleCommandEvent(): ConsoleCommandEvent
    {
        $command = $this->createMock(Command::class);
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        return new ConsoleCommandEvent($command, $input, $output);
    }

    public function testGetSubscribedEventsReturnsCorrectListeners(): void
    {
        $events = AppSecretValidator::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertArrayHasKey(ConsoleEvents::COMMAND, $events);

        $this->assertSame(['onKernelRequest', 256], $events[KernelEvents::REQUEST]);
        $this->assertSame(['onConsoleCommand', 256], $events[ConsoleEvents::COMMAND]);
    }

    public function testOnKernelRequestSkipsSubRequests(): void
    {
        $validator = $this->createValidator('short', 'prod');
        $event = $this->createSubRequestEvent();

        // Should not throw even with insecure secret since it's a sub-request
        $validator->onKernelRequest($event);

        // If we got here without exception, test passes
        $this->assertTrue(true);
    }

    public function testOnKernelRequestValidatesMainRequest(): void
    {
        $validator = $this->createValidator('insecure_short', 'prod');
        $event = $this->createMainRequestEvent();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET is set to an insecure default value');

        $validator->onKernelRequest($event);
    }

    public function testOnConsoleCommandTriggersValidation(): void
    {
        $validator = $this->createValidator('insecure_short', 'prod');
        $event = $this->createConsoleCommandEvent();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET is set to an insecure default value');

        $validator->onConsoleCommand($event);
    }

    public function testValidateOnlyRunsOnce(): void
    {
        $secureSecret = str_repeat('a', 32);
        $validator = $this->createValidator($secureSecret, 'dev');

        $event1 = $this->createMainRequestEvent();
        $event2 = $this->createMainRequestEvent();

        // First call should work
        $validator->onKernelRequest($event1);

        // Second call should be skipped (no logging)
        $this->logger->expects($this->never())->method('warning');
        $validator->onKernelRequest($event2);
    }

    public function testProductionThrowsOnInsecureSecret(): void
    {
        $validator = $this->createValidator('change_me', 'prod');
        $event = $this->createMainRequestEvent();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET is set to an insecure default value');

        $validator->onKernelRequest($event);
    }

    public function testProductionThrowsOnShortSecret(): void
    {
        $validator = $this->createValidator('only31characterslong1234567890', 'prod');
        $event = $this->createMainRequestEvent();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET is set to an insecure default value');

        $validator->onKernelRequest($event);
    }

    public function testDevLogsWarningOnInsecureSecret(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('APP_SECRET is set to an insecure default value'),
                $this->equalTo(['environment' => 'dev'])
            );

        $validator = $this->createValidator('change_me', 'dev');
        $event = $this->createMainRequestEvent();

        $validator->onKernelRequest($event);
    }

    public function testSecureSecretPassesInProduction(): void
    {
        $secureSecret = bin2hex(random_bytes(16)); // 32 hex characters
        $validator = $this->createValidator($secureSecret, 'prod');
        $event = $this->createMainRequestEvent();

        // Should not throw
        $validator->onKernelRequest($event);
        $this->assertTrue(true);
    }

    public function testSecureSecretNoWarningInDev(): void
    {
        $this->logger->expects($this->never())->method('warning');

        $secureSecret = bin2hex(random_bytes(16)); // 32 hex characters
        $validator = $this->createValidator($secureSecret, 'dev');
        $event = $this->createMainRequestEvent();

        $validator->onKernelRequest($event);
    }

    public function testInsecureSecretDetectionIsCaseInsensitive(): void
    {
        $validator = $this->createValidator('CHANGE_ME', 'prod');
        $event = $this->createMainRequestEvent();

        $this->expectException(\RuntimeException::class);

        $validator->onKernelRequest($event);
    }

    /**
     * @dataProvider knownInsecureSecretsProvider
     */
    public function testAllKnownInsecureSecretsDetected(string $insecureSecret): void
    {
        $validator = $this->createValidator($insecureSecret, 'prod');
        $event = $this->createMainRequestEvent();

        $this->expectException(\RuntimeException::class);

        $validator->onKernelRequest($event);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function knownInsecureSecretsProvider(): array
    {
        return [
            'CHANGE_ME_TO_A_SECURE_SECRET_KEY' => ['CHANGE_ME_TO_A_SECURE_SECRET_KEY'],
            'change_me' => ['change_me'],
            'changeme' => ['changeme'],
            'secret' => ['secret'],
            'app_secret' => ['app_secret'],
            'your_secret' => ['your_secret'],
            'test' => ['test'],
            'dev' => ['dev'],
            'development' => ['development'],
        ];
    }
}
