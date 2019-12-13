<?php
/**
 * DeconvolutionAlgorithm
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\DatabaseConnection;
use hrm\param\base\AnyTypeArrayParameter;

/**
 * An AnyTypeArrayParameter to represent the DeconvolutionAlgorithm.
 *
 * @package hrm
 */
class DeconvolutionAlgorithm extends AnyTypeArrayParameter
{

    /**
     * DeconvolutionAlgorithm constructor.
     */
    public function __construct()
    {
        parent::__construct("DeconvolutionAlgorithm");
    }

    /**
     * Confirms that this is NOT a Microscope Parameter.
     *
     * @return bool Always false.
     */
    public function isForMicroscope()
    {
        return False;
    }

    /**
     * Checks whether the Parameter is a Task Parameter.
     * @return bool Always true/
     */
    public function isTaskParameter()
    {
        return True;
    }

    /**
     * Returns the Parameter translated value.
     *
     * The translated form of the Parameter value is then one used in
     * the Tcl script. The translation of the deconvolution algorithm is
     * read from the database.
     * @return mixed translated value.
     */
    public function translatedValue()
    {
        $db = new DatabaseConnection();
        $result = $db->translationFor($this->name, $this->value);
        return $result;
    }

    /**
     * Checks whether the Parameter is valid.
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        for ($ch = 0; $ch < $this->numberOfChannels(); $ch++) {
            if ($this->value[$ch] == NULL) {
                $this->message = "Please select a deconvolution algorithm " .
                                 "for channel $ch!";
                return False;
            }
        }
        return True;
    }


    public function defaultValue()
    {
        $db = DatabaseConnection::get();
        $name = $this->name();
        $default = $db->defaultValue($name);
        return $default;
    }

    /**
     * Sets the value of the DeconvolutionAlgorithm.
     *
     * The value must be an array with 'maxChanCnt' values (those who refer to
     * non-existing channels should be null).
     *
     * Override the parent method in order to provide support for both the old 
     * DeconvolutionAlgorithm (one value for all channels) and the new one.
     *
     * @param array $value Array of values for the NumericalArrayParameter.
     */
    public function setValue($value)
    {
        $db = DatabaseConnection::get();
        $maxChanCnt = $db->getMaxChanCnt();

        if (is_array($value)) {
            $n = count($value);
            for ($i = 0; $i < $maxChanCnt; $i++) {
                if ($i < $n) {
                    if (isset($value[$i])) {
                        $this->value[$i] = $value[$i];
                    } else {
                        $this->value[$i] = $this->defaultValue();
                    }
                } else {
                    $this->value[$i] = null;
                }
            }
        } else {
            /* Previously this class had only one algorithm for all the channels. 
            Currently, however, one can set one algorithm per channel. To make the 
            transition from the old situation to the new one, repeat the selected 
            algorithm for all channels. */ 
            $this->value = array_fill(0, $maxChanCnt - 1, $value);
        }
    }
}
