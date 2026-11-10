<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use DateTimeInterface;

/** One product event in the bell (spec §8). Support replies belong to Help, not here. */
readonly class NotificationItem
{
    public function __construct(
        public string $id,
        public string $title,
        public DateTimeInterface $createdAt,
        public bool $read = false,
        public ?string $body = null,
        public ?string $url = null,
    ) {}

    /** @return array{id: string, title: string, body: string|null, url: string|null, read: bool, created_at: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'read' => $this->read,
            'created_at' => \DateTimeImmutable::createFromInterface($this->createdAt)
                ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
