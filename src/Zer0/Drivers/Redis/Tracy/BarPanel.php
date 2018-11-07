<?php

namespace Zer0\Drivers\Redis\Tracy;

use Zer0\Drivers\Redis\RedisDebug;

/**
 * Class BarPanel
 * @package Zer0\Drivers\Redis\Tracy
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
    public $icon = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACi0lEQVQ4T6VTz0uUURQ9973vfePvMdTRxLFRR5MmaBdBG6GNi8JFMBAtItwY9k+0jYqEKMJKMCkwohbRQqggt6FtBHOhkibND0dNR53vx73xfUMLYzbR273Lvefec889hP98VKleAFo603dehK6AEBfQDHv6RWphofB3/hGAhVSqTVveNfg8DGN6m0dGoRqiKDx5BCefP1Tg16L0eP/84mcCJACjTwMDVtvWz0ERuWE64oPVfScV7xfhrq/B7k4iemkIWy+nsDs/h7zrwgCo19hQynpKnvWQvqSS3+os3acbj6H73QxUXX04ZWFiHLmxu4DSOPA8rJUYIozFYhFno7U4bhvkHW+Vnnd1/Wgyqj1ma9TXVMPu6ACXSqg6dRqRZC9MvBO5e7fh5bI4YIFRhILDyDgc/BfpTU/PnX3fvymkItWKELMVmgzBjrWi8XIaAZ3C5ARKLGHRpsvwBajVdFCn5TF97O/OttqmZcdjZFxG0RNoAppNAKTAImF82xUQUQgeMxoCwYbjrtNkZ+K7sXQ8KIhFFHwW5IJOHoPDPQM2Aa2RMuCeB2RcH7ueBDrM0myqb2bP9S8UXFFBcoNVplGnCQU3mIbC2KbrI+MIHBZUKQoBIyJz9CrZk2k3KtZgFDYdRs7lkG9EAU1Gw4cg75R5R0NwDVsB2RIj7/ESTXV1vQVoyAp42yrkfshAzvGx4wmCSwvirbZCiYGs4+OXJxDmXSi6FV7iVCJxDqJGGZzWStlBpxZbo0YhXNyWW5btkAUMWdZEDwzwLL28vHPklKeTyRaHeZgFIwo4EWgeqBCMD8gHBYxZKyvv04D/xxMVzTQNaDeRvCjkXwewTSL3r66ufq1kvIoA/+Lw392sKiafC5ZQAAAAAElFTkSuQmCC';

    /**
     * Title
     * @var string
     */
    public $title = 'Redis';

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
     * @var RedisDebug
     */
    protected $redis;

    /**
     * BarPanel constructor.
     * @param RedisDebug $redis
     */
    /**
     * BarPanel constructor.
     * @param RedisDebug $redis
     */
    /**
     * BarPanel constructor.
     * @param RedisDebug $redis
     */
    public function __construct(RedisDebug $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Renders HTML code for custom tab.
     * @return string
     */
    public function getTab()
    {
        $queries = $this->redis->getQueryLog();
        if (count($queries) === 0) {
            return '';
        }
        $html = '<img src="' . $this->icon . '" alt="Redis" /> ';
        $count = count($queries);
        if ($count == 1) {
            $html .= '1 query';
        } else {
            $html .= $count . ' queries';
        }
        $html .= ' / ' . round(array_sum(array_column($queries, 'time')) * 1000, 1) . ' ms';
        return $html;
    }

    /**
     * Renders HTML code for custom panel.
     * @return string
     */
    public function getPanel()
    {
        $queries = $this->redis->getQueryLog();
        if (count($queries) === 0) {
            return '';
        }
        $html = '<h1 ' . $this->title_attributes . '>' . $this->title . '</h1>';
        $html .= '<div class="tracy-inner tracy-InfoPanel">';
        $html .= '<table style="width:400px;">';
        $html .= '<tr>';
        $html .= '<th>Time(ms)</td>';
        $html .= '<th>Statement</td>';
        $html .= '</tr>';
        foreach ($queries as $query) {
            $html .= '<tr>';
            $html .= '<td><span ' . $this->time_attributes . '>' . round($query['time'] * 1000, 3) . '</span></td>';
            $html .= '<td ' . $this->query_attributes . '>' . htmlspecialchars($query['query']) . '</td>';
            //$html .= '<td>' . nl2br($query['trace']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }
}
