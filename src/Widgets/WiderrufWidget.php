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
                if (is_string($tid) && strlen($tid) > 10) {
                    $tenantId = $tid;
                } elseif (is_array($tid) && isset($tid["mobile"])) {
                    $tenantId = $tid["mobile"];
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
