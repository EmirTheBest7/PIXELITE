<?php
declare(strict_types=1);

namespace Pixelite;

/**
 * Accepting an order == it is persisted. Telegram is a notification on top of that;
 * if it fails the order is kept, flagged `failed`, and can be retried from /admin.
 */
final class OrderService
{
    public function __construct(
        private OrderRepository $repo = new OrderRepository(),
        private TelegramNotifier $telegram = new TelegramNotifier(),
    ) {}

    /** @param array<string,string> $clean validated input. @return int new order id (0 = duplicate, ignored). @throws \Throwable only when the order could not be stored */
    public function submit(array $clean, string $locale): int
    {
        if ($this->repo->hasRecentDuplicate($clean['email'], $clean['description'])) {
            Logger::info('duplicate submit ignored');
            return 0;
        }
        $clean = PricingSnapshot::capture($clean['package'] ?? '', $locale) + $clean;   // server-side; any client-sent snapshot fields are never read
        $id = $this->repo->create($clean, $locale); // throws => caller shows "not saved" (true: nothing was stored)
        try {
            $this->notify($id);
        } catch (\Throwable $e) {
            Logger::error('notification step crashed after order was saved', ['order' => $id, 'error' => $e->getMessage()]);
        }
        return $id;
    }

    public function notify(int $id): string
    {
        $order = $this->repo->find($id);
        if ($order === null) {
            return TelegramNotifier::FAILED;
        }
        [$status, $error] = $this->telegram->send($order);
        $this->repo->markNotification($id, $status, $error);
        if ($status === TelegramNotifier::FAILED) {
            Logger::error('telegram notification failed', ['order' => $id, 'error' => $error]);
        }
        return $status;
    }
}
