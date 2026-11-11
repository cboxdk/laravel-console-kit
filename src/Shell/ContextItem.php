<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * One selectable thing at a context level: an organization, a project, an environment.
 * The `current` of a level and every row its list returns.
 *
 * `routeKey` is what goes into the URL for this item (the slug, per the URL standard).
 * It stays on the server: it is not part of {@see toArray()}, the shell's wire shape. It
 * defaults to the id, for a level whose URLs carry ids.
 *
 * `tone`, `label` and `testData` are app-defined (spec §5.1a). The shell never sorts
 * items; send them in the order the person should see.
 *
 * For environments (cbox-ui 0.11, spec §4.5):
 *  - `live` is the app's word on whether this is production (real customers, real money).
 *    `false` shows the "Not production" banner. Null leaves it to `testData` and `tone`.
 *  - `group` is the heading the item is listed under in "All environments" ("Live", "Test").
 *  - `description` is a second line under the name ("From branch feat/quotes").
 */
readonly class ContextItem
{
    public string $routeKey;

    public function __construct(
        public string $id = '',
        public string $name = '',
        ?string $routeKey = null,
        public ?string $initials = null,
        public ?Tone $tone = null,
        public ?string $label = null,
        public ?bool $testData = null,
        public ?bool $live = null,
        public ?string $group = null,
        public ?string $description = null,
    ) {
        $this->routeKey = $routeKey ?? $id;
    }

    /** @return array{id: string, name: string, initials?: string, tone?: string, label?: string, testData?: bool, live?: bool, group?: string, description?: string} */
    public function toArray(): array
    {
        $out = ['id' => $this->id, 'name' => $this->name];

        if ($this->initials !== null) {
            $out['initials'] = $this->initials;
        }

        if ($this->tone instanceof Tone) {
            $out['tone'] = $this->tone->value;
        }

        if ($this->label !== null) {
            $out['label'] = $this->label;
        }

        if ($this->testData !== null) {
            $out['testData'] = $this->testData;
        }

        if ($this->live !== null) {
            $out['live'] = $this->live;
        }

        if ($this->group !== null && $this->group !== '') {
            $out['group'] = $this->group;
        }

        if ($this->description !== null && $this->description !== '') {
            $out['description'] = $this->description;
        }

        return $out;
    }
}
