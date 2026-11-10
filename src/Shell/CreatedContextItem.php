<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * The answer to "New …" (spec §5.7): the new item's id and the details page the person
 * lands on, with its "Finish setting up {name}" section.
 */
readonly class CreatedContextItem
{
    public function __construct(
        public string $id,
        public string $detailsUrl,
    ) {}

    /** @return array{id: string, detailsUrl: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'detailsUrl' => $this->detailsUrl];
    }
}
