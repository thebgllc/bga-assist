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
                return;
            }
        }

        Assert::fail(
            sprintf(
                'Expected notification "%s" with subset %s was not sent. Captured notifications: %s',
                $type,
                json_encode($dataSubset),
                json_encode($this->notifications)
            )
        );
    }

    public function assertNotifiedPlayer(int $playerId, string $type, array $dataSubset = []): void
    {
        foreach ($this->notifications as $notification) {
            if ($notification['target'] !== $playerId || $notification['type'] !== $type) {
                continue;
            }
            if ($this->containsSubset($notification['data'], $dataSubset)) {
                return;
            }
        }

        Assert::fail(
            sprintf(
                'Expected player %d notification "%s" with subset %s was not sent. Captured notifications: %s',
                $playerId,
                $type,
                json_encode($dataSubset),
                json_encode($this->notifications)
            )
        );
    }

    public function assertNotNotified(string $type): void
    {
        foreach ($this->notifications as $notification) {
            if ($notification['type'] === $type) {
                Assert::fail(
                    sprintf(
                        'Notification "%s" should not have been sent, but was captured: %s',
                        $type,
                        json_encode($notification)
                    )
                );
            }
        }
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
