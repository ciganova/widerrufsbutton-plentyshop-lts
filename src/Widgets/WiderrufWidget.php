<?php
namespace Widerrufsbutton\Widgets;

use Ceres\Widgets\Helper\BaseWidget;

class WiderrufWidget extends BaseWidget
{
    protected $template = "Widerrufsbutton::Widgets.EmbedScript";

    protected function getTemplateData($widgetSettings, $isPreview)
    {
        $tid = $widgetSettings["tenant_id"] ?? "";
        // tenant_id might be stored as string directly or as ["mobile" => "..."] array
        if (is_array($tid)) {
            $tid = $tid["mobile"] ?? "";
        }
        $tenantId = is_string($tid) ? $tid : "";

        return [
            "tenant_id" => $tenantId,
            "embed_url" => "https://widerruf.paketwo.de",
        ];
    }
}
