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
 *     PaletteAction::route('members', 'Invite members', fragment: 'invite'); // …/members#invite
 *
 * A route action takes the current request's route parameters that the target route
 * needs (the organization slug, say), so it works on every page of a context without
 * the app passing them. {@see resolve()} turns it into the wire shape.
 *
 * `hint` is a short second line ("Create a draft for a customer"). `create` names a
 * creatable (`Console::shell()->creatables()`): cbox-ui opens the create sheet on that
 * form instead of following the URL, which stays the fallback (cbox-ui 0.11):
 *
 *     PaletteAction::route('invoices.create', 'New invoice', hint: 'Draft for a customer', create: 'invoice');
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
        public ?string $fragment = null,
        public ?string $hint = null,
        public ?string $create = null,
    ) {}

    /**
     * `fragment` is added to the built URL as `#fragment` (`members#invite`); the action's
     * id is then `{name}#{fragment}`, so two actions to one route stay apart.
     *
     * @param  array<string, string|int>  $parameters
     * @param  list<string>  $keywords
     */
    public static function route(string $name, string $label, array $parameters = [], ?string $icon = null, array $keywords = [], ?string $shortcut = null, ?string $fragment = null, ?string $hint = null, ?string $create = null): self
    {
        $fragment = $fragment === null ? null : ltrim($fragment, '#');
        $fragment = $fragment === '' ? null : $fragment;

        return new self($fragment === null ? $name : $name.'#'.$fragment, $label, null, $name, $parameters, $icon, $keywords, $shortcut, $fragment, $hint, $create);
    }

    /**
     * @param  list<string>  $keywords
     */
    public static function url(string $url, string $label, ?string $id = null, ?string $icon = null, array $keywords = [], ?string $shortcut = null, ?string $hint = null, ?string $create = null): self
    {
        return new self($id ?? self::slug($label), $label, $url, null, [], $icon, $keywords, $shortcut, null, $hint, $create);
    }

    /** This action with a second line under its label. */
    public function withHint(?string $hint): self
    {
        return new self($this->id, $this->label, $this->url, $this->routeName, $this->parameters, $this->icon, $this->keywords, $this->shortcut, $this->fragment, $hint, $this->create);
    }

    /** This action opening the create sheet on creatable `$key` (the URL stays the fallback). */
    public function withCreate(?string $key): self
    {
        return new self($this->id, $this->label, $this->url, $this->routeName, $this->parameters, $this->icon, $this->keywords, $this->shortcut, $this->fragment, $this->hint, $key);
    }

    /**
     * This action with its URL resolved against the current route, or null when the
     * route does not exist or cannot be built here (a required parameter is missing).
     */
    public function resolve(Router $router, ?Route $current): ?self
    {
        return $this->resolveIn($router, $current);
    }

    /**
     * {@see resolve()}, with `$context` (route parameter => value, from the resolved context
     * levels) filling a parameter neither the action nor the current route carries.
     *
     * @param  array<string, string|int>  $context
     */
    public function resolveIn(Router $router, ?Route $current, array $context = []): ?self
    {
        if ($this->routeName === null) {
            return $this->url !== null ? $this : null;
        }

        $url = ShellUrls::route($router, $this->routeName, $current, $this->parameters, $context);

        if ($url === null) {
            return null;
        }

        if ($this->fragment !== null && $this->fragment !== '') {
            $url .= '#'.rawurlencode(ltrim($this->fragment, '#'));
        }

        return new self($this->id, $this->label, $url, null, [], $this->icon, $this->keywords, $this->shortcut, null, $this->hint, $this->create);
    }

    /** @return array{id: string, label: string, url: string, icon?: string, keywords?: list<string>, shortcut?: string, hint?: string, create?: string} */
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

        if ($this->hint !== null && $this->hint !== '') {
            $out['hint'] = $this->hint;
        }

        if ($this->create !== null && $this->create !== '') {
            $out['create'] = $this->create;
        }

        return $out;
    }

    private static function slug(string $label): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $label), '-'));

        return $slug === '' ? 'action' : $slug;
    }
}
