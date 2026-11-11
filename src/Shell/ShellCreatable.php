<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * One thing the person can create from the shell's "+" and the palette's "New …" (spec
 * §6.3, cbox-ui 0.11): "Invoice", "Credit note", "API token". `area` is the key of the nav
 * area it is listed under in "What do you want to create?". cbox-ui renders the form body
 * through the app's `renderCreate`; the server only names what exists.
 *
 *     new ShellCreatable('invoice', 'Invoice', 'invoices', keywords: ['bill']);
 */
readonly class ShellCreatable
{
    /**
     * @param  list<string>  $keywords  extra words the create tree matches on
     */
    public function __construct(
        public string $key = '',
        public string $label = '',
        public string $area = '',
        public array $keywords = [],
        public ?string $submitLabel = null,
    ) {}

    /** @return array{key: string, label: string, area: string, keywords?: list<string>, submitLabel?: string} */
    public function toArray(): array
    {
        $out = ['key' => $this->key, 'label' => $this->label, 'area' => $this->area];

        if ($this->keywords !== []) {
            $out['keywords'] = $this->keywords;
        }

        if ($this->submitLabel !== null && $this->submitLabel !== '') {
            $out['submitLabel'] = $this->submitLabel;
        }

        return $out;
    }
}
