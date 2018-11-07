<?php

namespace Zer0\Model\Subtypes;

/**
 * Class Field
 * @package Zer0\Model\Subtypes
 */
class Field
{
    protected $name;

    /**
     * Constructor
     * @param string $name Field name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Parses field name to model/index/field parts
     *
     * @example 'User~online.id' =>  ['model' => 'User', 'index' => 'online', 'name' => 'id']
     *
     * @return array ['model'? => ..., 'index'? => ..., 'name' => ...]
     */
    public function parseName()
    {
        $split = explode('.', $this->name, 2);
        if (count($split) === 2) {
            $nsSplit = explode('~', $split[0], 2);
            if (count($nsSplit) === 2) {
                return [
                    'model' => $nsSplit[0],
                    'index' => $nsSplit[1],
                    'name' => $split[1],
                ];
            }
            return [
                'model' => $split[0],
                'name' => $split[1],
            ];
        }
        return [
            'name' => $split[0],
        ];
    }

    /**
     * __toString
     * @return string Field name
     */
    public function __toString()
    {
        return $this->name;
    }
}
