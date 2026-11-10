<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * One context level on the wire (`shell.context.levels[]`). Only the current selection
 * travels with the page; the menus fetch `listUrl` when they open (spec §11.2).
 *
 * `settingsUrl` is a URL template: the client replaces {@see SETTINGS_PLACEHOLDER} with
 * a row's id. It points at the kit's settings redirect, which resolves the id on the
 * server, so a level whose URLs carry slugs still works.
 */
readonly class ShellContextLevel
{
    public const SETTINGS_PLACEHOLDER = '{id}';

    public function __construct(
        public string $key,
        public string $label,
        public ContextItem $current,
        public string $listUrl,
        public string $allUrl,
        public string $settingsUrl,
        public ?string $createUrl = null,
    ) {}

    /** @return array{key: string, label: string, current: array<string, mixed>, listUrl: string, createUrl?: string, allUrl: string, settingsUrl: string} */
    public function toArray(): array
    {
        $out = [
            'key' => $this->key,
            'label' => $this->label,
            'current' => $this->current->toArray(),
            'listUrl' => $this->listUrl,
        ];

        if ($this->createUrl !== null) {
            $out['createUrl'] = $this->createUrl;
        }

        $out['allUrl'] = $this->allUrl;
        $out['settingsUrl'] = $this->settingsUrl;

        return $out;
    }
}
