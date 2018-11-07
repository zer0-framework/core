<?php

namespace Zer0\Model\Traits;

/**
 * Trait ConfigTrait
 * @package Zer0\Model\Traits
 */
trait ConfigTrait
{
    /** @var string Model class */
    public $modelClass;

    /** @var string Model name */
    public $modelName;

    /** @var string Delimiter for serializing data */
    protected $serDelimiter = ':';

    /**
     * Constructor populates properties from the supplied config
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        foreach ($config as $k => $v) {
            if (property_exists($this, $k)) {
                $this->{$k} = $v;
            }
        }
        $this->init();
    }

    /**
     * Returns a string built using the storages/indexes config definition, which specifies which values of the supplied
     * input array ($data) to serialize.
     * This is used mainly to serialize string values for Redis. This method expects the field list
     * to be defined in $this->{$confProp} and to have dollar signs ('$') prepended to field names.
     *
     * @example Let $this->fieldList = ['$user_id', '$sex];
     *          $this->serializeConfFields('fieldList', ['user_id' => '123', 'sex' => 'male', 'age' => '25'])
     *          returns '123:male'. Age was ignored, since it was not specified in the field list.
     *
     * @param  string $confProp Property name ($this->{$property}) containing an array of fields
     *                          to extract from $data. If property is array with 'to' field with
     *                          closure value, this will be used to transform $data.
     * @param  array $data Array of fields and values from which to retrieve values specified in $this->$propName.
     * @param  boolean $falseIfNotFound If something is missing in $obj, return false
     * @param  array &$used = null Used fields
     *
     * @return string|null
     */
    public function serializeConfFields($confProp, $data, $falseIfNotFound = false, &$used = null)
    {
        if (!isset($this->{$confProp})) {
            return null;
        }

        // If a transformation closure has been supplied, apply it and return
        if (isset($this->{$confProp}['to']) && $this->{$confProp}['to'] instanceof \Closure) {
            return $this->{$confProp}['to']($data);
        }

        $fields = $this->{$confProp};
        foreach ($fields as &$field) {
            // Config field list has fields with a dollar sign prepended. Remove before $data lookup.
            // @todo Explain why dollar signs? And when there won't be a dollar sign?
            if ($field[0] === '$') {
                $field = substr($field, 1);
                if (!isset($data[$field]) || is_array($data[$field])) {
                    if ($falseIfNotFound) {
                        return false;
                    }
                    $field = '';
                } else {
                    if ($used !== null) {
                        $used[$field] = $data[$field];
                    }
                    $field = $data[$field];
                }
            }
            $field = strtr($field, [$this->serDelimiter => '\\' . $this->serDelimiter, '\\' => '\\\\']);
        }
        return implode($this->serDelimiter, $fields);
    }

    /**
     * Returns value returned by the callback defined in a storage config definition.
     *
     * @param  string $confProp Property name to read
     * @return mixed|null
     */
    public function callConfCallback($confProp)
    {
        if (!isset($this->{$confProp})) {
            return null;
        }
        if ($this->{$confProp} instanceof \Closure) {
            $func = $this->{$confProp};
            return $func();
        }
        return $this->{$confProp};
    }

    /**
     * Deserialize a string serialized by serializeConfigFields()
     *
     * @param  string $confProp Property name to read config from
     * @param  string $str Serialized string
     * @param  array &$data Array to populate with data from serialized string
     */
    public function deserializeConfFields($confProp, $str, &$data)
    {
        // If a transformation closure has been supplied, apply it and return
        if (isset($this->{$confProp}['to']) && $this->{$confProp}['to'] instanceof \Closure) {
            $this->{$confProp}['from']($str, $data);
            return;
        }

        // Split input string at serialization delimiter (e.g. ':')
        $entities = explode("\x00", strtr($str, [
            '\\\\' => '\\',
            '\\' . $this->serDelimiter => $this->serDelimiter,
            $this->serDelimiter => "\x00"
        ]));

        // Populate output array
        $i = 0;
        foreach ($this->{$confProp} as $field) {
            // Omit dollar signs in output array keys
            if ($field[0] === '$') {
                $field = substr($field, 1);
                $data[$field] = isset($entities[$i]) ? $entities[$i] : null;
            }
            ++$i;
        }
    }
}
