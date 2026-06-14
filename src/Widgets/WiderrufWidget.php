<?php
namespace Widerrufsbutton\Widgets;

use Ceres\Widgets\Helper\BaseWidget;

class WiderrufWidget extends BaseWidget
{
    protected $template = "Widerrufsbutton::Widgets.EmbedScript";

    protected function getTemplateData($widgetSettings, $isPreview)
    {
        try {
            $tenantId = "";
            
            if (isset($widgetSettings["tenant_id"])) {
                $tid = $widgetSettings["tenant_id"];
                if (is_string($tid)) {
                    $tenantId = $tid;
                } elseif (is_array($tid)) {
                    $tenantId = $tid["mobile"] ?? $tid["desktop"] ?? "";
                }
            }
            if (empty($tenantId) && isset($widgetSettings["mobile"]["tenant_id"])) {
                $tenantId = $widgetSettings["mobile"]["tenant_id"];
            }
            if (empty($tenantId) && isset($widgetSettings["desktop"]["tenant_id"])) {
                $tenantId = $widgetSettings["desktop"]["tenant_id"];
            }
            if (empty($tenantId) && is_array($widgetSettings)) {
                foreach ($widgetSettings as $v) {
                    if (is_string($v) && strlen($v) > 30 && strpos($v, '-') !== false) {
                        $tenantId = $v; break;
                    }
                }
            }
        } catch (\Exception $e) {
            $tenantId = "";
        }

        return [
            "tenant_id" => $tenantId,
            "embed_url" => "https://widerruf.paketwo.de",
            "is_preview" => $isPreview,  // true = ShopBuilder, false = Live-Seite
        ];
    }
}
