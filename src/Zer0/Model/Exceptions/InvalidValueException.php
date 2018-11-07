<?php

namespace Zer0\Model\Exceptions;

/**
 * Class InvalidValueException
 * @package Zer0\Model\Exceptions
 */
class InvalidValueException extends ValidationErrorException
{
    protected $type;
    protected $field;
    protected $value;
    protected $requirement;
    protected $requirementText;


    /**
     * Returns array key for gather()
     * @return string
     */
    public function getKey()
    {
        return $this->field;
    }

    /**
     * Returns info for gather()
     * @return mixed
     */
    public function getInfo()
    {
        return [
            'type' => $this->type,
            'msg' => $this->getMessage(),
            'field' => $this->field,
            'value' => $this->value,
            'requirement' => $this->requirement,
        ];
    }

    /**
     * Sets field name
     * @param string $field
     * @return InvalidValueException $this
     */
    public function setField($field)
    {
        $this->field = $field;
        return $this;
    }

    /**
     * Sets value
     * @param string $value
     * @return InvalidValueException $this
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Sets the requirement
     *
     * @param string $requirement
     * @return $this
     */
    public function setRequirement($requirement)
    {
        $this->requirement = $requirement;
        $this->requirementText = 'does not meet a rule \'' . $this->requirement . '\'';
        return $this;
    }

    /**
     * Generates the message
     *
     * @return InvalidValueException $this
     */
    public function generateMessage()
    {
        $this->message = $this->field . ': ' . $this->requirementText . ': ' . var_export($this->value, true);
        return $this;
    }

    /**
     * @param null|string $message
     * @return InvalidValueException
     */
    public function setOrGenerateMessage(?string $message) : self
    {
        if ($message !== null) {
            $this->message  =$message;
        } else {
            $this->generateMessage();
        }
        return $this;
    }
}
