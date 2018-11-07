<?php

namespace Zer0\Model;

use PHPDaemon\Core\CallbackWrapper;
use PHPDaemon\Core\ClassFinder;
use PHPDaemon\Core\ComplexJob;
use PHPDaemon\Core\Daemon;
use Zer0\Model\Exceptions\UnknownFieldException;
use Zer0\Model\Exceptions\ValidationErrorException;
use Zer0\Model\Result\ResultList;
use Zer0\Model\Storages\DelayedRedis;

/**
 * Class Generic
 * A base model class which other models should extend.
 *
 * @method static Generic where($where, $values = null)
 * @method static Generic firstWhere($where, $values = null)
 * @method static Generic mapWhere($where, $values = null)
 * @method static Generic whereFieldEq($field, $values)
 * @package Core
 */
abstract class Generic implements \ArrayAccess, \IteratorAggregate, \Countable
{
    use Validator;

    /**
     * Namespace in $storagesConf for RedisDelayed instances
     * @var string
     */
    const DELAYEDREDIS_NS = 'delayed:';

    protected static $primaryKey;
    /**
     * Storages config. This defines DB tables, hash keys, and other storage-related settings.
     * [storageName => arrayOfSettings, ...]
     * The array is passed to \Zer0\Model\Storages\Generic constructor (\Zer0\Model\Traits\ConfigTrait)
     * @TODO: Need a list of available settings. Here or in storage classes.
     *
     * @example 'RedisHash' => [
     *              'prefix' => 'user:'
     *          ],
     *          'SQL' => [
     *              'table' => 'users',
     *          ]
     *
     * @example ['RedisZSet' => [
     *              'prefix' => 'friends:',
     *              'key' => ['$user_id'], // Note the "$" before field names
     *              'score' => ['$added'],
     *              'value' => ['$friend_id'],
     *           ],
     *           'SQL' => [
     *                'table' => 'friends_indiv',
     *                'replace' => true,
     *           ]]
     * @var array
     */
    protected static $storagesConf = [];
    /**
     * @var \Zer0\Model\Storages\Generic[] Array of storage objects created when initStorages() is called.
     *                                     Needs to be declared in child classes so that child-specific var
     *                                     is used instead of this one.
     */
    protected static $storages = [];
    /**
     * Indexes config
     * [indexName => arrayOfSettings, ...]
     * Array of settings will be passed to \Zer0\Model\Storages\Generic constructor
     * @example 'online' => [
     *       'type' => 'Sorted', // default is 'Generic', set by indexes()
     *       'score' => ['$last_login'],
     *       'value' => ['$id'],
     *   ],
     * @var array
     */
    protected static $indexesConf = [];
    /** @var \Zer0\Model\Indexes\AbstractIndex[] */
    protected static $indexes = [];
    /**
     * Default field/callback pairs for withVirtualFields()
     * ['fieldName' => callback, ...]
     *
     * @var array
     */
    protected static $defaultVFieldCbs = [];
    /**
     * Used for lazy static::baseInit() calls
     * [$className => true, ...]
     * @var array
     */
    protected static $baseInited = [];
    /**
     * If true, object is in read-only mode
     * @var boolean
     */
    protected $publicReadonly = false;
    /** @var \Zer0\Model\Storages\Generic[] Array of storages whose configs have been overriden via ->options() */
    protected $customStorages;
    /**
     * Array representation of a single model object
     * Null when object is not loaded.
     * False when has failed to load.
     * ['field' => 'value']
     * @var array
     */
    protected $data;
    /**
     * Joined fields and their values (data from other models).
     *
     * @var array ['fieldName' => mixed $value]
     */
    protected $joinedData = [];
    /**
     * INNER JOINs
     *
     * @var array [[$joined, $on, $fields], ...]
     */
    protected $innerJoins = [];
    /**
     * Update plan specifying transformations to model data, used to update storages.
     * https://docs.mongodb.org/manual/reference/operator/update/#id1
     *
     * @example $updatePlan = [
     *      '$inc' => [],
     *      '$set' => [],
     *      '$unset' => [],
     * ]
     *
     * @var array Update plan in mongo notation
     */
    protected $updatePlan = [];
    /** @var \Zer0\Model\Expressions\Conditions\Generic WHERE clause representation */
    protected $where;
    /** @var \Zer0\Model\Exceptions\BundleException[] Array of bundled exceptions */
    protected $exceptionsBundle;
    /** @var boolean Is this a new object? */
    protected $new;
    /**
     * Upsert mode?
     * Update-or-insert (Upsert) mode, equivalent to SQL REPLACE statement. When this is true,
     * data will be updated if it exists. If not, it will be added.
     *
     * @var boolean
     */
    protected $upsertMode = false;
    /**
     * Multi mode?
     *
     * @var boolean
     */
    protected $multiMode = false;
    /**
     * ORDER BY clause
     * See orderBy() and rawOrderBy()
     *
     * @var string|null
     */
    protected $orderBy;
    /**
     * GROUP BY clause
     * See groupBy() and rawGroupBy()
     *
     * @var string|null
     */
    protected $groupBy;
    /**
     * Current offset (for multiMode)
     *
     * @var integer
     */
    protected $offset = 0;
    /**
     * Fields to fetch from data storage
     *
     * @var array
     */
    protected $fields = [];
    /**
     * Add SQL_CALC_FOUND_ROWS and alternatives
     *
     * @var boolean
     */
    protected $calcTotal = false;
    /**
     * Add SQL_NO_CACHE to queries
     *
     * @var boolean
     */
    protected $noQueryCache = true;
    /**
     * Fieldname/callback pairs for virtual fields (properties whose values are set via callbacks).
     * Main use case is linking Models (simulating joins), etc.
     * See withVirtualFields() method.
     *
     * @var array [$field => $callback, ...] from ->withVirtualFields($field, $callback)
     */
    protected $vFieldCallbacks = [];
    /**
     * Number of objects affected by last operation (save(), delete())
     * @var integer
     */
    protected $affected = 0;

    /**
     * Array of options. See options()
     * [$optionName => $optionValue, ...]
     *
     * @example  $options = [
     *  'storages' => ['SQL' => true],  // This forces only SQL storage to be used.
     *  'indexes ' => ['online'],       // Only use online index
     * ];
     *
     * @var array
     */
    protected $options = [];

    /**
     * Is freed?
     * @var bool
     */
    protected $freed = false;

    /**
     * Constructor
     *
     * @param array|string|null $where WHERE clause
     * @param array|null|true $data An array of field => value, or true when creating a new
     *  object (see create()).
     */
    public function __construct($where = null, $data = null)
    {
        // Call baseInit() if not yet called
        if (!isset(self::$baseInited[static::class])) {
            self::$baseInited[static::class] = true;
            static::baseInit();
        }

        if ($data === true) {
            $this->new = true;
            $this->data = [];
        } elseif ($data !== null) {
            $this->data = $data;
            $this->new = false;
            $this->onLoad();
        }

        if ($where !== null) {
            $this->where = $where;
        } else {
            $this->loadDefaultWhere();
        }

    }

    /**
     * Called when the environment starts
     *
     * @return void
     */
    protected static function baseInit()
    {
        static::initStorages();
        static::initIndexes();
    }

    /**
     * Initialize $storages, array of \Zer0\Model\Storages\Generic objects, using the config values
     * specified in $storagesConf.
     *
     * @return void
     */
    protected static function initStorages()
    {
        $modelName = ClassFinder::getClassBasename(static::class);

        static::$storages = [];

        foreach (static::$storagesConf as $name => &$storageConf) {
            if (!isset($storageConf['type'])) {
                $storageConf['type'] = $name;
            }
            if (!isset($storageConf['primaryKey'])) {
                $storageConf['primaryKey'] = static::$primaryKey;
            }
            $storageConf['modelClass'] = static::class;
            $storageConf['modelName'] = $modelName;

            $class = Storages\Generic::class;
            $class = substr($class, 0, strrpos($class, '\\'))
                . '\\' . $storageConf['type'];
            static::$storages[$name] = new $class($storageConf);
        }
    }

    /**
     * @param string $storage
     * @return mixed|null
     */
    public static function storageConf(string $storage)
    {
        return static::$storagesConf[$storage] ?? null;
    }


    /**
     * @param string $storage
     * @param bool $write
     * @return null|Storages\Generic
     */
    public static function storage(string $storage, bool $write = false)
    {
        if (static::$storages === null) {
            static::initStorages();
        }
        return static::$storages[$storage] ?? null;
    }

    /**
     * Initialize indexes using config values specified in $indexesConf (see var definition for syntax).
     *
     * @return void
     */
    protected static function initIndexes()
    {
        static::$indexes = [];
        foreach (static::$indexesConf as $name => &$index) {
            if (!isset($index['type'])) {
                $index['type'] = 'Generic';
            }
            $index['name'] = $name;
            $index['modelClass'] = static::class;

            $class = ClassFinder::getNamespace(\Zer0\Model\Indexes\AbstractIndex::class) . '\\' . $index['type'];

            static::$indexes[$name] = new $class($index);
        }
    }

    /**
     * Sets default WHERE clause based on the primary key's specified value.
     *
     * @return Generic $this
     */
    public function loadDefaultWhere()
    {
        if ($this->data) {
            if (static::$primaryKey === null) {
                return $this;
            }
            $primaryKey = is_array(static::$primaryKey) ? static::$primaryKey : [static::$primaryKey];
            $cond = '';
            $values = [];
            foreach ($primaryKey as $field) {
                $cond .= ($cond !== '' ? ' AND ' : '') . $field . ' = ?';
                $values[] = $this->data[$field] ?? null;
            }
            $this->where($cond, $values);
        }
        return $this;
    }

    /**
     * Get array of meta field descriptions or a description of a specific field.
     *
     * @param null|string $field
     * @return array of meta fields description or a description of the specific field.
     */
    public static function rules(?string $field = null)
    {
        if ($field) {
            return static::$rules[$field] ?? $field;
        }

        return static::$rules;
    }

    /**
     * Creates a model instance and populates it with new data
     *
     * @param  array $attrs Array of field/value pairs ['field' => 'value', ...]
     * @return static
     */
    public static function create(?array $attrs = null)
    {
        $obj = new static(null, true);
        $obj->init();
        if ($attrs !== null) {
            $obj->attr($attrs);
        }
        return $obj;
    }

    /**
     * Populate or return model object attributes:
     *
     * (1) attr(array $hash) Populate model object attributes corresponding to key/values of given array
     * (2) attr(string $field) Returns an attribute value
     * (3) attr(string $field, mixed $value) Assigns value
     * (4) attr() Returns $this
     *
     * @param array|string|null $field Field/value array to apply OR field name of value to return OR field to which to assign $value
     * @param mixed $value $value to assign, if assigning
     * @return $this|mixed Returns $this if populating or assigning, attribute value if fetching attribute
     */
    public function attr($field = null, $value = null)
    {
        $numArgs = func_num_args();
        if ($numArgs === 1) {
            if (is_array($field)) {
                foreach ($field as $k => $v) {
                    $this[$k] = $v;
                }

                // Successful attribute population (1)
                return $this;
            }

            // Return attribute value in case (2)
            return $this[$field];
        } elseif ($numArgs === 2) {
            $this[$field] = $value;
        }

        // Successfully set attribute (or did nothing) - cases (3) or (4)
        return $this;
    }

    /**
     * Called when object is just created or cloned
     *
     * @return void
     */
    public function init()
    {
        $this->loadDefaultWhere();
    }

    /**
     * Returns Index by name
     * @param  string $name
     * @return Indexes\AbstractIndex
     */
    public static function index($name)
    {
        // Call baseInit() if not yet called
        $class = static::class;
        if (!isset(self::$baseInited[$class])) {
            self::$baseInited[$class] = true;
            static::baseInit();
        }
        return static::$indexes[$name] ?? null;
    }

    /**
     * Returns Primary key
     *
     * @param boolean $forceArray = false   If true, an array will be returned (e.g. ['$id']).
     * @return string|array
     */
    public static function primaryKeyScheme($forceArray = false)
    {
        if ($forceArray) {
            if (is_array(static::$primaryKey)) {
                return static::$primaryKey;
            }
            return ['$' . static::$primaryKey];
        }
        return static::$primaryKey;
    }

    /**
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws Exceptions\UndefinedMethodCalledException
     */
    public static function __callStatic($method, $args)
    {
        switch ($method) {
            case 'where':
                return (new static)->where(
                    $args[0] ?? null,
                    $args[1] ?? null
                );
            case 'whereFieldEq':
                return (new static)->_whereFieldEq(
                    $args[0] ?? null,
                    $args[1] ?? null
                );
            case 'any':
                return (new static)->_any();
            case 'firstWhere':
                return (new static)->where(
                    $args[0] ?? null,
                    $args[1] ?? null
                )->first();
        }

        throw new Exceptions\UndefinedMethodCalledException('Undefined method called: ' . $method);
    }

    /**
     * WHERE clause getter/setter. DON'T CALL DIRECTLY.
     * Underscore prefix signifies that this method should only be called via __call or _callStatic.
     * Overload the 'whereFieldEq' method because we can't call a static method in a non-static way.
     *
     * @example _whereFieldEq('id', '123') - set an IN predicate as where
     * @example _whereFieldEq('id', ['123', '456']) - set an IN predicate as where, make model multi-mode
     * @example _whereFieldEq('id = :ids AND age < :maxage', ['ids' => [1,2,3], 'maxage' = '18'])
     *
     * @param $field
     * @param mixed $values Can be null, scalar, array of scalars, or contain placeholders.
     *      See \Core\PDO::replacePlaceholders and PDOTest::testPlaceholderReplacement
     * @return Generic $this
     * @throws UnknownFieldException
     */
    public function _whereFieldEq($field, $values)
    {
        // Called with field/value pair, e.g. whereFieldEq('id', '123'). Create condition objects and set.
        if ($field === static::$primaryKey) {
            $this->where = $this->condition('PrimaryKeyIn', $field, $values);
            $this->multiMode = is_array($values);
        } else {
            if (!isset(static::$rules[$field])) {
                throw new UnknownFieldException($field);
            }
            $this->where = $this->condition('In', $field, $values);
            $this->multiMode = true;
        }
        $this->offset = 0;

        return $this;
    }

    /**
     * Instantiate a Conditions\Generic object (e.g. where clause).
     *
     * @param string $type
     * @param string $expr
     * @param array $values
     *
     * @return Expressions\Conditions\Generic
     */
    public function condition($type, $expr, $values)
    {
        $class = ClassFinder::getNamespace(\Zer0\Model\Expressions\Conditions\Generic::class) . '\\' . $type;
        return new $class($expr, $values, $this);
    }

    /**
     * Empty any()
     * @return Generic $this
     */
    public function _any()
    {
        $this->where = $this->condition('Any', null, null);
        $this->offset = 0;
        $this->multiMode = true;
        return $this;
    }

    /**
     * First
     *
     * @param callable $cb = null Callback
     * @return Generic|null
     */
    public function first($cb = null)
    {
        $this->multiMode = false;
        if ($this->data === null) {
            $this->load(null, null, $cb);
        } elseif ($cb !== null) {
            $cb($this->loaded() ? $this : null);
        }

        return $this->loaded() ? $this : null;
    }

    /**
     * Retrieves data from data store and populates object fields
     * If $cb argument is given, the operation will be done asynchronously,
     * meaning that the method will return null immediately and
     * $cb will get called on completion of the op.
     *
     * @param integer $limit = null
     * @param integer $offset = null
     * @param callable $cb ($this) = null
     * @return Generic|Iterator
     */
    public function load($limit = null, $offset = null, $cb = null)
    {
        if ($cb !== null) {
            return $this->loadAsync($limit, $offset, $cb);
        }
        // Fetch object(s) from data storage
        $result = $this->where->fetch(
            $this->fields,
            $this->orderBy,
            $offset !== null ? $offset : $this->offset,
            $this->multiMode ? $limit : 1,
            $this->innerJoins,
            $this->groupBy
        );
        if ($result === null) {
            // @TODO: exception, no storages are capable of processing this query
            $result = new ResultList([]);
        }

        if ($this->multiMode) {
            if ($offset == null && $limit !== null) {
                // Increment offset
                $this->offset += count($result->objects);
            }
            $it = new Iterator($this, $result);
            if ($this->vFieldCallbacks) {
                // Applying joined fields
                $it->withVirtualFields($this->vFieldCallbacks);
            }
            return $it;
        }
        if ($this->where === null) { // If 'where' is undefined
            $this->loadDefaultWhere();  // try to extract it from object (e.g. '<primary> = ?')
        }
        $this->data = current($result->objects);
        $this->onLoad();
        if ($this->vFieldCallbacks) {
            // Applying joined fields
            foreach ($this->vFieldCallbacks as $field => $cb) {
                $cb($this, $field);
            }
        }

        return $this;

    }

    /**
     *
     */
    protected function onLoad(): void {
        
    }

    /**
     * Retrieves data from data store and populates object fields
     *
     * The operation will be done asynchronously,
     * meaning that the method will return null immediately and
     * $cb will get called on completion of the op.
     *
     * @param integer $limit
     * @param integer $offset
     * @param callable $cb
     * @return Generic|Iterator
     */
    public function loadAsync($limit, $offset, $cb)
    {
        $cb = CallbackWrapper::wrap($cb);
        // Fetch object(s) from data storage
        $this->where->fetch(
            $this->fields,
            $this->orderBy,
            $offset !== null ? $offset : $this->offset,
            $this->multiMode ? $limit : 1,
            $this->innerJoins,
            $this->groupBy,
            function ($result) use ($cb, $offset, $limit) {
                if ($result === null) {
                    // @TODO: exception, no storages are capable of processing this query
                    $result = new ResultList([]);
                }

                if ($this->multiMode) {
                    if ($offset == null && $limit !== null) {
                        // Increment offset
                        $this->offset += count($result->objects);
                    }
                    $it = new Iterator($this, $result);
                    if ($this->vFieldCallbacks !== []) {
                        // Applying joined fields
                        $it->withVirtualFields($this->vFieldCallbacks);
                    }
                    if ($cb !== null) {
                        $cb($it);
                        return;
                    }
                }
                if ($this->where === null) { // If 'where' is undefined
                    $this->loadDefaultWhere();  // try to extract it from object (e.g. '<primargy> = ?')
                }
                $this->data = current($result->objects);
                if ($this->vFieldCallbacks !== []) {
                    // Applying joined fields
                    foreach ($this->vFieldCallbacks as $field => $cb) {
                        $cb($this, $field);
                    }
                }
                if ($cb !== null) {
                    $cb($this);
                }
            }
        );
        return $this;
    }

    /**
     * Has model been loaded successfully?
     *
     * @return bool
     */
    public function loaded()
    {
        return is_array($this->data);
    }

    /**
     * Delayed storage
     * Enables Delayed storage for this model instance,
     * "heavy" storages like MySQL will receive a batch update
     * with some delay.
     *
     * @param  integer $maxDelay = 1 Time in seconds
     * @param  boolean $forBatch = false If true, $this->options['storages'
     *                                   will be filled with storages without noDelay
     * @return Generic $this
     * @throws \Exception
     */
    public function delayed($maxDelay = 1, $forBatch = false)
    {
        $this->options['storages'] = [];
        if (!$forBatch) {
            if (!isset(static::$storagesConf['DelayedRedis'])) {
                throw new \Exception('delayed() requires \'DelayedRedis\' storage to be enabled in the model');
            }
            $this->options['storages'][static::DELAYEDREDIS_NS . $maxDelay] = [
                'type' => 'DelayedRedis',
                'maxDelay' => $maxDelay,
                'prefix' => static::$storagesConf['DelayedRedis']['prefix'],
                'mode' => 'normal',
            ];
        }
        foreach (static::$storages as $name => $storage) {
            if ($forBatch) {
                if (!$storage->noDelay) {
                    $this->options['storages'][$name] = true;
                }
            } else {
                if (!$storage instanceof DelayedRedis && $storage->noDelay) {
                    $this->options['storages'][$name] = true;
                }
            }
        }
        return $this;
    }

    /**
     * Returns saved exceptions
     * @return Exceptions\BundleException
     * @return boolean false
     */
    public function exceptions()
    {
        if ($this->exceptionsBundle) {
            $bundle = (new Exceptions\BundleException)->bundle($this->exceptionsBundle);
            $this->exceptionsBundle = null;
            return $bundle;
        }
        return false;
    }

    /**
     * Set flag to calculate total number of objects (or not).
     *
     * @param  boolean $bool On/off
     * @return Generic $this
     */
    public function calcTotal($bool = true)
    {
        $this->calcTotal = (bool)$bool;

        return $this;
    }

    /**
     * Set flag to enable/disable db query cache.
     *
     * @param  boolean $bool On/off
     * @return Generic $this
     */
    public function noQueryCache($bool = true)
    {
        $this->noQueryCache = (bool)$bool;

        return $this;
    }

    /**
     * Set flag to enable/disable Upsert mode
     *
     * @param  boolean $bool Enabled?
     * @return Generic $this
     */
    public function upsert($bool = true)
    {
        $this->upsertMode = (bool)$bool;

        return $this;
    }

    /**
     * Load data to implement IteratorAggregate interface
     *
     * @return Iterator
     */
    public function getIterator()
    {
        return $this->multi()->load();
    }

    /**
     * Set flag to enable/disable multi mode
     *
     * @param  boolean $bool Enabled?
     * @return Generic $this
     */
    public function multi($bool = true)
    {
        $this->multiMode = (bool)$bool;

        return $this;
    }

    /**
     * Add a key-value pair
     *
     * @param $field
     * @param $value
     * @return $this
     */
    public function addIndexData($field, $value = null)
    {
        if (!isset($this->updatePlan['$data'])) {
            $this->updatePlan['$data'] = [];
        }
        $this->updatePlan['$data'][$field] = $value;
        return $this;
    }

    /**
     * Check if at least one object exists
     * @return boolean
     */
    public function exists()
    {
        return (bool)$this->where->count(1);
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'type' => get_class($this),
            'new' => $this->new,
            'where' => $this->where,
            'multi' => $this->multiMode,
            'obj' => $this->data,
            'update' => $this->updatePlan
        ];
    }

    /**
     * Delete objects matching current where clause
     *
     * @param callable $cb = null Callback
     * @return $this
     * @throws \Exception
     */
    public function delete($cb = null)
    {
        if ($cb !== null) {
            $cb = CallbackWrapper::wrap($cb);
        }
        if ($this->publicReadonly) {
            return $this;
        }
        if ($cb !== null) {
            foreach ($this->indexes() as $index) {
                $index->onDelete($this->where, function () {
                });
            }
            $this->where->delete(function ($affected) use ($cb) {
                $this->affected = $affected;
                $cb($this);
            });
            return $this;
        }
        foreach ($this->indexes() as $index) {
            $index->onDelete($this->where);
        }
        $this->affected = $this->where->delete();
        return $this;
    }

    /**
     * Returns corresponding \Zer0\Model\Indexes objects for static::$indexConfs
     * filtered by $this->options['indexes']
     *
     * @return \Zer0\Model\Indexes\AbstractIndex[] with indexName as key: ['indexName' => Indexes\Generic, ...]
     */
    public function indexes()
    {
        if (isset($this->options['indexes'])) {
            return array_intersect_key(static::$indexes, $this->options['indexes']);
        }
        return static::$indexes;
    }

    /**
     * Return number of objects affected by last operation
     *
     * @return integer Number of objects affected by last operation
     */
    public function affected()
    {
        return $this->affected;
    }

    /**
     * Returns Model, i.e. $this
     *
     * @return Generic $this
     */
    public function model()
    {
        return $this;
    }

    /**
     * Return the number of objects. Wrapper for \Zer0\Model\Expressions\Conditions\Generic::count()
     *
     * @param integer $limit = null LIMIT
     * @param  callable $cb = null Callback
     * @return int
     */
    public function count($limit = null, $cb = null)
    {
        if ($cb !== null) {
            $cb = CallbackWrapper::wrap($cb);
        }
        return $this->where->count($limit, $this->innerJoins, $this->groupBy, $cb);
    }

    /**
     * Set an array of fields to retrieve. Check static::$rules and skip field if not found.
     *
     * @param  array $fields
     * @return Generic $this
     */
    public function fields($fields = [])
    {
        $fields = (array)$fields;
        $this->fields = [];
        foreach ($fields as $field) {
            if (!isset(static::$rules[$field])) {
                continue; // @TODO: throw an exception?
            }
            $this->fields[] = $field;
        }
        return $this;
    }

    /**
     * Raw (unsafe) field list for SELECT. Unlike fields(), the supplied field list is not checked against
     * static::$rules.
     *
     * @example rawFields('active, time')
     * @param  string|array $fields String (csv) or array of field names
     * @return Generic $this
     */
    public function rawFields($fields = [])
    {
        if (is_string($fields)) {
            $this->fields = array_map('trim', explode(',', $fields));
        } else {
            $this->fields = $fields;
        }
        return $this;
    }

    /**
     * Raw (unsafe) ORDER BY clause
     * @example rawOrderBy('active DESC, time DESC')
     *
     * @param  string $orderBy
     * @return Generic $this
     */
    public function rawOrderBy($orderBy)
    {
        $this->orderBy = new Expressions\OrderByClause($orderBy, null, $this);
        return $this;
    }

    /**
     * ORDER BY clause
     * @example orderBy(['field' => 'ASC', ...])
     * @example orderBy('field', 'DESC')
     * @param string|array $orderBy
     * @param string $dir
     * @return Generic $this
     * @throws UnknownFieldException
     */
    public function orderBy($orderBy, $dir = 'ASC')
    {
        if (!is_array($orderBy)) {
            $orderBy = [$orderBy => $dir];
        }
        $this->orderBy = Expressions\OrderByClause::fromArray($orderBy, $this);
        return $this;
    }

    /**
     * Raw (unsafe) GROUP BY clause
     * @example rawGroupBy('active, time')
     *
     * @param $groupBy
     * @return Generic $this
     */
    public function rawGroupBy($groupBy)
    {
        $this->groupBy = new Expressions\GroupByClause($groupBy, null, $this);
        return $this;
    }

    /**
     * GROUP BY clause
     * @example groupBy('foo')
     * @example groupBy(['foo', 'bar'])
     * @param $groupBy
     * @return Generic $this
     */
    public function groupBy($groupBy)
    {
        if (!is_array($groupBy)) {
            $groupBy = [$groupBy];
        }
        $this->groupBy = Expressions\GroupByClause::fromArray($groupBy, $this);
        return $this;
    }

    /**
     * Set Offset
     *
     * @param integer $offset
     * @return Generic $this
     */
    public function offset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Rewind the pointer
     *
     * @return Generic $this
     */
    public function rewind()
    {
        $this->offset = 0;

        return $this;
    }

    /**
     * Getter for properties, returns value of the $field
     *
     * @param $field
     * @return bool|mixed
     * @throws Exceptions\InvalidStateException
     */
    public function __get($field)
    {
        $method = 'get' . ucfirst($field);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        if ($this->data === null && !$this->new) {
            throw new Exceptions\InvalidStateException('record data is not loaded');
        }
        if (isset($this->joinedData[$field])) {
            return $this->joinedData[$field];
        }
        return $this->data[$field] ?? null;
    }

    /**
     * __set()
     *
     * @param $field
     * @param $value
     * @return void
     */
    public function __set($field, $value)
    {
        $method = 'set' . ucfirst($field);
        $this->$method($value);
    }

    /**
     * Returns model contents as an array
     *
     * @param array $limitFields
     * @return array
     */
    public function toArray($limitFields = null)
    {
        if (!$limitFields || $limitFields === true) {
            return $this->data;
        }

        return array_intersect_key($this->data, array_flip($limitFields));
    }

    /**
     * Return only subset of public keys
     * @paray array $limitFields subset of keys (all public by default)
     * @param null $limitFields
     * @return array
     */
    public function toPublicArray($limitFields = null)
    {
        if (!$limitFields) {
            $limitFields = static::$publicFields;
        } else {
            $limitFields = array_intersect(static::$publicFields, $limitFields);
        }
        $arr = $this->toArray();
        foreach ($arr as $k => $item) {
            if (is_numeric($k) || !in_array($k, $limitFields, true)) {
                unset($arr[$k]);
            }
        }
        return $arr;
    }

    /**
     * foreach()
     *
     * @param  callable $cb Callback that takes $this as input (for misc. transformations)
     * @return Generic $this
     */
    public function each($cb)
    {
        if ($this->multiMode) {
            return $this->load()->each($cb);
        }
        if ($this->data === null) {
            $this->load();
        }
        $cb($this);

        return $this;
    }

    /**
     * Return array containing all values for the given field (result will only have one element if not in multi mode)
     *
     * @param  string $field Field name
     * @return array
     */
    public function values($field)
    {
        if ($this->multiMode) {
            return $this->load()->values($field);
        }
        if ($this->data === null) {
            $this->load();
        }

        return ($v = $this[$field]) !== null ? [$v] : [];
    }

    /**
     * Assign field/value data from join to joinedData
     *
     * @example
     *     Let: $this->id = 1
     *     $data = ['1' => 'My name is Antonio', '2' => 'My name is Vasily'];
     *     $items->applyJoinedData('user_id', $data, 'description');
     *     Now, $this->$joinedData['description'] = 'My name is Antonio'
     *
     * @example $items->addDataFromJoin('category_id',
     *                                  Categories::whereFieldEq('id', $items->values('category_id'))
     *                                      ->extractJoinData('description', 'id'),
     *                                  'categoryDescription');
     *
     * @param  string $foreignKeyField Model attribute name whose value is used as a foreign key in $data
     *                                   referencing joined Model's data
     * @param  array $data Key => value array with foreign key id => joined model value
     * @param  string $localFieldName Field name to assign in Model->joinedData
     *
     * @return \Zer0\Model\Generic $this
     */
    public function addDataFromJoin($foreignKeyField, $data, $localFieldName)
    {
        $foreignKeyVal = $this[$foreignKeyField];
        if (isset($data[$foreignKeyVal])) {
            $this->assignJoinedField($localFieldName, $data[$foreignKeyVal]);
        }

        return $this;
    }

    /**
     * Populates joined field/values array with given data.
     *
     * @param  string $field Field
     * @param  mixed $value
     * @return Generic $this
     */
    public function assignJoinedField($field, $value)
    {
        $this->joinedData[$field] = $value;
        return $this;
    }

    /**
     * Returns a key/value array with data from current model (or models if multi). Used to
     * join data to another model. The specified key is the field used as a foreign key in
     * the joining model, while value is the field value from this model that we will make
     * available to the other model.
     *
     * @example Let: $this->id = 1, $this->age = 18:
     *          extractDataForJoin('age', 'id') yields [ 1 => 13 ]
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
    public function extractDataForJoin($valueField = null, $keyField = null)
    {
        if ($keyField === null && static::$primaryKey !== null) {
            $keyField = static::$primaryKey;
        }
        if ($this->multiMode) {
            return $this->load()->extractDataForJoin($valueField, $keyField);
        }
        if ($this->data === null) {
            $this->load();
        }

        return [$this[$keyField] => $this[$valueField]];
    }

    /**
     * INNER JOIN command
     * @param  string $joined Model name to join
     * @param  string $on Condition, e.g. 'User.id = Online.id'
     * @param  array $fields = [] Array of fields, e.g. ['categoryName' => 'Category.name']
     * @param null $iterator
     * @return Generic $this
     * @throws Exceptions\IndexNotFoundException
     * @throws Exceptions\ModelNotFoundException
     * @throws Exceptions\UnsupportedActionException
     */
    public function innerJoin($joined, $on = null, $fields = [], $iterator = null)
    {
        if ($iterator) {
            throw new Exceptions\UnsupportedActionException('Cannot perform INNER JOIN with iterator');
        }
        $relations = explode('~', $joined, 2);
        $modelClass = $relations[0];
        if (!class_exists($modelClass)) {
            throw new Exceptions\ModelNotFoundException($modelClass);
        }
        if (count($relations) === 2) {  // Index
            $indexName = $relations[1];
            $index = $modelClass::index($indexName);
            if ($index === false) {
                throw new Exceptions\IndexNotFoundException($modelClass . '::indexes[' .
                    var_export($indexName, true) . ']');
            }
            if ($on === null) {
                $on = $index->defaultOn;
            }
            if (is_string($on)) {
                $on = new Expressions\Conditions\Generic($on, null, $this);
            }
            $this->innerJoins[] = [$index, $on, $fields];
        } else {   // Model
            if (is_string($on)) {
                $on = new Expressions\Conditions\Generic($on, null, $this);
            }
            $this->innerJoins[] = [$modelClass, $on, $fields];
        }
        return $this;
    }

    /**
     * LEFT GROUPED JOIN command joins this model with an array of related models and assigns this
     * array to a local virtual field ($field).
     *
     * @param  string $joined Model name to join
     * @param  string $on Condition, e.g. 'User.id = Online.id'
     * @param  string|array $fields = [] Field name or array of fields, e.g. ['localFieldName' => 'Joined.FieldName']
     *          The key of this array is the key in the current model, value is the field in the joined model
     *          If a string is specified, the field name is assumed to be the same across current and joined model
     * @param  array $additionalJoins joined data for models joined
     *           I want to go deeper
     * @param  Iterator $iterator
     * @return Generic $this
     * @throws Exceptions\ModelNotFoundException
     * @throws Exceptions\UnsupportedActionException
     * @todo Change $field to $fields (support arrays of fields to return instead of full objects)
     * @example ('UserLangsLearn', 'UserLangsLearn.user_id = User.id', 'langsLearning')
     *          langsLearning contains array of objects(UserLangsLearn)
     */
    public function leftGroupedJoin(
        $joined,
        $on = null,
        $fields = [],
        $additionalJoins = [],
        $iterator = null
    ) {
        $this->leftJoin($joined, $on, $fields, $additionalJoins, true, $iterator);
        return $this;
    }

    /**
     * LEFT JOIN command.
     * Nested conditions are not yet supported.
     *
     * @param  string $joined Model name to join
     * @param  string $on Condition, e.g. 'User.id = Online.id'
     * @param  string|array $fields = [] Field name or array of fields, e.g. ['localFieldName' => 'Joined.FieldName']
     *          The key of this array is the key in the current model, value is the field in the joined model
     *          If a string is specified, the field name is assumed to be the same across current and joined model
     * @param  array $additionalJoins joined data for models joined
     *           I want to go deeper
     * @param  boolean $grouped = false Grouped?
     * @param  Iterator $iterator
     * @return Generic $this
     * @throws Exceptions\ModelNotFoundException
     * @throws Exceptions\UnsupportedActionException
     */
    public function leftJoin(
        $joined,
        $on = null,
        $fields = [],
        $additionalJoins = [],
        $grouped = false,
        $iterator = null
    ) {
        $relations = explode('~', $joined, 2);
        $modelClass = $relations[0];
        if (!class_exists($modelClass)) {
            throw new Exceptions\ModelNotFoundException($modelClass);
        }
        if (count($relations) === 2) {  // Index
            throw new Exceptions\UnsupportedActionException('Cannot perform LEFT JOIN <index>');
        } else {// Model
            if (is_string($on)) {
                $on = new Expressions\Conditions\Generic($on, null, $this);
            }
            $this->withVirtualFields(
                $fields,
                function ($model, $field) use ($modelClass, $joined, $on, $grouped, $additionalJoins) {
                    $mongoNotationOn = $on->mongoNotation;
                    $modelName = ClassFinder::getClassBasename(get_class($this));

                    $queryData = [];
                    $fieldsInConditions = [];
                    // @TODO: optimization: move this out of the callback
                    $joinedModelKey = null;
                    foreach ($mongoNotationOn as $left => $right) {
                        // @TODO: nested conditions
                        $leftField = (new \Zer0\Model\Subtypes\Field($left))->parseName();
                        if ($right instanceof \Zer0\Model\Subtypes\Field) {
                            // Field - Field predicate
                            $rightField = $right->parseName();

                            // If 'base.field = joined.field'
                            if (isset($leftField['model']) && $modelName === $leftField['model']) {
                                // then reverse it
                                unset($mongoNotationOn[$left]);
                                $mongoNotationOn[(string)$right] = new \Zer0\Model\Subtypes\Field($left);
                                list($left, $right) = [$right, $left];
                                list($leftField, $rightField) = [$rightField, $leftField];
                            }
                            unset($mongoNotationOn[(string)$left]);
                            $mongoNotationOn[$leftField['name']] =
                                new \Zer0\Model\Subtypes\Placeholder(count($queryData));
                            // Base (right) model values to pass to where() on joined model
                            if (is_array($model)) {
                                throw new \Exception('generic model array problem');
                            }
                            $queryData[] = $model->values($rightField['name']);
                            $fieldsInConditions[] = $rightField['name'];
                            $joinedModelKey = $leftField['name'];
                        } else {
                            // Field - Value predicate
                            unset($mongoNotationOn[(string)$left]);
                            $mongoNotationOn[$leftField['name']] = $right;
                        }
                    }

                    $origModelKey = $fieldsInConditions[0]; // @TODO: foreach
                    $on->setValues($queryData);
                    $on = $on->mutateWithMongoNotation($mongoNotationOn);
                    $joinedData = [];

                    // Fetch data from joined model
                    $models = $modelClass::where((string)$on, $on->getValues());
                    if (isset($additionalJoins) && count($additionalJoins)) {
                        foreach ($additionalJoins as $joinItem) {
                            $models = $models->withVirtualFields($joinItem);
                        }
                    }
                    $models = $models->load();
                    foreach ($models as $item) {
                        if ($grouped) {
                            if (!isset($joinedData[$item[$joinedModelKey]])) {
                                $joinedData[$item[$joinedModelKey]] = [];
                            }
                            $joinedData[$item[$joinedModelKey]] [] = $item;
                        } else {
                            $joinedData[$item[$joinedModelKey]] = $item;
                        }
                    }
                    if ($grouped) {
                        foreach ($model->values($joinedModelKey) as $k) {
                            if (!isset($joinedData[$k])) {
                                $joinedData[$k] = [];
                            }
                        }
                    }
                    $model->addDataFromJoin(
                        $origModelKey,
                        $joinedData,
                        $field
                    );
                },
                $iterator
            );
        }
        return $this;
    }

    /**
     * Set the value of a virtual field using a user-defined callback. A virtual field is a field not
     * defined in the current model/datastore but instead given a value by the specified callback.
     *
     * Can be called before or after load()
     *
     * withVirtualField() - Return existing virtual field/callback list
     * withVirtualField(field, callback, iterator) - Define a single virtual field+callback
     * withVirtualField([field => callback, ...], iterator) - Define multiple virtual fields/callbacks
     *
     * If a callback is not supplied, static::defaultWith callback will be used instead.
     * Callback will be invoked with ($this, $fieldName) and should set $this->$fieldName to some value.
     *
     * @param string|array $arg Field name or array of field => callback
     * @param callable $cb = null Callback when specifying single field/callback pair
     * @param Iterator $iterator = null Pass this iterator to callback when invoking
     * @return Generic $this
     */
    public function withVirtualFields($arg = null, $cb = null, $iterator = null)
    {
        if (!func_num_args()) {
            return $this->vFieldCallbacks;
        }
        if (is_array($arg)) {
            foreach ($arg as $field => $cb) {
                $this->withVirtualFields($field, $cb, $iterator);
            }

            return $this;
        }

        // Set/invoke single virtual field callback
        $field = $arg;
        if ($cb === null) {
            $cb = static::assignDefaultVFields($field);
        }
        $this->vFieldCallbacks[$field] = $cb;
        if (!$this->multiMode && $this->data) {
            $cb($this, $field);
        } elseif ($iterator !== null) {
            $cb($iterator, $field);
        }

        return $this;
    }

    /*
     * Called in save()
     *
     * @return void
     */

    /**
     * Sets a default callback for withVirtualFields()
     *
     * @param  mixed $field
     * @param callable $cb = null
     * @return Generic $this
     */
    public static function assignDefaultVFields($field, $cb = null)
    {
        if ($cb === null) {
            return static::$defaultVFieldCbs[$field] ?? null;
        }
        return static::$defaultVFieldCbs[$field] = $cb;
    }

    /**
     * Enter the exceptions bundling mode
     *
     * @return Generic $this
     */
    public function bundleExceptions()
    {
        if ($this->exceptionsBundle === null) {
            $this->exceptionsBundle = [];
        }
        return $this;
    }

    /**
     * Commits the transaction
     * Save data to storage. This means applying our update plan (transaction).
     *
     * @param callable|null $cb
     * @return ?self
     * @throws \Exception
     */
    public function save($cb = null)
    {
        if ($cb === true && class_exists(Daemon::class, false) && Daemon::$startTime) {
            $cb = function () {
            };
        }
        if ($cb !== null) {
            $cb = CallbackWrapper::wrap($cb);
        }
        if ($this->publicReadonly) {
            return $this;
        }
        if ($this->new) {
            $this->beforeSaveCreate();
        } else {
            $this->beforeSaveUpdate();
        }

        if ($this->exceptionsBundle) {
            $bundle = (new Exceptions\BundleException)->bundle($this->exceptionsBundle);
            $this->exceptionsBundle = null;
            throw $bundle;
        }

        $errors = $this->validationErrors();
        if (count($errors)) {
            throw new ValidationErrorException('Validation failed for fields: ' . implode(', ', array_keys($errors)));
        }

        if (!$this->new) {
            if (!count($this->updatePlan)) {
                return $this;
            }

            if (!$this->multiMode && $this->loaded()) {
                $this->updatePlan['$data'] = $this->data;
            }
        }

        // If $cb argument is given, do it asynchronously
        if ($cb !== null) {
            // Create ComplexJob and set the final callback
            $cj = new ComplexJob(function ($cj) use ($cb) {
                if (isset($cj->vars['failed'])) {
                    $this->affected = 0;
                } else {
                    $this->affected = max($cj->results);
                }
                $this->updatePlan = [];
                $this->new = false;
                $cb($this);
            });
            $cj->maxConcurrency(1); // Only one task shall be running simultaneously.
            if ($this->multiMode || !$this->new) {
                if (!$this->multiMode) {
                    $this->beforeSave();
                }
                $cj->more(function ($cj) { // This callback is called when existing tasks finish.
                    foreach ($this->indexes() as $k => $index) { // Iterating over indexes
                        if (isset($cj->vars['failed'])) {
                            break;
                        }
                        // Adding new task
                        yield 'i_' . $k => function ($jobname, $cj) use ($index) {
                            $index->onUpdate(
                                $this->where,
                                $this->updatePlan,
                                function ($res) use ($jobname, $cj) {
                                    $cj[$jobname] = true;
                                }
                            );
                        };
                    }
                    // Iterating over storages
                    foreach ($this->storages(true) as $k => $storage) {
                        if (isset($cj->vars['failed'])) {
                            break;
                        }
                        // Adding new task
                        yield 's_' . $k => function ($jobname, $cj) use ($storage) {
                            $storage->update(
                                $this->where,
                                $this->updatePlan,
                                function ($res) use ($jobname, $cj) {
                                    $cj[$jobname] = true;
                                }
                            );
                        };
                    }
                });
            } else {
                $cj->more(function ($cj) { // This callback is called when existing tasks finish.
                    foreach ($this->indexes() as $k => $index) {
                        if (isset($cj->vars['failed'])) {
                            break;
                        }
                        // Adding new task
                        yield 'i_' . $k => function ($jobname, $cj) use ($index) {
                            $index->onCreate(
                                $this->data,
                                $this->upsertMode,
                                function ($res) use ($jobname, $cj) {
                                    if ($res === 0) {
                                        $cj->vars['failed'] = true;
                                    }
                                    $cj[$jobname] = true;
                                }
                            );
                        };
                    }
                    // Iterating over storages
                    foreach ($this->storages(true) as $k => $storage) {
                        if (isset($cj->vars['failed'])) {
                            break;
                        }
                        // Adding new task
                        yield 's_' . $k => function ($jobname, $cj) use ($storage) {
                            $storage->create(
                                $this->data,
                                $this->upsertMode,
                                function ($res) use ($jobname, $cj) {
                                    $cj[$jobname] = $res;
                                }
                            );
                        };
                    }
                });
            }
            $cj();
            return null; // Stop here
        }
        if ($this->new) {
            if (!$this->multiMode) {
                $this->beforeSave();
            }
            foreach ($this->indexes() as $index) {
                $index->onCreate($this->data, $this->upsertMode);
            }
            foreach ($this->storages(true) as $storage) {
                $storage->create($this->data, $this->upsertMode);
                if ($storage->lastReturning ?? false) {
                    $this->data = array_merge($this->data, $storage->lastReturning);
                }
            }
            $this->new = false;
            if ($this->where === null) {
                $this->loadDefaultWhere();
            }
            $this->onSaveCreate();
        } else {
            if ($this->where === null) {
                return null; // @TODO: remove
            }
            if (!$this->multiMode) {
                $this->beforeSave();
            }
            foreach ($this->indexes() as $index) {
                $index->onUpdate($this->where, $this->updatePlan);
            }


            $this->affected = 0;
            foreach ($this->storages(true) as $storage) {
                $this->affected = max($this->affected, $storage->update($this->where, $this->updatePlan));
            }

            $this->onSaveUpdate();
        }
        $this->updatePlan = [];

        return $this;
    }

    /**
     * Called in save()
     * @return void
     */
    public function beforeSave()
    {
    }

    /**
     * Returns corresponding \Zer0\Model\Storages objects for static::$storageConfs filtered by
     * $this->options['storages']. If options['storages'] is set, only
     * those storages specified will be used (for example, if we want to get data only from DB on a Redis
     * miss).
     *
     * @param boolean $write Write operation? (Currently unused)
     * @return Storages\Generic[] with storageName indexes ['storageName' => Storages\Generic, ...]
     * @throws \Exception
     */
    public function storages($write = false)
    {
        if ($this->customStorages !== null) {
            return $this->customStorages;
        }
        if (static::$storages === null) {
            static::initStorages();
        }
        if (static::$storages === null) {
            $e = new \Exception();
            throw new \Exception('$storages is null: ' . get_class($this) . PHP_EOL
                . ' storagesConf = ' . var_export(static::$storagesConf, true));
        }
        if (isset($this->options['storages'])) {
            /** @var \Zer0\Model\Storages\Generic[] $storages */
            $storages = [];
            foreach ($this->options['storages'] as $storageName => $storageConf) {
                if (!is_array($storageConf) && isset(static::$storagesConf[$storageName])) {
                    // If, for example, ['SQL' => true], add SQL storage object to array
                    if ($storageConf === true) {
                        $storages[$storageName] = static::$storages[$storageName];
                    }
                } else {
                    // Storage conf from options is an array, so override default options and get a
                    // new storage object with this config.
                    if (!isset($storageConf['type'])) {
                        $storageConf['type'] = $storageName;
                    }
                    if (!isset($storageConf['primaryKey'])) {
                        $storageConf['primaryKey'] = static::$primaryKey;
                    }
                    $storageConf['modelClass'] = get_class($this);
                    $class = ClassFinder::getNamespace(\Zer0\Model\Storages\Generic::class) . '\\' . $storageConf['type'];

                    // This is a custom storage object, since config values have been overriden by options
                    $storages[$storageName] = new $class($storageConf);
                }
            }
            return $this->customStorages = $storages;
        }
        return static::$storages;
    }

    /**
     * Checks if property exists
     * @param string $field
     * @return boolean Exists?
     */
    public function offsetExists($field)
    {
        return $this[$field] !== null;
    }

    /**
     * Get property by name
     * @param string $field
     * @return mixed|null
     * @throws Exceptions\InvalidStateException
     */
    public function offsetGet($field)
    {
        $method = 'get' . ucfirst($field);
        if ($method === 'getIterator') { // getIterator() is reserved by \IteratorAggregate
            $method = '_getIterator';
        }
        if (strpos($method, ':') !== false) {
            $args = explode(':', $method);
            $method = array_shift($args);
            if (!method_exists($this, $method)) {
                return null;
            }
            return $this->$method(...$args);
        } elseif (method_exists($this, $method)) {
            return $this->$method();
        }
        if ($this->data === null && !$this->new) {
            throw new Exceptions\InvalidStateException;
        }
        if (isset($this->joinedData[$field])) {
            return $this->joinedData[$field];
        }
        return $this->data[$field] ?? null;
    }

    /**
     * Set property
     *
     * @param string $field
     * @param mixed $value
     * @return void
     * @throws \Exception
     */
    public function offsetSet($field, $value)
    {
        $method = 'set' . ucfirst($field);
        try {
            $this->$method($value);
        } catch (Exceptions\Interfaces\Gatherable $e) {
            if ($this->exceptionsBundle === null) {
                throw $e;
            } else {
                if (method_exists($e, 'getKey')) {
                    $this->exceptionsBundle[$e->getKey()] = $e;
                } else {
                    $this->exceptionsBundle[] = $e;
                }
            }
        }
    }

    /**
     * Unset field
     *
     * @param string $field
     * @return void
     */
    public function offsetUnset($field)
    {
        $method = 'unset' . ucfirst($field);
        $this->$method();
    }

    /**
     * Returns Primary key value
     * @return string
     */
    public function primaryKey()
    {
        return $this->data[static::$primaryKey] ?? null;
    }

    /**
     * Set options. See also Model->options var definition.
     *
     * @example  Model->options([
     *  'storages' => ['SQL' => true],  // This forces only SQL storage to be used.
     *  'indexes ' => ['online'],       // Only use online index
     * ]);
     *
     * @param  array $options Hash of options
     * @return Generic $this
     */
    public function options($options = null)
    {
        if ($options === null) {
            return $this->options;
        }
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * Called when the object is being cloned
     *
     * @param $obj
     * @return void $this
     */
    public function _cloned($obj)
    {
        $this->new = true;
        $this->data = [];
        $this->attr($obj);
        $this->init();
    }

    /**
     * __call
     *
     * @param string $method
     * @param array $args
     * @return null|mixed
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        try {
            if ($method === 'publicReadonly') {
                if ($this->publicReadonly) {
                    throw new Exceptions\AccessDeniedException('publicReadOnly() is inaccesible in read-only mode');
                }
                return $this->publicReadonly(...$args);
            }
            if ($method === 'beforeSaveCreate') {
                return null;
            }
            if ($method === 'onSaveCreate') {
                return null;
            }
            if ($method === 'beforeSaveUpdate') {
                return null;
            }
            if ($method === 'onSaveUpdate') {
                return null;
            }
            if ($method === 'setProperty') {
                if (method_exists($this, $method)) {
                    throw new Exceptions\AccessDeniedException($method . '() is inaccessible in read-only mode');
                }
                return $this->set(...$args);
            }

            // where() can be called in a non-static way to obtain current conditions or to change it.
            // $user->where('...') or $user->where()
            // E.g. we get mongoNotation like this: `User::where('id = ?', [1])->where()->mongoNotation`
            if ($method === 'where') {
                return $this->_where(...$args);
            }
            if ($method === 'whereFieldEq') {
                return $this->_whereFieldEq(...$args);
            }
            if ($method === 'clone') {
                return $this->_clone(...$args);
            }

            // get{Field}() returns $data value for the specified field
            if (strncmp($method, 'get', 3) === 0) {
                $field = lcfirst(substr($method, 3));
                if ($this->data === null && !$this->new) {
                    throw new Exceptions\InvalidStateException('record data is not loaded');
                }
                return $this->data[$field] ?? null;
            }

            // set{Field}() sets $data value for specified field
            if (strncmp($method, 'set', 3) === 0) {
                if ($this->publicReadonly) {
                    return $this;
                }
                if (method_exists($this, $method)) {
                    $this->$method(...$args);
                } else {
                    $this->setProperty(lcfirst(substr($method, 3)), $args[0] ?? null);
                }
                return $this;
            }
            if (strncmp($method, 'is', 2) === 0) {
                return (bool)$this[lcfirst(substr($method, 2))];
            }
            // incr{Field}($amount) wraps incrProperty()
            if (strncmp($method, 'incr', 4) === 0) {
                return $this->incrProperty(lcfirst(substr($method, 4)), $args[0] ?? null);
            }
            if (strncmp($method, 'unset', 5) === 0) {
                return $this->unsetProperty(lcfirst(substr($method, 5)));
            }
            if (strncmp($method, 'touch', 5) === 0) {
                return $this->touch(
                    lcfirst(substr($method, 5)),
                    $args[0] ?? null,
                    $args[1] ?? null
                );
            }
            if (strncmp($method, 'microtouch', 10) === 0) {
                return $this->microtouch(
                    lcfirst(substr($method, 10)),
                    $args[0] ?? null,
                    $args[1] ?? null
                );
            }
        } catch (\Exception $e) {
            if ($this->exceptionsBundle === null) {
                throw $e;
            } else {
                if (method_exists($e, 'getKey')) {
                    $this->exceptionsBundle[$e->getKey()] = $e;
                } else {
                    $this->exceptionsBundle[] = $e;
                }
            }
            return null;
        }

        throw new Exceptions\UndefinedMethodCalledException('Call to undefined method ' . get_class($this) . '->' .
            $method);
    }

    /**
     * Sets read-only mode for public-visibility calls.
     * Useful when passing Model objects into unsafe environments like template engines.
     *
     * @param  boolean $bool On/off
     * @return Generic $this
     */
    public function publicReadonly($bool = true)
    {
        $this->publicReadonly = (bool)$bool;

        return $this;
    }

    /**
     * Set a property of current model
     *
     * @param string $field Property
     * @param mixed $value Value
     * @return $this
     */
    protected function set($field, $value)
    {
        if ($value === null) {
            unset($this[$field]);
            return $this;
        }
        if ($this->data !== null) {
            $this->data[$field] = $value;
        }
        if ($this->new && !$this->upsertMode) {
            return $this;
        }

        unset($this->updatePlan['$unset'][$field]);
        if (!isset($this->updatePlan['$set'])) {
            $this->updatePlan['$set'] = [$field => $value];
        } else {
            $this->updatePlan['$set'][$field] = $value;
        }

        return $this;
    }

    /**
     * WHERE clause getter/setter. DON'T CALL DIRECTLY.
     * Underscore prefix signifies that this method should only be called via __call or _callStatic.
     * Overload the 'where' method because we can't call a static method in a non-static way.
     *
     * @example _where() returns current Model's where clauseп
     * @example _where('id = :ids AND age < :maxage', ['ids' => [1,2,3], 'maxage' = '18'])
     *
     * @param null|string $where Null to return current where, string with field name
     * @param mixed $values Can be null, scalar, array of scalars, or contain placeholders.
     *      See \Core\PDO::replacePlaceholders and PDOTest::testPlaceholderReplacement
     * @return Generic $this
     */
    protected function _where($where = null, $values = null)
    {
        // Return current where clause if called as where()
        if (!func_num_args()) {
            return $this->where;
        }
        if ($where instanceof Expressions\Conditions\Generic) {
            $this->where = $where;
        } else {
            // Called with a more general condition string,
            // e.g. where('id = :ids AND age < :maxage', ['ids' => [1,2,3], 'maxage' = '18'])
            // Create and set generic condition, which will parse this string into mongo notation.
            $this->where = $this->condition('Generic', $where, $values);
            $parsedWhere = $this->where->mongoNotation;
            if (is_string(static::$primaryKey) && count($parsedWhere) === 1 &&
                isset($parsedWhere[static::$primaryKey])
            ) { // @TODO: make more generic
                $parsedWherePart = $parsedWhere[static::$primaryKey];
                $this->where = $this->condition(
                    'PrimaryKeyIn',
                    static::$primaryKey,
                    isset($parsedWherePart['$in']) ? $parsedWherePart['$in'] : $parsedWherePart
                );
            }
            $this->multiMode = true;
        }
        $this->offset = 0;

        return $this;
    }

    /**
     * Clone the object
     *
     * @return Generic
     */
    public function _clone()
    {
        $class = get_class($this);
        $obj = $this->data;
        unset($obj['_id']);
        $clonedObj = new $class($this->where, null);
        $clonedObj->_cloned($obj);
        return $clonedObj;
    }

    /**
     * Default incrementer
     *
     * @param string $field Attribute
     * @param string $value Value
     * @return Generic $this
     */
    protected function incrProperty($field, $value)
    {
        $this->incr($field, $value);

        return $this;
    }

    /**
     * Increments a field by specified number
     *
     * @param string $field Field name
     * @param int $num Number
     * @return Generic $this
     */
    protected function incr($field, $num = 1)
    {
        if ($this->data !== null) {
            $entry = &$this->data[$field];
            if ($entry === null) {
                $entry = $num;
            } else {
                $entry += $num;
            }
        }

        if ($this->new && !$this->upsertMode) {
            return $this;
        }

        // Make sure this field is not being unset, since it must now have a new value
        $unset = isset($this->updatePlan['$unset'][$field]);
        unset($this->updatePlan['$unset'][$field]);

        if (isset($this->updatePlan['$set'][$field])) {
            $this->updatePlan['$set'][$field] += $num;
            return $this;
        }

        // Check for existing increment operations in our current transaction,
        // add to existing array and/or add amount by which to increment field.
        if (!isset($this->updatePlan['$inc'])) {
            $this->updatePlan['$inc'] = [$field => $num];
        } else {
            if ($unset || !isset($this->updatePlan['$inc'][$field])) {
                $this->updatePlan['$inc'][$field] = $num;
            } else {
                $this->updatePlan['$inc'][$field] += $num;
            }
        }

        return $this;
    }

    /**
     * Default unsetter
     * Sets $this->update['$unset'][$field] = 1
     * which will result in e.g. 'SET `field` = null' for SQL
     * and HDEL ... field' for Redis
     *
     * @param string $field Attribute
     * @return Generic $this
     * @throws ValidationErrorException
     */
    protected function unsetProperty($field)
    {
        $this->fieldValidate($field, null, static::$implicitRules);
        if ($this->data !== null) {
            unset($this->data[$field]);
            if ($this->new && !$this->upsertMode) {
                return $this;
            }
        }
        if (!isset($this->updatePlan['$unset'])) {
            $this->updatePlan['$unset'] = [$field => 1];
        }
        unset($this->updatePlan['$set'][$field]);
        unset($this->updatePlan['$inc'][$field]);
        $this->updatePlan['$unset'][$field] = 1;
        return $this;
    }

    /**
     * Updates a timestamp using time()
     *
     * @param  string $field Field
     * @param  integer $val = time()  Timestamp
     * @param  boolean $ifUpdated = false If true,
     *                 field shall not be updated if
     *                 the current transaction is empty
     * @return Generic $this
     */
    public function touch($field, $val = null, $ifUpdated = false)
    {
        if ($ifUpdated && !$this->beenUpdated()) {
            return $this;
        }
        if ($val === null) {
            $val = time();
        }

        return $this->set($field, $val);
    }

    /**
     * Checks the current transaction is not empty
     *
     * @return boolean
     */
    public function beenUpdated()
    {
        return count($this->updatePlan) > 0;
    }

    /**
     * Updates a timestamp using microtime()
     *
     * @param  string $field Field
     * @param  float $val = microtime(true) Timestamp
     * @param  boolean $ifUpdated = false If true,
     *                 field shall not be updated if
     *                 the current transaction is empty
     * @return Generic $this
     */
    public function microtouch($field, $val = null, $ifUpdated = false)
    {
        if ($ifUpdated && !$this->beenUpdated()) {
            return $this;
        }
        if ($val === null) {
            $val = microtime(true);
        }

        return $this->set($field, $val);
    }

    /**
     * Free (destroy) this object
     * @return void
     */
    public function free()
    {
        if ($this->freed) {
            return;
        }
        $this->freed = true;

        $this->where = null;
        $this->orderBy = null;
        $this->groupBy = null;
        $this->innerJoins = [];
        $this->data = null;
        $this->joinedData = [];
        $this->vFieldCallbacks = [];
        $this->options = [];
    }

    /**
     * Sets value if absent
     *
     * @param  string $field
     * @param  mixed $value
     * @return Generic $this
     */
    protected function defaultSet($field, $value)
    {
        if ($this->data === null) {
            return $this;
        }
        $entry = &$this->data[$field];
        if ($entry !== null) {
            return $this;
        }
        $this[$field] = $value;

        return $this;
    }
}
