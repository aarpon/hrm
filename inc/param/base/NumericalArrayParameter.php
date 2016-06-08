<?php
/**
 * NumericalArrayParameter
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param\base;

use hrm\DatabaseConnection;

require_once dirname(__FILE__) . '/../../bootstrap.inc.php';

/**
 * Class for a Parameter that has an array of numbers as possible value,
 * where each entry represents a channel.
 *
 * @package hrm
 */
class NumericalArrayParameter extends NumericalParameter
{

    /**
     * Number of channels for which to provide NumericalArrayParameter values.
     * @var int
     */
    protected $numberOfChannels;

    /**
     * NumericalArrayParameter constructor: creates an empty NumericalArrayParameter.
     * @param string $name Name of the new NumericalArrayParameter.
     */
    public function __construct($name)
    {
        parent::__construct($name);
        $this->reset();
    }

    /**
     * Confirms that the Parameter can have a variable number of channels.
     *
     * This overloads the base function
     *
     * @return bool Always true.
     */
    public function isVariableChannel()
    {
        return True;
    }

    /**
     * Sets the Parameter value(s) to empty.
     */
    public function reset()
    {
        $db = new DatabaseConnection;

        for ($i = 0; $i < $db->getMaxChanCnt(); $i++) {
            $this->value[$i] = NULL;
        }

        $this->numberOfChannels = 1;
    }

    /**
     * Sets the number of channels.
     * @param int $number Number of channels.
     */
    public function setNumberOfChannels($number)
    {

        $db = new DatabaseConnection;
        $maxChanCnt = $db->getMaxChanCnt();

        if ($number == $this->numberOfChannels) {
            return;
        }
        if ($number < 1) {
            $number = 1;
        }
        if ($number > $maxChanCnt) {
            $number = $maxChanCnt;
        }
        for ($i = $number; $i < $maxChanCnt; $i++) {
            $this->value[$i] = NULL;
        }
        $this->numberOfChannels = $number;
    }

    /**
     * Returns the number of channels
     * @return int The umber of channels.
     */
    public function numberOfChannels()
    {
        return $this->numberOfChannels;
    }

    /**
     * Checks whether all values in the NumericalArrayParameter are valid.
     *
     * Each value in the NumericalArrayParameter must be a number and might
     * optionally have to be larger than or equal to a given minum value and
     * smaller than or equal to a given maximum.
     *
     * @return bool True if all values are valid, false otherwise.
     */
    public function check()
    {
        $this->message = '';
        $result = True;
        // First check that all values are set
        if (array_search("", array_slice($this->value,
                0, $this->numberOfChannels)) !== FALSE
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
        // Now check the values themselves
        for ($i = 0; $i < $this->numberOfChannels; $i++) {
            $result = $result && parent::checkValue($this->value[$i]);
        }
        return $result;
    }

    /**
     * Sets the value of the NumericalArrayParameter.
     *
     * The value must be an array with 'maxChanCnt' values (those who refer to
     * non-existing channels should be null).
     *
     * @todo Notice that right now we do not force the input argument to
     * be an array, since this requires a large refactoring that will be
     * done in a later stage.
     *
     * @param array $value Array of values for the NumericalArrayParameter.
     */
    public function setValue($value)
    {
        $db = new DatabaseConnection;
        $maxChanCnt = $db->getMaxChanCnt();

        $n = count($value);
        for ($i = 0; $i < $maxChanCnt; $i++) {
            if ($i < $n) {
                $this->value[$i] = $value[$i];
            } else {
                $this->value[$i] = null;
            }
        }
    }

    /**
     * Returns the string representation of the NumericalArrayParameter.
     * @param int $numberOfChannels Number of channels.
     * @return string String representation of the NumericalArrayParameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        $value = array_slice($this->value, 0, $numberOfChannels);
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
