<?php

namespace Zer0\Model\Exceptions;

/**
 * Class InvalidValueEmpty
 * @package Zer0\Model\Exceptions
 */
class InvalidValueEmpty extends InvalidValueException
{
    protected $type = 'empty';

    /**
     * @param $requirement
     * @return $this|InvalidValueException
     */
    public function setRequirement($requirement)
    {
        $this->requirement = $requirement;
        $this->requirementText = 'must not be empty';
        return $this;
    }

    /**
     * Generates the message
     *
     * @return InvalidValueException $this
     */
    public function generateMessage()
    {
        $this->message = 'Please enter a ' . ucfirst($this->field);
        return $this;
    }
}
