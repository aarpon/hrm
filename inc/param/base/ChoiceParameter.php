<?php
/**
 * ChoiceParameter
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
 * The ChoiceParameter can assume a limited number of possible values.
 *
 * @package hrm
 */
abstract class ChoiceParameter extends Parameter
{

    /**
     * Possible values for the ChoiceParameter.
     * @var array
     */
    protected $possibleValues;

    /**
     * ChoiceParameter constructor> creates an empty ChoiceParameter.
     * @param string $name Name of the new ChoiceParameter.
     */
    protected function __construct($name)
    {
        parent::__construct($name);

        // Get and set the Parameter possible values
        $db = new DatabaseConnection;
        $values = $db->readPossibleValues($this);
        $this->possibleValues = $values;

        // Get and set the Parameter default value
        $defaultValue = $this->defaultValue();
        if ($defaultValue !== NULL) {
            $this->value = $defaultValue;
        }
    }

    /**
     * Returns the possible values for the Parameter.
     * @return array The possible values.
     */
    public function possibleValues()
    {
        return $this->possibleValues;
    }

    /**
     * Returns the possible values for the Parameter as a comma-separated string.
     * @return string The possible values as a comma-separated string.
     */
    public function possibleValuesString()
    {
        $string = '';
        $values = $this->possibleValues();
        foreach ($values as $each) {
            $string = $string . $each;
            if (end($values) != $each) {
                $string = $string . ", ";
            }
        }
        return $string;
    }

    /**
     * Checks whether the Parameter is valid.
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        $this->message = '';
        $result = in_array($this->value, $this->possibleValues());
        if ($result == False) {
            $this->message = 'Bad value ' . $this->value() . ' for ' . $this->name();
        }
        return $result;
    }

    /**
     * Returns the string representation of the Parameter.
     * @param int $numberOfChannels Number of channels (default 0).
     * @return string String representation of the Parameter.
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

    /**
     * Returns the default value for the Parameters that have a default
     * value ot NULL for those that don't.
     *
     * This function should be <b>overloaded</b> by the subclasses
     * @return mixed The default value or NULL.
     */
    public function defaultValue()
    {
        $db = new DatabaseConnection;
        $name = $this->name();
        $default = $db->defaultValue($name);
        return ($default);
    }

}
