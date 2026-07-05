<?php

declare(strict_types=1);

namespace BgaHarness;

use PHPUnit\Framework\Assert;

class BgaNotificationSpy
{
    private array $notifications = [];

    public function notifyAllPlayers(string $type, string $message, array $data): void
    {
        $this->notifications[] = [
            'target' => 'all',
            'type' => $type,
            'message' => $message,
            'data' => $data,
        ];
    }

    public function notifyPlayer(int $playerId, string $type, string $message, array $data): void
    {
        $this->notifications[] = [
            'target' => $playerId,
            'type' => $type,
            'message' => $message,
            'data' => $data,
        ];
    }

    public function assertNotified(string $type, array $dataSubset = []): void
    {
        foreach ($this->notifications as $notification) {
            if ($notification['type'] !== $type) {
                continue;
            }
            if ($this->containsSubset($notification['data'], $dataSubset)) {
                Assert::assertTrue(true);
                return;
            }
        }

        Assert::fail('Expected notification was not sent: ' . $type);
    }

    public function assertNotifiedPlayer(int $playerId, string $type, array $dataSubset = []): void
    {
        foreach ($this->notifications as $notification) {
            if ($notification['target'] !== $playerId || $notification['type'] !== $type) {
                continue;
            }
            if ($this->containsSubset($notification['data'], $dataSubset)) {
                Assert::assertTrue(true);
                return;
            }
        }

        Assert::fail('Expected player notification was not sent: ' . $type);
    }

    public function assertNotNotified(string $type): void
    {
        foreach ($this->notifications as $notification) {
            if ($notification['type'] === $type) {
                Assert::fail('Notification should not have been sent: ' . $type);
            }
        }

        Assert::assertTrue(true);
    }

    public function assertNotificationCount(int $expected): void
    {
        Assert::assertCount($expected, $this->notifications);
    }

    public function getNotifications(): array
    {
        return $this->notifications;
    }

    public function reset(): void
    {
        $this->notifications = [];
    }

    private function containsSubset(array $actual, array $subset): bool
    {
        foreach ($subset as $key => $value) {
            if (!array_key_exists($key, $actual) || $actual[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
