<?php
namespace Widerrufsbutton\Providers;

use Plenty\Plugin\ServiceProvider;

/**
 * Widerrufsbutton ServiceProvider — ShopBuilder Widget
 *
 * Registriert das Widerruf-Widget für den ShopBuilder.
 * Kein Footer-Inject — der Händler platziert es selbst.
 */
class WiderrufsbuttonServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Widget wird automatisch über contentWidgets.json geladen
    }
}
