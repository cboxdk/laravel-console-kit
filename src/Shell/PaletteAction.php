<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * A command the palette offers under "Actions" (spec §6.1): "New deployment", "Invite
 * members". Build one from a named route or a URL:
 *
 *     PaletteAction::route('deployments.create', 'New deployment');
 *     PaletteAction::url('/orgs/cbox/settings/members?invite=1', 'Invite members');
 *
 * A route action takes the current request's route parameters that the target route
 * needs (the organization slug, say), so it works on every page of a context without
 * the app passing them. {@see resolve()} turns it into the wire shape.
 */
readonly class PaletteAction
{
    /**
     * @param  array<string, string|int>  $parameters
     * @param  list<string>  $keywords
     */
    public function __construct(
        public string $id,
        public string $label,
        public ?string $url = null,
        public ?string $routeName = null,
        public array $parameters = [],
        public ?string $icon = null,
        public array $keywords = [],
        public ?string $shortcut = null,
    ) {}

    /**
     * @param  array<string, string|int>  $parameters
     * @param  list<string>  $keywords
     */
    public static function route(string $name, string $label, array $parameters = [], ?string $icon = null, array $keywords = [], ?string $shortcut = null): self
    {
        return new self($name, $label, null, $name, $parameters, $icon, $keywords, $shortcut);
    }

    /**
     * @param  list<string>  $keywords
     */
    public static function url(string $url, string $label, ?string $id = null, ?string $icon = null, array $keywords = [], ?string $shortcut = null): self
    {
        return new self($id ?? self::slug($label), $label, $url, null, [], $icon, $keywords, $shortcut);
    }

    /**
     * This action with its URL resolved against the current route, or null when the
     * route does not exist or cannot be built here (a required parameter is missing).
     */
    public function resolve(Router $router, ?Route $current): ?self
    {
        if ($this->routeName === null) {
            return $this->url !== null ? $this : null;
        }

        $url = ShellUrls::route($router, $this->routeName, $current, $this->parameters);

        return $url === null ? null : new self($this->id, $this->label, $url, null, [], $this->icon, $this->keywords, $this->shortcut);
    }

    /** @return array{id: string, label: string, url: string, icon?: string, keywords?: list<string>, shortcut?: string} */
    public function toArray(): array
    {
        $out = ['id' => $this->id, 'label' => $this->label, 'url' => $this->url ?? ''];

        if ($this->icon !== null) {
            $out['icon'] = $this->icon;
        }

        if ($this->keywords !== []) {
            $out['keywords'] = $this->keywords;
        }

        if ($this->shortcut !== null) {
            $out['shortcut'] = $this->shortcut;
        }

        return $out;
    }

    private static function slug(string $label): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $label), '-'));

        return $slug === '' ? 'action' : $slug;
    }
}
