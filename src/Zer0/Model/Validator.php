<?php

namespace Zer0\Model;

use Zer0\Model\Exceptions\InvalidArgumentException;
use Zer0\Model\Exceptions\UnknownFieldException;

/**
 * Trait Validator
 * @package Zer0\Model
 */
trait Validator
{
    use ValidationMethods;

    /**
     * @var array
     */
    protected static $rulesParsed = [];

    /**
     * @var array
     */
    protected $errorMessages = [];

    /**
     * Returns saved validation errors
     * @return array
     */
    public function validationErrors(): array
    {
        if (!$this->exceptionsBundle) {
            return [];
        }
        return (new Exceptions\BundleException)->bundle($this->exceptionsBundle)->validationErrors();
    }

    /**
     *
     */
    protected function beforeSaveCreate(): void
    {
        $this->validate();
    }

    /*
     * Called in save()
     *
     * @return void
     */

    /**
     * Validates object
     * @return boolean
     * @throws \Exception
     */
    public function validate(): bool
    {
        if ($this->new) {
            foreach (static::$rules as $field => $rules) {
                try {
                    $value = $this->data[$field] ?? null;
                    $this->fieldValidate($field, $value, static::$implicitRules);
                } catch (\Exception $e) {
                    if ($this->exceptionsBundle === null) {
                        throw $e;
                    } else {
                        $this->exceptionsBundle[$field] = $e;
                    }
                }
            }
        }
        if ($this->exceptionsBundle !== null) {
            return count($this->exceptionsBundle) === 0;
        }

        return true;
    }

    /**
     * Validates field
     * @param  string $field
     * @param  mixed $value
     * @param  array $onlyTypes = [] Only types
     * @throws Exceptions\ValidationErrorException
     * @return void
     */
    protected function fieldValidate($field, $value, $onlyTypes = []): void
    {
        if (!isset(static::$rulesParsed[static::class])) {
            static::$rulesParsed[static::class] = [];
        }
        $fieldRulesParsed =& static::$rulesParsed[static::class][$field];
        if ($fieldRulesParsed === null) {
            if (!isset(static::$rules[$field])) {
                throw (new UnknownFieldException)
                    ->setField($field)
                    ->setValue($value)
                    ->setOrGenerateMessage($this->errorMessage($field));
            }
            $fieldRulesParsed = static::parseFieldRules(static::$rules[$field]);
        }
        if ($value === null) {
            $requiredFound = false;
            foreach ($fieldRulesParsed as $rule) {
                list($ruleName, $parameters) = $rule;
                if (strpos($ruleName, 'Required') === 0) {
                    $requiredFound = true;
                    break;
                }
            }
            if (!$requiredFound) {
                return;
            }
        }
        foreach ($fieldRulesParsed as $rule) {
            list($ruleName, $parameters) = $rule;
            if ($onlyTypes && !in_array($ruleName, $onlyTypes)) {
                continue;
            }
            if (!call_user_func_array(
                [$this, 'validate' . ucfirst($ruleName)],
                [$field, $value, $parameters]
            )
            ) {
                throw (new \Zer0\Model\Exceptions\InvalidValueException)
                    ->setField($field)
                    ->setValue($value)
                    ->setRequirement(static::buildRuleString($rule))
                    ->setOrGenerateMessage($this->errorMessage($field, $rule));
            }
        }
    }

    /**
     * Parses rules of a field
     * @param  array|string $rules
     * @return array $rules
     */
    protected static function parseFieldRules($rules): array
    {
        if (is_array($rules)) {
            foreach ($rules as &$rule) {
                $rule[0] = static::studly(trim($rule[0]));
            }
        } else {
            $rules = explode('|', $rules);
            foreach ($rules as &$rule) {
                $orig = $rule;
                $explode = explode(':', $rule, 2);
                $ruleName = static::studly($explode[0]);
                if (isset($explode[1])) {
                    if ($ruleName == 'regex') {
                        $parameters = [$explode[1]];
                    } else {
                        $parameters = str_getcsv($explode[1]);
                    }
                } else {
                    $parameters = [];
                }
                $rule = [$ruleName, $parameters, '_' => $orig];
            }
        }
        return $rules;
    }

    /**
     * Builds textual representation of a rule
     * @param  array $rule
     * @return string
     */
    protected static function buildRuleString($rule): string
    {
        if (isset($rule['_'])) {
            return $rule['_'];
        }
        list($ruleName, $parameters) = $rule;
        $str = $ruleName;
        if ($parameters) {
            $str .= ':';
            $i = 0;
            foreach ($parameters as $param) {
                if (!is_int($param) && !ctype_digit($param)) {
                    $param = '"' . $param . '"';
                }
                $str .= ($i > 0 ? ',' : '') . $param;
                ++$i;
            }
        }
        return $str;
    }

    /**
     * Get a rule and its parameters for a given attribute.
     *
     * @param  string $field
     * @param  string|array $rules
     * @return array|null
     */
    protected function rule(string $field, $rules): ?array
    {
        if (!isset(static::$rulesParsed[static::class])) {
            static::$rulesParsed[static::class] = [];
        }
        $fieldRulesParsed =& static::$rulesParsed[static::class][$field];
        if ($fieldRulesParsed === null) {
            if (!isset(static::$rules[$field])) {
                return null;
            }
            $fieldRulesParsed = static::parseFieldRules(static::$rules[$field]);
        }
        foreach ($fieldRulesParsed as $rule) {
            list($ruleName, $parameters) = $rule;
            if (in_array($ruleName, $rules)) {
                return [$ruleName, $parameters];
            }
        }
        return null;
    }

    /**
     * Default setter
     *
     * @param string $field Attribute
     * @param string $value Value
     * @return self
     * @throws \Exception
     */
    protected function setProperty(string $field, $value): self
    {
        $this->fieldValidate($field, $value);
        return $this->set($field, $value);
    }

    /**
     * @param string $field
     * @param array|null $rule
     * @return null|string
     */
    protected function errorMessage(string $field, ?array $rule = null): ?string
    {
        $ruleName = strtolower($rule[0]);
        if (strpos($ruleName, 'required') === 0) {
            $ruleName = 'required';
        }
        if ($rule === null) {
            $messageKey = '.unknown';
        } else {
            $messageKey = $field . '.' . $ruleName;
        }
        $message = $this->errorMessages[$messageKey] ?? null;
        if ($message !== null) {
            $message = sprintf($message, $field);
        }
        return $message;
    }
}
