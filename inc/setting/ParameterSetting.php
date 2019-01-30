<?php
/**
 * ParameterSetting
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\setting;

use hrm\DatabaseConnection;
use hrm\HuygensTools;
use hrm\param\base\Parameter;
use hrm\param\CCDCaptorSizeX;
use hrm\param\CCDCaptorSizeY;
use hrm\param\MicroscopeType;
use hrm\param\PinholeSize;
use hrm\param\PSF;
use hrm\setting\base\Setting;

require_once dirname(__FILE__) . '/../bootstrap.php';

/**
 * A ParameterSetting is a complete set of microscope, image, SPIM, STED,
 * aberration correction, pixel size calculation and capture parameters.
 *
 * @package hrm
 */
class ParameterSetting extends Setting {

    /**
     * ParameterSetting constructor.
     */
    public function __construct() {

        // Call the parent constructor.
        parent::__construct();

        // @todo Retrieve this information from the database.
        $parameterClasses = array(
            'IsMultiChannel',
            'ImageFileFormat',
            'NumberOfChannels',
            'MicroscopeType',
            'NumericalAperture',
            'ObjectiveMagnification',
            'ObjectiveType',
            'SampleMedium',
            'Binning',
            'ExcitationWavelength',
            'EmissionWavelength',
            'CMount',
            'TubeFactor',
            'CCDCaptorSize',
            'CCDCaptorSizeX',
            'CCDCaptorSizeY',
            'ZStepSize',
            'TimeInterval',
            'PinholeSize',
            'PinholeSpacing',
            'PointSpreadFunction',
            'PSF',
            'CoverslipRelativePosition',
            'AberrationCorrectionNecessary',
            'AberrationCorrectionMode',
            'AdvancedCorrectionOptions',
            'StedDepletionMode',
            'StedSaturationFactor',
            'StedWavelength',
            'StedImmunity',
            'Sted3D',
            'SpimExcMode',
            'SpimGaussWidth',
            'SpimFocusOffset',
            'SpimCenterOffset',
            'SpimNA',
            'SpimFill',
            'SpimDir'
        );

        // Instantiate the Parameter objects
        // Please mind that the full class name with namespace must be provided.
        foreach ($parameterClasses as $class) {
            $className = 'hrm\\param\\' . $class;
            $param = new $className;
            /** @var Parameter $param */
            $name = $param->name();
            $this->parameter[$name] = $param;
        }
    }

    /**
     * Returns the name of the database table in which the list of Setting names
     * are stored.
     *
     * Besides the name, the table contains the Setting's name, owner and the
     * standard (default) flag.
     *
     * @return string The table name.
     */
    public static function table() {
        return "parameter_setting";
    }

    /**
     * Returns the name of the database table in which the list of shared
     * Setting names are stored.
     *
     * @return string The shared table name.
     */
    public static function sharedTable() {
        return "shared_parameter_setting";
    }

    /**
     * Returns the name of the database table in which all the Parameters
     * for the Settings stored in the table specified in table().
     * @return string The parameter table name.
     * @see table()
    */
    public static function parameterTable() {
        return "parameter";
    }

    /**
     * Returns the name of the database table to use for sharing settings.
     * @return string The shared parameter table name.
     * @see sharedTable()
    */
    public static function sharedParameterTable() {
        return "shared_parameter";
    }

    /**
     * A general check on  the status of the image parameter setting and its
     * compatibility with the selected image format.
     * @return bool True if the setting is OK, false otherwise.
    */
    public function checkParameterSetting( ) {

        $ok = True;

        /* Initialization: among others, create an array where to
           accumulate the microscopic parameters.*/
        $postedParams = array();

        $db = new DatabaseConnection();
        $imageFormat = $this->parameter("ImageFileFormat")->value();

        /* Loop over the values of this setting's parameters. */
        foreach ($this->parameter as $objName => $objInstance) {

            switch ( $objName ) {
                case 'SpimExcMode':
                case 'SpimGaussWidth':
                case 'SpimFocusOffset':
                case 'SpimCenterOffset':
                case 'SpimNA':
                case 'SpimFill':
                case 'SpimDir':
                case "StedDepletionMode" :
                case "StedWavelength" :
                case "StedSaturationFactor" :
                case "StedImmunity" :
                case "Sted3D" :
                case "ExcitationWavelength" :
                case "EmissionWavelength" :
                case "PinholeSize" :
                /** @var PinholeSize $objInstance */
                    $chanValues = $objInstance->value();

                    foreach ( $chanValues as $chan => $value) {
                        if (isset($value)) {
                            $postedParams["$objName$chan"] = $value;
                        }
                    }
                    break;
                default:
                    /** @var Parameter $objInstance */
                    $postedParams[$objName] = $objInstance->value();
            }

                /* Set the confidence level of this parameter according
                 to the file format chosen in the image selection page. */
            $cLevel = $db->getParameterConfidenceLevel( $imageFormat, $objName );
            $objInstance->setConfidenceLevel( $cLevel );
        }

            /* Check if the status of the parameter setting is compatible
             with the selected file format. */

        if ( !$this->checkPostedImageParameters($postedParams) ) {
            $ok = False;
        }

        if ($ok) {
            if (  !$this->checkPostedMicroscopyParameters($postedParams) ) {
                $ok = False;
            }
        }

        if ($ok) {
            if ( !$this->checkPostedCapturingParameters($postedParams) ) {
                $ok = False;
            }
        }

        if ($ok) {
            if (!$this->checkPostedAberrationCorrectionParameters($postedParams)) {
                $ok = False;
            }
        }

        if ($ok) {
            if ( !$this->checkPostedStedParameters($postedParams) )  {
                $ok = False;
            }
        }

        if ($ok) {
            if ( !$this->checkPostedSpimParameters($postedParams) )  {
                $ok = False;
            }
        }

        if ( !$ok ) {
            $this->message  = "The selected parameter set contains empty values ";
            $this->message .= "which the $imageFormat format misses in its ";
            $this->message .= "metadata. Please proceed to add them or select a ";
            $this->message .= "different parameter set.";
        }

        return $ok;
    }


    /**
     * Checks that the posted Image Parameters are all defined and valid.
     *
     * These Parameters must all be defined.
     *
     * @param array $postedParameters The array of posted parameters.
     * @return bool True if all Parameters are defined and valid, false
     * otherwise.
    */
    public function checkPostedImageParameters(array $postedParameters) {

        if (count($postedParameters) == 0) {
            $this->message = '';
            return False;
        }

        $this->message = '';
        //$noErrorsFound = True;  @todo Remove since unused.

        // The PSF type must be defined
        if (!isset($postedParameters["PointSpreadFunction"]) ||
                $postedParameters["PointSpreadFunction"] == "") {
            $this->message = "Please indicate whether you " .
                    "would like to calculate a theoretical PSF " .
                    "or use an existing measured one!";
            return False;
        } else {
            $parameter = $this->parameter("PointSpreadFunction");
            $parameter->setValue($postedParameters["PointSpreadFunction"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                return False;
            }
        }

        // The number of channels must be defined for all file formats
        if (!isset($postedParameters["NumberOfChannels"]) ||
                $postedParameters["NumberOfChannels"] == "") {
            $this->message = "Please set the number of channels!";
            return False;
        }
        $parameter = $this->parameter("NumberOfChannels");
        $parameter->setValue($postedParameters["NumberOfChannels"]);
        $this->set($parameter);
        if (!$parameter->check()) {
            $this->message = $parameter->message();
            return False;
        }

        // All checked correctly, we can return success
        $this->message = "";
        return True;
    }

    /**
     * Checks that the posted Microscopy Parameters are all defined and valid.
     *
     * These Parameter might have different confidence levels
     *
     * @param array $postedParameters The array of posted parameters.
     * @return bool True if all Parameters are defined and valid, false
     * otherwise.
    */
    public function checkPostedMicroscopyParameters(array $postedParameters) {

        if (count($postedParameters) == 0) {
            $this->message = '';
            return False;
        }

        $db = new DatabaseConnection;
        $maxChanCnt = $db->getMaxChanCnt();

        $this->message = '';
        $noErrorsFound = True;

        // Get the names of the relevant parameters
        $names = $this->microscopeParameterNames();

        // Small correction to the multi-channel names
        $names[array_search('ExcitationWavelength', $names)] =
            'ExcitationWavelength0';
        $names[array_search('EmissionWavelength', $names)] =
            'EmissionWavelength0';

        // Preliminary sanity checks.
        if (isset($postedParameters['MicroscopeType'])
          && $postedParameters['MicroscopeType'] != 'two photon') {
            for ($i = 0; $i < $maxChanCnt; $i++) {
                if (!isset($postedParameters["EmissionWavelength$i"])) {
                    continue;
                }             
                if (!isset($postedParameters["ExcitationWavelength$i"])) {
                    continue;
                }             
                if ($postedParameters["EmissionWavelength$i"] 
                    < $postedParameters["ExcitationWavelength$i"]) {
                    $noErrorsFound = false;                    
                    $this->message  = "Impossible combination of wavelengths: ";
                    $this->message .= "the emission wavelength is shorter ";
                    $this->message .= "than the excitation wavelength in channel $i.";
                    break;        
                }
            }            
        }
        if (isset($postedParameters['MicroscopeType'])
          && $postedParameters['MicroscopeType'] == 'two photon') {
            for ($i = 0; $i < $maxChanCnt; $i++) {
                if (!isset($postedParameters["EmissionWavelength$i"])) {
                    continue;
                }             
                if (!isset($postedParameters["ExcitationWavelength$i"])) {
                    continue;
                }             
                if ($postedParameters["EmissionWavelength$i"] 
                    > $postedParameters["ExcitationWavelength$i"]) {
                    $noErrorsFound = false;
                    $this->message  = "Impossible combination of wavelengths for a two photon ";
                    $this->message .= "microscope: the emission wavelength is shorter ";
                    $this->message .= "than the excitiation wavelength in channel $i.";
                    break;        
                }
            }            
        }

        if (!$noErrorsFound) {
            return $noErrorsFound;
        }

        // We handle multi-value parameters differently than single-valued ones
        // Excitation wavelengths
        for ($i = 0; $i < $maxChanCnt; $i++) {
            $value[$i] = null;
            if (isset($postedParameters["ExcitationWavelength$i"])) {
                $value[$i] = $postedParameters["ExcitationWavelength$i"];
                unset($postedParameters["ExcitationWavelength$i"]);
            }
        }
        $name = 'ExcitationWavelength';
        unset($names[array_search("ExcitationWavelength0", $names)]);
        // @todo Correctly process the case where $value is not defined
        $valueSet = count(array_filter($value)) > 0;

        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            // Check
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
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
                $this->message = "Please set the excitation wavelength!";
                $noErrorsFound = False;
            }

        }

        // Emission wavelengths
        for ($i = 0; $i < $maxChanCnt; $i++) {
            $value[$i] = null;
            if (isset($postedParameters["EmissionWavelength$i"])) {
                $value[$i] = $postedParameters["EmissionWavelength$i"];
                unset($postedParameters["EmissionWavelength$i"]);
            }
        }
        $name = 'EmissionWavelength';
        unset($names[array_search("EmissionWavelength0", $names)]);
        $valueSet = count(array_filter($value)) > 0;

        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            // Check
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
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
                $this->message = "Please set the emission wavelength!";
                $noErrorsFound = False;
            }

        }

        // Check that the Parameters are set and contain valid values
        foreach ($names as $name) {

            // State of the Parameter and the submitted value(s)
            $valueSet = isset($postedParameters[$name]) &&
                    $postedParameters[$name] != '';

            // If the value is set, we check it no matter if it must be
            // provided or not
            if ($valueSet) {

                if ($name == "SampleMedium"
                    && $postedParameters[$name] == "custom") {
                    if (isset($postedParameters['SampleMediumCustomValue'])) {
                        $value = $postedParameters['SampleMediumCustomValue'];
                    }
                } elseif($name == "ObjectiveType"
                         && $postedParameters[$name] == "custom") {
                    $value = $postedParameters['ObjectiveTypeCustomValue'];
                } else {
                    $value = $postedParameters[$name];
                }

                // If the value is set we must check it (independent of the
                // $mustProvide flag)
                $parameter = $this->parameter($name);
                $parameter->setValue($value);

                $this->set($parameter);
                if (!$parameter->check()) {
                    $this->message = $parameter->message();
                    $noErrorsFound = False;
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

                    switch ($name) {
                        case "MicroscopeType" :
                            $this->message =
                                "Please set the microscope type!";
                            break;
                        case "NumericalAperture" :
                            $this->message =
                                "Please set the numerical aperture!";
                            break;
                        case "ObjectiveType" :
                            $this->message =
                                "Please set the objective type!";
                            break;
                        case "SampleMedium" :
                            $this->message =
                                "Please set the refractive index " .
                                    "of the sample medium!";
                            break;
                        case "ExcitationWavelength" :
                            $this->message =
                                "Please set the excitation wavelength!";
                            break;
                        case "EmissionWavelength" :
                            $this->message =
                                "Please set the emission wavelength!";
                            break;
                    }
                    $noErrorsFound = False;
                }
            }
        }

        return $noErrorsFound;
    }

    /**
     * Checks that the posted SPIM Parameters are all defined and valid.
     * @param array $postedParameters The array of posted parameters.
     * @return bool True if all Parameters are defined and valid, false
     * otherwise.
    */
    public function checkPostedSpimParameters(array $postedParameters) {

        $this->message = '';

        if (count($postedParameters) == 0) {
            return False;
        }

        if (!$this->isSpim()) {
            return True;
        }

        $noErrorsFound = True;

        // Excitation Mode
        $value = array(null, null, null, null, null);
        for ($i = 0; $i < 5; $i++) {
            if (isset($postedParameters["SpimExcMode$i"])) {
                $value[$i] = $postedParameters["SpimExcMode$i"];
                unset($postedParameters["SpimExcMode$i"]);
            }
        }
        $name = 'SpimExcMode';
        $valueSet = count(array_filter($value)) > 0;

        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            // Keep the 'excModes' so that it can be checked below if any SPIM
            // parameters need to be forced, e.g when 'excMode' is 'High NA'.
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            } else {
                $excModes = $parameter->value();
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
                $this->message = "Please set the SPIM excitation mode!";
                $noErrorsFound = False;
            }
        }


        // Gaussian Width
        $value = array(null, null, null, null, null);

        for ($i = 0; $i < 5; $i++) {
            if (isset($postedParameters["SpimGaussWidth$i"])) {
                $value[$i] = $postedParameters["SpimGaussWidth$i"];
                unset($postedParameters["SpimGaussWidth$i"]);

                // Fallback to '0' if no value was provided and
                // the channel is 'High NA'.
                if (empty($value[$i])
                    && isset($excModes[$i])
                    && $excModes[$i] == "High NA") {
                    $value[$i] = 0;
                }
            }
        }
        $name = 'SpimGaussWidth';

        // Do not filter '0'. Thus, use 'strlen' as callback for filtering.
        $valueSet = count(array_filter($value, 'strlen')) > 0;
        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
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
                $this->message = "Please set the Spim Gaussian Width!";
                $noErrorsFound = False;
            }
        }


        // Spim Sheet Focus Offset
        $value = array(null, null, null, null, null);
        for ($i = 0; $i < 5; $i++) {
            if (isset($postedParameters["SpimFocusOffset$i"])) {
                $value[$i] = $postedParameters["SpimFocusOffset$i"];
                unset($postedParameters["SpimFocusOffset$i"]);

                // Fallback to '0' if no value was provided and
                // the channel is 'High NA'.
                if (empty($value[$i])
                    && isset($excModes[$i])
                    && $excModes[$i] == "High NA") {
                    $value[$i] = 0;
                }
            }
        }
        $name = 'SpimFocusOffset';

        // Do not filter '0'. Thus, use 'strlen' as callback for filtering.
        $valueSet = count(array_filter($value, 'strlen')) > 0;
        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
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
                $this->message = "Please set the Spim Focus Offset!";
                $noErrorsFound = False;
            }
        }


        // Spim Sheet Center Offset
        $value = array(null, null, null, null, null);
        for ($i = 0; $i < 5; $i++) {
            if (isset($postedParameters["SpimCenterOffset$i"])) {
                $value[$i] = $postedParameters["SpimCenterOffset$i"];
                unset($postedParameters["SpimCenterOffset$i"]);

                // Fallback to '0' if no value was provided and
                // the channel is 'High NA'.
                if (empty($value[$i])
                    && isset($excModes[$i])
                    && $excModes[$i] == "High NA") {
                    $value[$i] = 0;
                }
            }
        }
        $name = 'SpimCenterOffset';

        // Do not filter '0'. Thus, use 'strlen' as callback for filtering.
        $valueSet = count(array_filter($value, 'strlen')) > 0;
        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
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
                $this->message = "Please set the Spim Center Offset!";
                $noErrorsFound = False;
            }
        }


        // Spim NA
        $value = array(null, null, null, null, null);
        for ($i = 0; $i < 5; $i++) {
            if (isset($postedParameters["SpimNA$i"])) {
                $value[$i] = $postedParameters["SpimNA$i"];
                unset($postedParameters["SpimNA$i"]);

                // Fallback to '0' if no value was provided and
                // the channel is not 'High NA'.
                if (empty($value[$i])
                    && isset($excModes[$i])
                    && $excModes[$i] != "High NA") {
                    $value[$i] = 0;
                }
            }
        }
        $name = 'SpimNA';

        // Do not filter '0'. Thus, use 'strlen' as callback for filtering.
        $valueSet = count(array_filter($value, 'strlen')) > 0;
        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
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
                $this->message = "Please set the Spim NA!";
                $noErrorsFound = False;
            }
        }


        // Spim Fill Factor
        $value = array(null, null, null, null, null);
        for ($i = 0; $i < 5; $i++) {
            if (isset($postedParameters["SpimFill$i"])) {
                $value[$i] = $postedParameters["SpimFill$i"];
                unset($postedParameters["SpimFill$i"]);

                // Fallback to '0' if no value was provided and
                // the channel is not 'High NA'.
                if (empty($value[$i])
                    && isset($excModes[$i])
                    && $excModes[$i] != "High NA") {
                    $value[$i] = 0;
                }
            }
        }
        $name = 'SpimFill';

        // Do not filter '0'. Thus, use 'strlen' as callback for filtering.
        $valueSet = count(array_filter($value, 'strlen')) > 0;
        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
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
                $this->message = "Please set the Spim Fill Factor!";
                $noErrorsFound = False;
            }
        }


        // Spim Direction
        $value = array(null, null, null, null, null);
        for ($i = 0; $i < 5; $i++) {
            if (isset($postedParameters["SpimDir$i"])) {
                $value[$i] = $postedParameters["SpimDir$i"];
                unset($postedParameters["SpimDir$i"]);
            }
        }
        $name = 'SpimDir';

        // Do not filter '0'. Thus, use 'strlen' as callback for filtering.
        $valueSet = count(array_filter($value, 'strlen')) > 0;
        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
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
                $this->message = "Please set the Spim Direction!";
                $noErrorsFound = False;
            }
        }

        return $noErrorsFound;
    }

    /**
     * Checks that the posted STED Parameters are all defined and valid.
     * @param array $postedParameters The array of posted parameters.
     * @return bool True if all Parameters are defined and valid, false
     * otherwise.
    */
    public function checkPostedStedParameters(array $postedParameters) {

        if (count($postedParameters) == 0) {
            return False;
        }

        if (!$this->isSted() && !$this->isSted3D()) {
            return True;
        }

        $db = new DatabaseConnection;
        $maxChanCnt = $db->getMaxChanCnt();

        $this->message = '';
        $noErrorsFound = True;

        // Depletion Mode
        for ($i = 0; $i < $maxChanCnt; $i++) {
            $value[$i] = null;
            if (isset($postedParameters["StedDepletionMode$i"])) {
                $value[$i] = $postedParameters["StedDepletionMode$i"];
                unset($postedParameters["StedDepletionMode$i"]);
            }
        }
        $name = 'StedDepletionMode';
        // @todo Correctly process the case where $value is not defined.
        $valueSet = count(array_filter($value)) > 0;

        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            // Keep the 'deplModes' so that it can be checked below if any STED
            // parameters need to be forced, e.g when 'deplMode' is 'confocal'.
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            } else {
                $deplModes = $parameter->value();
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
                $this->message = "Please set the Sted depletion mode!";
                $noErrorsFound = False;
            }
        }


        // Saturation Factor
        for ($i = 0; $i < $maxChanCnt; $i++) {
            $value[$i] = null;
            if (isset($postedParameters["StedSaturationFactor$i"])) {
                $value[$i] = $postedParameters["StedSaturationFactor$i"];
                unset($postedParameters["StedSaturationFactor$i"]);

                // Fallback to '0' if no value was provided and
                // the channel is confocal.
                if (empty($value[$i])
                    && isset($deplModes[$i])
                    && $deplModes[$i] == "off-confocal") {
                    $value[$i] = 0;
                }
            }
        }
        $name = 'StedSaturationFactor';

        // Do not filter '0'. Thus, use 'strlen' as callback for filtering.
        $valueSet = count(array_filter($value, 'strlen')) > 0;
        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
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
                $this->message = "Please set the Sted saturation factor!";
                $noErrorsFound = False;
            }
        }


        // Sted Wavelength
        for ($i = 0; $i < $maxChanCnt; $i++) {
            $value[$i] = null;
            if (isset($postedParameters["StedWavelength$i"])) {
                $value[$i] = $postedParameters["StedWavelength$i"];
                unset($postedParameters["StedWavelength$i"]);

                // Fallback to '0' if no value was provided and
                // the channel is confocal.
                if (empty($value[$i])
                    && isset($deplModes[$i])
                    && $deplModes[$i] == "off-confocal") {
                    $value[$i] = 0;
                }
            }
        }
        $name = 'StedWavelength';

        // Do not filter '0'. Thus, use 'strlen' as callback for filtering.
        $valueSet = count(array_filter($value, 'strlen')) > 0;
        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
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
                $this->message = "Please set the Sted wavelength!";
                $noErrorsFound = False;
            }
        }


        // Sted Immunity Fraction
        for ($i = 0; $i < $maxChanCnt; $i++) {
            $value[$i] = null;
            if (isset($postedParameters["StedImmunity$i"])) {
                $value[$i] = $postedParameters["StedImmunity$i"];
                unset($postedParameters["StedImmunity$i"]);

                // Fallback to '0' if no value was provided and
                // the channel is confocal.
                if (empty($value[$i])
                    && isset($deplModes[$i])
                    && $deplModes[$i] == "off-confocal") {
                    $value[$i] = 0;
                }
            }
        }
        $name = 'StedImmunity';

        // Do not filter '0'. Thus, use 'strlen' as callback for filtering.
        $valueSet = count(array_filter($value, 'strlen')) > 0;
        if ($valueSet) {

            // Set the value
            $parameter = $this->parameter($name);
            $parameter->setValue($value);
            $this->set($parameter);

            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
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
                $this->message = "Please set the Sted immunity fraction!";
                $noErrorsFound = False;
            }
        }


        // Sted 3D
        if ($this->isSted3D()) {
            for ($i = 0; $i < $maxChanCnt; $i++) {
                $value[$i] = null;
                if (isset($postedParameters["Sted3D$i"])) {
                    $value[$i] = $postedParameters["Sted3D$i"];
                    unset($postedParameters["Sted3D$i"]);

                    // Fallback to '0' if no value was provided and
                    // the channel is confocal.
                    if (empty($value[$i])
                        && isset($deplModes[$i])
                        && $deplModes[$i] == "off-confocal") {
                        $value[$i] = 0;
                    }
                }
            }
            $name = 'Sted3D';

            // Do not filter '0'. Thus, use 'strlen' as callback for filtering.
            $valueSet = count(array_filter($value, 'strlen')) > 0;
            if ($valueSet) {

                // Set the value
                $parameter = $this->parameter($name);
                $parameter->setValue($value);
                $this->set($parameter);

                if (!$parameter->check()) {
                    $this->message = $parameter->message();
                    $noErrorsFound = False;
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
                    $this->message = "Please set the Sted 3D percentage!";
                    $noErrorsFound = False;
                }
            }
        }


        return $noErrorsFound;
    }

    /**
     * Checks that the posted Capturing Parameters are all defined and valid.
     * @param array $postedParameters The array of posted parameters.
     * @return bool True if all Parameters are defined and valid, false
     * otherwise.
    */
    public function checkPostedCapturingParameters(array $postedParameters) {

        if (count($postedParameters) == 0) {
            $this->message = '';
            return False;
        }

        $db = new DatabaseConnection;
        $maxChanCnt = $db->getMaxChanCnt();

        $this->message = '';
        $noErrorsFound = True;

        // Here we test the Parameters explicitly
        // CCDCaptorSizeX
        $valueSet = isset($postedParameters["CCDCaptorSizeX"]) &&
                $postedParameters["CCDCaptorSizeX"] != '';

        $parameter = $this->parameter("CCDCaptorSizeX");

        if ($valueSet) {

            // Set the Parameter and check the value
            $parameter->setValue($postedParameters["CCDCaptorSizeX"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        } else {

            $mustProvide = $parameter->mustProvide();

            // Reset the Parameter
            $parameter->reset();
            $this->set($parameter);

            // If the Parameter value must be provided, we return an error
            if ($mustProvide) {
                $this->message = "Please set the pixel size!";
                $noErrorsFound = False;
            }
        }

        // CCDCaptorSizeY
        if ($this->isArrDetConf()) {
            $valueSet = isset($postedParameters["CCDCaptorSizeY"]) &&
            $postedParameters["CCDCaptorSizeY"] != '';

            $parameter = $this->parameter("CCDCaptorSizeY");

            if ($valueSet) {

            // Set the Parameter and check the value
                $parameter->setValue($postedParameters["CCDCaptorSizeY"]);
                $this->set($parameter);
                if (!$parameter->check()) {
                    $this->message = $parameter->message();
                    $noErrorsFound = False;
                }
            } else {

                $mustProvide = $parameter->mustProvide();

                // Reset the Parameter
                $parameter->reset();
                $this->set($parameter);

                // If the Parameter value must be provided, we return an error
                if ($mustProvide) {
                    $this->message = "Please set the pixel size!";
                    $noErrorsFound = False;
                }
            }
        }

        // ZStepSize
        $valueSet = isset($postedParameters["ZStepSize"]) &&
                $postedParameters["ZStepSize"] != '';

        $parameter = $this->parameter("ZStepSize");

        if ($valueSet) {

            // Set the Parameter and check the value
            $parameter->setValue($postedParameters["ZStepSize"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        } else {

            $mustProvide = $parameter->mustProvide();

            // Reset the Parameter
            $parameter->reset();
            $this->set($parameter);

            // If the Parameter value must be provided, we return an error
            if ($mustProvide) {
                $this->message = "Please set the z-step!";
                $noErrorsFound = False;
            }
        }


        // TimeInterval
        $valueSet = isset($postedParameters["TimeInterval"]) &&
                $postedParameters["TimeInterval"] != '';

        $parameter = $this->parameter("TimeInterval");

        if ($valueSet) {

            // Set the Parameter and check the value
            $parameter->setValue($postedParameters["TimeInterval"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        } else {

            $mustProvide = $parameter->mustProvide();
            // Reset the Parameter
            $parameter->reset();
            $this->set($parameter);

            // If the Parameter value must be provided, we return an error
            if ($mustProvide) {
                $this->message = "Please set the time interval!";
                $noErrorsFound = False;
            }
        }

        // PinholeSize must be defined for all confocal microscopes
        if ($this->hasPinhole()) {

            // Pinhole sizes
            for ($i = 0; $i < $maxChanCnt; $i++) {
                $value[$i] = null;
                if (isset($postedParameters["PinholeSize$i"])) {
                    $value[$i] = $postedParameters["PinholeSize$i"];
                    unset($postedParameters["PinholeSize$i"]);
                }
            }
            $name = 'PinholeSize';
            // @todo Correctly process the case where $value is not defined.
            $valueSet = count(array_filter($value)) > 0;
            if ($valueSet) {

                // Set the value
                $parameter = $this->parameter($name);
                $parameter->setValue($value);
                $this->set($parameter);

                // Check
                if (!$parameter->check()) {
                    $this->message = $parameter->message();
                    $noErrorsFound = False;
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
                    $this->message = "Please set the pinhole size!";
                    $noErrorsFound = False;
                }

            }
        }

        // PinholeSpacing must be defined for spinning disk confocals
        if ($this->isNipkowDisk()) {

            $valueSet = isset($postedParameters["PinholeSpacing"]) &&
                    $postedParameters["PinholeSpacing"] != '';

            $parameter = $this->parameter("PinholeSpacing");

            if ($valueSet) {

                // Set the Parameter and check the value
                $parameter->setValue($postedParameters["PinholeSpacing"]);
                $this->set($parameter);
                if (!$parameter->check()) {
                    $this->message = $parameter->message();
                    $noErrorsFound = False;
                }
            } else {

                $mustProvide = $parameter->mustProvide();

                // Reset the Parameter
                $parameter->reset();
                $this->set($parameter);

                // If the Parameter value must be provided, we return an error
                if ($mustProvide) {
                    $this->message = "Please set the pinhole spacing!";
                    $noErrorsFound = False;
                }
            }
        }

        return $noErrorsFound;
    }


    /**
     * Checks that the posted Aberration Correction Parameters are all defined
     * and valid.
     * @param array $postedParameters The array of posted parameters.
     * @return bool True if all Parameters are defined and valid, false
     * otherwise.
    */
    public function checkPostedAberrationCorrectionParameters(array $postedParameters) {

        if (count($postedParameters) == 0) {
            $this->message = '';
            return False;
        }

        $this->message = '';
        $noErrorsFound = True;

        // If no aberration correction is necessary, or it is not active, we do not need to
        // test (since most parameter values will not be set anyway)
        if (isset($postedParameters["AberrationCorrectionNecessary"]) &&
            ($postedParameters["AberrationCorrectionNecessary"] == 0)) {
            $this->message = '';
            $noErrorsFound = True;
            return $noErrorsFound;
        }

        // CoverslipRelativePosition
        $valueSet = isset($postedParameters["CoverslipRelativePosition"]) &&
                $postedParameters["CoverslipRelativePosition"] != '';

        $parameter = $this->parameter("CoverslipRelativePosition");

        if ($valueSet) {

            // Set the Parameter and check the value
            $parameter->setValue($postedParameters["CoverslipRelativePosition"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        } else {

            $mustProvide = $parameter->mustProvide();

            // Reset the Parameter
            $parameter->reset();
            $this->set($parameter);

            // If the Parameter value must be provided, we return an error
            if ($mustProvide) {
                $this->message =
                    "Please choose the relative coverslip position!";
                $noErrorsFound = False;
            }
        }

        // AberrationCorrectionMode
        $valueSet = isset($postedParameters["AberrationCorrectionMode"]) &&
                $postedParameters["AberrationCorrectionMode"] != '';

        $parameter = $this->parameter("AberrationCorrectionMode");

        if ($valueSet) {

            // Set the Parameter and check the value
            $parameter->setValue($postedParameters["AberrationCorrectionMode"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        } else {

            $mustProvide = $parameter->mustProvide();

            // Reset the Parameter
            $parameter->reset();
            $this->set($parameter);

            // If the Parameter value must be provided, we return an error
            if ($mustProvide) {
                $this->message = "Please set the aberration correction mode!";
                $noErrorsFound = False;
            }
        }

        // AdvancedCorrectionOptions
        $valueSet = isset($postedParameters["AdvancedCorrectionOptions"]) &&
                $postedParameters["AdvancedCorrectionOptions"] != '';

        $parameter = $this->parameter("AdvancedCorrectionOptions");

        if ($valueSet) {

            // Set the Parameter and check the value
            $parameter->setValue($postedParameters["AdvancedCorrectionOptions"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        } else {

            $mustProvide = $parameter->mustProvide();

            // Reset the Parameter
            $parameter->reset();
            $this->set($parameter);

            // If the Parameter value must be provided, we return an error
            if ($mustProvide) {
                $this->message =
                    "Please indicate the options for the advanced correction!";
                $noErrorsFound = False;
            }
        }

        // The following Parameters may be defined depending on the values of
        // the previous one, but we will check them only if no errors have been
        // found so far
        if ($noErrorsFound == False) {
            return $noErrorsFound;
        }

        return $noErrorsFound;
    }

    /**
     * Checks that the posted Calculate Pixel Size Parameters are all defined
     * and valid.
     * @param array $postedParameters The array of posted parameters.
     * @return bool True if all Parameters are defined and valid, false
     * otherwise.
     */
    public function checkPostedCalculatePixelSizeParameters(array $postedParameters) {

        if (count($postedParameters) == 0) {
            $this->message = '';
            return False;
        }

        $this->message = '';
        $noErrorsFound = True;

        $names = $this->pixelSizeCalculationParameterNames();

        // Check that the Parameters are set and contain valid values
        foreach ($names as $name) {
            if (!isset($postedParameters[$name]) ||
                    $postedParameters[$name] == '') {
                // The Parameter is not set or empty, return an informative
                // error message
                switch ($name) {
                    case "CCDCaptorSize" :
                        $this->message = "Please set the CCD pixel size!";
                        break;
                    case "Binning" :
                        $this->message = "Please set the binning!";
                        break;
                    case "NumberOfChannels" :
                        $this->message = "Please set CMount!";
                        break;
                    case "TubeFactor" :
                        $this->message = "Please set the tube factore!";
                        break;
                    case "ObjectiveMagnification" :
                        $this->message =
                            "Please set the objective magnification!";
                        break;
                }
                $noErrorsFound = False;
            } else {
                // The Parameter is set, now check the value
                $parameter = $this->parameter($name);
                $parameter->setValue($postedParameters[$name]);
                $this->set($parameter);
                if (!$parameter->check()) {
                    $this->message = $parameter->message();
                    $noErrorsFound = False;
                }
            }
        }

        return $noErrorsFound;
    }

    /**
     * Returns the Parameter values needed for the aberration correction.
     *
     * The Paramters are returned in an array; the keys are the Parameter names.
     * @return array with all Parameter values for the aberration correction.
     */
    public function getAberractionCorrectionParameters() {
        $parameters = array(
            'AberrationCorrectionNecessary' =>
                $this->parameter('AberrationCorrectionNecessary')->value(),
            'CoverslipRelativePosition' =>
                $this->parameter('CoverslipRelativePosition')->value(),
            'AberrationCorrectionMode' =>
                $this->parameter('AberrationCorrectionMode')->value(),
            'AdvancedCorrectionOptions' =>
                $this->parameter('AdvancedCorrectionOptions')->value());
        return $parameters;
    }

    /**
     * Returns all Image Parameter names.
     * @return array Array of Image Parameter names.
    */
    public function imageParameterNames() {
        $names = array();
        /** @var Parameter $parameter */
        foreach ($this->parameter as $parameter) {
            if ($parameter->isForImage()) {
                $names[] = $parameter->name();
            }
        }

        return $names;
    }

    /**
     * Returns all Microscope Parameter names.
     * @return array Array of Microscope Parameter names.
    */
    public function microscopeParameterNames() {
        $names = array();
        /** @var Parameter $parameter */
        foreach ($this->parameter as $parameter) {
            if ($parameter->isForMicroscope()) {
                $names[] = $parameter->name();
            }
        }

        return $names;
    }

    /**
     * Returns all STED Parameter names.
     * @return array Array of STED Parameter names.
     */
    public function stedParameterNames() {
        $names = array();
        /** @var Parameter $parameter */
        foreach ($this->parameter as $parameter) {
            if ($parameter->isForSted()) {
                $names[] = $parameter->name();
            }
        }

        return $names;
    }

    /**
     * Returns all SPIM Parameter names.
     * @return array Array of SPIM Parameter names.
     */
    public function spimParameterNames() {
        $names = array();
        /** @var Parameter $parameter */
        foreach ($this->parameter as $parameter) {
            if ($parameter->isForSpim()) {
                $names[] = $parameter->name();
            }
        }

        return $names;
    }

    /**
     * Returns all Capture Parameter names.
     * @return array Array of Capture Parameter names.
     */
    public function capturingParameterNames() {
        $names = array();
        /** @var Parameter $parameter */
        foreach ($this->parameter as $parameter) {
            if ($parameter->isForCapture()) {
                $names[] = $parameter->name();
            }
        }
        return $names;
    }

    /**
     * Returns all Correction Parameter names.
     * @return array Array of Correction Parameter names.
     */
    public function correctionParameterNames() {
        $names = array();
        /** @var Parameter $parameter */
        foreach ($this->parameter as $parameter) {
            if ($parameter->isForCorrection()) {
                $names[] = $parameter->name();
            }
        }
        return $names;
    }

    /**
     * Returns all Pixel Size Calculation Parameter names.
     * @return array Array of Pixel Size Calculation Parameter names.
     */
    public function pixelSizeCalculationParameterNames() {
        $names = array();
        /** @var Parameter $parameter */
        foreach ($this->parameter as $parameter) {
            if ($parameter->isForPixelSizeCalculation()) {
                $names[] = $parameter->name();
            }
        }
        return $names;
    }

    /**
     * Displays the setting as a text containing Parameter names and their values.
     * @param int $numberOfChannels Number of channels (ignored).
     * @param string|null $micrType Microscope type (ignored).
     * @param float|null $timeInterval Sample T (ignored).
     * @return string Parameter names and their values as a string.
     */
    public function displayString($numberOfChannels = 0,
                                  $micrType         = NULL,
                                  $timeInterval     = 0) {
        /**
         * Please notice: the input arguments $numberOfChannels and $micrType
         * are ignored.
         */

        $result = '';


        // These parameters are important to properly display all the others
        $numberOfChannels = $this->parameter("NumberOfChannels")->value();
        $PSFmode = $this->parameter("PointSpreadFunction")->value();
        $aberrationCorrectionNecessary =
            $this->parameter("AberrationCorrectionNecessary")->value();
        $aberrationCorrectionMode =
            $this->parameter("AberrationCorrectionMode")->value();

        // Not everything needs to be displayed, either because the Parameter
        // might be only internally used, or because it does not make sense for
        // the current Setting (e.g. it does not make sense to display the
        // pinhole size if the microscope type is 'widefield'.
        /** @var Parameter $parameter */
        foreach ($this->parameter as $parameter) {
            if (!$this->isArrDetConf() && $parameter->name() == 'CCDCaptorSizeY')
                continue;
            if (!$this->hasPinhole() && $parameter->name() == 'PinholeSize')
                continue;
            if ($parameter->name() == 'ImageFileFormat')
                continue;
            if ($parameter->name() == 'IsMultiChannel')
                continue;
            if (!$this->isNipkowDisk() &&
                    $parameter->name() == 'PinholeSpacing')
                continue;
            if ($parameter->name() == 'CMount') // This is obsolete
                continue;
            if ($parameter->name() == 'TubeFactor') // This is obsolete
                continue;
            if ($parameter->name() == 'ObjectiveMagnification') // This is obsolete
                continue;
            if ($parameter->name() == 'Binning') // This is obsolete
                continue;
            if ($parameter->name() == 'AberrationCorrectionNecessary'
              && $PSFmode == 'measured')
                continue;
            if ($parameter->name() == 'CoverslipRelativePosition'
              && ($PSFmode == 'measured' || !$aberrationCorrectionNecessary))
                continue;
            if ($parameter->name() == 'AberrationCorrectionMode'
              && ($PSFmode == 'measured' || !$aberrationCorrectionNecessary))
                continue;
            if ($parameter->name() == 'AdvancedCorrectionOptions'
              && ($PSFmode == 'measured' || !$aberrationCorrectionNecessary))
                continue;
            if ($parameter->name() == 'AdvancedCorrectionOptions'
              && $aberrationCorrectionMode != 'advanced')
                continue;
            if ($parameter->name() == 'PSF' && $PSFmode == 'theoretical')
                continue;
            if ($parameter->name() == 'StedDepletionMode'
                && (!$this->isSted() && !$this->isSted3D()))
                continue;
            if ($parameter->name() == 'StedSaturationFactor'
                && (!$this->isSted() && !$this->isSted3D()))
                continue;
            if ($parameter->name() == 'StedWavelength'
                && (!$this->isSted() && !$this->isSted3D()))
                continue;
            if ($parameter->name() == 'StedImmunity'
                && (!$this->isSted() && !$this->isSted3D()))
                continue;
            if ($parameter->name() == 'Sted3D' && !$this->isSted3D())
                continue;

            // To avoid confusion it would be desirable to filter SPIM
            // parameters on a per channel basis, but we don't do that yet
            // for any HRM parameters.
            if ($parameter->name() == 'SpimExcMode' && !$this->isSpim())
                continue;
            if ($parameter->name() == 'SpimGaussWidth' && !$this->isSpim())
                continue;
            if ($parameter->name() == 'SpimFocusOffset' && !$this->isSpim())
                continue;
            if ($parameter->name() == 'SpimCenterOffset' && !$this->isSpim())
                continue;
            if ($parameter->name() == 'SpimNA' && !$this->isSpim())
                continue;
            if ($parameter->name() == 'SpimFill' && !$this->isSpim())
                continue;
            if ($parameter->name() == 'SpimDir' && !$this->isSpim())
                continue;

            /** @var PSF $parameter */
            if ($parameter->name() == 'PSF' && $PSFmode == 'measured') {

                // If this is a shared template, process the PSF file paths
                // to return the final path as it will be when the shared
                // template is accepted.
                $buffer = "psf_sharing/buffer/";
                $psfFiles = $parameter->value();
                for ($i = 0; $i < count($psfFiles); $i++) {

                    // Get current PSF file path
                    $f = $psfFiles[$i];

                    // Process it
                    $pos = strpos($f, $buffer);
                    if ($pos === 0) {

                        // Remove all temporary path prefix
                        $tmp = substr($f, strlen($buffer));
                        $pos = strpos($tmp, "/");
                        $tmp = substr($tmp, $pos + 1); // Remove owner
                        $pos = strpos($tmp, "/");
                        $tmp = substr($tmp, $pos + 1); // Remove previous owner

                        // Update the parameter
                        $psfFiles[$i] = $tmp;
                    }
                }
                $parameter->setValue($psfFiles);
                $result = $result . $parameter->displayString($numberOfChannels);
                continue;
            }
            $result = $result . $parameter->displayString($numberOfChannels);
        }
        return $result;
    }

    /**
     * Asks HuCore to calculate the ideal (Nyquist) sampling rate for the
     * current conditions.
     * @return array|false An array with the ideal XY and Z sampling, or false
     * if the sampling could not be calculated due to missing values.
    */
    public function calculateNyquistRate() {
        // Use the most restrictive wavelength to compute the adaption
        $parameter = $this->parameter('EmissionWavelength');
        if ($this->isTwoPhoton()
                || $this->isMultiPointOrSinglePointConfocal()) {
            $parameter = $this->parameter('ExcitationWavelength');
        }
        $value = $parameter->value();
        $mostRestrictiveChannel = 0;
        $mostRestrictiveWavelength = $value[0];
        for ($i = 1; $i < $this->numberOfChannels(); $i++) {
            if ($value[$i] < $mostRestrictiveWavelength) {
                $mostRestrictiveChannel = $i;
                $mostRestrictiveWavelength = $value[$i];
            }
        }
        $parameter = $this->parameter('EmissionWavelength');
        $value = $parameter->value();
        $em = $value[$mostRestrictiveChannel];
        $parameter = $this->parameter('ExcitationWavelength');
        $value = $parameter->value();
        $ex = $value[$mostRestrictiveChannel];

        $parameter = $this->parameter('NumericalAperture');
        $na = (float) $parameter->value();

        $parameter = $this->parameter('MicroscopeType');
        $micr = $parameter->translatedValue();

        $parameter = $this->parameter('ObjectiveType');
        $ril = $parameter->translatedValue();

        if ($this->isTwoPhoton()) {
            $pcnt = 2;
        } else {
            $pcnt = 1;
        }
        // Only micr, na, em, ex and pcnt are necessary to calculate it.
        if ($micr == null || $na == null || $em == null ||
                $ex == null || $ril == null) {
            return false;
        }
        $opt = "-micr $micr -na $na -em $em -ex $ex -pcnt $pcnt -ril $ril";
        $ideal = HuygensTools::askHuCore("calculateNyquistRate", $opt);
        if ($ideal == null) {
            $ideal = array(
                "xy" => -1,
                "z" => -1
            );
        }
        // print_r($ideal);
        return array($ideal['xy'], $ideal['z']);
    }

    /**
     * Returns all 3-D geometries (i.e. 'XYZ', 'XYZ - time')
     * @return array The 3D geometries.
     * @todo Check if this is still in use.
    */
    public function threeDimensionalGeometries() {
        static $threeDimensionalGeometries;
        if ($threeDimensionalGeometries == NULL) {
            $db = new DatabaseConnection();
            $threeDimensionalGeometries = $db->geometriesWith(True, NULL);
        }
        return $threeDimensionalGeometries;
    }

    /**
     * Returns all time series geometries (i.e. 'XY - time', 'XYZ - time')
     * @return array The time series geometries.
     * @todo Check if this is still in use.
     */
    public function timeSeriesGeometries() {
        static $timeSeriesGeometries;
        if ($timeSeriesGeometries == NULL) {
            $db = new DatabaseConnection();
            $timeSeriesGeometries = $db->geometriesWith(NULL, True);
        }
        return $timeSeriesGeometries;
    }

    /**
     * Returns all fixed geometry file formats (i.e. those that are 2D)
     * @return array The fixed geometry file formats.
     * @todo Check if this is still in use.
    */
    public function fixedGeometryFileFormats() {
        static $fixedGeometryFileFormats;
        if ($fixedGeometryFileFormats == NULL) {
            $db = new DatabaseConnection();
            $fixedGeometryFileFormats = $db->fileFormatsWith(NULL, NULL, True);
        }
        return $fixedGeometryFileFormats;
    }

    /**
     * Checks whether currently chosen file format has fixed geometry.
     * @return bool True if the chosen file format has fixed geometry, false
     * otherwise.
    */
    public function isFixedGeometryFormat() {
        $param = $this->parameter('ImageFileFormat');
        $result = in_array($param->value(), $this->fixedGeometryFileFormats());
        return $result;
    }

    /**
     * Returns the file formats that support single channel images.
     * @return	array Array of file formats.
     * @todo Check if this is still in use.
    */
    public function singleChannelFileFormats() {
        static $singleChannelFileFormats;
        if ($singleChannelFileFormats == NULL) {
            $db = new DatabaseConnection();
            $singleChannelFileFormats = $db->fileFormatsWith(True, NULL, NULL);
        }
        return $singleChannelFileFormats;
    }

    /**
     * Returns the file formats that support multi-channel images.
     * @return array Array of of multi-channel file formats.
     * @todo Check if this is still in use.
    */
    public function multiChannelFileFormats() {
        static $multiChannelFileFormats;
        if ($multiChannelFileFormats == NULL) {
            $db = new DatabaseConnection();
            $multiChannelFileFormats = $db->fileFormatsWith(False, NULL, NULL);
        }
        return $multiChannelFileFormats;
    }

    /**
     * Returns the file formats that support a variable number of channels
     * per file.
     * @return array Array of file formats that support variable number of
     * channels.
     * @todo Check if this is still in use.
    */
    public function variableChannelFileFormats() {
        static $variableChannelFileFormats;
        if ($variableChannelFileFormats == NULL) {
            $db = new DatabaseConnection();
            $variableChannelFileFormats =
                $db->fileFormatsWith(NULL, True, NULL);
        }
        return $variableChannelFileFormats;
    }

    /**
     * Checks whether current Setting is for multi-channel file formats
     * @return bool True if the number of channels set for current Setting
     * is > 1, false otherwise.
     * @todo Check if this is still in use.
    */
    public function isMultiChannel() {
        $parameter = $this->parameter('NumberOfChannels');
        $num_channels = (int) $parameter->value();
        if ($num_channels > 1) {
            $result = True;
        } else {
            $result = False;
        }
        return $result;
    }

    /**
     * Checks whether currently selected file format is variable channel
     * @return bool True if the currently selected file format is variable
     * channel, false otherwise.
     * @see variableChannelFileFormats()
     * @todo Check if this is still in use.
    */
    function isVariableChannelFormat() {
        $result = False;
        $parameter = $this->parameter('ImageFileFormat');
        if (in_array($parameter->value(), $this->variableChannelFileFormats())) {
            $result = True;
        }
        return $result;
    }

    /**
     * Checks whether currently selected file format is one of the TIFF variants.
     * @return bool True if the currently selected file format is a TIFF variant.
     * false othewise.
     * @todo Check if this is still in use.
    */
    public function isTif() {
        $result = False;
        $parameter = $this->parameter('ImageFileFormat');
        if (strstr($parameter->value(), 'tif'))
            $result = True;
        return $result;
    }

    /**
     * Returns the number of channels of the Setting.
     * @return int The number of channels.
    */
    public function numberOfChannels() {
        $parameter = $this->parameter('NumberOfChannels');
        if ($parameter) {
            return ( (int) $parameter->value() );
        } else {
            return ( (int) 1 );
        }
    }

    /**
     * Returns the main microscope mode..
     * @return string One of confocal, widefield, STED, STED 3D, spinning disk,
     * SPIM or multiphoton.
    */
    public function microscopeType( ) {
        /** @var MicroscopeType $parameter */
        $parameter = $this->parameter('MicroscopeType');
        if ($parameter) {
            return $parameter->value();
        } else {
            return NULL;
        }
    }

    /**
     * Checks whether the currently selected microscope type is spinning
     * (Nipkow) disk confocal.
     * @return bool true if the currently selected microscope type is spinning
     * (Nipkow) disk, false otherwise.
    */
    public function isNipkowDisk() {
        return $this->isMultiPointConfocal();
    }

    /**
     * Checks whether the currently selected microscope type is 2-Photon
     * @return bool True if the currently selected microscope type is 2-Photon,
     * false otherwise.
    */
    public function isTwoPhoton() {
        /** @var MicroscopeType $parameter */
        $parameter = $this->parameter('MicroscopeType');
        $value = $parameter->value();
        return ($value == 'two photon');
    }

    /**
     * Checks whether the currently selected microscope type is single-point
     * confocal.
     * @return bool True if the currently selected microscope type is
     * single-point confocal, false otherwise.
    */
    public function isSinglePointConfocal() {
        /** @var MicroscopeType $parameter */
        $parameter = $this->parameter('MicroscopeType');
        $value = $parameter->value();
        return ($value == 'single point confocal');
    }

    /**
     * Checks whether the currently selected microscope type is sted.
     * @return bool True if the currently selected microscope type is sted,
     * false otherwise.
    */
    public function isSted() {
        /** @var MicroscopeType $parameter */
        $parameter = $this->parameter('MicroscopeType');
        $value = $parameter->value();
        return ($value === 'STED');
    }

    /**
     * Checks whether the currently selected microscope type is sted 3D.
     * @return bool True if the currently selected microscope type is sted 3D,
     * false otherwise.
    */
    public function isSted3D() {
        /** @var MicroscopeType $parameter */
        $parameter = $this->parameter('MicroscopeType');
        $value = $parameter->value();
        return ($value === 'STED 3D');
    }

    /**
     * Checks whether the currently selected microscope type is spim.
     * @return bool True if the currently selected microscope type is spim,
     * false otherwise.
    */
    public function isSpim() {
        /** @var MicroscopeType $parameter */
        $parameter = $this->parameter('MicroscopeType');
        $value = $parameter->value();
        return ($value === 'SPIM');
    }

    /**
     * Checks whether the currently selected microscope type is array detector confocal.
     * @return bool True if the currently selected microscope type is array detector
     * confocal, false otherwise.
    */
    public function isArrDetConf() {
        /** @var MicroscopeType $parameter */
        $parameter = $this->parameter('MicroscopeType');
        $value = $parameter->value();
        return ($value === 'array detector confocal');
    }

    /**
     * Checks whether the currently selected microscope type is spinning
     * (Nipkow) disk confocal.
     * @return bool True if the currently selected microscope type is spinning
     * (Nipkow) disk, false otherwise.
    */

    public function isMultiPointConfocal() {
        /** @var MicroscopeType $parameter */
        $parameter = $this->parameter('MicroscopeType');
        $value = $parameter->value();
        return ($value == 'multipoint confocal (spinning disk)');
    }

    /**
     * Checks whether the currently selected microscope type is widefield.
     * @return bool True if the currently selected microscope type is widefield,
     * false otherwise.
    */
    public function isWidefield() {
        /** @var MicroscopeType $parameter */
        $parameter = $this->parameter('MicroscopeType');
        $value = $parameter->value();
        return ($value == 'widefield');
    }

    /**
     * Checks whether the currently selected microscope type is either
     * widefield of spinning disk.
     * @return bool True if the currently selected microscope type is either
     * widefield of spinning disk, false otherwise.
    */
    public function isWidefieldOrMultiPoint() {
        return ($this->isWidefield() || $this->isMultiPointConfocal());
    }

    /**
     * Checks whether the currently selected microscope type is either
     * 2-Photon or single-point confocal.
     * @return bool True if the currently selected microscope type is either
     * 2-Photon or single-point confocal, false otherwise.
    */
    public function isTwoPhotonOrSinglePoint() {
        return ($this->isSinglePointConfocal() || $this->isTwoPhoton());
    }

    /**
     * Checks whether the currently selected microscope type is any type of
     * confocal.
     * @return bool True if the currently selected microscope type is any type
     * of confocal, false otherwise.
    */
    public function isMultiPointOrSinglePointConfocal() {
        return ($this->isSinglePointConfocal() ||
                $this->isMultiPointConfocal());
    }

    /**
     * This determines which microscope types have a pinhole
     * @return bool True if the microscope contains a pinhole, false otherwise.
    */
    public function hasPinhole() {
        if ($this->isMultiPointOrSinglePointConfocal()) {
            return True;
        } elseif ($this->isSted()) {
            return True;
        } elseif ($this->isSted3D()) {
            return True;
        } else {
            return False;
        }
    }

    /**
     * Returns the pixel size (the value of CCDCaptorSizeX) in nm.
     * @todo This is redundant!
     * @return int Pixel size in nm,
    */
    public function pixelSize() {
        /** @var CCDCaptorSizeX $param */
        $param = $this->parameter('CCDCaptorSizeX');
        $size = (float) $param->value();
        return $size;
    }

    /**
     * Returns the sample size in X direction in um.
     *
     * This is simply pixelSize() / 1000.
     *
     * @return float Sample size in um.
    */
    public function sampleSizeX() {
        $size = $this->pixelSize();
        return $size / 1000;
    }

    /**
     * Returns the sample size in Y direction in um.
     *     
     *
     * @return float Sample size in um.
    */
    public function sampleSizeY() {

        /* In HRM 3.6 we make a distinction between X and Y for 
        array detectors only. This is because of the different
        sampling sizes in Airyscan fast mode. All other microscope
        type use the same sampling sizes for X and Y. */
        if ($this->isArrDetConf()) {            
            $param = $this->parameter('CCDCaptorSizeY');
            $size = (float) $param->value();
            return $size / 1000;
        } else {
            return $this->sampleSizeX();            
        }
    }

    /**
     * Returns the sample size in um in Z direction.
     *
     * This is the value of the z-step size / 1000.
     *
     * @return float Sample size in um.
    */
    public function sampleSizeZ() {
        $param = $this->parameter('ZStepSize');
        $size = (float) $param->value();
        return $size / 1000;
    }

    /**
     * Returns the time interval between consecutive time points in a time
     * series in seconds.
     * @return float Time interval in seconds.
    */
    public function sampleSizeT() {
        $param = $this->parameter('TimeInterval');
        $size = (float) $param->value();
        return $size / 1;
    }

    /**
     * Get the list of templates shared with the given user.
     * @param string $username Name of the user to query for.
     * @return array List of shared templates with the user.
    */
    public static function getTemplatesSharedWith($username) {
        $db = new DatabaseConnection();
        $result = $db->getTemplatesSharedWith($username, self::sharedTable());
        return $result;
    }

    /**
     * Get the list of templates shared by the given user.
     * @param string $username Name of the user to query for.
     * @return array List of shared templates by the user.
    */
    public static function getTemplatesSharedBy($username) {
        $db = new DatabaseConnection();
        $result = $db->getTemplatesSharedBy($username, self::sharedTable());
        return $result;
    }

    /**
     * Map Huygens parameters to HRM parameters.
     * @param array $huArray An array with the result of 'image setp -tclReturn'.
    */
    public function parseParamsFromHuCore(array $huArray){

         // Sanity checks: remove trailing spaces.
        foreach ($huArray as $key => $value) {
            $huArray[$key] = trim($value, " ");
        }

        // Microscope Type.
        if (strpos($huArray['parState,micr'], "default") === FALSE) {
            $huMicrType = explode(" ", $huArray['micr'], 5);
            /** @var MicroscopeType $hrmMicrType */
            $hrmMicrType = $this->parameter['MicroscopeType'];

            // By default, take the first value.
            $micrVal = $hrmMicrType->translateHucore($huMicrType[0]);

            // If there is STED, just make sure that it's the right one.
            if (strpos($huMicrType[0], "sted") !== FALSE) {
                $sted3d = array_map('floatval', explode(' ', $huArray['sted3D']));
                if (strpos($huArray['parState,sted3D'], "default") !== FALSE
                || $sted3d[0] == 0) {
                     $micrVal = $hrmMicrType->translateHucore('sted');
                } else {
                     $micrVal = $hrmMicrType->translateHucore('sted3d');
                }
            }

            $hrmMicrType->setValue($micrVal);
        }

        // Number of channels.
        if (isset($huMicrType)) {
            $chanCnt = count($huMicrType);
        } else {
            $chanCnt = 1;
        }

        $db = new DatabaseConnection();
        $maxChanCnt = $db->getMaxChanCnt();
        if ($chanCnt > $maxChanCnt) {
            $chanCnt = $maxChanCnt;
        }
        $this->parameter['NumberOfChannels']->setValue($chanCnt);

        // Sampling sizes. Exceptionally, no CL is checked here.
        if (isset($huArray['s'])) {
            $sampleSizes = array_map('floatval',  explode(' ', $huArray['s']));

            $sampleSizes[0] = round($sampleSizes[0] * 1000);
            $this->parameter['CCDCaptorSizeX']->setValue($sampleSizes[0]);

            $sampleSizes[1] = round($sampleSizes[1] * 1000);
            $this->parameter['CCDCaptorSizeY']->setValue($sampleSizes[1]);

            $sampleSizes[2] = round($sampleSizes[2] * 1000);
            $this->parameter['ZStepSize']->setValue($sampleSizes[2]);

            $this->parameter['TimeInterval']->setValue($sampleSizes[3]);
        }

        // Numerical Aperture.
        if (strpos($huArray['parState,na'], "default") === FALSE) {
            $na = explode(" ", $huArray['na'], 5);
            $this->parameter['NumericalAperture']->setValue($na[0]);
        }

        // Objective Type.
        if (strpos($huArray['parState,ril'], "default") === FALSE) {
            $lensImm = array_map('floatval',
                                 explode(' ', $huArray['ril']));
            $this->parameter['ObjectiveType']->setValue($lensImm[0]);
        }

        // Sample Medium.
        if (strpos($huArray['parState,ri'], "default") === FALSE) {
            $embMedium = array_map('floatval',
                                   explode(' ', $huArray['ri']));
            $this->parameter['SampleMedium']->setValue($embMedium[0]);
        }

        // Excitation Wavelength.
        if (strpos($huArray['parState,ex'], "default") === FALSE) {
            $lambdaEx = array_map('intval',
                                  explode(' ', $huArray['ex']));
            $this->parameter['ExcitationWavelength']->setValue($lambdaEx);
        }

        // Emission Wavelength.
        if (strpos($huArray['parState,em'], "default") === FALSE) {
            $lambdaEm = array_map('intval',
                                  explode(' ', $huArray['em']));
            $this->parameter['EmissionWavelength']->setValue($lambdaEm);
        }

        // Pinhole size.
        if (strpos($huArray['parState,pr'], "default") === FALSE) {
            $pinhole = array_map('intval',
                                 explode(' ', $huArray['pr']));
            $this->parameter['PinholeSize']->setValue($pinhole);
        }

        // Pinhole spacing.
        if (strpos($huArray['parState,ps'], "default") === FALSE) {
            $phSpacing = array_map('floatval',
                                   explode(' ', $huArray['ps']));
            $this->parameter['PinholeSpacing']->setValue($phSpacing[0]);
        }

        // Coverslip Relative Position.
        if (strpos($huArray['parState,imagingDir'], "default") === FALSE) {
            // Downward is closest.
            $imagingDir   = explode(' ', $huArray['imagingDir']);
            $coversPos = "farthest";
            if (strcmp("downward", $imagingDir[0])) {
                $coversPos = "closest";
            }
            $this->parameter['CoverslipRelativePosition']->setValue($coversPos);
        }

        // STED Depletion Mode.
        if (strpos($huArray['parState,stedMode'], "default") === FALSE) {
            $stedMode = explode(' ', $huArray['stedMode']);

            // Rename some modes if the mType is set to confocal.
            for($i = 0; $i < count($stedMode); $i++) {
                if($huMicrType[$i] == 'confocal') {
                    $stedMode[$i] = 'off-confocal';
                }
            }
            $this->parameter['StedDepletionMode']->setValue($stedMode);
        }

        // STED Saturation Factor.
        if (strpos($huArray['parState,stedSatFact'], "default") === FALSE) {
            $stedSatFact = array_map('floatval',
                                     explode(' ', $huArray['stedSatFact']));
            $this->parameter['StedSaturationFactor']->setValue($stedSatFact);
        }

        // STED Wavelength.
        if (strpos($huArray['parState,stedLambda'], "default") === FALSE) {
            $stedLambda = array_map('floatval',
                                    explode(' ', $huArray['stedLambda']));
            $this->parameter['StedWavelength']->setValue($stedLambda);
        }

        // STED Immunity Fraction.
        if (strpos($huArray['parState,stedImmunity'], "default") === FALSE) {
            $stedImmunity = array_map('floatval',
                                      explode(' ', $huArray['stedImmunity']));
            $this->parameter['StedImmunity']->setValue($stedImmunity);
        }

        // Whether STED is STED3D.
        if (strpos($huArray['parState,sted3D'], "default") === FALSE) {
            $sted3d = array_map('floatval',
                                explode(' ', $huArray['sted3D']));
            $this->parameter['Sted3D']->setValue($sted3d);
        }

        // SPIM Excitation Mode.
        if (strpos($huArray['parState,spimExc'], "default") === FALSE) {
            $spimExcMode = array_map('floatval',
                                     explode(' ', $huArray['spimExc']));
            $this->parameter['SpimExcMode']->setValue($spimExcMode);
        }

        // SPIM Gaussian Width.
        if (strpos($huArray['parState,spimGaussWidth'], "default") === FALSE) {
            $spimGaussWidth = array_map('floatval',
                                     explode(' ', $huArray['spimGaussWidth']));
            $this->parameter['SpimGaussWidth']->setValue($spimGaussWidth);
        }

        // SPIM Center Offset.
        if (strpos($huArray['parState,spimCenterOff'], "default") === FALSE) {
            $spimCenterOff = array_map('floatval',
                                       explode(' ', $huArray['spimCenterOff']));
            $this->parameter['SpimCenterOffset']->setValue($spimCenterOff);
        }

        // SPIM Focus Offset.
        if (strpos($huArray['parState,spimFocusOff'], "default") === FALSE) {
            $spimFocusOff = array_map('floatval',
                                      explode(' ', $huArray['spimFocusOff']));
            $this->parameter['SpimFocusOffset']->setValue($spimFocusOff);
        }

        // SPIM NA.
        if (strpos($huArray['parState,spimNA'], "default") === FALSE) {
            $spimNA = array_map('floatval',
                                explode(' ', $huArray['spimNA']));
            $this->parameter['SpimNA']->setValue($spimNA);
        }

        // SPIM Fill Factor.
        if (strpos($huArray['parState,spimFill'], "default") === FALSE) {
            $spimFill = array_map('floatval',
                                explode(' ', $huArray['spimFill']));
            $this->parameter['SpimFill']->setValue($spimFill);
        }

        // SPIM Imaging Direction.
        if (strpos($huArray['parState,spimDir'], "default") === FALSE) {
            $spimDir = array_map('floatval',
                                 explode(' ', $huArray['spimDir']));
            $this->parameter['SpimDir']->setValue($spimDir);
        }
    }
}
