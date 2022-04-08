<?php
/**
 * SignalNoiseRatio
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\DatabaseConnection;
use hrm\param\base\NumericalArrayParameter;

/**
 * A NumericalArrayParameter to represent the SNR per channel.
 *
 * @package hrm@param
 */
class SignalNoiseRatio extends NumericalArrayParameter
{

    /**
     * The array of chosen deconvolution algorithms. One per channel.
     * @var string
     */
    private $algorithm;

    /**
     * SignalNoiseRatio constructor.
     */
    public function __construct()
    {
        parent::__construct("SignalNoiseRatio");
        $this->algorithm = 'cmle';
    }

    /**
     * Checks whether the Parameter is a Task Parameter
     * @return bool Always true.
     */
    public function isTaskParameter()
    {
        return True;
    }

    /**
     * Checks whether the Parameter is valid
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        $result = parent::check();
        if ($result == false) {
            $this->message = "SNR: " . $this->message;
        }
        return $result;
    }

    /**
     * Sets the deconvolution algorithm.
     * @param string $algorithm Sets the algorithm, 'cmle'/'qmle'/'gmle/skip'.
     */
    public function setAlgorithm($algorithm)
    {
        $this->algorithm = $algorithm;
    }

    /**
     * Returns the string representation of the Parameter for the cmle/qmle/gmle
     * algorithm
     *
     * The algorithm is stored internally in the Parameter and is either 'cmle'
     * (default, set when the Parameter is instantiated),or 'qmle'.
     *
     * @param int $numberOfChannels Number of channels (default 0)
     * @return string String representation of the Parameter for the cmle/qmle/gmle algorithm.
     */
    public function displayString($numberOfChannels = 0)
    {
        $result = $this->formattedName();
        
        if (!is_numeric($numberOfChannels)) {
            $db = DatabaseConnection::get();
            $numberOfChannels = $db->getMaxChanCnt();
        }
        
        for ($ch = 0; $ch < $numberOfChannels; $ch++) {
            $snrChan = "*not set*";

            switch ($this->algorithm[$ch]) {
                case "skip":
                    $snrChan = "-";
                    break;
                case "qmle":  
                case "gmle":
                case "cmle":                
                default:
                    if (isset($this->value[$ch]) && $this->value[$ch] != "") {
                        $snrChan = $this->value[$ch];
                    }                 
            }

            $result .= $snrChan;            
            if ($ch != $numberOfChannels - 1) {
                $result .= ", ";
            }
        }

        $result .= "\n";

        return $result;
    }
}
