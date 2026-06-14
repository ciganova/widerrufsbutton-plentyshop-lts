<?php
namespace Widerrufsbutton\Widgets;

use Ceres\Widgets\Helper\BaseWidget;

class WiderrufWidget extends BaseWidget
{
    protected $template = "Widerrufsbutton::Widgets.EmbedScript";

    protected function getTemplateData($widgetSettings, $isPreview)
    {
        $tenantId = $widgetSettings["tenant_id"]["mobile"] ?? "";

        return [
            "tenant_id" => $tenantId,
            "embed_url" => "https://widerruf.paketwo.de",
        ];
    }
}
