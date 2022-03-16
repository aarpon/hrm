<?php
/**
 * TaskSetting
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\setting;

use hrm\DatabaseConnection;
use hrm\param\base\Parameter;
use hrm\param\SignalNoiseRatio;
use hrm\setting\base\Setting;
use hrm\System;

/**
 * A TaskSetting is a complete set of restoration parameters.
 *
 * @package hrm
 */
class TaskSetting extends Setting
{

    /**
     * TaskSetting constructor.
     */
    public function __construct()
    {
        // Call the parent constructor.
        parent::__construct();

        // @todo Retrieve this information from the database.
        $parameterClasses = array(
            'Acuity',
            'AcuityMode',
            'Autocrop',
            'SignalNoiseRatio',
            'BackgroundOffsetPercent',
            'NumberOfIterations',
            'OutputFileFormat',
            'MultiChannelOutput',
            'QualityChangeStoppingCriterion',
            'DeconvolutionAlgorithm',
            'ArrayDetectorReductionMode',
            'ZStabilization',
            'ChromaticAberration',
            'TStabilization',
            'TStabilizationMethod',
            'TStabilizationRotation',
            'TStabilizationCropping',
	    'HotPixelCorrection');

        // Instantiate the Parameter objects
        foreach ($parameterClasses as $class) {
            $className = 'hrm\\param\\' . $class;
            $param = new $className;
            /** @var Parameter $param */
            $name = $param->name();
            $this->parameter[$name] = $param;
            $this->numberOfChannels = null;
        }
    }

    /**
     * Returns the name of the database table in which the list of
     * Setting names are stored.
     *
     * Besides the name, the table contains the Setting's name, owner and
     * the standard (default) flag.
     * @return string The name of the table.
     */
    public static function table()
    {
        return "task_setting";
    }

    /**
     * Returns the name of the database table in which the list of
     * shared Setting names are stored.
     * @return string The name of the shared table.
     */
    public static function sharedTable()
    {
        return "shared_task_setting";
    }

    /**
     * Returns the name of the database table in which all the Parameters
     * for the Settings stored in the table specified in table().
     * @return string The name of the parameter table.
     * @see table()
     */
    public static function parameterTable()
    {
        return "task_parameter";
    }

    /**
     * Returns the name of the database table to use for sharing settings.
     * @return string The name of the shared parameter table.
     * @see sharedTable()
     */
    public static function sharedParameterTable()
    {
        return "shared_task_parameter";
    }

    /**
     * Checks that the posted Task Parameters are all defined and valid.
     * @param array $postedParameters The array of posted parameters.
     * @return bool True if all Parameters are defined and valid, false
     * otherwise.
     */
    public function checkPostedTaskParameters(array $postedParameters)
    {
        if (count($postedParameters) == 0) {
            $this->message = '';
            return false;
        }

        $db = DatabaseConnection::get();
        $maxChanCnt = $db->getMaxChanCnt();

        // Initialize the $value array to store deconvolution algorithms
        $value = array();
        for ($i = 0; $i < $maxChanCnt; $i++) {
            $value[$i] = null;
        }

        $this->message = '';
        $noErrorsFound = true;

        // Deconvolution Algorithm - this should always be defined, but since
        // other parameters depend on it, in case it is not defined we return
        // here
        $skipDeconAll = true;
        for ($i = 0; $i < $maxChanCnt; $i++) {
            $value[$i] = null;
            if (isset($postedParameters["DeconvolutionAlgorithm$i"])) {
                $value[$i] = $postedParameters["DeconvolutionAlgorithm$i"];
                unset($postedParameters["DeconvolutionAlgorithm$i"]);

                if ($value[$i] != "skip") {
                    $skipDeconAll = false;
                }
            }
        }

        $name = 'DeconvolutionAlgorithm';

        // Is there at least one channel with an assigned deconvolution algorithm?
        $valueSet = count(array_filter($value)) > 0;
    
        if ($valueSet) {
            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);
    
            // Keep the 'deconAlgorithms' so that it can be checked below if any
            // parameters need to be forced, e.g when 'QMLE'.
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = false;
            } else {
                $deconAlgorithms = $parameter->value();
            }
        } else {
            // In this case it is important to know whether the Parameter
            // must have a value or not
            $parameter = $this->parameter($name);
            $mustProvide = $parameter->mustProvide();
    
            // Reset the Parameter
            $parameter->reset();
            $this->set($parameter);
    
            // If the Parameter value must be provided, we return an error
            if ($mustProvide) {
                $this->message = "Please set the Deconvolution Algorithm!";
                $noErrorsFound = false;
            }
        }

        // Signal-To-Noise Ratio
        // Depending on the choice of the deconvolution algorithm, we will
        // check only the relevant entries
        for ($i = 0; $i < $maxChanCnt; $i++) {
            $value[$i] = null;
            $name = "SignalNoiseRatio" . strtoupper($deconAlgorithms[$i]) . "$i";
            if (isset($postedParameters[$name])) {
                // We need to set a default value in case of skipped channels
                // so that the parameter can get processed. Unfortunately,
                // there's no default for this parameter in the DB.
                if ($deconAlgorithms[$i] == "skip") {
                    $value[$i] = 20;
                } else {
                    $value[$i] = $postedParameters[$name];
                }
            }
        }

        /** @var SignalNoiseRatio $parameter */
        $parameter = $this->parameter("SignalNoiseRatio");
        $parameter->setValue($value);
        $this->set($parameter);
        if (!$skipDeconAll && !$parameter->check()) {
            $this->message = $parameter->message();
            $noErrorsFound = false;
        }

        
        // Acuity mode
        if (isset($postedParameters["AcuityMode"]) || $postedParameters["AcuityMode"] == '') {
            $parameter = $this->parameter("AcuityMode");
            $parameter->setValue($postedParameters["AcuityMode"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = false;
            }
        }

        // Acuity
        for ($i = 0; $i < $maxChanCnt; $i++) {
            $value[$i] = null;
            $name = "Acuity$i";
            if (isset($postedParameters[$name])) {
                // We need to set a default value in case of skipped channels
                // so that the parameter can get processed. Unfortunately,
                // there's no default for this parameter in the DB. We also
                // default to this value if acuity mode is disabled.
                if ($deconAlgorithms[$i] == "skip" || $parameter->value() == "off") {
                    $value[$i] = "0";
                } else {
                    $value[$i] = $postedParameters[$name];
                }
            }
        }

        /** @var Acuity $parameter */
        $parameter = $this->parameter("Acuity");
        $parameter->setValue($value);
        $this->set($parameter);
        if (!$skipDeconAll && !$parameter->check()) {
            $this->message = $parameter->message();
            $noErrorsFound = false;
        }

        // Background estimation
        if (!isset($postedParameters["BackgroundEstimationMode"]) || $postedParameters["BackgroundEstimationMode"] == '') {
            $this->message = 'Please choose a background estimation mode!';
            $noErrorsFound = false;
        } else {
            $value = array_fill(0, $maxChanCnt, null);
            switch ($postedParameters["BackgroundEstimationMode"]) {
                case 'auto':
                    $value[0] = 'auto';
                    break;

                case 'object':
                    $value[0] = 'object';
                    break;

                case 'manual':
                    for ($i = 0; $i < $maxChanCnt; $i++) {
                        $value[$i] = null;
                        $name = "BackgroundOffsetPercent$i";
                        if (isset($postedParameters[$name])) {
                            $value[$i] = $postedParameters[$name];
                        }
                    }
                    break;

                default:
                    $this->message = 'Unknown background estimation mode!';
                    $noErrorsFound = false;
            }
            $parameter = $this->parameter("BackgroundOffsetPercent");
            $parameter->setValue($value);
            $this->set($parameter);
            if (!$skipDeconAll && !$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = false;
            }
        }

        // Number of iterations
        if (isset($postedParameters["NumberOfIterations"]) || $postedParameters["NumberOfIterations"] == '') {
            $parameter = $this->parameter("NumberOfIterations");
            $parameter->setValue($postedParameters["NumberOfIterations"]);
            $this->set($parameter);
            if (!$skipDeconAll && !$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = false;
            }
        }

        // Quality change
        if (isset($postedParameters["QualityChangeStoppingCriterion"]) || $postedParameters["QualityChangeStoppingCriterion"] == '') {
            $parameter = $this->parameter("QualityChangeStoppingCriterion");
            $parameter->setValue($postedParameters["QualityChangeStoppingCriterion"]);
            $this->set($parameter);
            if (!$skipDeconAll && !$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = false;
            }
        }

        // Stabilization in Z
        if (isset($postedParameters["ZStabilization"]) || $postedParameters["ZStabilization"] == '') {
            $parameter = $this->parameter("ZStabilization");
            $parameter->setValue($postedParameters["ZStabilization"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = false;
            }
        }

        // Autocrop
        if (isset($postedParameters["Autocrop"]) || $postedParameters["Autocrop"] == '') {
            $parameter = $this->parameter("Autocrop");
            $parameter->setValue($postedParameters["Autocrop"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = false;
            }
        }

        // ArrayDetectorReductionMode
        if (isset($postedParameters["ArrayDetectorReductionMode"]) || $postedParameters["ArrayDetectorReductionMode"] == '') {
            $parameter = $this->parameter("ArrayDetectorReductionMode");
            $parameter->setValue($postedParameters["ArrayDetectorReductionMode"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = false;
            }
        }

        return $noErrorsFound;
    }

    /**
     * Checks that the posted Aberration Correction Parameters are defined.
     * This correction is optional.
     * @param array $postedParameters The array of posted parameters.
     * @return bool True if all Parameters are defined and valid, false
     * otherwise.
     */
    public function checkPostedChromaticAberrationParameters(array $postedParameters)
    {
        if (count($postedParameters) == 0) {
            $this->message = '';
            return false;
        }

        $this->message = '';
        $noErrorsFound = true;

        foreach ($postedParameters as $name => $param) {
            if (strpos($name, 'ChromaticAberration') === false) {
                continue;
            }
            if ($param != "" && !is_numeric($param)) {
                $noErrorsFound = false;
                $this->message = "Value must be numeric";
                break;
            }
        }

        if (!$noErrorsFound) {
            return $noErrorsFound;
        }

        $parameter = $this->parameter("ChromaticAberration");

        /* The posted parameters are received in increasing 'chan component'
           order. */
        $i = 0;
        foreach ($postedParameters as $name => $param) {
            if (strpos($name, 'ChromaticAberration') === false) {
                continue;
            }

            $valuesArray[$i] = $param;
            $i++;
        }

        $parameter->setValue($valuesArray);

        return $noErrorsFound;
    }

    /**
     * Checks that the posted T Stabilization Parameters are defined.
     * This correction is optional.
     * @param array $postedParameters The array of posted parameters.
     * @return bool True if all Parameters are defined and valid, false
     * otherwise.
     */
    public function checkPostedTStabilizationParameters(array $postedParameters)
    {
        if (count($postedParameters) == 0) {
            $this->message = '';
            return false;
        }

        $this->message = '';
        $noErrorsFound = true;
        
        // Stabilization in T
        if (isset($postedParameters["TStabilization"]) || $postedParameters["TStabilization"] == '') {
            $parameter = $this->parameter("TStabilization");
            $parameter->setValue($postedParameters["TStabilization"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = false;
            }
        }

        // Stabilization in T: Method
        if (isset($postedParameters["TStabilizationMethod"]) || $postedParameters["TStabilizationMethod"] == '') {
            $parameter = $this->parameter("TStabilizationMethod");
            $parameter->setValue($postedParameters["TStabilizationMethod"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = false;
            }
        }

        // Stabilization in T: Rotations
        if (isset($postedParameters["TStabilizationRotation"]) || $postedParameters["TStabilizationRotation"] == '') {
            $parameter = $this->parameter("TStabilizationRotation");
            $parameter->setValue($postedParameters["TStabilizationRotation"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = false;
            }
        }

        // Stabilization in T: Cropping
        if (isset($postedParameters["TStabilizationCropping"]) || $postedParameters["TStabilizationCropping"] == '') {
            $parameter = $this->parameter("TStabilizationCropping");
            $parameter->setValue($postedParameters["TStabilizationCropping"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = false;
            }
        }

        return $noErrorsFound;
    }


    /**
     * Checks that the posted Hot Pixel Correction Parameters are defined.
     * This correction is optional.
     * @param array $postedParameters The array of posted parameters.
     * @return bool True if all Parameters are defined and valid, false
     * otherwise.
     */
     // For now this is a dummy function as any hot pixel choice should be accepted. 
    public function checkPostedHotPixelCorrectionParameters(array $postedParameters)
    {
        if (count($postedParameters) == 0) {
            $this->message = '';
            return false;
        }

        $this->message = '';
        $noErrorsFound = true;
        
        return $noErrorsFound;
    }


    /**
     * Returns all Task Parameter names.
     * @return array Array of Task Parameter names.
     */
    public function taskParameterNames()
    {
        $names = array();
        /** @var Parameter $parameter */
        foreach ($this->parameter as $parameter) {
            if ($parameter->isTaskParameter()) {
                $names[] = $parameter->name();
            }
        }
        return $names;
    }

    /**
     * Returns the number of channels of the Setting
     * @return int The number of channels for the Setting.
     */
    public function numberOfChannels()
    {
        return $this->numberOfChannels;
    }

    /**
     * Displays the setting as a text containing Parameter names
     * and their values
     * @param int $numberOfChannels Number of channels (optional, default
     * value is 0)
     * @param string|null $micrType Microscope type (optional).
     * @param int $timeInterval Sample T (optional).
     * @return string Parameter names and their values as a string.
     */
    public function displayString($numberOfChannels = 0, $micrType = null, $timeInterval = 0)
    {
        $result = '';

        if ($numberOfChannels == 0) {
            $numberOfChannels = $this->numberOfChannels();
        }
        
        // These parameters are important to properly display other parameters.
        $algorithm = $this->parameter('DeconvolutionAlgorithm')->value();
        $TStabilization = $this->parameter('TStabilization')->value();
        foreach ($this->parameter as $parameter) {
            /** @var SignalNoiseRatio $parameter */
            if ($parameter->name() == 'SignalNoiseRatio') {
                $parameter->setAlgorithm($algorithm);
            }
            if ($parameter->name() == 'OutputFileFormat') {
                continue;
            }
            if ($parameter->name() == 'MultiChannelOutput') {
                continue;
            }
            if ($parameter->name() == 'ZStabilization' && !strstr($micrType, "STED")) {
                continue;
            }
            if ($parameter->name() == 'TStabilization' && $timeInterval == 0) {
                continue;
            }
            if ($parameter->name() == 'TStabilizationMethod' && ($TStabilization == 0 || $timeInterval == 0)) {
                continue;
            }
            if ($parameter->name() == 'TStabilizationRotation' && ($TStabilization == 0 || $timeInterval == 0)) {
                continue;
            }
            if ($parameter->name() == 'TStabilizationCropping' && ($TStabilization == 0 || $timeInterval == 0)) {
                continue;
            }
            if ($parameter->name() == 'ChromaticAberration' && $numberOfChannels == 1) {
                continue;
            }
            if ($parameter->name() == 'ArrayDetectorReductionMode' && !strstr($micrType, "array detector confocal")) {
                continue;
            }
            $result = $result . $parameter->displayString($numberOfChannels);
        }
        return $result;
    }

    /**
     * Checks whether the restoration should allow for Z stabilization.
     * @param ParameterSetting $paramSetting An instance of the ParameterSetting
     * class.
     * @return bool True to enable stabilization option, false otherwise.
     */
    public function isEligibleForZStabilization(ParameterSetting $paramSetting)
    {

        if (!$paramSetting->isSted() && !$paramSetting->isSted3D()) {
            return false;
        }
        if ($paramSetting->parameter("ZStepSize")->value() === '0') {
            return false;
        }
        if (!System::hasLicense("stabilizer")) {
            return false;
        }
        if (!System::hasLicense("sted")) {
            return false;
        }
        if (!System::hasLicense("sted3d")) {
            return false;
        }
        return true;
    }


    /**
     * Checks whether the restoration should allow for T stabilization.
     * @param ParameterSetting $paramSetting An instance of the ParameterSetting
     * class.
     * @return bool True to enable stabilization option, false otherwise.
     */
    public function isEligibleForTStabilization(ParameterSetting $paramSetting)
    {
        if ($paramSetting->parameter("TimeInterval")->value() === '0') {
            return false;
        }
        if (!System::hasLicense("stabilizer")) {
            return false;
        }
        return true;
    }


    /**
     * Checks whether the restoration should allow for CAC.
     * @return bool True to enable CAC, false otherwise.
     */
    public function isEligibleForCAC()
    {
        if ($this->numberOfChannels() == 1) {
            return false;
        }
        if (!System::hasLicense("chromaticS")) {
            return false;
        }

        return true;
    }


    /**
     * Checks whether the restoration should allow for array reduction.
     * @param ParameterSetting $paramSetting An instance of the ParameterSetting
     * class.
     * @return bool True to enable array reduction, false otherwise.
     */
    public function isEligibleForArrayReduction(ParameterSetting $paramSetting)
    {
        if (!$paramSetting->isArrDetConf()) {
            return false;
        }
        if (!System::hasLicense("detector-array")) {
            return false;
        }

        return true;
    }


    /**
     * Get the list of templates shared with the given user.
     * @param string $username Name of the user to query for.
     * @return array List of shared templates with the user.
     */
    public static function getTemplatesSharedWith($username)
    {
        $db = DatabaseConnection::get();
        return $db->getTemplatesSharedWith($username, self::sharedTable());
    }

    /**
     * Get the list of templates shared by the given user.
     * @param string $username Name of the user to query for.
     * @return array List of shared templates by the user.
     */
    public static function getTemplatesSharedBy($username)
    {
        $db = DatabaseConnection::get();
        return $db->getTemplatesSharedBy($username, self::sharedTable());
    }


    /**
     * Parse Huygens parameters to HRM parameters.
     * @param array $huArray An array with the result of 'image setp -tclReturn'.
     */
    public function parseParamsFromHuCore(array $huArray)
    {

        // Sanity checks: remove trailing spaces.
        foreach ($huArray as $key => $value) {
            $huArray[$key] = trim($value, " ");
        }

        $db = DatabaseConnection::get();
        $maxChanCnt = $db->getMaxChanCnt();

        // We only look at the first 6 channels for the decon algorithm.
        $algorithm = $this->parameter('DeconvolutionAlgorithm');
        $algArray = array();
        for ($ch = 0; $ch < $maxChanCnt; $ch++) {
            if (isset($huArray['cmle:' . $ch])) {
                $algArray[$ch] = "cmle";
            } elseif (isset($huArray['qmle:' . $ch])) {
                $algArray[$ch] = "qmle";
            } elseif (isset($huArray['gmle:' . $ch])) {
                $algArray[$ch] = "gmle";
            } elseif (isset($huArray['deconSkip:' . $ch])) {
                $algArray[$ch] = "skip";
            } else {
                $algArray[$ch] = "cmle";
            }
        }
        $algorithm->setValue($algArray);

        // SNR.
        for ($chan = 0; $chan < $maxChanCnt; $chan++) {
            if ($algArray[$chan] == "cmle") {
                $key = "cmle:" . $chan . " snr";
            } elseif ($algArray[$chan] == "gmle") {
                $key = "gmle:" . $chan . " snr";
            } elseif ($algArray[$chan] == "qmle") {
                $key = "qmle:" . $chan . " snr";
            }
            
            if (isset($huArray[$key])) {
                $snr[$chan] = $huArray[$key];
            }
        }
        
        if (isset($snr)) {
            $this->parameter['SignalNoiseRatio']->setValue($snr);
        }
        
        // Acuity.
  
        for ($chan = 0; $chan < $maxChanCnt; $chan++) {
            if ($algArray[$chan] == "cmle") {
                $key = "cmle:" . $chan . " acuity";
            } elseif ($algArray[$chan] == "gmle") {
                $key = "gmle:" . $chan . " acuity";
            } elseif ($algArray[$chan] == "qmle") {
                $key = "qmle:" . $chan . " acuity";
            }
            
            if (isset($huArray[$key])) {
                $acuity[$chan] = $huArray[$key];
                $this->parameter['Acuity']->setValue($acuity);
            }
        }
        
        // Acuity mode. Turn it off unless there is at least one channel for
        // which a value of on has been provided. 
        
        $globalAcuityMode = 'off';
        for ($chan = 0; $chan < $maxChanCnt; $chan++) {
            if ($algArray[$chan] == "cmle") {
                $key = "cmle:" . $chan . " acuityMode";
            } elseif ($algArray[$chan] == "gmle") {
                $key = "gmle:" . $chan . " acuityMode";
            } elseif ($algArray[$chan] == "qmle") {
                $key = "qmle:" . $chan . " acuityMode";
            }

            if (isset($huArray[$key])) {
                $acuityMode = $huArray[$key];
                
                switch ($acuityMode) {
                case 'auto':
                    break;
                case 'on':
                    $globalAcuityMode = 'on';
                    break;
                case 'off':
                    if ($globalAcuityMode != "on") $globalAcuityMode = 'off';
                    break;
                default:
                    $this->message = 'Unknown acuity mode!';
                    $noErrorsFound = false;
                }
            }
        }
        $this->parameter['AcuityMode']->setValue($globalAcuityMode);

        // Autocrop.
        if (isset($huArray['autocrop enabled'])) {
            $autocrop = $huArray['autocrop enabled'];
            $this->parameter['Autocrop']->setValue($autocrop);
        }

        // Background.
        // Set it to manual only if all channels are specified.
        // Otherwise set it to the first other mode encountered.
        for ($chan = 0; $chan < $maxChanCnt; $chan++) {
            $keyCmleBgMode = "cmle:" . $chan . " bgMode";
            $keyQmleBgMode = "qmle:" . $chan . " bgMode";
            $keyGmleBgMode = "gmle:" . $chan . " bgMode";
            $keyCmleBgVal = "cmle:" . $chan . " bg";
            $keyQmleBgVal = "qmle:" . $chan . " bg";
            $keyGmleBgVal = "gmle:" . $chan . " bg";

            if (isset($huArray[$keyCmleBgMode])) {
                $bgMode = $huArray[$keyCmleBgMode];
            } elseif (isset($huArray[$keyQmleBgMode])) {
                $bgMode = $huArray[$keyQmleBgMode];
            } elseif (isset($huArray[$keyGmleBgMode])) {
                $bgMode = $huArray[$keyGmleBgMode];
            } else {
                $bgMode = "auto";
            }

            if (isset($huArray[$keyCmleBgVal])) {
                $bgVal = $huArray[$keyCmleBgVal];
            } elseif (isset($huArray[$keyQmleBgVal])) {
                $bgVal = $huArray[$keyQmleBgVal];
            } elseif (isset($huArray[$keyGmleBgVal])) {
                $bgVal = $huArray[$keyGmleBgVal];
            } else {
                $bgVal = 0.;
            }

            if ($bgMode == "auto" || $bgMode == "object") {
                $bgArr = array_fill(0, $maxChanCnt, $bgMode);
                break;
            } elseif ($bgMode == "lowest" || $bgMode == "widefield") {
                $bgArr = array_fill(0, $maxChanCnt, "auto");
                break;
            } elseif ($bgMode == "manual") {
                $bgArr[$chan] = $bgVal;
            } else {
                $bgArr = array_fill(0, $maxChanCnt, "auto");
                break;
            }
        }
        $this->parameter['BackgroundOffsetPercent']->setValue($bgArr);

        // Iterations.
        $itMax = 0;
        for ($chan = 0; $chan < $maxChanCnt; $chan++) {
            if ($algArray[$chan] == "cmle") {
                $key = "cmle:" . $chan . " it";
            } elseif ($algArray[$chan] == "gmle") {
                $key = "gmle:" . $chan . " it";
            } elseif ($algArray[$chan] == "qmle") {
                $key = "qmle:" . $chan . " it";
            }

            if (isset($huArray[$key])) {
                $it = $huArray[$key];
                if ($it > $itMax) {
                    $itMax = $it;
                }
            }
        }
        $this->parameter['NumberOfIterations']->setValue($itMax);

        // Array Detector Reduction Mode.
        for ($chan = 0; $chan < $maxChanCnt; $chan++) {
            if ($algArray[$chan] == "cmle") {
                $key = "cmle:" . $chan . " reduceMode";
            }
            if (isset($huArray[$key])) {
                $reductionMode = $huArray[$key];
                $this->parameter('ArrayDetectorReductionMode')->setValue($reductionMode);
                break;
            }
        }
        
        // Quality factor. The lower the more stringent.
        $qMin = PHP_INT_MAX;
        for ($chan = 0; $chan < $maxChanCnt; $chan++) {
            if ($algArray[$chan] == "cmle") {
                $key = "cmle:" . $chan . " q";
            } elseif ($algArray[$chan] == "gmle") {
                $key = "gmle:" . $chan . " q";
            } elseif ($algArray[$chan] == "qmle") {
                $key = "qmle:" . $chan . " q";
            }

            if (isset($huArray[$key])) {
                $q = $huArray[$key];
                if ($q < $qMin) {
                    $qMin = $q;
                }
            }
        }
        $this->parameter["QualityChangeStoppingCriterion"]->setValue($qMin);


        // Stabilization in Z.
        if (isset($huArray['stabilize enabled'])) {
            $stabilize = $huArray['stabilize enabled'];
            $this->parameter['ZStabilization']->setValue($stabilize);
        }

        // Chromatic Aberration.
        $compCnt = 5;
        for ($chan = 0; $chan < $maxChanCnt; $chan++) {
            $key = "shift:" . $chan . " vector";

            unset($vector);
            if (isset($huArray[$key])) {
                $vector = explode(" ", $huArray[$key], $compCnt);
            }

            for ($comp = 0; $comp < $compCnt; $comp++) {
                $compKey = $chan * $compCnt + $comp;

                if (isset($vector[$comp])) {
                    $aberration[$compKey] = $vector[$comp];
                } else {
                    if ($comp < $compCnt - 1) {
                        $aberration[$compKey] = 0.;
                    } else {
                        // Scale component.
                        $aberration[$compKey] = 1.;
                    }
                }
            }
        }
        if (isset($aberration)) {
            $this->parameter['ChromaticAberration']->setValue($aberration);
        }

        // Stabilization in T.
        if (isset($huArray['stabilize:post enabled'])) {
            $stabilize = $huArray['stabilize:post enabled'];
            $this->parameter['TStabilization']->setValue($stabilize);
        }

        // Stabilization in T: Method
        if (isset($huArray['stabilize:post mode'])) {
            $method = $huArray['stabilize:post mode'];
            $this->parameter['TStabilizationMethod']->setValue($method);
        }

        // Stabilization in T: Rotations
        if (isset($huArray['stabilize:post rot'])) {
            $rotation = $huArray['stabilize:post rot'];
            $this->parameter['TStabilizationRotation']->setValue($rotation);
        }

        // Stabilization in T: Cropping
        if (isset($huArray['stabilize:post crop'])) {
            $cropping = $huArray['stabilize:post crop'];
            $this->parameter['TStabilizationCropping']->setValue($cropping);
        }
    }
}
