<?php
/**
 * NumericalParameter
 *
 * @package hrm
 * @subpackage param\base
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param\base;

use hrm\DatabaseConnection;

require_once dirname(__FILE__) . '/../../bootstrap.inc.php';

/**
 * Class NumericalParameter
 *
 * Class for a Parameter that has a scalar number as possible value.
 *
 * @package hrm\param
 */
class NumericalParameter extends Parameter
{

    /**
     * Minimum possible value for the NumericalParameter.
     * @var int|float|null
     */
    protected $min;

    /**
     * Maximum possible value for the NumericalParameter.
     * @var int|float|null
     */
    protected $max;

    /**
     * If true, the value must be checked against the minimum.
     * @var bool
     */
    protected $checkMin;

    /**
     *  If true, the value must be checked against the maximum.
     * @var bool
     */
    protected $checkMax;

    /**
     * If true, the value must be >= than the minimum value, otherwise it
     * must be > the minimum value.
     * @var bool
     */
    protected $isMinIncluded;

    /**
     * If true, the value must be <= than the maximum value, otherwise it
     * must be < the maximum value.
     * @var bool
     */
    protected $isMaxIncluded;

    /**
     * Constructor: creates an empty NumericalParameter
     * @param string $name Name of the new NumericalParameter.
     */
    public function __construct($name)
    {
        parent::__construct($name);

        $this->min = NULL;
        $this->max = NULL;
        $this->checkMin = False;
        $this->checkMax = False;
        $this->isMinIncluded = True;
        $this->isMaxIncluded = True;

        // Gets the Parameter's possible values, default value and all
        // boundary values from the database and sets them
        $db = new DatabaseConnection;
        $values = $db->readNumericalValueRestrictions($this);
        $min = intval($values[0]);
        $max = intval($values[1]);
        $minIncluded = $values[2];
        $maxIncluded = $values[3];
        $default = $values[4];
        if ($min != NULL) {
            $this->setMin($min);
        }
        if ($max != NULL) {
            $this->setMax($max);
        }
        if ($minIncluded == 't') {
            $this->isMinIncluded = True;
        } else {
            $this->isMinIncluded = False;
        }
        if ($maxIncluded == 't') {
            $this->isMaxIncluded = True;
        } else {
            $this->isMaxIncluded = False;
        }
        if ($default != NULL) {
            if (count($default) == 1) {
                $default = intval($default);
            }
            // @todo The inheriting classes will call their setValue()
            // method here. The value should be an array, but this is not
            // the case for StedImmunity.
            $this->setValue($default);
        }
    }

    /**
     * Set the minimum allowed value for the NumericalParameter.
     *
     * The value itself may be allowed or not.
     *
     * @param int|float @value Minimum value.
     */
    public function setMin($value)
    {
        $this->min = $value;
        $this->checkMin = True;
    }

    /**
     * Set the maximum allowed value for the NumericalParameter.
     *
     * The value itself may be allowed or not.
     *
     * @param int|float @value Maximum value.
     */
    public function setMax($value)
    {
        $this->max = $value;
        $this->checkMax = True;
    }

    /**
     * Checks whether the NumericalParameter value should be checked against
     * its minimum value.
     * @return bool True if the value has to be checked against its minimum
     * value, false otherwise.
     */
    public function checkMin()
    {
        return $this->checkMin;
    }

    /**
     * Checks whether the NumericalParameter value should be checked against
     * its maximum value.
     * @return bool True if the value has to be checked against its maximum
     * value, false otherwise.
     */
    public function checkMax()
    {
        return $this->checkMax;
    }

    /**
     * Returns the minimum allowed value for the NumericalParameter.
     * @return int|float|null The minimum allowed value.
     */
    public function min()
    {
        return $this->min;
    }

    /**
     * Returns the maximum allowed value for the NumericalParameter.
     * @return int|float|null The maximum allowed value.
     */
    public function max()
    {
        return $this->max;
    }

    /**
     * Checks whether the NumericalParameter is valid.
     * @return bool True if the NumericalParameter is valid, false otherwise.
     */
    public function check()
    {
        $this->message = '';
        return ($this->checkValue($this->value));
    }

    /**
     * Checks whether the value is valid.
     *
     * The value of a NumericalParameter must be a number and might optionally
     * have to be larger than or equal to a given minum value and smaller than
     * or equal to a given maximum.
     * @param int|float $value Value to be checked.
     * @return bool True if the value is valid, false otherwise.
     */
    protected function checkValue($value)
    {
        if (is_array($value)) {
            $this->message = "Scalar expected.\n";
            return False;
        }
        if (is_numeric($value) == 0) {
            $this->message = "The value must be numeric.\n";
            return False;
        }
        if ($this->isMinIncluded) {
            if ($this->checkMin && !((float)$value >= $this->min)) {
                $this->message = "The value must be >= $this->min.";
                return False;
            }
        }
        if (!$this->isMinIncluded) {
            if ($this->checkMin && !((float)$value > $this->min)) {
                $this->message = "The value must be > $this->min.";
                return False;
            }
        }
        if ($this->isMaxIncluded) {
            if ($this->checkMax && !((float)$value <= $this->max)) {
                $this->message = "The value must be <= $this->max.";
                return False;
            }
        }
        if (!$this->isMaxIncluded) {
            if ($this->checkMax && !((float)$value < $this->max)) {
                $this->message = "The value must be < $this->max.";
                return False;
            }
        }
        return True;
    }

    /**
     * Sets the value of the NumericalParameter.
     *
     * The value must be a scalar.
     *
     * @param int|float $value Value for the NumericalParameter.
     */
    public function setValue($value)
    {
        if (is_array($value)) {
            $value = $value[0];
        }
        $this->value = $value;
    }

    /**
     * Returns the string representation of the NumericalParameter.
     * @param int $numberOfChannels Number of channels.
     * @return string String representation of the NumericalParameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        $result = $this->formattedName();
        if ($this->notSet()) {
            $result = $result . "*not set*" . "\n";
        } else {
            $result = $result . $this->value . "\n";
        }
        return $result;
    }

}
