<?php
/**
 * SignalNoiseRatio
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalArrayParameter;

/**
 * Class SignalNoiseRatio
 *
 * A NumericalParameter to represent the SNR per channel.
 *
 * @package hrm@param
 */
class SignalNoiseRatio extends NumericalArrayParameter
{

    /**
     * The deconvolution algorithm chosen.
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
     * @param string $algorithm Sets the algorithm, either 'cmle' or 'qmle'.
     */
    public function setAlgorithm($algorithm)
    {
        $this->algorithm = $algorithm;
    }

    /**
     * Returns the string representation of the Parameter for the cmle or qmle
     * algorithm
     *
     * The algorithm is stored internally in the Parameter and is either 'cmle'
     * (default, set when the Parameter is instantiated),or 'qmle'.
     *
     * @param int $numberOfChannels Number of channels (default 0)
     * @return string String representation of the Parameter for the cmle or
     * qmle algorithm.
     */
    public function displayString($numberOfChannels = 0)
    {
        switch ($this->algorithm) {
            case "qmle":
                $snr = array("1" => "low", "2" => "fair", "3" => "good", "4" => "inf");
                $value = array_slice($this->value, 0, $numberOfChannels);
                $val = array();
                for ($i = 0; $i < $numberOfChannels; $i++) {
                    $val[$i] = $snr[$value[$i]];
                }
                $value = implode(", ", $val);
                $result = $this->formattedName();
                return $result . $value . "\n";
                break;
            case "cmle" :
            default:
                return (parent::displayString($numberOfChannels));
                break;
        }
    }

}
