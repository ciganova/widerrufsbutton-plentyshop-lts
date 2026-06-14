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
            
            // Debug: check ALL possible locations for tenant_id
            if (isset($widgetSettings["tenant_id"])) {
                $tid = $widgetSettings["tenant_id"];
                if (is_string($tid)) {
                    $tenantId = $tid;
                } elseif (is_array($tid)) {
                    $tenantId = $tid["mobile"] ?? $tid["desktop"] ?? "";
                }
            }
            
            // Fallback: some PlentyONE versions nest settings under "mobile"/"desktop"
            if (empty($tenantId) && isset($widgetSettings["mobile"]["tenant_id"])) {
                $tenantId = $widgetSettings["mobile"]["tenant_id"];
            }
            if (empty($tenantId) && isset($widgetSettings["desktop"]["tenant_id"])) {
                $tenantId = $widgetSettings["desktop"]["tenant_id"];
            }
            
            // Last resort: scan all array values
            if (empty($tenantId) && is_array($widgetSettings)) {
                foreach ($widgetSettings as $k => $v) {
                    if (is_string($v) && strlen($v) > 30 && strpos($v, '-') !== false) {
                        $tenantId = $v;
                        break;
                    }
                    if (is_array($v)) {
                        foreach ($v as $vk => $vv) {
                            if (is_string($vv) && strlen($vv) > 30 && strpos($vv, '-') !== false) {
                                $tenantId = $vv;
                                break 2;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $tenantId = "";
        }

        return [
            "tenant_id" => $tenantId,
            "embed_url" => "https://widerruf.paketwo.de",
        ];
    }
}
