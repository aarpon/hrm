<?php
/**
 * ChromaticAberration
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\DatabaseConnection;
use hrm\param\base\NumericalVectorParameter;
use hrm\param\base\Parameter;

/**
 * A multi-channel, vector parameter to characterize the chromatic aberration.
 *
 * @todo This did not inherit from any base class. Now it inherits from Parameter.
 * Make sure that it still works as expected!
 *
 *
 * @package hrm
 */
class ChromaticAberration extends Parameter
{

    /**
     * The aberration value. An array with one element per channel and vector component.
     * @var array
     */
    public $value;

    /**
     * The number of channels for which a vector is needed.
     * @var int
     */
    public $chanCnt;

    /**
     * The numer of vector components used to describe the CA. Currently 5.
     * @var int
     */
    public $componentCnt;

    /**
     * ChromaticAberration constructor.
     *
     * This method does NOT call the parent constructor!
     *
     * @todo: Provide an input argument $chanCnt with the number of channels of
     * the data set.
     */
    public function __construct()
    {

        $this->name = "ChromaticAberration";

        /* 5 components for shift x, y, z, rotation and scale. */
        $this->componentCnt = 5;

        $db = new DatabaseConnection;
        $this->chanCnt = $db->getMaxChanCnt();

        // Add a NumericalVectorParameter per channel
        for ($chan = 0; $chan < $this->chanCnt; $chan++) {
            $this->value[$chan] = new NumericalVectorParameter(
                $this->name() . "Ch" . $chan, $this->componentCnt());
        }
    }

    /**
     * A function for retrieving the number of elements of the vector.
     * @return int The number of vector elements.
     */
    public function componentCnt()
    {
        return $this->componentCnt;
    }

    /**
     * Checks whether the Parameter is a Task Parameter.
     * @return bool Always true.
     */
    public function isTaskParameter()
    {
        return True;
    }

    /**
     * Confirms that the Parameter can have a variable number of channels.
     * This overloads the base function.
     * @return bool Always true.
     */
    public function isVariableChannel()
    {
        return True;
    }

    /**
     * The string representation of the Parameter.
     * @param int $chanCnt The number of channels.
     * @return string String representation of the Parameter.
     */
    public function displayString($chanCnt)
    {
        $result = "";

        if (!is_numeric($chanCnt)) {
            $db = new DatabaseConnection;
            $chanCnt = $db->getMaxChanCnt();
        }

        for ($i = 0; $i < $chanCnt; $i++) {
            $result .= $this->value[$i]->displayString();
        }

        return $result;
    }

    /**
     * A function to set the parameter value (this is taken from the browser
     * session or from the database).
     * @param string $values A '#' formatted string or an array with the CA
     * components.
     */
    public function setValue($values)
    {

        if (!is_array($values)) {
            /* The first element of the array will be empty due to the explode. */
            $valuesArray = explode('#', $values);
            unset($valuesArray[0]);
        } else {
            $valuesArray = $values;
        }

        if (empty($valuesArray) || is_null($valuesArray)) {
            return;
        }

        for ($chan = 0; $chan < $this->chanCnt; $chan++) {
            $offset = $chan * $this->componentCnt;
            $chanArray = array_slice($valuesArray, $offset, $this->componentCnt);
            $this->value[$chan]->setValue($chanArray);
        }
    }

    /**
     * A function for retrieving the parameter value for all channels.
     * @return array An array with one component per channel and vector element.
     */
    public function value()
    {
        $valuesArray = explode('#', $this->internalValue());

        /* The first element of the array will be empty due to the explode. */
        unset($valuesArray[0]);

        /* Re-index with array_values. */
        return array_values($valuesArray);
    }

    /**
     * A function for retrieving the parameter value for a specific channel
     * @param int $chan The requested channel.
     * @return array An array with one component per vector element.
     */
    public function chanValue($chan)
    {
        $valuesArray = $this->value();
        $offset = $chan * $this->componentCnt;
        $chanArray = array_slice($valuesArray, $offset, $this->componentCnt);

        return $chanArray;
    }

    /**
     * A function to set the number of channels for the correction.
     * @param int $chanCnt The number of channels.
     */
    public function setNumberOfChannels($chanCnt)
    {
        $this->chanCnt = $chanCnt;
    }

    /**
     * Returns the default value for the Parameters that have a default value
     * or NULL for those that don't.
     * @return mixed The default value or null
     */
    public function defaultValue()
    {
        $db = new DatabaseConnection;
        $name = $this->name();
        $default = $db->defaultValue($name);
        return ($default);
    }

    /**
     * Returns the internal value of the ChromaticAberration.
     * @return string The internal value of the Parameter: a # formatted string.
     */
    public function internalValue()
    {
        $result = "";

        for ($i = 0; $i < $this->chanCnt; $i++) {
            $result .= $this->value[$i]->value();
        }

        return $result;
    }

    /**
     * Checks whether the Parameter is valid.
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        // @todo Implement check() method.
        return true;
    }
}
