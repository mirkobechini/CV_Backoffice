<?php

namespace App\Services;

/**
 * Accumula email/eventi Telegram in sospeso durante un giro di
 * GenerateNotifications, invece dei 3 parametri sciolti passati per
 * riferimento (array &$emailsByUser, array &$telegramEventsByUser,
 * int &$created) che ogni notify*()/dispatchEvent() doveva ripetere
 * identici — 10 metodi con 7-9 parametri ciascuno (audit qualità
 * 2026-11-25, F1). Le proprietà di un oggetto sono già mutabili per
 * riferimento passando l'oggetto stesso, senza bisogno di `&`.
 */
class NotificationBatch
{
    /** @var array<int, array<int, array{type: string, title: string, body: string, url: ?string}>> */
    public array $emailEventsByUser = [];

    /** @var array<int, array<int, array{0: string, 1: string}>> */
    public array $telegramEventsByUser = [];

    public int $created = 0;

    public function addEmailEvent(int $userId, string $type, string $title, string $body, ?string $url): void
    {
        $this->emailEventsByUser[$userId][] = compact('type', 'title', 'body', 'url');
    }

    public function addTelegramEvent(int $userId, string $title, string $message): void
    {
        $this->telegramEventsByUser[$userId][] = [$title, $message];
    }

    public function incrementCreated(): void
    {
        $this->created++;
    }
}
