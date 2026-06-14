<?php
namespace Widerrufsbutton\Widgets;

use Plenty\Modules\ShopBuilder\Contracts\Widget;
use Plenty\Modules\ShopBuilder\Models\WidgetSettings;

/**
 * WiderrufWidget — ShopBuilder-Widget
 *
 * Rendert die DSGVO-konforme Widerrufsbelehrung per Embed-Script
 * von widerruf.paketwo.de. Konfigurierbar per Tenant-ID.
 */
class WiderrufWidget extends Widget
{
    /**
     * Render the widget output.
     */
    public function getPreview(): string
    {
        return $this->render('EmbedScript');
    }

    public function getData(): array
    {
        return [
            'tenant_id' => $this->widgetSettings->getSetting('tenant_id', ''),
            'embed_url' => 'https://widerruf.paketwo.de',
        ];
    }
}
