<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/** `shell.person`: the signed-in person, for the account button at the bottom of the rail. */
readonly class ShellPerson
{
    public string $initials;

    public function __construct(
        public string $id = '',
        public string $name = '',
        ?string $initials = null,
        public ?string $avatarUrl = null,
    ) {
        $this->initials = $initials ?? self::initialsOf($name);
    }

    /**
     * Up to two letters: the first letter of the first and the last word ("Sylvester
     * Damgaard" → "SD", "Cbox" → "C"). Empty for an empty name.
     */
    public static function initialsOf(string $name): string
    {
        $words = preg_split('/[\s._@-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false || $words === []) {
            return '';
        }

        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr($words[count($words) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /** @return array{id: string, name: string, initials: string, avatarUrl?: string} */
    public function toArray(): array
    {
        $out = ['id' => $this->id, 'name' => $this->name, 'initials' => $this->initials];

        if ($this->avatarUrl !== null) {
            $out['avatarUrl'] = $this->avatarUrl;
        }

        return $out;
    }
}
