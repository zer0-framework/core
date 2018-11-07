<?php

namespace Zer0\Model;

/**
 * Class Helper
 * @package Zer0\Model
 */
class Helper
{

    /**
     * Generates basic onMiss callback for RedisZSet
     *
     * @param string $key E.g. 'user_id'
     * @param \Zer0\Model\Generic $model
     * @param callable $after = null Called
     * @return \Closure
     */
    public static function zSetOnMissCallback($key, $model, $after = null)
    {
        /** @var \Zer0\Model\Expressions\ */
        /**
         * @param $storage
         * @param $where
         */
        return function ($storage, $where) use ($key, $model, $after) {
            if (!isset($where->mongoNotation[$key])) {
                return;
            }
            if (!is_scalar($where->mongoNotation[$key])) {
                return; // IN-queries are not supported yet
            }

            $storage->multi();
            // Use options to force data to be fetched from db
            $objects = $model::whereFieldEq($key, $where->mongoNotation[$key])
                ->options(['storages' => ['SQL' => true]]);
            foreach ($objects as $obj) {
                $storage->create($obj->toArray());
            }
            if ($after !== null) {
                $after([$key => $where->mongoNotation[$key]], $storage);
            }
            $storage->exec();
        };
    }

    /**
     * Generates storages config entry with closures to convert a timestamp to and from different formats
     * (e.g. MySQL datetime value to timestamp for Redis)
     *
     * @param string $field Field name, e.g. 'added'
     * @param  string $format = 'Y-m-d H:i:s' Date format
     * @return array [to => Closure, from => Closure]
     */
    public static function timestampConverters($field, $format = 'Y-m-d H:i:s')
    {
        return [
            'to' => function ($obj) use ($field) {
                return strtotime(isset($obj[$field]) ? $obj[$field] : '');
            },
            'from' => function ($value, &$obj) use ($field, $format) {
                $obj[$field] = date($format, $value);
            },
        ];
    }
}
