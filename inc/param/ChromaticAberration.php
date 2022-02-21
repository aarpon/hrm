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
 * A single-channel, vector parameter to characterize the chromatic aberration.
 * Has one instance per channel.
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
     * The channel this vector discribes.
     * @var int
     */
    public $channel;

    
    /**
     * The maximum numer of vector components used to describe the CA.
     * @var int
     */
    public $maxComponentCnt;


    /**
     * The numer of vector components used to describe the CA. Currently 5 or
     * 14.
     * @var int
     */
    public $componentCnt;

    /**
     * ChromaticAberration constructor.
     *
     * This method does NOT call the parent constructor!
     *
     */
    public function __construct($ch)
    {

        $this->name = "ChromaticAberrationCh" . $ch;
        $this->channel = $ch;
        
        /* 14 components for shift x, y, z, rotation,  scaleX, scaleY, scaleZ,
           angleX, angleY, barrelPincushion1, barrelPincushion2,
           barrelPincushion3, barrelPincushionXcenter,
           barrelPincushionYcenter. Return either the first 5 or all 14
           parametes, depending on the input. Default to componentCnt 5.*/
        $this->maxComponentCnt = 14;
        $this->componentCnt = 5;

        // Add a NumericalVectorParameter per channel with maxComponentCnt size.
        $this->value = new NumericalVectorParameter(
            $this->name(), $this->maxComponentCnt());
    }

    /**
     * A function for retrieving the maximum number of elements of the vector.
     * @return int The number of vector elements.
     */
    public function maxComponentCnt()
    {
        return $this->maxComponentCnt;
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
     * A function for retrieving the number of elements to show of the vector.
     * @return int The number of vector elements.
     */
    public function shownComponentCnt()
    {
        return $this->componentCnt();
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
     * @return string String representation of the Parameter.
     */
    public function displayString($chanCnt = 0)
    {
        // Don't show anything when the channel is irrelevant.
        if ($this->channel > $chanCnt) {
            return "";
        }
        return rtrim($this->value->displayString(), ", \n\r\t\v\x00"). "\n";
    }

    /**
     * A function to set the parameter value (this is taken from the browser
     * session or from the database).
     * @param string $values A '#' formatted string or an array with the CA
     * components.
     */
    public function setValue($values)
    {
        // If it comes from the database explode it into an array.
        if (!is_array($values)) {
            /* The first element of the array will be empty due to the explode. */
            $valuesArray = explode('#', $values);
            unset($valuesArray[0]);
            $valuesArray = array_values($valuesArray);
        } else {
            $valuesArray = $values;
        }

        if (empty($valuesArray) || is_null($valuesArray)) {
            return;
        }
        
        // If all the values are 0 it is the 14 parameter reference, set it
        // like the 5 parameter reference so it is properly recognized.
        $isReference = 1;
        foreach ($valuesArray as $val) {
            if (floatval($val) != 0.0) {
                $isReference = 0;
                break;
            }
        }
        if ($isReference) {
            $valuesArray = array("0","0","0","0","1",null,null,null,
                                 null,null,null,null,null,null);
        }
        
        //error_log(implode('_', $valuesArray));
        $this->value->setValue($valuesArray);
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
     * A function to set the number of channels for the correction.
     * @param int $chanCnt The number of channels.
     *
     * TODO: not necessary?
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
        $db = DatabaseConnection::get();
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
        return $this->value->value();
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
