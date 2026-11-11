<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Testing;

use Cbox\Console\Kit\Shell\Shell;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View;
use PHPUnit\Framework\Assert;

/**
 * Fluent assertions on a `shell` prop, from an Inertia or Blade response or a built
 * {@see Shell}:
 *
 *     $this->assertShell($this->get('/orgs/cbox/deployments'))
 *         ->assertLevel('organization', 'org_1')
 *         ->assertActiveArea('deployments')
 *         ->assertNotificationsDisabled();
 */
class AssertableShell
{
    /** @param array<string, mixed> $shell */
    public function __construct(private readonly array $shell) {}

    /** @param Shell|array<string, mixed> $shell */
    public static function of(Shell|array $shell): self
    {
        return new self($shell instanceof Shell ? $shell->toArray() : $shell);
    }

    /**
     * The `shell` prop of an Inertia page (first visit or an Inertia visit) or the
     * `$shell` a Blade view was given.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function fromResponse(TestResponse $response): self
    {
        $shell = $response->headers->has('X-Inertia')
            ? $response->json('props.shell')
            : (self::pageProps($response) ?? $response->viewData('shell'));

        Assert::assertIsArray($shell, 'The response has no shell prop.');

        $out = [];

        foreach ($shell as $key => $value) {
            $out[(string) $key] = $value;
        }

        return new self($out);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->shell;
    }

    /** A value by dot path (`context.levels.0.current.id`). */
    public function get(string $path): mixed
    {
        return data_get($this->shell, $path);
    }

    public function assertPath(string $path, mixed $expected): static
    {
        Assert::assertSame($expected, $this->get($path), "shell.{$path} is not what was expected.");

        return $this;
    }

    public function assertApp(string $key): static
    {
        return $this->assertPath('app.key', $key);
    }

    /** @param list<string> $keys */
    public function assertLevels(array $keys): static
    {
        Assert::assertSame($keys, array_column($this->levels(), 'key'), 'The shell shows other context levels.');

        return $this;
    }

    public function assertLevel(string $key, ?string $currentId = null): static
    {
        $level = $this->level($key);

        Assert::assertNotNull($level, "The shell has no [{$key}] level.");

        if ($currentId !== null) {
            Assert::assertSame($currentId, data_get($level, 'current.id'), "The [{$key}] level is not on [{$currentId}].");
        }

        return $this;
    }

    public function assertNoLevel(string $key): static
    {
        Assert::assertNull($this->level($key), "The shell has a [{$key}] level.");

        return $this;
    }

    public function assertCanCreate(string $key, bool $expected = true): static
    {
        Assert::assertSame($expected, is_string(data_get($this->level($key), 'createUrl')), "The [{$key}] level's \"New …\" is not as expected.");

        return $this;
    }

    public function assertNavArea(string $key): static
    {
        Assert::assertContains($key, array_column($this->areas(), 'key'), "The rail has no [{$key}] area.");

        return $this;
    }

    public function assertNoNavArea(string $key): static
    {
        Assert::assertNotContains($key, array_column($this->areas(), 'key'), "The rail shows the [{$key}] area.");

        return $this;
    }

    public function assertActiveArea(string $key, ?string $page = null): static
    {
        $this->assertPath('nav.activeArea', $key);

        return $page === null ? $this : $this->assertPath('nav.activePage', $page);
    }

    public function assertNotificationsDisabled(): static
    {
        Assert::assertSame(['enabled' => false], $this->get('notifications'), 'The bell is shown.');

        return $this;
    }

    public function assertNotificationsEnabled(?int $unread = null): static
    {
        $this->assertPath('notifications.enabled', true);

        return $unread === null ? $this : $this->assertPath('notifications.unread', $unread);
    }

    public function assertHelpEnabled(?string $topic = null): static
    {
        $this->assertPath('help.enabled', true);

        return $topic === null ? $this : $this->assertPath('help.topic', $topic);
    }

    public function assertHelpDisabled(): static
    {
        return $this->assertPath('help.enabled', false);
    }

    /** `shell.banner`, the single strip cbox-ui 0.10.1 and older show. */
    public function assertBanner(string $kind, ?string $text = null): static
    {
        $this->assertPath('banner.kind', $kind);

        return $text === null ? $this : $this->assertPath('banner.text', $text);
    }

    /** No banner at all: neither `banner` nor `banners`. */
    public function assertNoBanner(): static
    {
        Assert::assertArrayNotHasKey('banner', $this->shell, 'The shell shows a banner.');
        Assert::assertArrayNotHasKey('banners', $this->shell, 'The shell shows banners.');

        return $this;
    }

    /**
     * The kinds in `shell.banners`, in order: `['sandbox', 'notice']`.
     *
     * @param  list<string>  $kinds
     */
    public function assertBanners(array $kinds): static
    {
        Assert::assertSame($kinds, array_map(static fn (mixed $banner): mixed => data_get($banner, 'kind'), $this->list('banners')), 'The shell\'s banners differ.');

        return $this;
    }

    /** One of `shell.banners` has this kind (and text). */
    public function assertHasBanner(string $kind, ?string $text = null): static
    {
        foreach ($this->list('banners') as $banner) {
            if (data_get($banner, 'kind') === $kind && ($text === null || data_get($banner, 'text') === $text)) {
                return $this;
            }
        }

        Assert::fail("The shell has no {$kind} banner".($text === null ? '.' : " reading \"{$text}\"."));
    }

    public function assertAction(string $id, ?string $url = null): static
    {
        foreach ($this->list('palette.actions') as $action) {
            if (data_get($action, 'id') === $id) {
                if ($url !== null) {
                    Assert::assertSame($url, data_get($action, 'url'), "The [{$id}] action goes elsewhere.");
                }

                return $this;
            }
        }

        Assert::fail("The palette has no [{$id}] action.");
    }

    /** @return list<mixed> */
    private function levels(): array
    {
        return $this->list('context.levels');
    }

    /** @return list<mixed> */
    private function areas(): array
    {
        return $this->list('nav.areas');
    }

    private function level(string $key): mixed
    {
        foreach ($this->levels() as $level) {
            if (data_get($level, 'key') === $key) {
                return $level;
            }
        }

        return null;
    }

    /** @return list<mixed> */
    private function list(string $path): array
    {
        $value = $this->get($path);

        return is_array($value) ? array_values($value) : [];
    }

    /** @param TestResponse<\Symfony\Component\HttpFoundation\Response> $response */
    private static function pageProps(TestResponse $response): mixed
    {
        $original = $response->baseResponse;

        if (! $original instanceof Response || ! $original->original instanceof View) {
            return null;
        }

        return data_get($original->original->getData(), 'page.props.shell');
    }
}
