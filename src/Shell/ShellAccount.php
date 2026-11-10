<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * `shell.account`: where the account menu at the bottom of the rail links to (spec §10).
 * Profile and security (in Cbox ID), Switch account and Sign out; a row whose URL is
 * absent is not shown. `languages` and `formats` (code => label) are the choices the
 * menu offers; cbox-ui has defaults for both.
 */
readonly class ShellAccount
{
    /**
     * @param  array<string, string>  $languages  code => label, e.g. ['en' => 'English', 'da' => 'Dansk']
     * @param  array<string, string>  $formats  BCP 47 code => label
     */
    public function __construct(
        public ?string $profileUrl = null,
        public ?string $switchAccountUrl = null,
        public ?string $signOutUrl = null,
        public string $signOutMethod = 'post',
        public ?string $signedInWith = null,
        public array $languages = [],
        public array $formats = [],
    ) {}

    /** True when there is nothing to send, so the prop leaves `account` out. */
    public function isEmpty(): bool
    {
        return $this->profileUrl === null && $this->switchAccountUrl === null && $this->signOutUrl === null
            && $this->signedInWith === null && $this->languages === [] && $this->formats === [];
    }

    /**
     * @return array{profileUrl?: string, switchAccountUrl?: string, signOutUrl?: string, signOutMethod?: string, signedInWith?: string, languages?: list<array{code: string, label: string}>, formats?: list<array{code: string, label: string}>}
     */
    public function toArray(): array
    {
        $out = [];

        if ($this->profileUrl !== null) {
            $out['profileUrl'] = $this->profileUrl;
        }

        if ($this->switchAccountUrl !== null) {
            $out['switchAccountUrl'] = $this->switchAccountUrl;
        }

        if ($this->signOutUrl !== null) {
            $out['signOutUrl'] = $this->signOutUrl;

            if ($this->signOutMethod === 'get') {
                $out['signOutMethod'] = 'get';
            }
        }

        if ($this->signedInWith !== null) {
            $out['signedInWith'] = $this->signedInWith;
        }

        if ($this->languages !== []) {
            $out['languages'] = self::options($this->languages);
        }

        if ($this->formats !== []) {
            $out['formats'] = self::options($this->formats);
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $options
     * @return list<array{code: string, label: string}>
     */
    private static function options(array $options): array
    {
        $out = [];

        foreach ($options as $code => $label) {
            $out[] = ['code' => (string) $code, 'label' => $label];
        }

        return $out;
    }
}
