<?php

namespace Zeno\Plugin\Support;

use Closure;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class PluginTranslations
{
    public function __construct(
        private PluginRegistry $plugins,
        private PluginState $state,
        private Translator $translator,
    ) {}

    /**
     * @param  array<string, mixed>  $global
     * @param  Closure(string): array<string, mixed>  $loadProjectPage
     * @return array<string, mixed>
     */
    public function resolve(Request $request, array $global, Closure $loadProjectPage): array
    {
        $translations = $this->withPluginMenuTitles($global);
        $businessName = Str::of((string) $request->route()?->getName())
            ->after($this->routeNamePrefix().'.');
        $pluginKey = $businessName->before('.')->toString();

        if ($this->plugins->has($pluginKey)) {
            $namespace = $this->plugins->get($pluginKey)->translationNamespace();
            $pluginTranslations = $namespace === null ? [] : $this->translator->get("{$namespace}::admin");

            return is_array($pluginTranslations)
                ? array_replace_recursive($translations, $pluginTranslations)
                : $translations;
        }

        if ($businessName->isEmpty()) {
            return $translations;
        }

        return array_replace_recursive(
            $translations,
            $loadProjectPage($businessName->replace('.', '/')->toString()),
        );
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     */
    private function withPluginMenuTitles(array $translations): array
    {
        foreach ($this->state->enabledKeys() as $pluginKey) {
            if (! $this->plugins->has($pluginKey)) {
                continue;
            }

            $namespace = $this->plugins->get($pluginKey)->translationNamespace();

            if ($namespace === null) {
                continue;
            }

            $menuTitles = $this->translator->get("{$namespace}::admin.plugins.{$pluginKey}.menu_titles");

            if (is_array($menuTitles)) {
                $translations['plugins'][$pluginKey]['menu_titles'] = $menuTitles;
            }
        }

        return $translations;
    }

    private function routeNamePrefix(): string
    {
        return trim(config()->string(config()->string('plugin.route.name_prefix_config')), '.');
    }
}
