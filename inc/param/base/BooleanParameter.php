<?php
/**
 * BooleanParameter
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
 * Class for a Parameter that has only true and false as possible value.
 *
 * @package hrm
 */
class BooleanParameter extends ChoiceParameter
{

    /**
     * Constructor: creates an empty Parameter
     * @param string $name Name of the new Parameter.
     */
    public function __construct($name)
    {
        parent::__construct($name);
        $this->possibleValues = array('True', 'False');
        $this->value = 'False';
    }

    /**
     * Checks whether the value is true.
     * @return bool True if the value of the BooleanParameter is "True", false
     * otherwise.
     */
    public function isTrue()
    {
        return ($this->value == "True");
    }

    /**
     * Checks whether the Parameter is a BooleanParameter.
     * @return bool True if the Parameter is a BooleanParameter, false otherwise.
     */
    public function isBoolean()
    {
        return True;
    }

    /**
     * Returns the string representation of the BooleanParameter.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the BooleanParameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        if ($this->value() == True) {
            $value = 'yes';
        } else {
            $value = 'no';
        }
        $result = $this->formattedName();
        $result = $result . $value . "\n";
        return $result;
    }

    /**
     * Checks whether the BooleanParameter is valid.
     * @return bool True if the BooleanParameter is valid, false otherwise.
     */
    public function check()
    {
        $this->message = '';
        $result = in_array($this->value, array('True', 'False'));
        if ($result == False) {
            $this->message = 'Bad value ' . $this->value() . ' for ' . $this->name();
        }
        return $result;
    }

}
