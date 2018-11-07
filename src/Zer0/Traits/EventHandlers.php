<?php
namespace Zer0\Traits;

use PHPDaemon\Core\CallbackWrapper;

/**
 * Trait EventHandlers
 * @package Zer0\Traits
 */
trait EventHandlers
{
    /**
     * @var array Event handlers
     */
    protected $eventHandlers = [];

    /**
     * @var boolean Unshift $this to arguments of callback?
     */
    protected $addThisToEvents = true;

    /**
     * @var string Last called event name
     */
    protected $lastEventName;

    /**
     * Propagate event
     * @param  string $name Event name
     * @param  mixed ...$args Arguments
     * @return EventHandlers
     */
    public function event($name, ...$args)
    {
        if ($this->addThisToEvents) {
            array_unshift($args, $this);
        }
        if (isset($this->eventHandlers[$name])) {
            $this->lastEventName = $name;
            foreach ($this->eventHandlers[$name] as $cb) {
                if ($cb(...$args) === true) {
                    return $this;
                }
            }
        }
        return $this;
    }

    /**
     * Propagate event
     * @param  string $name Event name
     * @param  mixed ...$args Arguments
     * @return EventHandlers
     */
    public function trigger($name, ...$args)
    {
        if ($this->addThisToEvents) {
            array_unshift($args, $this);
        }
        if (isset($this->eventHandlers[$name])) {
            $this->lastEventName = $name;
            foreach ($this->eventHandlers[$name] as $cb) {
                if ($cb(...$args) === true) {
                    return $this;
                }
            }
        }
        return $this;
    }

    /**
     * Propagate event
     * @param  string $name Event name
     * @param  mixed ...$args Arguments
     * @return integer
     */
    public function triggerAndCount($name, ...$args)
    {
        if ($this->addThisToEvents) {
            array_unshift($args, $this);
        }
        $cnt = 0;
        if (isset($this->eventHandlers[$name])) {
            $this->lastEventName = $name;
            foreach ($this->eventHandlers[$name] as $cb) {
                if ($cb(...$args) !== 0) {
                    ++$cnt;
                }
            }
        }
        return $cnt;
    }

    /**
     * Use it to define event name, when one callback was bind to more than one events
     * @return string
     */
    public function getLastEventName()
    {
        return $this->lastEventName;
    }

    /**
     * Bind event or events
     * @alias EventHandlers::bind
     * @param string|array $event Event name
     * @param callable $cb Callback
     * @return self
     */
    public function on($event, $cb)
    {
        return $this->bind($event, $cb);
    }

    /**
     * Bind event or events
     * @param string|array $event Event name
     * @param callable $cb Callback
     * @return EventHandlers
     */
    public function bind($event, $cb)
    {
        $event = (array) $event;
        foreach ($event as $e) {
            $arr =& $this->eventHandlers[$e];
            if ($arr === null) {
                $arr = [];
            }
            if (in_array($e, $arr, true)) {
                continue;
            }
            $arr[] = $cb;
        }
        return $this;
    }

    /**
     * Unbind event(s) or callback from event(s)
     * @alias EventHandlers::unbind
     * @param string|array $event Event name
     * @param callable $cb Callback, optional
     * @return self
     */
    public function off($event, $cb = null)
    {
        return $this->unbind($event, $cb);
    }

    /**
     * Unbind event(s) or callback from event(s)
     * @param string|array $event Event name
     * @param callable $cb Callback, optional
     * @return EventHandlers
     */
    public function unbind($event, $cb = null)
    {
        if ($cb !== null) {
            $cb = CallbackWrapper::wrap($cb);
        }
        $event = (array) $event;
        foreach ($event as $e) {
            if (!isset($this->eventHandlers[$e])) {
                continue;
            }
            if ($cb === null) {
                unset($this->eventHandlers[$e]);
                continue;
            }
            $key = array_search($cb, $this->eventHandlers[$e], true);
            if ($key !== false) {
                unset($this->eventHandlers[$e][$key]);
            }
        }
        return $this;
    }

    /**
     * Clean up all events
     * @return void
     */
    protected function cleanupEventHandlers()
    {
        $this->eventHandlers = [];
    }
}
