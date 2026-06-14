<?php
namespace Widerrufsbutton\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\Templates\Twig;
use IO\Helper\ResourceContainer;

/**
 * Widerrufsbutton ServiceProvider — plentyShop LTS
 *
 * Injiziert die DSGVO-konforme Widerrufsbelehrung per Embed-Script
 * in den Footer des plentyShop LTS.
 *
 * Pattern: IO.Resources.Import → addScriptTemplate()
 * (siehe Cookbook: Adding Scripts in plentyShop LTS)
 *
 * Phase 2 (später): register() enthält den RouteServiceProvider
 * für das interaktive Widerrufsformular mit Order-Lookup.
 */
class WiderrufsbuttonServiceProvider extends ServiceProvider
{
    const PRIORITY = 0;

    /**
     * Register additional service providers.
     */
    public function register()
    {
        // Phase 2: Interaktives Formular (Controller + Routes)
        // $this->getApplication()->register(WiderrufsbuttonRouteServiceProvider::class);
    }

    /**
     * Boot: Skripte und Styles in den Shop einbinden.
     */
    public function boot(Twig $twig, Dispatcher $dispatcher)
    {
        // ── Embed-Script (Widerrufsbelehrung via SaaS) ────────────────
        $dispatcher->listen('IO.Resources.Import', function (ResourceContainer $container)
        {
            $container->addScriptTemplate('Widerrufsbutton::content.EmbedScript');
        }, self::PRIORITY);

        // ── Inline-Style für den Embed-Container ────────────
        $dispatcher->listen('IO.Resources.Import', function (ResourceContainer $container)
        {
            $container->addStyleTemplate('Widerrufsbutton::content.EmbedStyle');
        }, self::PRIORITY);
    }
}
