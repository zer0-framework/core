<?php

namespace Zer0\Model;

use Symfony\Component\Translation\Translator;
use Zer0\Model\Exceptions\InvalidArgumentException;
use Zer0\Model\Subtypes\Boolean;
use Zer0\Model\Subtypes\Timestamp;

/**
 * Trait ValidationMethods
 * @package Zer0\Model
 */
trait ValidationMethods
{
    /**
     * The size related validation rules.
     *
     * @var array
     */
    protected static $sizeRules = ['Size', 'Between', 'Min', 'Max'];

    /**
     * The numeric related validation rules.
     *
     * @var array
     */
    protected static $numericRules = ['Numeric', 'Integer'];

    /**
     * The validation rules that imply the field is required.
     *
     * @var array
     */
    protected static $implicitRules = [
        'Required',
        'RequiredWith',
        'RequiredWithAll',
        'RequiredWithout',
        'RequiredWithoutAll',
        'RequiredIf',
        'Accepted',
    ];

    /**
     * Convert a value to studly caps case.
     *
     * @param  string $value
     * @return string
     */
    public static function studly($value)
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /**
     * Convert a string to snake case.
     *
     * @param  string $value
     * @param  string $delimiter
     * @return string
     */
    public static function snake($value, $delimiter = '_')
    {
        $key = $value . $delimiter;
        if (!ctype_lower($value)) {
            $value = strtolower(preg_replace('/(.)(?=[A-Z])/', '$1' . $delimiter, $value));
            $value = preg_replace('/\s+/', '', $value);
        }
        return $value;
    }

    /**
     * "Validate" optional attributes.
     *
     * Always returns true, just lets us put sometimes in rules.
     *
     * @return bool
     */
    protected function validateSometimes()
    {
        return true;
    }

    /**
     * Validate the given attribute is filled if it is present.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateFilled($attribute, $value)
    {
        if (array_key_exists($attribute, $this->data)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that a required attribute exists.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateRequired($attribute, $value)
    {
        if (is_null($value)) {
            return false;
        } elseif (is_string($value) && trim($value) === '') {
            return false;
        } elseif ((is_array($value) || $value instanceof \Countable) && count($value) < 1) {
            return false;
        } elseif ($value instanceof File) {
            return (string)$value->getPath() != '';
        }

        return true;
    }

    /**
     * Validate that an attribute exists when any other attribute exists.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  mixed $parameters
     * @return bool
     */
    protected function validateRequiredWith($attribute, $value, $parameters)
    {
        if (!$this->allFailingRequired($parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Determine if all of the given attributes fail the required test.
     *
     * @param  array $attributes
     * @return bool
     */
    protected function allFailingRequired(array $attributes)
    {
        foreach ($attributes as $key) {
            if ($this->validateRequired($key, $this[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate that an attribute exists when all other attributes exists.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  mixed $parameters
     * @return bool
     */
    protected function validateRequiredWithAll($attribute, $value, $parameters)
    {
        if (!$this->anyFailingRequired($parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Determine if any of the given attributes fail the required test.
     *
     * @param  array $attributes
     * @return bool
     */
    protected function anyFailingRequired(array $attributes)
    {
        foreach ($attributes as $key) {
            if (!$this->validateRequired($key, $this[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that an attribute exists when another attribute does not.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  mixed $parameters
     * @return bool
     */
    protected function validateRequiredWithout($attribute, $value, $parameters)
    {
        if ($this->anyFailingRequired($parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute exists when all other attributes do not.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  mixed $parameters
     * @return bool
     */
    protected function validateRequiredWithoutAll($attribute, $value, $parameters)
    {
        if ($this->allFailingRequired($parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute exists when another attribute has a given value.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  mixed $parameters
     * @return bool
     */
    protected function validateRequiredIf($attribute, $value, $parameters)
    {
        $this->requireParameterCount(2, $parameters, 'required_if');

        $data = $this[$parameters[0]];

        $values = array_slice($parameters, 1);

        if (in_array($data, $values)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Require a certain number of parameters to be present.
     *
     * @param  int $count
     * @param  array $parameters
     * @param  string $rule
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function requireParameterCount($count, $parameters, $rule)
    {
        if (count($parameters) < $count) {
            throw new \InvalidArgumentException("Validation rule $rule requires at least $count parameters.");
        }
    }

    /**
     * Validate that an attribute has a matching confirmation.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateConfirmed($attribute, $value)
    {
        return $this->validateSame($attribute, $value, [$attribute . '_confirmation']);
    }

    /**
     * Validate that two attributes match.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateSame($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'same');

        $other = $this[$parameters[0]];

        return isset($other) && $value === $other;
    }

    /**
     * Validate that an attribute is different from another attribute.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateDifferent($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'different');

        $other = $this[$parameters[0]];

        return isset($other) && $value !== $other;
    }

    /**
     * Validate that an attribute was "accepted".
     *
     * This validation rule implies the attribute is "required".
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateAccepted($attribute, $value)
    {
        $acceptable = ['yes', 'on', '1', 1, true, 'true'];

        return $this->validateRequired($attribute, $value) && in_array($value, $acceptable, true);
    }

    /**
     * Validate that an attribute is an array.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateArray($attribute, $value)
    {
        return is_array($value);
    }

    /**
     * Validate that an attribute is a boolean.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateBoolean($attribute, $value)
    {
        $acceptable = [true, false, 0, 1, '0', '1'];
        if ($value instanceof Boolean) {
            $value = $value->getValue();
        }

        return in_array($value, $acceptable, true);
    }

    /**
     * Validate that an attribute is an integer.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateInteger($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate that an attribute is a string.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateString($attribute, $value)
    {
        return is_string($value);
    }

    /**
     * Validate that an attribute is a datetime.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateDatetime($attribute, $value)
    {
        return is_string($value);
    }

    /**
     * Validate the attribute is a valid JSON string.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateJson($attribute, $value)
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Validate that an attribute has a given number of digits.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateDigits($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'digits');

        return $this->validateNumeric($attribute, $value)
            && strlen((string)$value) == $parameters[0];
    }

    /**
     * Validate that an attribute is numeric.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateNumeric($attribute, $value)
    {
        return is_numeric($value);
    }


    /**
     * Validate that an attribute is a timestamp.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateTimestamp($attribute, $value)
    {
        return $value instanceof Timestamp;
    }


    /**
     * Validate that an attribute is between a given number of digits.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateDigitsBetween($attribute, $value, $parameters)
    {
        $this->requireParameterCount(2, $parameters, 'digits_between');

        $length = strlen((string)$value);

        return $this->validateNumeric($attribute, $value)
            && $length >= $parameters[0] && $length <= $parameters[1];
    }

    /**
     * Validate the size of an attribute.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateSize($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'size');

        return $this->_getSize($attribute, $value) == $parameters[0];
    }

    /**
     * Get the size of an attribute.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return mixed
     */
    protected function _getSize($attribute, $value)
    {
        $hasNumeric = $this->hasRule($attribute, static::$numericRules);

        // This method will determine if the attribute is a number, string, or file and
        // return the proper size accordingly. If it is a number, then number itself
        // is the size. If it is a file, we take kilobytes, and for a string the
        // entire length of the string will be considered the attribute size.
        if (is_numeric($value) && $hasNumeric) {
            return $value;
        } elseif (is_array($value)) {
            return count($value);
        } elseif ($value instanceof File) {
            return $value->_getSize() / 1024;
        }

        return mb_strlen($value);
    }

    /**
     * Determine if the given attribute has a rule in the given set.
     *
     * @param  string $attribute
     * @param  string|array $rules
     * @return bool
     */
    protected function hasRule($attribute, $rules)
    {
        return !is_null($this->rule($attribute, $rules));
    }

    /**
     * Validate the size of an attribute is between a set of values.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateBetween($attribute, $value, $parameters)
    {
        $this->requireParameterCount(2, $parameters, 'between');

        $size = $this->_getSize($attribute, $value);

        return $size >= $parameters[0] && $size <= $parameters[1];
    }

    /**
     * Validate the size of an attribute is greater than a minimum value.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateMin($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'min');

        return $this->_getSize($attribute, $value) >= $parameters[0];
    }

    /**
     * Validate the size of an attribute is less than a maximum value.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateMax($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'max');

        if ($value instanceof UploadedFile && !$value->isValid()) {
            return false;
        }

        return $this->_getSize($attribute, $value) <= $parameters[0];
    }

    /**
     * Validate an attribute is not contained within a list of values.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateNotIn($attribute, $value, $parameters)
    {
        return !$this->validateIn($attribute, $value, $parameters);
    }

    /**
     * Validate an attribute is contained within a list of values.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateIn($attribute, $value, $parameters)
    {
        if (is_array($value) && $this->hasRule($attribute, 'Array')) {
            return count(array_diff($value, $parameters)) == 0;
        }

        return !is_array($value) && in_array((string)$value, $parameters);
    }

    /**
     * Validate the uniqueness of an attribute value on a given database table.
     *
     * If a database column is not specified, the attribute will be used.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateUnique($attribute, $value, $parameters)
    {
        return true; // @TODO
    }

    /**
     * Validate that an attribute is a valid IP.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateIp($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate that an attribute is a valid e-mail address.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateEmail($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate that an attribute is a valid URL.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateUrl($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate the MIME type of a file is an image MIME type.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateImage($attribute, $value)
    {
        return $this->validateMimes($attribute, $value, ['jpeg', 'png', 'gif', 'bmp', 'svg']);
    }

    /**
     * Validate the guessed extension of a file upload is in a set of file extensions.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateMimes($attribute, $value, $parameters)
    {
        if (!$this->isAValidFileInstance($value)) {
            return false;
        }

        return $value->getPath() != '' && in_array($value->guessExtension(), $parameters);
    }

    /**
     * Check that the given value is a valid file instance.
     *
     * @param  mixed $value
     * @return bool
     */
    protected function isAValidFileInstance($value)
    {
        if ($value instanceof UploadedFile && !$value->isValid()) {
            return false;
        }

        return $value instanceof File;
    }

    /**
     * Validate the MIME type of a file upload attribute is in a set of MIME types.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateMimetypes($attribute, $value, $parameters)
    {
        if (!$this->isAValidFileInstance($value)) {
            return false;
        }

        return $value->getPath() != '' && in_array($value->getMimeType(), $parameters);
    }

    /**
     * Validate that an attribute contains only alphabetic characters.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateAlpha($attribute, $value)
    {
        return is_string($value) && preg_match('/^[\pL\pM]+$/u', $value);
    }

    /**
     * Validate that an attribute contains only alpha-numeric characters.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateAlphaNum($attribute, $value)
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        return preg_match('/^[\pL\pM\pN]+$/u', $value);
    }

    /**
     * Validate that an attribute contains only alpha-numeric characters, dashes, and underscores.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateAlphaDash($attribute, $value)
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        return preg_match('/^[\pL\pM\pN_-]+$/u', $value);
    }

    /**
     * Validate that an attribute passes a regular expression check.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateRegex($attribute, $value, $parameters)
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        $this->requireParameterCount(1, $parameters, 'regex');

        return preg_match($parameters[0], $value);
    }

    /**
     * Validate that an attribute is a valid date.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateDate($attribute, $value)
    {
        if ($value instanceof \DateTime) {
            return true;
        }

        if (strtotime($value) === false) {
            return false;
        }

        $date = date_parse($value);

        return checkdate($date['month'], $date['day'], $date['year']);
    }

    /**
     * Validate that an attribute matches a date format.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateDateFormat($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'date_format');

        $parsed = date_parse_from_format($parameters[0], $value);

        return $parsed['error_count'] === 0 && $parsed['warning_count'] === 0;
    }

    /**
     * Validate the date is before a given date.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateBefore($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'before');

        if ($format = $this->dateFormat($attribute)) {
            return $this->validateBeforeWithFormat($format, $value, $parameters);
        }

        if (!($date = strtotime($parameters[0]))) {
            return strtotime($value) < strtotime($this[$parameters[0]]);
        }

        return strtotime($value) < $date;
    }

    /**
     * Get the date format for an attribute if it has one.
     *
     * @param  string $attribute
     * @return string|null
     */
    protected function dateFormat($attribute)
    {
        if ($result = $this->rule($attribute, 'DateFormat')) {
            return $result[1][0];
        }
    }

    /**
     * Validate the date is before a given date with a given format.
     *
     * @param  string $format
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateBeforeWithFormat($format, $value, $parameters)
    {
        $param = $this[$parameters[0]] ?: $parameters[0];

        return $this->checkDateTimeOrder($format, $value, $param);
    }

    /**
     * Given two date/time strings, check that one is after the other.
     *
     * @param  string $format
     * @param  string $before
     * @param  string $after
     * @return bool
     */
    protected function checkDateTimeOrder($format, $before, $after)
    {
        $before = $this->dateTimeWithOptionalFormat($format, $before);

        $after = $this->dateTimeWithOptionalFormat($format, $after);

        return ($before && $after) && ($after > $before);
    }

    /**
     * Get a DateTime instance from a string.
     *
     * @param  string $format
     * @param  string $value
     * @return \DateTime|null
     */
    protected function dateTimeWithOptionalFormat($format, $value)
    {
        $date = \DateTime::createFromFormat($format, $value);

        if ($date) {
            return $date;
        }

        try {
            return new \DateTime($value);
        } catch (\Exception $e) {
            //
        }
    }

    /**
     * Validate the date is after a given date.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateAfter($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'after');

        if ($format = $this->dateFormat($attribute)) {
            return $this->validateAfterWithFormat($format, $value, $parameters);
        }

        if (!($date = strtotime($parameters[0]))) {
            return strtotime($value) > strtotime($this[$parameters[0]]);
        }

        return strtotime($value) > $date;
    }

    /**
     * Validate the date is after a given date with a given format.
     *
     * @param  string $format
     * @param  mixed $value
     * @param  array $parameters
     * @return bool
     */
    protected function validateAfterWithFormat($format, $value, $parameters)
    {
        $param = $this[$parameters[0]] ?: $parameters[0];

        return $this->checkDateTimeOrder($format, $param, $value);
    }

    /**
     * Validate that an attribute is a valid timezone.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    protected function validateTimezone($attribute, $value)
    {
        try {
            new \DateTimeZone($value);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Get the data type of the given attribute.
     *
     * @param  string $attribute
     * @return string
     */
    protected function attributeType($attribute)
    {
        // We assume that the attributes present in the file array are files so that
        // means that if the attribute does not have a numeric rule and the files
        // list doesn't have it we'll just consider it a string by elimination.
        if ($this->hasRule($attribute, static::$numericRules)) {
            return 'numeric';
        } elseif ($this->hasRule($attribute, ['Array'])) {
            return 'array';
        } elseif (array_key_exists($attribute, $this->files)) {
            return 'file';
        }

        return 'string';
    }
}
