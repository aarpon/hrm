<?php
/**
 * NumericalVectorParameter
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param\base;

/**
 * Class for a channel Parameter consisting of N components.
 *
 * @package hrm
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
        $this->componentCnt = $componentCnt;
        $this->reset();
    }

    /**
     * Sets the NumericalVectorParameter value(s) to null.
     */
    public function reset()
    {        
        for ($i = 1; $i <= $this->componentCnt; $i++) {
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
     * @todo This method calls parent::check() passing one argument, but
     * parent::check() does not take input arguments!
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
    public function setValue($values)
    {
        foreach ($values as $i => $value) {
            if ($i < $this->componentCnt) {
                $this->value[$i] = $value;
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
    public function displayString($chanCnt = 0)
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
