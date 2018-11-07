<?php

namespace Zer0\Model;

use Zer0\Model\Exceptions\BundleException;
use Zer0\Model\Generic as Model;
use Zer0\Model\Result\AbstractResult;
use Zer0\Model\Result\ResultMap;

/**
 * Class Iterator
 * @package Zer0\Model
 */
class Iterator implements \ArrayAccess, \Iterator, \Countable
{
    /** @var \Zer0\Model\Generic[] Array of models */
    protected $items;

    /** @var string $model class name */
    protected $class;

    /** @var \Zer0\Model\Generic */
    protected $model;

    /** @var array Array of keys of $items. These will be numeric indexes. */
    protected $keys;

    /** @var integer Iterator pointer position */
    protected $pos = 0;

    /** @var boolean Flag indicating whether to yield objects as arrays */
    protected $asArray = false;

    /** @var array Array of bundled exceptions \Zer0\Model\Exceptions\Bundle */
    protected $exceptionsBundle;

    /**
     * Constructor
     *
     * @param Model $model Associated model on which this iterator operates
     * @param AbstractResult $result
     */
    public function __construct($model, AbstractResult $result)
    {
        $this->keys = array_keys($result->objects);
        if ($result instanceof ResultMap) {
            $this->keys = array_map('strval', $this->keys);
        }

        $this->model = $model;
        $this->items = $result->objects;
        $this->class = get_class($model);
    }

    /**
     * Retrieve exceptions
     *
     * @return Exceptions\BundleException,
     * @return false|\Exception
     */
    public function exceptions()
    {
        if ($this->exceptionsBundle) {
            $e = (new BundleException)->bundle($this->exceptionsBundle);
            $this->exceptionsBundle = null;
            return $e;
        }
        return false;
    }


    /**
     * Set the value of a virtual field using a user-defined callback. A virtual field is a field not
     * defined in the current model/datastore but instead given a value by the specified callback.
     *
     * withVirtualField() - Return existing virtual field/callback list
     * withVirtualField(field, callback) - Define a single virtual field+callback
     * withVirtualField([field => callback, ...]) - Define multiple virtual fields/callbacks
     *
     * If a callback is not supplied, the model's static::defaultWith callback will be used instead.
     * Callback will be invoked with ($this, $fieldName) and should set $this->$fieldName to some value.
     *
     * @param string|array $arg Field name or array of field => callback
     * @param callable $cb = null Callback when specifying single field/callback pair
     * @return Generic $this
     */
    public function withVirtualFields($arg, $cb = null)
    {
        if (!func_num_args()) {
            return $this->model->withVirtualFields();
        }

        // $arg is an array of [fieldName => callback]
        if (is_array($arg)) {
            foreach ($arg as $name => $cb) {
                $this->withVirtualFields($name, $cb);
            }
            return $this;
        }

        // Single field/callback pair ($arg is field name)
        $this->model->withVirtualFields($arg, $cb, $this);

        return $this;
    }

    /**
     * LEFT JOIN command
     * @param  string $joined Model name to join
     * @param  string $on Condition, e.g. 'User.id = Online.id'
     * @param  array $fields = [] Array of fields, e.g. ['categoryName' => 'Category.name']
     * @param  array $additionalFields = [] additional fields to join for each
     *           joined item
     * @param  boolean $grouped = false Grouped?
     * @return Iterator $this
     * @throws Exceptions\ModelNotFoundException
     * @throws Exceptions\UnsupportedActionException
     */
    public function leftJoin($joined, $on = null, $fields = [], $additionalFields = [], $grouped = false)
    {
        $this->model->leftJoin($joined, $on, $fields, $additionalFields, $grouped, $this);
        return $this;
    }

    /**
     * LEFT GROUPED JOIN command
     * @param  string $joined Model name to join
     * @param  string $on Condition, e.g. 'User.id = Online.id'
     * @param  array $fields = [] Array of fields, e.g. ['categoryName' => 'Category.name']
     * @param  array $additionalFields = [] additional fields to join for each
     *           joined item
     * @return Iterator $this
     * @throws Exceptions\ModelNotFoundException
     * @throws Exceptions\UnsupportedActionException
     */
    public function leftGroupedJoin($joined, $on = null, $fields = [], $additionalFields = [])
    {
        $this->model->leftJoin($joined, $on, $fields, $additionalFields, true, $this);
        return $this;
    }

    /**
     * Set joinedData field/value pairs in each item (model).
     *
     * @example
     *     Let: $data = ['1' => 'My name is Antonio', '2' => 'My name is Vasily'];
     *     $items->addDataFromJoin('user_id', $data, 'description')
     *     Now, $joinedData['description'] for each user model will contain the appropriate description
     *     determined by user id.
     *
     * @example $items->addDataFromJoin('category_id',
     *                                  Categories::whereFieldEq('id', $items->values('category_id'))
     *                                      ->extractJoinData('description', 'id'),
     *                                  'categoryDescription');
     *
     * @param  string $foreignKeyField Model attribute name whose value is used as a foreign key in $data
     *                                    referencing joined Model's data
     * @param  array $data Key=>value array containing the data to join for each item identifier
     * @param  string $localFieldName Field name to set/update in Model->joinedData
     *
     * @return Iterator $this
     */
    public function addDataFromJoin($foreignKeyField, $data, $localFieldName)
    {
        /** @var \Zer0\Model\Generic $item */
        foreach ($this as $item) {
            if (!isset($item[$foreignKeyField])) {
                continue;
            }
            $k = $item[$foreignKeyField];
            if (isset($data[$k])) {
                $item->assignJoinedField($localFieldName, $data[$k]);
            }
        }
        return $this;
    }

    /**
     * Returns a key/value array with data from items. Used to
     * join data to another model. The specified key is the field used as a foreign key in
     * the joining model, while value is the field value from this model that we will make
     * available to the other model.
     *
     * @example Let: $this[0]->id = 1, $this[1]->id = 2; $this[0]->age = 30, $this[1]->age = 18:
     *          extractDataForJoin('age', 'id') yields [ 1 => 30, 2 => 18 ]
     *
     * @example $items->addDataFromJoin('category_id',
     *                                  Categories::whereFieldEq('id', $items->values('category_id'))
     *                                      ->extractJoinData('description', 'id'),
     *                                  'categoryDescription');
     *
     * @param  string $valueField Field name
     * @param  string $keyField Keyfield name
     * @return array
     */
    public function extractDataForJoin($valueField = null, $keyField = 'id')
    {
        $arr = [];
        foreach ($this->keys as $i) {
            $item = $this[$i];
            $arr[$item[$keyField]] = $valueField === null ? $item : $item[$valueField];
        }
        return $arr;
    }

    /**
     * foreach()
     * @param  callable $cb
     * @return self
     */
    public function each($cb)
    {
        foreach ($this as $o) {
            $cb($o);
        }

        return $this;
    }

    /**
     * @param callable $mapFunction
     * @return array
     */
    public function map(callable $mapFunction)
    {
        $result = [];

        foreach ($this as $entity) {
            $result[] = $mapFunction($entity);
        }

        return $result;
    }

    /**
     * First
     * @return $this
     */
    public function first()
    {
        return $this[0];
    }

    /**
     * Count number of objects in iterator
     *
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * __call
     * @param string $method
     * @param array $args
     * @return mixed|null
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        try {
            if ($method === 'where') {
                return $this->_where(...$args);
            }
            if ($method === 'clone') {
                return $this->_clone(...$args);
            }
            if (strncmp($method, 'get', 3) === 0) {
                return $this->getProperty(lcfirst(substr($method, 3)));
            }
            if ((strncmp($method, 'set', 3) === 0)
                || (strncmp($method, 'is', 2) === 0)
                || (strncmp($method, 'incr', 4) === 0)
                || (strncmp($method, 'unset', 5) === 0)
                || (strncmp($method, 'touch', 5) === 0)
                || (strncmp($method, 'microtouch', 10) === 0)
            ) {
                return $this->model->$method(...$args);
            }
        } catch (\Exception $e) {
            if ($this->exceptionsBundle === null) {
                throw $e;
            } else {
                $this->exceptionsBundle[] = $e;
            }
        }
        throw new \Exception('Call to undefined method ' . get_class($this) . '->' . $method);
    }

    /**
     * Get property
     * @param  string $field
     * @return mixed
     */
    public function getProperty($field)
    {
        $c = count($this->items);
        if ($c === 0) {
            return null;
        }
        if ($c === 1) {
            return $this[0][$field];
        }
        return $this->values($field);
    }

    /**
     * Returns an array containing all values for the given field name across all items
     *
     * @param  string $field Field name
     * @return array
     */
    public function values($field)
    {
        $vals = [];
        foreach ($this as $item) {
            if (($v = $item[$field]) !== null) {
                $vals[] = $v;
            }
        }
        return array_unique($vals);
    }

    /**
     * Returns Model
     * @return Model
     */
    public function model()
    {
        return $this->model;
    }

    /**
     * Current object
     * @return object Model
     */
    public function current()
    {
        return $this[$this->pos];
    }

    /**
     * Current object
     * @return void Model
     */
    public function next()
    {
        ++$this->pos;
    }

    /**
     * Current key
     * @return integer
     */
    public function key()
    {
        return $this->pos;
    }

    /**
     * Rewind the pointer
     * @return  void
     */
    public function rewind()
    {
        $this->pos = 0;
    }

    /**
     * Returns $objects by reference
     * @return Model[]
     */
    public function &objectsRef()
    {
        return $this->items;
    }

    /**
     * Returns $keys by reference
     * @return string[]
     */
    public function &keysRef()
    {
        return $this->keys;
    }

    /**
     * @param bool $bool
     * @return $this
     */
    /**
     * @param bool $bool
     * @return $this
     */
    /**
     * @param bool $bool
     * @return $this
     */
    public function asArray($bool = true)
    {
        $this->asArray = (bool)$bool;
        return $this;
    }

    /**
     * @param null $fields
     * @return array|Generic[]
     */
    /**
     * @param null $fields
     * @return array|Generic[]
     */
    /**
     * @param null $fields
     * @return array|Generic[]
     */
    public function toArray($fields = null)
    {
        if ($fields === true) {
            return $this->items;
        }
        $arr = [];
        foreach ($this as $obj) {
            $arr[] = $obj->toArray($fields);
        }
        return $arr;
    }

    /**
     * valid()
     * @return boolean
     */
    public function valid()
    {
        return isset($this->keys[$this->pos]);
    }

    /**
     * Returns an object
     *
     * @param int|string $offset
     * @return array|Model|null
     */
    public function offsetGet($offset)
    {
        if (is_int($offset)) {
            if (!isset($this->keys[$offset])) {
                return null;
            }
            $offset = $this->keys[$offset];
        }

        if (!isset($this->items[$offset])) {
            return null;
        }

        $el = &$this->items[$offset];
        if (!$el instanceof $this->model) {
            if ($this->asArray) {
                return $el;
            }

            return $el = new $this->class(null, $el);
        } elseif ($this->asArray) {
            return $el->toArray();
        }

        return $el;
    }

    /**
     * Checks if offset exists
     *
     * @param  int|string $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        if (is_int($offset)) {
            if (!isset($this->keys[$offset])) {
                return false;
            }

            $offset = $this->keys[$offset];
        }

        if (!isset($this->items[$offset])) {
            return false;
        }

        return true;
    }

    /**
     * offsetSet()
     * @param  int|string $key Offset
     * @param  Model $value Value
     * @throws \Exception
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->keys[] = $key;
        if (is_array($value)) {
            $this->items[$key] = $value;
        } elseif ($value instanceof Model) {
            $this->items[$key] = $value;
        } else {
            throw new \Exception('Unknown type of value');
        }
    }

    /**
     * offsetUnset()
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
    }

    /**
     * Free the iterator
     */
    public function free()
    {
    }
}
