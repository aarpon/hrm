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

require_once dirname(__FILE__) . '/../bootstrap.php';


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
            'Autocrop',
            'SignalNoiseRatio',
            'BackgroundOffsetPercent',
            'NumberOfIterations',
            'OutputFileFormat',
            'MultiChannelOutput',
            'QualityChangeStoppingCriterion',
            'DeconvolutionAlgorithm',
            'ReductionMode',
            'ZStabilization',
            'ChromaticAberration',
            'TStabilization',
            'TStabilizationMethod',   
            'TStabilizationRotation',
            'TStabilizationCropping');

        // Instantiate the Parameter objects
        foreach ($parameterClasses as $class) {

            $className = 'hrm\\param\\' . $class;
            $param = new $className;
            /** @var Parameter $param */
            $name = $param->name();
            $this->parameter[$name] = $param;

            $this->numberOfChannels = NULL;
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
            return False;
        }

        $db = new DatabaseConnection;
        $maxChanCnt = $db->getMaxChanCnt();

        $this->message = '';
        $noErrorsFound = True;

        // Get the names of the relevant parameters
        //$names = $this->taskParameterNames(); @todo Remove since unused.

        // Deconvolution Algorithm - this should always be defined, but since
        // other parameters depend on it, in case it is not defined we return
        // here
        if (!isset($postedParameters["DeconvolutionAlgorithm"])) {
            $this->message = 'Please choose a deconvolution algorithm!';
            return False;
        }

        // Set the Parameter and check the value
        $parameter = $this->parameter("DeconvolutionAlgorithm");
        $parameter->setValue($postedParameters["DeconvolutionAlgorithm"]);
        $this->set($parameter);
        if (!$parameter->check()) {
            $this->message = 'Unknown deconvolution algorithm!';
            return False;
        }
        $algorithm = strtoupper($parameter->value());

        // Signal-To-Noise Ratio
        // Depending on the choice of the deconvolution algorithm, we will
        // check only the relevant entries
        for ($i = 0; $i < $maxChanCnt; $i++) {
            $value[$i] = null;
            $name = "SignalNoiseRatio" . $algorithm . "$i";
            if (isset($postedParameters[$name])) {
                $value[$i] = $postedParameters[$name];
            }
        }
        /** @var SignalNoiseRatio $parameter */
        $parameter = $this->parameter("SignalNoiseRatio");
        $parameter->setValue($value);
        $this->set($parameter);
        if (!$parameter->check()) {
            $this->message = $parameter->message();
            $noErrorsFound = False;
        }

        // Background estimation
        if (!isset($postedParameters["BackgroundEstimationMode"]) ||
            $postedParameters["BackgroundEstimationMode"] == ''
        ) {
            $this->message = 'Please choose a background estimation mode!';
            $noErrorsFound = False;
        } else {
            $value = array_fill(0, $maxChanCnt, null);
            switch ($postedParameters["BackgroundEstimationMode"]) {
                case 'auto':

                    $value[0] = 'auto';
                    break;

                case 'object' :

                    $value[0] = 'object';
                    break;

                case 'manual' :

                    for ($i = 0; $i < $maxChanCnt; $i++) {
                        $value[$i] = null;
                        $name = "BackgroundOffsetPercent$i";
                        if (isset($postedParameters[$name])) {
                            $value[$i] = $postedParameters[$name];
                        }
                    }
                    break;

                default :
                    $this->message = 'Unknown background estimation mode!';
                    $noErrorsFound = False;
            }
            $parameter = $this->parameter("BackgroundOffsetPercent");
            $parameter->setValue($value);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        }

        // Number of iterations
        if (isset($postedParameters["NumberOfIterations"]) ||
            $postedParameters["NumberOfIterations"] == ''
        ) {
            $parameter = $this->parameter("NumberOfIterations");
            $parameter->setValue($postedParameters["NumberOfIterations"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        }

        // Quality change
        if (isset($postedParameters["QualityChangeStoppingCriterion"]) ||
            $postedParameters["QualityChangeStoppingCriterion"] == ''
        ) {
            $parameter = $this->parameter("QualityChangeStoppingCriterion");
            $parameter->setValue($postedParameters["QualityChangeStoppingCriterion"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        }

        // Stabilization in Z
        if (isset($postedParameters["ZStabilization"]) ||
            $postedParameters["ZStabilization"] == ''
        ) {
            $parameter = $this->parameter("ZStabilization");
            $parameter->setValue($postedParameters["ZStabilization"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        }

        // Autocrop
        if (isset($postedParameters["Autocrop"]) ||
            $postedParameters["Autocrop"] == ''
        ) {
            $parameter = $this->parameter("Autocrop");
            $parameter->setValue($postedParameters["Autocrop"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        }

        // ReductionMode
        if (isset($postedParameters["ReductionMode"]) ||
            $postedParameters["ReductionMode"] == ''
        ) {
            $parameter = $this->parameter("ReductionMode");
            $parameter->setValue($postedParameters["ReductionMode"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
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
            return False;
        }

        $this->message = '';
        $noErrorsFound = True;

        foreach ($postedParameters as $name => $param) {
            if (strpos($name, 'ChromaticAberration') === false) {
                continue;
            }
            if ($param != "" && !is_numeric($param)) {
                $noErrorsFound = False;
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
            return False;
        }

        $this->message = '';
        $noErrorsFound = True;
        
        // Stabilization in T
        if (isset($postedParameters["TStabilization"]) ||
            $postedParameters["TStabilization"] == ''
        ) {
            $parameter = $this->parameter("TStabilization");
            $parameter->setValue($postedParameters["TStabilization"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        }

        // Stabilization in T: Method
        if (isset($postedParameters["TStabilizationMethod"]) ||
            $postedParameters["TStabilizationMethod"] == ''
        ) {
            $parameter = $this->parameter("TStabilizationMethod");
            $parameter->setValue($postedParameters["TStabilizationMethod"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        }

        // Stabilization in T: Rotations
        if (isset($postedParameters["TStabilizationRotation"]) ||
            $postedParameters["TStabilizationRotation"] == ''
        ) {
            $parameter = $this->parameter("TStabilizationRotation");
            $parameter->setValue($postedParameters["TStabilizationRotation"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        }

        // Stabilization in T: Cropping
        if (isset($postedParameters["TStabilizationCropping"]) ||
            $postedParameters["TStabilizationCropping"] == ''
        ) {
            $parameter = $this->parameter("TStabilizationCropping");
            $parameter->setValue($postedParameters["TStabilizationCropping"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        }

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
     * @param float|null $timeInterval Sample T (optional).
     * @return string Parameter names and their values as a string.
     */
    public function displayString($numberOfChannels = 0,
                                  $micrType         = NULL,
                                  $timeInterval     = 0)
    {
        $result = '';

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
            if ($parameter->name() == 'ZStabilization'
                && !strstr($micrType, "STED")
            ) {
                continue;
            }
            if ($parameter->name() == 'TStabilization'
              && $timeInterval == 0)
                continue;
            if ($parameter->name() == 'TStabilizationMethod'
              && ($TStabilization == 0 || $timeInterval == 0))
                continue;
            if ($parameter->name() == 'TStabilizationRotation'
              && ($TStabilization == 0 || $timeInterval == 0))
                continue;
            if ($parameter->name() == 'TStabilizationCropping'
              && ($TStabilization == 0 || $timeInterval == 0))
                continue;
            if ($parameter->name() == 'ChromaticAberration'
              && $numberOfChannels == 1)
                continue;
            if ($parameter->name() == 'ReductionMode'
              && !strstr($micrType, "detector-array"))
                continue;
            $result = $result .
                $parameter->displayString($numberOfChannels);
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
            return FALSE;
        }
        if ($paramSetting->parameter("ZStepSize")->value() === '0') {
            return FALSE;
        }
        if (!System::hasLicense("stabilizer")) {
            return FALSE;
        }
        if (!System::hasLicense("sted")) {
            return FALSE;
        }
        if (!System::hasLicense("sted3d")) {
            return FALSE;
        }
        return TRUE;
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
            return FALSE;
        }
        if (!System::hasLicense("stabilizer")) {
            return FALSE;
        }
        return TRUE;
    }


    /**
     * Checks whether the restoration should allow for CAC.
     * @param ParameterSetting $paramSetting An instance of the ParameterSetting
     * class (ignored).
     * @return bool True to enable CAC, false otherwise.
     * @todo Why is this taking a ParameterSetting as an input?
     */
    public function isEligibleForCAC(ParameterSetting $paramSetting)
    {
        if ($this->numberOfChannels() == 1) {
            return FALSE;
        }
        if (!System::hasLicense("chromaticS")) {
            return FALSE;
        }

        return TRUE;
    }


    /**
     * Get the list of templates shared with the given user.
     * @param string $username Name of the user to query for.
     * @return array List of shared templates with the user.
     */
    public static function getTemplatesSharedWith($username)
    {
        $db = new DatabaseConnection();
        $result = $db->getTemplatesSharedWith($username, self::sharedTable());
        return $result;
    }

    /**
     * Get the list of templates shared by the given user.
     * @param string $username Name of the user to query for.
     * @return array List of shared templates by the user.
     */
    public static function getTemplatesSharedBy($username)
    {
        $db = new DatabaseConnection();
        $result = $db->getTemplatesSharedBy($username, self::sharedTable());
        return $result;
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

        $db = new DatabaseConnection;
        $maxChanCnt = $db->getMaxChanCnt();

        // We only look at the first channel for the decon algorithm.
        if (strpos($huArray['cmle:0'], "") === FALSE) {
            $algorithm = $this->parameter('DeconvolutionAlgorithm');
            $algorithm->setValue("cmle");
        } else if (strpos($huArray['qmle:0'], "") === FALSE) {
            $algorithm = $this->parameter('DeconvolutionAlgorithm');
            $algorithm->setValue("qmle");
        } else if (strpos($huArray['gmle:0'], "") === FALSE) {
            $algorithm = $this->parameter('DeconvolutionAlgorithm');
            $algorithm->setValue("gmle");
        }

        // SNR.
        for ($chan = 0; $chan < $maxChanCnt; $chan++) {
            if ($this->parameter('DeconvolutionAlgorithm')->value() == "cmle") {
                $key = "cmle:" . $chan . " sn";
            } else if ($this->parameter('DeconvolutionAlgorithm')->value() == "gmle") {
                $key = "gmle:" . $chan . " sn";
            } else {
                $key = "qmle:" . $chan . " sn";
            }

            if (strpos($huArray[$key], "") === FALSE) {
                $snr[$chan] = $huArray[$key];
            }
        }
        if (isset($snr)) {
            $this->parameter['SignalNoiseRatio']->setValue($snr);
        }

        // Autocrop.
        if (strpos($huArray['autocrop enabled'], "") === FALSE) {
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
            } else if (isset($huArray[$keyQmleBgMode])) {
                $bgMode = $huArray[$keyQmleBgMode];
            } else if (isset($huArray[$keyGmleBgMode])) {
                $bgMode = $huArray[$keyGmleBgMode];
            } else {
                $bgMode = "auto";
            }

            if (isset($huArray[$keyCmleBgVal])) {
                $bgVal = $huArray[$keyCmleBgVal];
            } else if (isset($huArray[$keyQmleBgVal])) {
                $bgVal = $huArray[$keyQmleBgVal];
            } else if (isset($huArray[$keyGmleBgVal])) {
                $bgVal = $huArray[$keyGmleBgVal];
            } else {
                $bgVal = 0.;
            }

            if ($bgMode == "auto" || $bgMode == "object") {
                $bgArr = array_fill(0, $maxChanCnt, $bgMode);
                break;
            } else if ($bgMode == "lowest" || $bgMode == "widefield") {
                $bgArr = array_fill(0, $maxChanCnt, "auto");
                break;
            } else if ($bgMode == "manual") {
                $bgArr[$chan] = $bgVal;
            } else {
                $bgArr = array_fill(0, $maxChanCnt, "auto");
                break;
            }
        }
        $this->parameter['BackgroundOffsetPercent']->setValue($bgArr);

        // Iterations.
        for ($chan = 0; $chan < $maxChanCnt; $chan++) {
            if ($this->parameter('DeconvolutionAlgorithm')->value() == "cmle") {
                $key = "cmle:" . $chan . " it";
            } else if ($this->parameter('DeconvolutionAlgorithm')->value() == "gmle") {
                $key = "gmle:" . $chan . " it";
            } else {
                $key = "qmle:" . $chan . " it";
            }

            if (strpos($huArray[$key], "") === FALSE) {
                $it = $huArray[$key];
                $itOld = $this->parameter['NumberOfIterations']->value();
                if ($it > $itOld) {
                    $this->parameter['NumberOfIterations']->setValue($it);
                }
            }
        }

        // Reduction Mode.
        for ($chan = 0; $chan < $maxChanCnt; $chan++) {
            if ($this->parameter('DeconvolutionAlgorithm')->value() == "cmle") {
                $key = "cmle:" . $chan . " reduceMode";
            } 
            if (strpos($huArray[$key], "") === FALSE) {
                $reductionMode = $huArray[$key];          
                $this->parameter('ReductionMode')->setValue($reductionMode);
                break;      
            }
        }
        

        // Quality factor.
        for ($chan = 0; $chan < $maxChanCnt; $chan++) {
            if ($this->parameter('DeconvolutionAlgorithm')->value() == "cmle") {
                $key = "cmle:" . $chan . " q";
            } else if ($this->parameter('DeconvolutionAlgorithm')->value() == "gmle") {
                $key = "gmle:" . $chan . " q";
            } else {
                $key = "qmle:" . $chan . " q";
            }

            if (strpos($huArray[$key], "") === FALSE) {
                $q = $huArray[$key];
                $key = 'QualityChangeStoppingCriterion';
                $qOld = $this->parameter[$key]->value();
                if ($q > $qOld) {
                    $this->parameter[$key]->setValue($q);
                }
            }
        }

        // Stabilization in Z.
        if (strpos($huArray['stabilize enabled'], "") === FALSE) {
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
        if (strpos($huArray['stabilize:post enabled'], "") === FALSE) {
            $stabilize = $huArray['stabilize:post enabled'];
            $this->parameter['TStabilization']->setValue($stabilize);
        }

        // Stabilization in T: Method
        if (strpos($huArray['stabilize:post mode'], "") === FALSE) {
            $method = $huArray['stabilize:post mode'];
            $this->parameter['TStabilizationMethod']->setValue($method);
        }

        // Stabilization in T: Rotations
        if (strpos($huArray['stabilize:post rot'], "") === FALSE) {
            $rotation = $huArray['stabilize:post rot'];
            $this->parameter['TStabilizationRotation']->setValue($rotation);
        }

        // Stabilization in T: Cropping
        if (strpos($huArray['stabilize:post crop'], "") === FALSE) {
            $cropping = $huArray['stabilize:post crop'];
            $this->parameter['TStabilizationCropping']->setValue($cropping);
        }
    }

}
