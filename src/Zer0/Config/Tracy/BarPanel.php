<?php

namespace Zer0\Config\Tracy;

use Zer0\Config\Config;

/**
 * Class BarPanel
 * @package Zer0\Config\Tracy
 */
class BarPanel implements \Tracy\IBarPanel
{
    /**
     * Base64 icon for Tracy panel.
     * @var string
     * @see http://www.flaticon.com/free-icon/database_51319
     * @author Freepik.com
     * @license http://file000.flaticon.com/downloads/license/license.pdf
     */
    public $icon = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/PjxzdmcgaGVpZ2h0PSIzMnB4IiB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCAzMiAzMiIgd2lkdGg9IjMycHgiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6c2tldGNoPSJodHRwOi8vd3d3LmJvaGVtaWFuY29kaW5nLmNvbS9za2V0Y2gvbnMiIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIj48dGl0bGUvPjxkZXNjLz48ZGVmcy8+PGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIiBpZD0iUGFnZS0xIiBzdHJva2U9Im5vbmUiIHN0cm9rZS13aWR0aD0iMSI+PGcgZmlsbD0iIzE1N0VGQiIgaWQ9Imljb24tMzgtZmlsZS15bWwiPjxwYXRoIGQ9Ik04LjAwNjg0ODM0LDEwIEM2LjM0NjIxMTg1LDEwIDUsMTEuMzQyMjY0MyA1LDEyLjk5ODc4NTYgTDUsMjAuMDAxMjE0NCBDNSwyMS42NTczOTc5IDYuMzM1OTkxNTUsMjMgOC4wMDY4NDgzNCwyMyBMMjQuOTkzMTUxNywyMyBDMjYuNjUzNzg4MSwyMyAyOCwyMS42NTc3MzU3IDI4LDIwLjAwMTIxNDQgTDI4LDEyLjk5ODc4NTYgQzI4LDExLjM0MjYwMjEgMjYuNjY0MDA4NSwxMCAyNC45OTMxNTE3LDEwIEw4LjAwNjg0ODM0LDEwIEw4LjAwNjg0ODM0LDEwIFogTTExLDE3IEwxMSwyMCBMMTAsMjAgTDEwLDE3IEw4LDE0IEw4LDEzIEw5LDEzIEw5LDE0IEwxMC41LDE2LjI1IEwxMiwxNCBMMTIsMTMgTDEzLDEzIEwxMywxNCBMMTEsMTcgTDExLDE3IFogTTE2LjUsMTYgTDE1LDEzIEwxNC41LDEzIEwxNCwxMyBMMTQsMjAgTDE1LDIwIEwxNSwxNSBMMTYsMTcgTDE2LjUsMTcgTDE3LDE3IEwxOCwxNSBMMTgsMjAgTDE5LDIwIEwxOSwxMyBMMTguNSwxMyBMMTgsMTMgTDE2LjUsMTYgTDE2LjUsMTYgWiBNMjUsMTkgTDI1LDIwIEwyMCwyMCBMMjAsMTMgTDIxLDEzIEwyMSwxOSBMMjUsMTkgTDI1LDE5IFoiIGlkPSJmaWxlLXltbCIvPjwvZz48L2c+PC9zdmc+';

    /**
     * Title
     * @var string
     */
    public $title = 'YAML';

    /**
     * Title HTML attributes
     * @var string
     */
    public $title_attributes = 'style="font-size:1.6em"';

    /**
     * Time table cell HTML attributes
     * @var string
     */
    public $time_attributes = 'style="font-weight:bold;color:#333;font-family:Courier New;font-size:1.1em"';

    /**
     * Query table cell HTML attributes
     * @var string
     */
    public $query_attributes = '';


    /**
     * @var Config
     */
    protected $config;

    /**
     * BarPanel constructor.
     * @param Config $config
     */
    /**
     * BarPanel constructor.
     * @param Config $config
     */
    /**
     * BarPanel constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Renders HTML code for custom tab.
     * @return string
     */
    public function getTab(): ?string
    {
        return '<img src="' . $this->icon . '" height=24 width=24 alt="YAML" /> ';
    }

    /**
     * Renders HTML code for custom panel.
     * @return string
     */
    public function getPanel(): ?string
    {
        $files = $this->config->getLoadedFiles();
        $html = '<h1 ' . $this->title_attributes . '>' . $this->title . '</h1>';
        $html .= '<div class="tracy-inner tracy-InfoPanel">';
        if (count($files) > 0) {
            $html .= '<table style="width:400px;">';
            $html .= '<tr>';
            $html .= '<th>File</td>';
            $html .= '</tr>';
            foreach ($files as $file) {
                $html .= '<tr>';
                $html .= '<td ' . $this->query_attributes . '>' . htmlspecialchars($file) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        } else {
            $html .= '<p style="font-size:1.2em;font-weigt:bold;padding:10px">No YAML configs were parsed!</p>';
        }
        $html .= '</div>';

        return $html;
    }
}
