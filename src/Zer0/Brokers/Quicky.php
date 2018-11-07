<?php

namespace Zer0\Brokers;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Quicky
 * @package Zer0\Brokers
 */
class Quicky extends Base
{
    /**
     * @param ConfigInterface $config
     * @return \Quicky
     */
    public function instantiate(ConfigInterface $config): \Quicky
    {
        $quicky = new \Quicky();
        //$quicky->load_filter('pre', 'optimize');

        foreach ($config->toArray() as $key => $value) {
            $ref =& $quicky;
            foreach (explode('.', $key) as $split) {
                if (is_object($ref)) {
                    $ref =& $ref->{$split};
                } elseif (is_array($ref)) {
                    $ref =& $ref[$split];
                }
            }
            $ref = $value;
        }

        $quicky->lang_callback = function ($phrase) {
            return _($phrase);
        };

        $quicky->lang_callback_e = function ($expr) {
            return '_(' . $expr . ')';
        };
        return $quicky;
    }
}
