<?php
/**
 * NumericalVectorParameter
 *
 * @package hrm
 * @subpackage param\base
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param\base;

require_once dirname(__FILE__) . '/../../bootstrap.inc.php';

/**
 * Class NumericalVectorParameter
 *
 * Class for a channel Parameter consisting of N components.
 *
 * @package hrm\param
 */
class NumericalVectorParameter extends NumericalParameter
{

    /**
     * Number of components in the vector.
     * @var int
     */
    public $componentCnt;

    /**
     * Constructor: creates an empty NumericalVectorParameter.
     * @param string $name Name of the new NumericalVectorParameter.
     * @param int $componentCnt Number of components for the NumericalVectorParameter.
     */
    public function __construct($name, $componentCnt)
    {
        parent::__construct($name);
        $this->reset($componentCnt);
    }

    /**
     * Sets the NumericalVectorParameter value(s) to null.
     * @param int $componentCnt Number of components for the NumericalVectorParameter.
     */
    public function reset($componentCnt)
    {
        $this->componentCnt = $componentCnt;
        for ($i = 1; $i <= $componentCnt; $i++) {
            $this->value[$i] = NULL;
        }
    }

    /**
     * Checks whether all values in the NumericalVectorParameter are valid.
     *
     * Each value in the NumericalVectorParameter must be a number and might
     * optionally have to be larger than or equal to a given minimum value and
     * smaller than or equal to a given maximum.
     * @return bool True if all values are valid, false otherwise.
     */
    public function check()
    {
        $this->message = '';
        $result = True;

        /* First check that all values are set. */
        if (array_search("", array_slice($this->value,
                0, $this->componentCnt)) !== FALSE
        ) {
            if ($this->mustProvide()) {
                $this->message = 'Some of the values are missing!';
            } else {
                $this->message = 'You can omit typing values for this ' .
                    'parameter. If you decide to provide them, though, ' .
                    'you must provide them all.';
            }
            return false;
        }

        /* Now check the values themselves. */
        for ($i = 0; $i < $this->componentCnt; $i++) {
            // @todo parent::check() does not take input arguments!
            $result &= parent::check($this->value[$i]);
        }

        return $result;
    }

    /**
     * Sets the value of the NumericalVectorParameter.
     *
     * The value must be an array with as many components as $componentCnt
     * @param array $value Array of values for the NumericalVectorParameter.
     */
    public function setValue($value)
    {
        $n = count($value);
        for ($i = 0; $i < $this->componentCnt; $i++) {
            if ($i < $n) {
                $this->value[$i] = $value[$i];
            } else {
                $this->value[$i] = null;
            }
        }
    }

    /**
     * Function for retrieving a string representation of the NumericalVectorParameter.
     * @return string A '#'-separated string denoting the NumericalVectorParameter components.
     */
    public function value()
    {
        $result = "";

        for ($i = 0; $i < $this->componentCnt; $i++) {
            $result .= "#";
            if (isset($this->value[$i])) {
                $result .= $this->value[$i];
            }
        }

        return $result;
    }

    /**
     * Returns the string representation of the NumericalVectorParameter.
     * @return string String representation of the NumericalVectorParameter.
     */
    public function displayString()
    {
        ksort($this->value);
        $value = array_slice($this->value, 0, $this->componentCnt);
        $value = implode($value, ', ');
        $result = $this->formattedName();
        if ($this->notSet()) {
            $result = $result . "*not set*" . "\n";
        } else {
            $result = $result . $value . "\n";
        }

        return $result;
    }

}
