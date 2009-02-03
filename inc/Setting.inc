<?php

// Module Setting.inc

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS) 

// contributors : 
// 	     Pierre Travo	(concept)	     
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL
// license as circulated by CEA, CNRS and INRIA at the following URL
// "http://www.cecill.info". 

// As a counterpart to the access to the source code and  rights to copy,
// modify and redistribute granted by the license, users are provided only
// with a limited warranty and the software's author, the holder of the
// economic rights, and the successive licensors  have only limited
// liability. 

// In this respect, the user's attention is drawn to the risks associated
// with loading, using, modifying and/or developing or reproducing the
// software by the user in light of its specific status of free software,
// that may mean that it is complicated to manipulate, and that also
// therefore means that it is reserved for developers and experienced
// professionals having in-depth IT knowledge. Users are therefore encouraged
// to load and test the software's suitability as regards their requirements
// in conditions enabling the security of their systems and/or data to be
// ensured and, more generally, to use and operate it in the same conditions
// as regards security. 

// The fact that you are presently reading this means that you have had
// knowledge of the CeCILL license and that you accept its terms.

require_once ("Parameter.inc");
require_once ("Database.inc");
require_once ("hrm_config.inc");

//!---------------------------------------------------------
// @class    Setting
// @desc     Abstract superclass of specific setting classes
//!---------------------------------------------------------
Class Setting {
	var $parameter; // @var parameter    Array    The set of parameter
	var $message; // @var message      String   The user message for the last inconsistent set of parameters  
	var $owner; // @var owner        Owner    The user or owner of the setting         
	var $name; // @var name         String   The name of the setting 
	var $isDefault; // @var isDefault    Boolean  Tells whether the setting is the default setting

	//!---------------------------------------------------------
	// @function    Setting::Setting
	// @desc        Constructor. Creates an empty setting
	// @return      void
	//!---------------------------------------------------------
	function Setting() {
		$this->parameter = array ();
		$this->isDefault = False;
	}

	//!---------------------------------------------------------
	// @function    Setting::parameter
	// @desc        Answer the parameter named name
	// @param       name  String  The name of the parameter
	// @return      Parameter
	//!---------------------------------------------------------
	function parameter($name) {
		return $this->parameter[$name];
	}

	//!---------------------------------------------------------
	// @function    Setting::set
	// @desc        Set a parameter. The parameter is stored
	//              in the setting under its name.
	// @param       parameter  Parameter  The parameter to be set
	// @return      Void
	//!---------------------------------------------------------  
	function set($parameter) {
		$this->parameter[$parameter->name()] = $parameter;
	}

	// TODO refactor
	//!---------------------------------------------------------
	// @function    Setting::values
	// @desc        Answer the parameter values
	// @param       name  String  The name of the parameter
	// @return      Array
	//!---------------------------------------------------------
	function values($name) {
		$db = new DatabaseConnection();
		$parameter = $this->parameter($name);
		return $db->readPossibleValues($parameter);
	}

	// TODO refactor
	//!---------------------------------------------------------
	// @function    Setting::translatedValues
	// @desc        Answer the translated parameter values
	// @param       name  String  The name of the parameter
	// @return      Array
	//!---------------------------------------------------------
	function translation($name, $value) {
		$db = new DatabaseConnection();
		$parameter = new Parameter($name);
		$parameter->setValue($value);
		return $db->translationFor($parameter);
	}

	//!---------------------------------------------------------
	// @function    Setting::message
	// @desc        Answer the message set by the last check
	//              performed on this setting
	// @return      String
	//!---------------------------------------------------------    
	function message() {
		return $this->message;
	}

	//!---------------------------------------------------------
	// @function    Setting::checkChoiceValue
	// @desc        Check if a choice parameter has a valid value
	// @param       name  String  The name of the parameter
	// @param       label String  The name of the parameter as it is displayed on the screen
	// @return      Void
	//!---------------------------------------------------------  
	function checkChoiceValue($name, $label) {
		$parameter = $this->parameter($name);
		if (!$parameter->check()) {
			$options = $parameter->possibleValuesString();
			$this->message = "Please select the $label (" . $options . ')!';
			return False;
		}
		return True;
	}

	//!---------------------------------------------------------
	// @function    Setting::checkSampleMedium
	// @desc        Check if the sample medium is defined
	// @return      Bool
	//!---------------------------------------------------------  
	function checkSampleMedium() {
		$parameter = $this->parameter('SampleMedium');
		$value = $parameter->value( );
		if ( empty( $value ) == true ) {
			$this->message = "Please choose one of the preset sample media or specify a refractive index yourself!";
			return False;
		}
		return True;
	}

	//!---------------------------------------------------------
	// @function    Setting::checkNumericalValue
	// @desc        Check if a numerical parameter has a valid value
	// @param       name  String  The name of the parameter
	// @param       label String  The name of the parameter as it is displayed on the screen
	// @return      Void
	//!---------------------------------------------------------  
	function checkNumericalValue($name, $label) {
		$parameter = $this->parameter($name);
		$min = $parameter->min();
		$max = $parameter->max();
		$notice = '';
		if ($parameter->checkMin() && $parameter->checkMax()) {
			$notice = "The value must be between $min and $max.";
		} else {
			if ($parameter->checkMin()) {
				$notice = "The value must be bigger than $min.";
			}
			if ($parameter->checkMax()) {
				$notice = "The value must be smaller than $max.";
			}
		}
		if (!$parameter->check()) {
			$this->message = "Please enter a valid $label! $notice";
			return False;
		}
		return True;
	}

	//!---------------------------------------------------------
	// @function    Setting::checkNumericalValues
	// @desc        Check if each of the numerical values (one 
	//              for each channel) is valid.
	// @param       name             String  The name of the parameter
	// @param       label            String  The name of the parameter as it is displayed on the screen
	// @param       numberOfChannels Integer The actual number of channels
	// @return      Boolean
	//!---------------------------------------------------------  
	function checkNumericalValues($name, $label, $numberOfChannels) {
		$parameter = $this->parameter($name);
		$values = $parameter->internalValue();
		if ($name == "BackgroundOffsetPercent" && ($values[0] == "auto" || $values[0] == "object"))
			return True;
		// TODO: check if this should be the normal behavior
		if ($numberOfChannels < 2) {
			$result = $this->checkNumericalValue($name, $label);
			return $result;
		}
		$result = True;
		$theLabel = $label;
		for ($i = 0; $i < $numberOfChannels; $i++) {
			$ok = $parameter->checkValue($values[$i]);
			if (!$ok) {
				$theLabel = $label . " for channel $i";
				$result = $result && $this->checkNumericalValue($name, $theLabel);
				break;
			}
		}
		return $result;
	}

	//!---------------------------------------------------------
	// @function    Setting::parameterNames()
	// @desc        Answer the names of all parameters
	// @return      Array
	//!---------------------------------------------------------    
	function parameterNames() {
		$names = array ();
		foreach ($this->parameter as $parameter) {
			$names[] = $parameter->name();
		}
		return $names;
	}

	//!---------------------------------------------------------
	// @function    Setting::rangeParameter()
	// @desc        Answer all range parameter (parameter for 
	//              which between 2 and 4 values can be entered)
	// @return      Array
	//!---------------------------------------------------------    
	function rangeParameter() {
		$result = array ();
		foreach ($this->parameter as $parameter) {
			if ($parameter->isRangeParameter()) {
				$result[] = $parameter;
			}
		}
		return $result;
	}

	//!---------------------------------------------------------
	// @function    Setting::owner
	// @desc        Answer the owner of the setting
	// @return      Owner
	//!---------------------------------------------------------
	function owner() {
		return $this->owner;
	}

	//!---------------------------------------------------------
	// @function    Setting::setOwner
	// @desc        Set the owner of the setting
	// @param       owner    Owner    set the owner of the setting
	// @return      void
	//!---------------------------------------------------------
	function setOwner($owner) {
		$this->owner = $owner;
	}

	//!---------------------------------------------------------
	// @function    Setting::name
	// @desc        Answer the name of the setting
	// @return      string
	//!---------------------------------------------------------
	function name() {
		return $this->name;
	}

	//!---------------------------------------------------------
	// @function    Setting::setName
	// @desc        Set the name of the setting
	// @param       name    String    set the name of the setting
	// @return      void
	//!---------------------------------------------------------
	function setName($name) {
		$this->name = $name;
	}

	//!---------------------------------------------------------
	// @function    Setting::isDefault
	// @desc        Answer whether the setting is the user's 
	//              default setting
	// @return      boolean
	//!---------------------------------------------------------
	function isDefault() {
		return $this->isDefault;
	}

	//!---------------------------------------------------------
	// @function    Setting::beDefault
	// @desc        Make the setting the user's default setting
	// @return      void
	//!---------------------------------------------------------
	function beDefault() {
		$this->isDefault = True;
	}

	//!---------------------------------------------------------
	// @function    Setting::resetDefault
	// @desc        The setting won't be the default setting 
	//              anymore
	// @return      void
	//!---------------------------------------------------------
	function resetDefault() {
		$this->isDefault = False;
	}

	//!---------------------------------------------------------
	// @function    Setting::copyParamterFrom
	// @desc        Copy the parameter values from setting to
	//              the receiver.
	// @param       setting  Setting  The setting from which the
	//                                values are copied to the 
	//                                receiver
	// @return      void
	//!---------------------------------------------------------
	function copyParameterFrom($setting) {
		foreach ($setting->parameterNames() as $name) {
			$parameter = $this->parameter[$name];
			$otherParameter = $setting->parameter($name);
			$newValue = $otherParameter->internalValue();
			$parameter->setValue($newValue);
			$this->parameter[$name] = $parameter;
		}
	}

	//!---------------------------------------------------------
	// @function    Setting::load
	// @desc        Load the parameter values for this setting
	//              from the database and answer a modified 
	//              version of the receiver.
	// @return      bool
	//!---------------------------------------------------------  
	function load() {
		$db = new DatabaseConnection();
		$result = $db->loadParameterSettings($this);
		if (!$result) {
			$this->message = "load setting - database access failed!";
		}
		return $result;
	}

	//!---------------------------------------------------------
	// @function    Setting::save
	// @desc        Save the parameter setting with the parameter
	//              values under its name in the database.
	// @return      bool
	//!---------------------------------------------------------    
	function save() {
		$db = new DatabaseConnection();
		$result = $db->saveParameterSettings($this);
		if (!$result) {
			$this->message = "save setting - database access failed!";
		}
		return $result;
	}

	//!---------------------------------------------------------
	// @function    Setting::display
	// @desc        Display the setting as a text containing
	//              the parameters and their values.
	// @return      Void
	//!---------------------------------------------------------      
	function display() {
		print $this->displayString();
	}

	//!---------------------------------------------------------
	// @function    Setting::displayString
	// @desc        Answer the display string of the setting.
	//              It contains the names and values of the 
	//              settings parameters.
	// @return      Void
	//!---------------------------------------------------------        
	function displayString() {
		$result = '';
		
		// These parameters are important to properly display all the others
		$numberOfChannels = $this->parameter( "NumberOfChannels" )->value( );
		$PSF = $this->parameter( "PointSpreadFunction" )->value( );
		
		// The relevant parameters for theoretical vs. measured PSF are
		// displayed, making sure that the number of entries (e.g. for the
		// wavelengths) reflect the user-defined number of channels
		foreach ($this->parameter as $parameter) {
			if ($this->isFixedGeometryFormat() && $parameter->name() == 'ImageGeometry')
				continue;
			if (!$this->isMultiPointOrSinglePointConfocal() && $parameter->name() == 'PinholeSize')
				continue;
			if (!$this->isMultiChannel() && $parameter->name() == 'NumberOfChannels')
				continue;
			if (!$this->isThreeDimensional() && $parameter->name() == 'ZStepSize')
				continue;
			if ($parameter->name() == 'IsMultiChannel' || $parameter->name() == 'HasAdaptedValues' || $parameter->name() == 'PSF')
				continue;
			if ( ( $parameter->name() == 'ExcitationWavelength' || $parameter->name() == 'EmissionWavelength' ) && $PSF == 'measured' )
				continue;
			if ($parameter->name() == 'SampleMedium' && $PSF == 'measured' )
				continue;		
			if ($parameter->name() == 'CMount' ) // This is obsolete
				continue;		
			if ($parameter->name() == 'TubeFactor' ) // This is obsolete
				continue;		
			if ($parameter->name() == 'CCDCaptorSizeX' && $PSF == 'measured' )
				continue;		
			if ($parameter->name() == 'CoverslipRelativePosition' && $PSF == 'measured' )
				continue;
            $result = $result . $parameter->displayString( $numberOfChannels );
		}
		return $result;
	}
}

//!---------------------------------------------------------
// @class    ParameterSetting
// @desc     A parameter setting is a complete set of 
//           microscope, image and capture parameters.
//!---------------------------------------------------------
Class ParameterSetting extends Setting {
	//!---------------------------------------------------------
	// @function    ParameterSetting::ParameterSetting
	// @desc        Constructor. Creates a new ParameterSetting
	// @return      void
	//!---------------------------------------------------------
	function ParameterSetting() {
		$this->Setting();
		// manage parameter adaption
		// manage measured PSF
		// support for Nipkow spinning disk
		$parameterClasses = array (
			'HasAdaptedValues',
			'IsMultiChannel',
			'ImageFileFormat',
			'NumberOfChannels',
			'ImageGeometry',
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
			'CCDCaptorSizeX',
			'ZStepSize',
			'TimeInterval',
			'PinholeSize',
			'PinholeSpacing',
			'PointSpreadFunction',
			'PSF',
			'CoverslipRelativePosition'
		);
		$db = new DatabaseConnection();
		foreach ($parameterClasses as $class) {
			$param = new $class;
			$name = $param->name();
			$param->setupAllowedValues($db);
			$this->parameter[$name] = $param;
		}
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::imageParameterNames()
	// @desc        Answer the names of parameters concerning the image.
	// @return      Array
	//!---------------------------------------------------------    
	function imageParameterNames() {
		$names = array ();
		foreach ($this->parameter as $parameter) {
			if ($parameter->isForImage()) {
				$names[] = $parameter->name();
			}
		}
		return $names;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::microscopeParameterNames()
	// @desc        Answer the names of parameters concerning the microscope
	// @return      Array
	//!---------------------------------------------------------    
	function microscopeParameterNames() {
		$names = array ();
		foreach ($this->parameter as $parameter) {
			if ($parameter->isForMicroscope()) {
				$names[] = $parameter->name();
			}
		}
		return $names;
	}
	//!---------------------------------------------------------
	// @function    ParameterSetting::capturingParameterNames()
	// @desc        Answer the names of parameters concerning the captor
	// @return      Array
	//!---------------------------------------------------------    
	function capturingParameterNames() {
		$names = array ();
		foreach ($this->parameter as $parameter) {
			if ($parameter->isForCapture()) {
				$names[] = $parameter->name();
			}
		}
		return $names;
	}
	//!---------------------------------------------------------
	// @function    ParameterSetting::checkImageParameter()
	// @desc        Check the image parameter. Answer true if they
	//              are valid. Answer false and set the message
	//              otherwise.
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkImageParameter() {
		$result = True;
		$this->message = '';
		if ($this->isMultiChannel()) {
			$result = $this->checkMultiChannelImageParameter();
		} else {
			$result = $this->checkSingleChannelImageParameter();
		}
		return $result;
	}
	//!---------------------------------------------------------
	// @function    ParameterSetting::checkMicroscopeParameter()
	// @desc        Check the microscope parameter. Answer true if they
	//              are valid. Answer false and set the message
	//              otherwise.
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkMicroscopeParameter() {
		$this->message = '';
		if (!$this->checkChoiceValue('MicroscopeType', 'microscope type')) {
			return False;
		}
		if (!$this->checkNumericalAperture()) {
			return False;
		}
		if (!$this->checkWavelength('Excitation')) {
			return False;
		}
		if (!$this->checkWavelength('Emission')) {
			return False;
		}
		if (!$this->checkChoiceValue('ObjectiveType', 'objective type')) {
			return False;
		}
		if (!$this->checkSampleMedium()) {
			return False;
		}		//if (!$this->checkNumericalValue('CMount', 'cmount')) {
		//	return False;
		//}
		//if (!$this->checkNumericalValue('TubeFactor', 'tube factor')) {
		//	return False;
		//}
		return True;
	}
	//!---------------------------------------------------------
	// @function    ParameterSetting::checkCapturingParameter()
	// @desc        Check the capturing  parameter. Answer true if they
	//              are valid. Answer false and set the message
	//              otherwise.
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkCapturingParameter() {
		$this->message = '';

		$text = $this->captorSizeText();
		if (!$this->checkNumericalValue('CCDCaptorSizeX', $text)) {
			return False;
		}
		if ($this->isThreeDimensional()) {
			if (!$this->checkNumericalValue('ZStepSize', 'size of the z-step')) {
				return False;
			}
		}
		if ($this->isTimeSeries()) {
			if (!$this->checkNumericalValue('TimeInterval', 'time interval')) {
				return False;
			}
		}
		if ($this->isMultiPointOrSinglePointConfocal()) {
			// Check pinhole radius for every channel
			if (!$this->checkPinholeSize()) {
				return False;
			}
		}
		// Support for Nipkow spinning disk
		if ($this->isNipkowDisk()) {
			if (!$this->checkPinholeSpacing()) {
				return False;
			}
		}
		// Check Nyquist rate only if parameters are not already adapted
		if (!$this->hasAdaptedParameters() && !$this->checkNyquistRate()) {
			return False;
		}
		return True;
	}
	
	//!---------------------------------------------------------
	// @function    ParameterSetting::checkCalculateParameter()
	// @desc        Check the parameters needed the compute the pixel size
	//		from the microscope and camera parameters. Answer true
	//              if they are valid. Answer false and set the message
	//              otherwise.
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkCalculateParameter() {
		$this->message = '';
		if (!$this->checkNumericalValue('CCDCaptorSizeX', 'CCD element size')) {
			$this->message = "Please enter a valid CCD element size!";
			return False;
		}
		if (!$this->checkNumericalValue('CMount', 'cmount')) {
			return False;
		}
		if (!$this->checkNumericalValue('TubeFactor', 'tube factor')) {
			return False;
		}
		return True;
	}

	// manage parameter adaption
	function hasAdaptedParameters() {
		$parameter = $this->parameter('HasAdaptedValues');
		if ($parameter->value() == "True")
			return True;
		else
			return False;
	}

	// manage parameter adaption
	function setAdaptedParameters($value) {
		$parameter = $this->parameter('HasAdaptedValues');
		if ($value)
			$parameter->setValue("True");
		else
			$parameter->setValue("False");
		$this->parameter['HasAdaptedValues'] = $parameter;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::checkPointSpreadFunction()
	// @desc        Answer true if a PSF file is selected for every
	//              channel. Answer false and set the message
	//              otherwise.
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkPointSpreadFunction() {
		$this->message = '';
		$parameter = $this->parameter('PSF');
		$value = $parameter->value();
		for ($i = 0; $i < $this->numberOfChannels(); $i++) {
			if ($value[$i] == NULL) {
				$this->message = "Please select a PSF file for channel $i!";
				return False;
			}
		}
		return True;
	}

	// Support for Nipkow spinning disk
	//!---------------------------------------------------------
	// @function    ParameterSetting::checkPinholeSpacing
	// @desc        TODO
	// @return      Boolean
	//!---------------------------------------------------------
	function checkPinholeSpacing() {
		$result = True;
		$parameter = $this->parameter('PinholeSpacing');
		$pinholeSpacingInputString = $parameter->value();
		if (!is_numeric($pinholeSpacingInputString)) {
			$this->message = "The pinhole spacing must be a number!";
			if (strstr($pinholeSpacingInputString, ',')) {
				$this->message = $this->message . " Please use a point (.) instead of a comma (,)!";
			}
			$result = False;
		}
		return $result;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::checkPinholeSize
	// @desc        TODO
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkPinholeSize() {
		$parameter = $this->parameter('PinholeSize');
		for ($i = 0; $i < $this->numberOfChannels(); $i++) {
			if (!$parameter->checkValue($parameter->value[$i])) {
				$this->message = "Please enter a valid pinhole size for channel $i!";
				return False;
			}
		}
		return True;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::checkNumericalAperture
	// @desc        The numerical aperture must not be bigger 
	//              than the lens refractive index or the medium
	//              refractive index. It must not be smaller than
	//              0.2
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkNumericalAperture() {
		$result = True;
		
		$parameter = $this->parameter('NumericalAperture');
		$numericalApertureInputString = $parameter->value();
		$numericalAperture = (float) $parameter->value();
		
		$parameter = $this->parameter('ObjectiveType');
		$lensRefractiveIndex = (float) $parameter->translatedValue();
		
		if ($numericalAperture > $lensRefractiveIndex) {
			$this->message = "The numerical aperture cannot be larger than the lens refractive index which is $lensRefractiveIndex for the selected objective type. Please lower the numerical aperture!";
			$result = False;
		}
		// The following check (numericalAperture>mediumRefractiveIndex) was
		// removed in HuygensPro, and we therefore de-activate it in the HRM
		// as well
		//$parameter = $this->parameter('SampleMedium');
		//$mediumRefractiveIndex = (float) $parameter->translatedValue();
		//if ($numericalAperture > $mediumRefractiveIndex) {
		//	$this->message = "The numerical aperture cannot be larger than the medium refractive index which is $mediumRefractiveIndex for the selected medium. Please lower the numerical aperture!";
		//	$result = False;
		//}
		if ($numericalAperture < 0.1) {
			$this->message = "The numerical aperture must not be smaller than 0.1!";
			$result = False;
		}
		if (!is_numeric($numericalApertureInputString)) {
			$this->message = "The numerical aperture must be a number!";
			if (strstr($numericalApertureInputString, ',')) {
				$this->message = $this->message . " Please use a point (.) instead of a comma (,)!";
			}
			$result = False;
		}
		return $result;
	}

	// TODO refactor
	//!---------------------------------------------------------
	// @function    ParameterSetting::adaptedLateralSampleSize
	// @desc        Test if the sampling size lies around the ideal
	//              sampling size according to the nyquist criterium.
	//              If this is not the case adapt the sample size 
	//              to the ideal value according to the nyquist
	//              criterium.
	// @param       size  float  The sample size entered by the user
	// @return      float
	//!---------------------------------------------------------    
	function adaptedLateralSampleSize() {
		$result = False;
		// Use the most restrictive wavelength to compute the adaption
		$parameter = $this->parameter('EmissionWavelength');
		if ($this->isTwoPhoton() || $this->isMultiPointOrSinglePointConfocal()) {
			$parameter = $this->parameter('ExcitationWavelength');
		}
		$value = $parameter->value();
		$mostRestrictiveWavelengthChannel = 0;
		$mostRestrictiveWavelength = $value[0];
		for ($i = 1; $i < $this->numberOfChannels(); $i++) {
			if ($value[$i] < $mostRestrictiveWavelength) {
				$mostRestrictiveWavelengthChannel = $i;
				$mostRestrictiveWavelength = $value[$i];
			}
		}
		// Check for undersampling and oversampling using the Nyquist criterium
		$parameter = $this->parameter('CCDCaptorSizeX');
		$deltaX = (float) $parameter->value();
		$parameter = $this->parameter('NumericalAperture');
		$na = (float) $parameter->value();
		$lambda = $mostRestrictiveWavelength;
		$idealDeltaX = $lambda / (4 * $na);
		if ($this->isMultiPointOrSinglePointConfocal())
			$idealDeltaX = $idealDeltaX / 2;
		if ((0.5 * $idealDeltaX <= $deltaX) && ($deltaX <= 1.2 * $idealDeltaX)) {
			$result = $deltaX;
		} else {
			$result = $idealDeltaX;
		}
		return $result;
	}

	// TODO refactor
	//!---------------------------------------------------------
	// @function    ParameterSetting::adaptedLateralSampleSize
	// @desc        Test if the sampling size lies around the ideal
	//              sampling size according to the nyquist criterium.
	//              If this is not the case adapt the sample size 
	//              to the ideal value according to the nyquist
	//              criterium.
	// @param       size  float  The sample size entered by the user
	// @return      float
	//!---------------------------------------------------------    
	function adaptedLateralSampleSizeFor($size) {
		$result = False;
		// Check for undersampling and oversampling using the Nyquist criterium
		$deltaX = $size;
		$parameter = $this->parameter('NumericalAperture');
		$na = (float) $parameter->value();
		$parameter = $this->parameter('ExcitationWavelength');
		$value = $parameter->value();
		$lambda = $value[0];
		$idealDeltaX = $lambda / (4 * $na);
		$idealDeltaX = $idealDeltaX / 2;
		if ((0.5 * $idealDeltaX <= $deltaX) && ($deltaX <= 1.2 * $idealDeltaX)) {
			$result = $deltaX;
		} else {
			$result = $idealDeltaX;
		}
		return $result;
	}

	/* TODO refactor
	//!---------------------------------------------------------
	// @function    ParameterSetting::adaptedLateralSampleSizeFor
	// @desc        Test if the sampling size lies around the ideal
	//              sampling size according to the nyquist criterium.
	//              If this is not the case adapt the sample size 
	//              to the ideal value according to the nyquist
	//              criterium.
	// @param       channel  int  The number of the channel (1-4)
	// @return      float
	//!---------------------------------------------------------   
	function adaptedLateralSampleSizeFor($channel) {
	    $result = False;
	    // Check for undersampling and oversampling using the Nyquist criterium
	    $parameter = $this->parameter('CCDCaptorSizeX');    
	    $deltaX = (float)$parameter->value();
	    $parameter = $this->parameter('NumericalAperture');    
	    $na = (float)$parameter->value();
	    $parameter = $this->parameter('ExcitationWavelength');
	    $value = $parameter->value();
	    $lambda = $value[$channel];
	    $idealDeltaX = $lambda / (4 * $na); 	
	    $idealDeltaX = $idealDeltaX / 2;
	    if ((0.5 * $idealDeltaX <= $deltaX) && ($deltaX <= 1.2 * $idealDeltaX)) {
	        $result = $deltaX;
	    } else {
	        $result = $idealDeltaX;
	    } 
	    return $result; 
	}*/

	/* TODO refactor
	//!---------------------------------------------------------
	// @function    ParameterSetting::adaptedNumericalAperture
	// @desc        Test if the sampling size lies around the ideal
	//              sampling size according to the nyquist criterium.
	//              If this is not the case lower the numerical 
	//              aperture until the condition is met. Answer the
	//              adapted numerical aperture if one was found. Uses
	//              the most restrictive wavelength.
	//              Otherwise answer Null.
	// @return      Boolean
	//!---------------------------------------------------------    
	function adaptedNumericalAperture() {
	    $result = Null;
	    // use most restrictive wavelength
	    $parameter = $this->parameter('EmissionWavelength');
	    if ($this->isTwoPhoton()) {
	        $parameter = $this->parameter('ExcitationWavelength');
	    } 
	    $value = $parameter->value();
	    $mostRestrictiveWavelengthChannel = 0;
	    $mostRestrictiveWavelength = $value[0];
	    for ($i=1; $i< $this->numberOfChannels(); $i++) {
	        if ($value[$i] < $mostRestrictiveWavelength) {
	            $mostRestrictiveWavelengthChannel = $i;
	            $mostRestrictiveWavelength = $value[$i];
	        }
	    }
	    $parameter = $this->parameter('CCDCaptorSizeX');    
	    $deltaX = (float)$parameter->value();
	    $parameter = $this->parameter('NumericalAperture');    
	    $na = (float)$parameter->value();
	    $lambda = $mostRestrictiveWavelength;
	    for ($adapted_na = $na; $adapted_na >= 0.2; $adapted_na = $adapted_na - 0.0025) {
	        $idealDeltaX = $lambda / (4 * $adapted_na);
	        if ((0.5 * $idealDeltaX <= $deltaX) && ($deltaX <= 1.2 * $idealDeltaX)) {
	            $result = $adapted_na;
	            break;
	        } 
	    }
	    return $result;     
	}
	
	//!---------------------------------------------------------
	// @function    ParameterSetting::adaptedNumericalApertureFor
	// @desc        Test if the sampling size lies around the ideal
	//              sampling size according to the nyquist criterium.
	//              If this is not the case lower the numerical 
	//              aperture until the condition is met. Answer the
	//              adapted numerical aperture if one was found. 
	//              Otherwise answer Null.
	// @param       channel  int  The number of the channel (1-4)
	// @return      Boolean
	//!---------------------------------------------------------    
	function adaptedNumericalApertureFor($channel) {
	    $result = Null;
	    $parameter = $this->parameter('CCDCaptorSizeX');    
	    $deltaX = (float)$parameter->value();
	    $parameter = $this->parameter('NumericalAperture');    
	    $na = (float)$parameter->value();
	    $parameter = $this->parameter('EmissionWavelength');
	    if ($this->isTwoPhoton()) { 
	        $parameter = $this->parameter('ExcitationWavelength');
	    } 
	    $value = $parameter->value();
	    $lambda = $value[$channel];
	    for ($adapted_na = $na; $adapted_na >= 0.2; $adapted_na = $adapted_na - 0.0025) {
	        $idealDeltaX = $lambda / (4 * $adapted_na);
	        if ((0.5 * $idealDeltaX <= $deltaX) && ($deltaX <= 1.2 * $idealDeltaX)) {
	            $result = $adapted_na;
	            break;
	        } 
	    }
	    return $result;     
	}*/

        //!---------------------------------------------------------
        // @function    ParameterSetting::calculateNyquistRate
        // @desc        Ask hucore for the ideal (Nyquist) sampling rate
        //              for the current conditions. 
        // @return      An array with the XY and Z ideal sampling.
        //!---------------------------------------------------------    
        function calculateNyquistRate() {
            // Use the most restrictive wavelength to compute the adaption
            $parameter = $this->parameter('EmissionWavelength');
            if ( $this->isTwoPhoton()
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

            $opt = "-micr $micr -na $na -em $em -ex $ex -pcnt $pcnt -ril $ril";
            $ideal = askHuCore ("calculateNyquistRate", $opt);
            // echo "NYQUIST: $ideal";
            return explode(' ', $ideal);
        }

	// sample size adaption for all microscopes
	// TODO don't answer false if data is over sampled
	// TODO refactor
	//!---------------------------------------------------------
	// @function    ParameterSetting::isNyquistRateOK
	// @desc        TODO
	// @return      Boolean
	//!---------------------------------------------------------    
	function isNyquistRateOK() {
		$result = True;
		$adaption = False;
		// Use the most restrictive wavelength to compute the adaption
		$parameter = $this->parameter('EmissionWavelength');
		if ($this->isTwoPhoton() || $this->isMultiPointOrSinglePointConfocal()) {
			$parameter = $this->parameter('ExcitationWavelength');
		}
		$value = $parameter->value();
		$mostRestrictiveWavelengthChannel = 0;
		$mostRestrictiveWavelength = $value[0];
		for ($i = 1; $i < $this->numberOfChannels(); $i++) {
			if ($value[$i] < $mostRestrictiveWavelength) {
				$mostRestrictiveWavelengthChannel = $i;
				$mostRestrictiveWavelength = $value[$i];
			}
		}
		// Check if data is under sampled and if parameter values can be adapted
		// In the case of confocal microscopes, the sample size is adapted

		// 1) check if undersampled or oversampled
		// sample size
		$parameter = $this->parameter('CCDCaptorSizeX');
		$deltaX = (float) $parameter->value();
		// ideal sample size
		$lambda = $mostRestrictiveWavelength;
		$parameter = $this->parameter('NumericalAperture');
		$na = (float) $parameter->value();
		$idealDeltaX = $lambda / (4 * $na);
		if ($this->isMultiPointOrSinglePointConfocal())
			$idealDeltaX /= 2;
		// sampling factor
		$factor = $idealDeltaX / $deltaX;

		// if 3-D, check if sample size in z direction also has to be adapted
		if ($this->isThreeDimensional()) {
			$parameter = $this->parameter('ZStepSize');
			$sampleSizeZ = (float) $parameter->value();
			$idealSampleSizeZ = $this->idealSampleSizeZ(); // EDIT
			$z_factor = $idealSampleSizeZ / $sampleSizeZ;
		}

		// 2) if undersampled, compute adapted sample size, warn and ask for confirmation
		if ($factor < 1 || !((0.5 * $idealSampleSizeZ <= $sampleSizeZ) && ($sampleSizeZ <= 1.2 * $idealSampleSizeZ))) { // EDIT
			// TODO refactor here
			$this->message = "<p>WARNING: sample size will be adapted to ideal</p>\n";
			$this->message = $this->message . "<p>Sampling rate w.r.t. Nyquist criterion:<br />";
			$this->message = $this->message . "X-dir: " . number_format(round($idealDeltaX / $deltaX, 2), 2, ',', '') . "<br />Y-dir: " . number_format(round($idealDeltaX / $deltaX, 2), 2, ',', '');
			if ($this->isThreeDimensional()) {
				$param = $this->parameter('ZStepSize');
				$size = (float) $param->value();
				$this->message = $this->message . "<br />Z-dir: " . number_format(round($idealSampleSizeZ / $sampleSizeZ, 2), 2, ',', ''); // EDIT
			}
			$this->message = $this->message . "<br />(values > 1 represent supersampling)</p>\n";
			$this->message = $this->message . "<p>Sampling precisely according to the Nyquist criterion would involve sampling distances (nm):<br />";
			$this->message = $this->message . "X-dir: " . floor($idealDeltaX) . "<br />Y-dir: " . floor($idealDeltaX);
			if ($this->isThreeDimensional()) {
				$this->message = $this->message . "<br />Z-dir: " . floor($idealSampleSizeZ) . "<br />"; // EDIT
			}
			$this->message = $this->message . "</p>\n<p>If you still want to use these parameter settings, press the OK button again.</p>\n";
			$this->setAdaptedParameters(True);
			$result = False;
		}

		// 3) else if oversampled by factor > 1.5, warn and ask for confirmation
		else if ($factor >= 1.5 || $z_factor > 1.5) {
			$this->message = "";
			if ($factor >= 1.5) {
				$this->message .= "The image is oversampled by a factor of " . number_format(round($factor, 2), 2, ',', '') . " in lateral direction";
			}
			if ($z_factor > 1.5) {
				$this->message .= "\nThe image is oversampled by a factor of " . number_format(round($z_factor, 2), 2, ',', '') . " in axial direction";
			}
			$this->message .= "\nIf you still want to use these parameter settings, press the OK button again.\n";
			$result = False;
		}

		// 4) else accept happily
		else {
			$result = True;
		}
		//}
		return $result;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::checkNyquistRate
	// @desc        For widefield microscope check the nyquist
	//              rate. Adapt the numerical aperture for each
	//              channel if necessary. Answer true if under
	//              sampling and over sampling are limited to
	//              a reasonable amount.
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkNyquistRate() {
		// Check Nyquist Rate for every microscope
		//return $this->isNyquistRateOK();
		return True;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::checkWavelength()
	// @desc        Check the emission or excitation wavelengths.
	//              This case is treated seperately because there
	//              is one wavelength value per channel.
	// @param       name  String  Emission or Excitation
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkWavelength($name) {
		$parameterName = $name . 'Wavelength';
		$label = strtolower($name);
		$parameter = $this->parameter($parameterName);
		for ($i = 0; $i < $this->numberOfChannels(); $i++) {
			if (!$parameter->checkValue($parameter->value[$i])) {
				$this->message = "Please enter a valid $label wavelength for channel $i!";
				return False;
			}
		}
		return True;
	}
	//!---------------------------------------------------------
	// @function    ParameterSetting::checkSingleChannelImageParameter()
	// @desc        Check the parameter if single channel had been
	//              chosen.
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkSingleChannelImageParameter() {

		// Modification by Aaron Ponti, 2005/12/13

		return True;

		// End of modification by Aaron Ponti, 2005/12/13

		$result = True;
		$parameter = $this->parameter('ImageFileFormat');
		$fileFormat = $parameter->value();
		if ((!in_array($fileFormat, $this->singleChannelFileFormats()))) {
			$this->message = "Please select a single channel file format (" . implode(", ", $this->singleChannelFileFormats()) . ")!";
			return False;
		}
		if ($fileFormat != 'tiff-single') {
			$result = $this->checkGeometry('single');
		}
		return $result;
	}
	//!---------------------------------------------------------
	// @function    ParameterSetting::checkMultiChannelImageParameter()
	// @desc        Check the parameter if multichannel had been
	//              chosen.
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkMultiChannelImageParameter() {
		$result = True;
		$parameter = $this->parameter('ImageFileFormat');
		$fileFormat = $parameter->value();
		if ((!in_array($fileFormat, $this->multiChannelFileFormats()))) {
			$this->message = "Please select a multichannel file format (" . implode(", ", $this->multiChannelFileFormats()) . ")!";
			return False;
		}
		if (!in_array($fileFormat, $this->fixedGeometryFileFormats())) {
			$result = $this->checkGeometry('multi');
		}
		if (in_array($fileFormat, $this->variableChannelFileFormats())) {
			$result = $this->checkNumberOfChannels() && $result;
		}
		return $result;
	}
	//!---------------------------------------------------------
	// @function    ParameterSetting::checkGeometry
	// @desc        Check the image geometry parameter (two or
	//              three dimensional, time series or single image).
	// @param       prefix  String  single or multi (for single 
	//              channel or multi channel geometries.
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkGeometry($prefix) {
		$result = True;
		$parameter = $this->parameter('ImageGeometry');
		$geometry = $parameter->internalValue();
		if ($geometry == '') {
			$geometry = '_';
		}
		$parts = explode('_', $geometry);
		$geometry = $parts[1];
		$prefix_ok = True;
		if ($prefix != $parts[0]) {
			$prefix_ok = False;
		}
		if (!$parameter->check() || !$prefix_ok) {
			$this->message = "Please select the image kind (" . implode(", ", $parameter->possibleValues()) . ")!";
			return False;
		}
		return $result;
	}
	//!---------------------------------------------------------
	// @function    ParameterSetting::checkNumberOfChannels()
	// @desc        Check the number of channels. Must be between 
	//              1 to 5.
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkNumberOfChannels() {
		$result = True;
		$parameter = $this->parameter('NumberOfChannels');
		$numberOfChannels = (int) $parameter->value();
		if ($numberOfChannels < 1 || $numberOfChannels > 5 ) {
			$result = False;
			$this->message = "Please select the number of channels (1-5)!";
		}
		return $result;
	}

	function threeDimensionalGeometries() {
		static $threeDimensionalGeometries;
		if ($threeDimensionalGeometries == NULL) {
			$db = new DatabaseConnection();
			$threeDimensionalGeometries = $db->geometriesWith(True, NULL);
		}
		return $threeDimensionalGeometries;
	}

	function timeSeriesGeometries() {
		static $timeSeriesGeometries;
		if ($timeSeriesGeometries == NULL) {
			$db = new DatabaseConnection();
			$timeSeriesGeometries = $db->geometriesWith(NULL, True);
		}
		return $timeSeriesGeometries;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::fixedGeometryFileFormats
	// @desc        Answer an array of file formats that have a
	//              fixed geometry (2d images).
	// @return      array
	//!---------------------------------------------------------    
	function fixedGeometryFileFormats() {
		static $fixedGeometryFileFormats;
		if ($fixedGeometryFileFormats == NULL) {
			$db = new DatabaseConnection();
			$fixedGeometryFileFormats = $db->fileFormatsWith(NULL, NULL, True);
		}
		return $fixedGeometryFileFormats;
	}

	function isFixedGeometryFormat() {
		$param = $this->parameter('ImageFileFormat');
		$result = in_array($param->value(), $this->fixedGeometryFileFormats());
		return $result;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::singleChannelFileFormats
	// @desc        Answer an array of file formats that support
	//              single channel images.
	// @return      array
	//!---------------------------------------------------------    
	function singleChannelFileFormats() {
		static $singleChannelFileFormats;
		if ($singleChannelFileFormats == NULL) {
			$db = new DatabaseConnection();
			$singleChannelFileFormats = $db->fileFormatsWith(True, NULL, NULL);
		}
		return $singleChannelFileFormats;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::multiChannelFileFormats
	// @desc        Answer an array of file formats that support
	//              multi channel images.
	// @return      array
	//!---------------------------------------------------------    
	function multiChannelFileFormats() {
		static $multiChannelFileFormats;
		if ($multiChannelFileFormats == NULL) {
			$db = new DatabaseConnection();
			$multiChannelFileFormats = $db->fileFormatsWith(False, NULL, NULL);
		}
		return $multiChannelFileFormats;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::variableChannelFileFormats
	// @desc        Answer an array of file formats that support
	//              a variable number of channels in one file
	// @return      array
	//!---------------------------------------------------------    
	function variableChannelFileFormats() {
		static $variableChannelFileFormats;
		if ($variableChannelFileFormats == NULL) {
			$db = new DatabaseConnection();
			$variableChannelFileFormats = $db->fileFormatsWith(NULL, True, NULL);
		}
		return $variableChannelFileFormats;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::isMultiChannel
	// @desc        Answer true if the setting is for a multi
	//              channel image.
	// @return      boolean
	//!---------------------------------------------------------    
	function isMultiChannel() {
		$parameter = $this->parameter('NumberOfChannels');
		$num_channels = (int) $parameter->value();
		if ($num_channels > 1) {
			$result = True;
		} else {
			$result = False;
		}
		return $result;
	}

	function isVariableChannelFormat() {
		$result = False;
		$parameter = $this->parameter('ImageFileFormat');
		if (in_array($parameter->value(), $this->variableChannelFileFormats())) {
			$result = True;
		}
		return $result;
	}

	function isTif() {
		$result = False;
		$parameter = $this->parameter('ImageFileFormat');
		if (strstr($parameter->value(), 'tif'))
			$result = True;
		return $result;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::numberOfChannels
	// @desc        Answer the number of channels of the setting.
	// @return      Integer
	//!---------------------------------------------------------    
	function numberOfChannels() {
		$result = 3;
		if (!$this->isMultiChannel()) {
			return 1;
		}
		if ($this->isVariableChannelFormat()) {
			$parameter = $this->parameter('NumberOfChannels');
			return (int) $parameter->value();
		}
		return $result;
	}

	// Support for Nipkow spinning disk
	//!---------------------------------------------------------
	// @function    ParameterSetting::isNipkowDisk
	// @desc        Answer true if the setting is for a Nipkow
	//              spinning disk microscope.
	// @return      bool
	//!---------------------------------------------------------    
	function isNipkowDisk() {
		return $this->isMultiPointConfocal();
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::isTwoPhoton
	// @desc        Answer true if the setting is for a two
	//              photon microscope.
	// @return      bool
	//!---------------------------------------------------------    
	function isTwoPhoton() {
		$parameter = $this->parameter('MicroscopeType');
		$value = $parameter->value();
		return ($value == 'two photon');
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::isSinglePointConfocal
	// @desc        Answer true if the setting is for a single
	//              point confocal microscope.
	// @return      bool
	//!---------------------------------------------------------      
	function isSinglePointConfocal() {
		$parameter = $this->parameter('MicroscopeType');
		$value = $parameter->value();
		return ($value == 'single point confocal');
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::isMultiPointConfocal
	// @desc        Answer true if the setting is for a multi
	//              point confocal microscope.
	// @return      bool
	//!---------------------------------------------------------      
	function isMultiPointConfocal() {
		$parameter = $this->parameter('MicroscopeType');
		$value = $parameter->value();
		return ($value == 'multipoint confocal (spinning disk)');
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::isWidefield
	// @desc        Answer true if the setting is for a widefield
	//              microscope.
	// @return      bool
	//!---------------------------------------------------------      
	function isWidefield() {
		$parameter = $this->parameter('MicroscopeType');
		$value = $parameter->value();
		return ($value == 'widefield');
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::isWidefieldOrMultiPoint
	// @desc        Answer true if the setting is for a widefield
	//              or multi point confocal microscope.
	// @return      bool
	//!---------------------------------------------------------    
	function isWidefieldOrMultiPoint() {
		return ($this->isWidefield() || $this->isMultiPointConfocal());
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::isTwoPhotonOrSinglePoint
	// @desc        Answer true if the setting is for a two photon
	//              or single point confocal microscope.
	// @return      bool
	//!---------------------------------------------------------      
	function isTwoPhotonOrSinglePoint() {
		return ($this->isSinglePointConfocal() || $this->isTwoPhoton());
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::isMultiPointOrSinglePointConfocal
	// @desc        Answer true if the setting is for a single
	//              point or multi point confocal microscope.
	// @return      bool
	//!---------------------------------------------------------      
	function isMultiPointOrSinglePointConfocal() {
		return ($this->isSinglePointConfocal() || $this->isMultiPointConfocal());
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::isThreeDimensional
	// @desc        Answer true if the setting is for a three
	//              dimensional image geometry.
	// @return      bool
	//!---------------------------------------------------------      
	function isThreeDimensional() {
		$parameter = $this->parameter('ImageGeometry');
		$value = $parameter->value();
		$format = $this->parameter('ImageFileFormat');
		$formatValue = $format->value();
		return (in_array($value, $this->threeDimensionalGeometries()) && !in_array($formatValue, $this->fixedGeometryFileFormats()));
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::isTimeSeries
	// @desc        Answer true if the setting is for a time
	//              series of images.
	// @return      bool
	//!---------------------------------------------------------      
	function isTimeSeries() {
		if ($this->isFixedGeometryFormat())
			return False;
		$parameter = $this->parameter('ImageGeometry');
		$value = $parameter->value();
		return (in_array($value, $this->timeSeriesGeometries()));
	}

	// pixel size = lateral sample size (nm)
	// TODO rename CCDCaptorSizeX to SampleSizeX
	//!---------------------------------------------------------
	// @function    ParameterSetting::pixelSize
	// @desc        Answer the lateral sample size in nanometer
	// @return      number
	//!---------------------------------------------------------        
	function pixelSize() {
		$param = $this->parameter('CCDCaptorSizeX');
		$size = (float) $param->value();
		//return $this->sampleSize($size);
		return $size;
	}

	// TODO refactor
	//!---------------------------------------------------------
	// @function    ParameterSetting::sampleSizeX
	// @desc        Answer the lateral sample size in micron
	// @return      number
	//!---------------------------------------------------------        
	function sampleSizeX() {
		$size = $this->pixelSize();
		//return $this->sampleSize($size) / 1000;
		return $size / 1000;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::sampleSizeY
	// @desc        Answer the lateral sample size in micron
	// @return      number
	//!---------------------------------------------------------        
	function sampleSizeY() {
		return $this->sampleSizeX();
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::sampleSizeZ
	// @desc        Answer the sample size in z direction in micron
	// @return      number
	//!---------------------------------------------------------        
	function sampleSizeZ() {
		$param = $this->parameter('ZStepSize');
		$size = (float) $param->value();
		//if ($this->hasAdaptedParameters()) $size = $size * $this->sampleSizeAdaptionFactor();
		return $size / 1000;
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::sampleSizeT
	// @desc        Answer the time between to images of the 
	//              time series in seconds. 
	// @return      number
	//!---------------------------------------------------------        
	function sampleSizeT() {
		$param = $this->parameter('TimeInterval');
		$size = (float) $param->value();
		return $size / 1;
	}

	// sample size adaption for all microscopes
	//!---------------------------------------------------------
	// @function    ParameterSetting::sampleSize
	// @desc        Answer the lateral sample size in nanometer.
	//              For confocal microscopes the sample size
	//              may be adapted to fit the nyquist criterium.
	// @param       size  float  The ccd captor or sampling size
	//              in nanometer. This Bloquer les numéros 0900?is the value the user 
	//              entered. 
	// @return      number
	//!---------------------------------------------------------          

    // WARNING: the sampling sizes is a property of the image, not something
    // taht can be adapted after the acquisition!!! It makes no sense to adapt
    // the samplign to the Nyquist rate. What must be done is to ACQUIRE the
    // image with the Nyquist rate, but never describe it with fake parameters!
	function sampleSize($size) {
		$result = $size;
		//if ($this->isMultiPointOrSinglePointConfocal()) {
		// Adapt sample size if necessary according to the nyquist criterium
		//    $result = $this->sampleSizeForConfocal($size);
		//}
		// if ($this->hasAdaptedParameters() && 1 == 0 )
		//	$result = $this->adaptedLateralSampleSizeFor($size);
		return $result;
	}

	// sample size adaption for all micrsocopes
	function sampleSizeAdaptionFactor() {
		$result = 1; // for widefield and two photon since NA is adapted and sample size is not changed
		/*if ($this->isMultiPointOrSinglePointConfocal()) {
		    $size = $this->pixelSize();
		    $result = $this->sampleSize($size) / $size;
		}*/
		return $result;
	}

	/* TODO refactor
	//!---------------------------------------------------------
	// @function    ParameterSetting::sampleSizeForCCDCaptorElementSize
	// @desc        Compute and answer the sample size (pixel size) 
	//              from the single ccd captor size and the microscope 
	//              parameters
	// @param       size  float  The ccd captor size in nanometer. 
	//              This is the value the user entered. 
	// @return      number
	//!---------------------------------------------------------
	function sampleSizeForCCDCaptorElementSize($size) {
	    $param = $this->parameter('Binning');
	    $binning = (float)$param->value();
	    $param = $this->parameter('ObjectiveMagnification');
	    $magnification = (float)$param->value();
	    $param = $this->parameter('CMount');
	    $cmount = (float)$param->value();
	    $param = $this->parameter('TubeFactor');
	    $tube = (float)$param->value();
	    $result = ($size * $binning) / ($magnification * $cmount * $tube);
	    return $result;
	}*/

	/* TODO refactor
	//!---------------------------------------------------------
	// @function    ParameterSetting::sampleSizeForConfocal
	// @desc        If necessary adapt the sample size to fit
	//              the nyquist criterium.
	// @param       size  float  The pixel size the user entered. 
	// @return      number
	//!---------------------------------------------------------
	function sampleSizeForConfocal($size) {
	    $result = $this->adaptedLateralSampleSize(1);
	    return $result;
	}*/

	//!---------------------------------------------------------
	// @function    ParameterSetting::table()
	// @desc        Answer the name of the database table in 
	//              which the parameter settings are stored. The 
	//              table contains the setting's name the owner 
	//              and the standard (default) flag.
	// @return      String
	//!---------------------------------------------------------    
	function table() {
		return "parameter_setting";
	}

	//!---------------------------------------------------------
	// @function    ParameterSetting::parameterTable()
	// @desc        Answer the name of the database table in
	//              which the parameters for settings of the 
	//              receiver's kind are stored. 
	// @return      String
	//!---------------------------------------------------------    
	function parameterTable() {
		return "parameter";
	}

	// TODO refactor and remove if not needed
	function usesPhotomultiplier() {
		if ($this->isSinglePointConfocal() || $this->isTwoPhoton()) {
			$result = True;
		} else {
			$result = False;
		}
		return $result;
	}

	// TODO refactor and remove if not needed
	function usesCCDCamera() {
		if ($this->isMultiPointConfocal() || $this->isWidefield()) {
			$result = True;
		} else {
			$result = False;
		}
		return $result;
	}

	// TODO refactor and remove if not needed
	function captorSizeText() {
		$result = 'x size of the ccd captor';
		if ($this->usesPhotomultiplier()) {
			$result = 'pixel size';
		}
		return $result;
	}

	// TODO refactor if needed
	function idealSampleSizeZ() {
		// use most restrictive wavelength
		if ($this->isWidefield()) {
			$parameter = $this->parameter('EmissionWavelength');
		} else {
			$parameter = $this->parameter('ExcitationWavelength');
		}
		$value = $parameter->value();
		$mostRestrictiveWavelengthChannel = 0;
		$mostRestrictiveWavelength = $value[0];
		for ($i = 1; $i < $this->numberOfChannels(); $i++) {
			if ($value[$i] < $mostRestrictiveWavelength) {
				$mostRestrictiveWavelengthChannel = $i;
				$mostRestrictiveWavelength = $value[$i];
			}
		}
		$lambda = $mostRestrictiveWavelength;
		if ($this->isWidefield()) {
			$factor = 2;
		} else {
			$factor = 4;
		}
		$parameter = $this->parameter('ObjectiveType');
		$rim = (float) $parameter->translatedValue();
		// TODO check if NA should be adapted in case of widefield and two-photon
		$parameter = $this->parameter('NumericalAperture');
		$na = (float) $parameter->value();
		$deltaZ = $lambda / ($factor * $rim * (1 - cos(asin($na / $rim))));
		return $deltaZ;
	}

	function idealSampleSizeZFor($channel) {
		if ($this->isWidefield()) {
			$parameter = $this->parameter('EmissionWavelength');
			$value = $parameter->value();
			$lambda = $value[$channel];
			$factor = 2;
		} else {
			$parameter = $this->parameter('ExcitationWavelength');
			$value = $parameter->value();
			$lambda = $value[$channel];
			$factor = 4;
		}
		$parameter = $this->parameter('ObjectiveType');
		$rim = (float) $parameter->translatedValue();
		// TODO check if NA should be adapted in case of widefield and two-photon
		$parameter = $this->parameter('NumericalAperture');
		$na = (float) $parameter->value();
		$deltaZ = $lambda / ($factor * $rim * (1 - cos(asin($na / $rim))));
		return $deltaZ;
	}
}

//!---------------------------------------------------------
// @class    TaskSetting
// @desc     A task setting is a complete set of 
//           image processing parameters.
//!---------------------------------------------------------
Class TaskSetting extends Setting {
	var $numberOfChannels;
	//!---------------------------------------------------------
	// @function    TaskSetting::TaskSetting
	// @desc        Konstruktor. Creates a new TaskSetting
	// @return      void
	//!---------------------------------------------------------
	function TaskSetting() {
		$this->Setting();
		$parameterClasses = array (
			'StyleOfProcessing',	// step processing or not
			'RemoveNoise',
			'RemoveNoiseEffectiveness',
			'RemoveBackground',
			'RemoveBackgroundPercent',
			'FullRestoration',
			'SignalNoiseRatio',
			'SignalNoiseRatioUseRange',
			'SignalNoiseRatioRange',
			'BackgroundOffsetPercent',	// intensity value(s) to be remove from each channel (background mode: remove constant value) 
			'BackgroundOffsetUseRange',
			'BackgroundOffsetRange',
			'NumberOfIterations',
			'NumberOfIterationsUseRange',
			'NumberOfIterationsRange',
			'OutputFileFormat',
			'MultiChannelOutput',
			'QualityChangeStoppingCriterion',
			'DeconvolutionAlgorithm'
		);
		$db = new DatabaseConnection();
		foreach ($parameterClasses as $class) {
			$param = new $class;
			$name = $param->name();
			$param->setupAllowedValues($db);
			// definition of the associative array that contains all the pairs task parametr name / task parameter value
			$this->parameter[$name] = $param;	// parameter is a member of the class 'Setting'
			$this->numberOfChannels = NULL;
		}
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::isOutputIms
	// @desc        Answer true if the output file format ims
	//              (imaris) has been choosen
	// @return      Boolean
	//!---------------------------------------------------------    
	function isOutputIms() {
		$result = False;
		$parameter = $this->parameter('OutputFileFormat');
		if (strstr($parameter->value(), 'ims'))
			$result = True;
		return $result;
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::isStepProcessing
	// @desc        Answer true if step processing will be performed
	//              because either step processing or step combined
	//              processing had been choosen as style of processingy
	// @return      Boolean
	//!---------------------------------------------------------
	function isStepProcessing() {
		$parameter = $this->parameter('StyleOfProcessing');
		return ($parameter->value() == 'step' || $parameter->value() == 'step combined');
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::isStepCombinedProcessing
	// @desc        Answer true if step combined processing has
	//              been choosen. The images will first be processed
	//              using step processing. Afterwards a standard
	//              full restoration with the same psf and fixed
	//              parameters is applied to the results.
	// @return      Boolean
	//!---------------------------------------------------------
	function isStepCombinedProcessing() {
		$parameter = $this->parameter('StyleOfProcessing');
		return $parameter->value() == 'step combined';
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::setNumberOfChannels
	// @desc        Set the number of channels. The number has to be
	//              known for the parameters that have to be 
	//              supplied per channel like background offset
	//              and remove background.
	// @param       channels  int  The number of channels
	// @return      Void
	//!---------------------------------------------------------    
	function setNumberOfChannels($channels) {
		$this->numberOfChannels = $channels;
		foreach ($this->parameter as $parameter) {
			if ($parameter->isVariableChannel()) {
				$parameter->setNumberOfChannels($this->numberOfChannels);
				$this->set($parameter);
			}
		}
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::numberOfChannels
	// @desc        Answer the number of channels. The number has to be
	//              known for the parameters that have to be 
	//              supplied per channel like background offset
	//              and remove background.
	// @return      int
	//!---------------------------------------------------------    
	function numberOfChannels() {
		return $this->numberOfChannels;
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::checkSignalNoiseRatio
	// @desc        TODO
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkSignalNoiseRatio() {
		$parameter = $this->parameter('SignalNoiseRatioUseRange');
		if ($parameter->value() == 'True') {
			$parameter = $this->parameter('SignalNoiseRatioRange');
            $range = count(array_filter($parameter->value[0]));
			for ($i = 0; $i < $this->numberOfChannels(); $i++) {
				$val = $parameter->value[$i];
                if ($range != count(array_filter($val))) {
                    $this->message = "Please enter the same number of values for all channels!";
                    return False;
                }

				if ($val[0] == NULL) {
					$this->message = "Please enter a valid signal/noise ratio for channel " . $i . "! The value must be larger than 0.";
					return False;
				}
				
				/*for ($j = 0; $j < count($val); $j++) {
					if (!$parameter->checkValue($val[$j])) {
						$this->message = "Please enter a valid signal/noise ratio for channel " . $i . "! The value must be larger than 0.";
						return False;
					}
					else {
						$this->message .= " channel " . $i . " value " . $j . " OK";
					}
				}*/
				
			}
			
		}
		else {
			$parameter = $this->parameter('SignalNoiseRatio');
			for ($i = 0; $i < $this->numberOfChannels(); $i++) {
				if (!$parameter->checkValue($parameter->value[$i])) {
					$this->message = "Please enter a valid signal/noise ratio for channel " . $i . "! The value must be larger than 0.";
					return False;
				}
			}
		}
		
		return True;
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::checkParameter
	// @desc        Check the entered parameter. Answer true if
	//              they are ok and false if some illegal values
	//              have been supplied.
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkParameter() {
		$result = True;
		$numberOfOperations = 0;
		$this->message = '';
		$parameter = $this->parameter('RemoveNoise');
		if ($parameter->value() == 'True') {
			$numberOfOperations++;
			if (!$this->checkChoiceValue('RemoveNoiseEffectiveness', 'effectiveness of the remove noise task')) {
				return False;
			}
		}
		$parameter = $this->parameter('RemoveBackground');
		if ($parameter->value() == 'True') {
			$numberOfOperations++;
			if (!$this->checkNumericalValues('RemoveBackgroundPercent', 'value for the remove background task', $this->numberOfChannels())) {
				return False;
			}
		}
		$parameter = $this->parameter('FullRestoration');
		if ($parameter->value() == 'True') {
			$numberOfOperations++;
			/*$parameter = $this->parameter('SignalNoiseRatioUseRange');
			if ($parameter->value() == 'True') {
				if (!$this->checkRange('SignalNoiseRatioRange', 'signal/noise ratio range')) {
					return False;
				}
			} else {*/
				if (!$this->checkSignalNoiseRatio() /*!$this->checkNumericalValue('SignalNoiseRatio', 'signal/noise ratio')*/
					) {
					return False;
				}
			//}
			$parameter = $this->parameter('BackgroundOffsetUseRange');
			if ($parameter->value() == 'True') {
				if (!$this->checkRange('BackgroundOffsetRange', 'background offset range')) {
					return False;
				}
			} else {
				if (!$this->checkNumericalValues('BackgroundOffsetPercent', 'background offset', $this->numberOfChannels())) {
					return False;
				}
			}
			$parameter = $this->parameter('NumberOfIterationsUseRange');
			if ($parameter->value() == 'True') {
				if (!$this->checkRange('NumberOfIterationsRange', 'number of iterations range')) {
					return False;
				}
			} else {
				if (!$this->checkNumericalValue('NumberOfIterations', 'number of iterations')) {
					return False;
				}
			}
			if (!$this->checkNumericalValue('QualityChangeStoppingCriterion', 'quality change stopping criterion')) {
				return False;
			}
		}
		$parameter = $this->parameter('MultiChannelOutput');
		if ($parameter->value() == 'True') {
			$numberOfOperations++;
		}
		if ($numberOfOperations < 1) {
			$this->message = "Please choose at least one operation!";
			return False;
		}
		return $result;
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::checkRange
	// @desc        Check the entered values for the range parameter. 
	//              For a range parameter at least two values must
	//              be entered. Each value must be a valid value
	//              for the parameter.
	// @return      Boolean
	//!---------------------------------------------------------    
	function checkRange($parameterName, $originalLabel) {
		$param = $this->parameter($parameterName);
		$originalValues = $param->value();
		$values = $param->value();
		$result = True;
		/* normally useless
		if ($values[0] == NULL || $values[1] == NULL || $values[0] == '' || $values[1] = '') {
			$this->message = "Please enter at least two values for the $originalLabel, or don't use range.";
			return False;
		}*/
		$index = 0;
		foreach ($originalValues as $value) {
			$label = $originalLabel . " value $index";
			if ($value != NULL && $value != '') {
				$param->setValue($value);
				$this->set($param);
				if (!$this->checkNumericalValue("$parameterName", $label)) {
					$param->setValue($originalValues);
					$this->set($param);
					return False;
				}
			}
			$index++;
		}
		$param->setValue($originalValues);
		$this->set($param);

		return $result;
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::table()
	// @desc        Answer the name of the database table in 
	//              which the task settings are stored. The 
	//              table contains the setting's name the owner 
	//              and the standard (default) flag.
	// @return      String
	//!---------------------------------------------------------    
	function table() {
		return "task_setting";
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::parameterTable()
	// @desc        Answer the name of the database table in
	//              which the parameters for settings of the 
	//              receiver's kind are stored. 
	// @return      String
	//!---------------------------------------------------------    
	function parameterTable() {
		return "task_parameter";
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::display
	// @desc        Display the setting as a text containing
	//              the parameters and their values.
	// @return      Void
	//!---------------------------------------------------------      
	function display() {
		print $this->displayString();
	}

	// TODO refactor!
	//!---------------------------------------------------------
	// @function    TaskSetting::displayString
	// @desc        Answer the display string of the setting.
	//              It contains the names and values of the 
	//              task settings parameters.
	// @return      Void
	//!---------------------------------------------------------        
	function displayString( $numberOfChannels = 0 ) {	// return the string that will be attach to the success notification message 
		$result = '';
        $algorithm = $this->parameter('DeconvolutionAlgorithm')->value();
		$parameter = $this->parameter('SignalNoiseRatioUseRange');
		$rangeUsedForSignalNoiseRatio = ($parameter->value() == 'True');
		$parameter = $this->parameter('BackgroundOffsetUseRange');
		$rangeUsedForBackgroundOffset = ($parameter->value() == 'True');
		$parameter = $this->parameter('NumberOfIterationsUseRange');
		$rangeUsedForNumberOfIterations = ($parameter->value() == 'True');
		$parameter = $this->parameter('RemoveNoise');
		$removeNoise = ($parameter->value() == 'True');
		$parameter = $this->parameter('RemoveBackground');
		$removeBackground = ($parameter->value() == 'True');
		$parameter = $this->parameter('FullRestoration');
		$fullRestoration = ($parameter->value() == 'True');
		foreach ($this->parameter as $parameter) {
			if ($parameter->name() == 'StyleOfProcessing' ) {
				// There is only one style of processing
				continue;
			}
			if ($parameter->name() == 'SignalNoiseRatio' && $rangeUsedForSignalNoiseRatio) {	
				continue;
			}
			if ($parameter->name() == 'SignalNoiseRatioRange' && !$rangeUsedForSignalNoiseRatio) {
				continue;
			}
			if ($parameter->name() == 'BackgroundOffsetPercent' && $rangeUsedForBackgroundOffset) {
				continue;
			}
			if ($parameter->name() == 'BackgroundOffsetRange' && !$rangeUsedForBackgroundOffset) {
				continue;
			}
			if ($parameter->name() == 'NumberOfIterations' && $rangeUsedForNumberOfIterations) {
				continue;
			}
			if ($parameter->name() == 'NumberOfIterationsRange' && !$rangeUsedForNumberOfIterations) {
				continue;
			}
			if ($parameter->name() == 'RemoveNoiseEffectiveness' && !$removeNoise) {
				continue;
			}
			if ($parameter->name() == 'RemoveBackgroundPercent' && !$removeBackground) {
				continue;
			}
			if (in_array($parameter->name(), array (
					'BackgroundOffsetPercent',
					'NumberOfIterations'
				)) && !$fullRestoration) {
				continue;
			}
			if ($parameter->name() == 'FullRestoration' || $parameter->name() == 'RemoveNoise' || $parameter->name() == 'RemoveBackground')
				continue;
			//if ($parameter->name() == 'SignalNoiseRatio') {
			//	$snr = $parameter->value();
			//	$parameter->setValue(array_slice($snr, 0, $this->numberOfChannels() ));
			//}
            if ($parameter->name() == 'SignalNoiseRatio') {
                $snr = $parameter->value();
				$parameter->setValue(array_slice($snr, 0, $this->numberOfChannels() ));
                if ($algorithm == 'cmle')
                    $result = $result . $parameter->displayCMLEString($this->numberOfChannels());
                else if ($algorithm == 'qmle')
                    $result = $result . $parameter->displayQMLEString($this->numberOfChannels());
            }
            else
                $result = $result . $parameter->displayString($this->numberOfChannels());
			//if ($parameter->name() == 'SignalNoiseRatio') {
			//	$parameter->setValue($snr);
			//}
		}
		return $result;
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::displayWithoutOutputFileFormat
	// @desc        Display the setting as a text containing
	//              the parameters and their values. Only
	//              the parameter OutputFileFormat is left out.
	//              This is used for the web display where the
	//              output file format has not been choosen yet.
	// @return      Void
	//!---------------------------------------------------------        
	function displayWithoutOutputFileFormat( $numberOfChannels = 0 ) {
		print $this->displayStringWithoutOutputFileFormat( $numberOfChannels );
	}

	//!---------------------------------------------------------
	// @function    TaskSetting::displayStringWithoutOutputFileFormat
	// @desc        Answer the display string of the setting.
	//              It contains the names and values of the 
	//              task settings parameters. Only
	//              the parameter OutputFileFormat is left out.
	//              This is used for the web display where the
	//              output file format has not been choosen yet.
	// @return      Void
	//!---------------------------------------------------------
	function displayStringWithoutOutputFileFormat( $numberOfChannels = 0 ) {
		$parameter = $this->parameter('OutputFileFormat');
		$parameterList = $this->parameter;
		unset ($parameterList['OutputFileFormat']);
		$this->parameter = $parameterList;
		$result = $this->displayString( $numberOfChannels );
		$parameterList['OutputFileFormat'] = $parameter;
		$this->parameter = $parameterList;
		return $result;
	}
}

//!---------------------------------------------------------
// @class    JobParameterSetting
// @desc     A parameter setting is a complete set of 
//           microscope, image and capture parameters. A job
//           parameter setting is a parameter setting that 
//           is used when a job is executed by the queue
//           manager. It uses different database tables and
//           knows how to put its parameter settings onto a 
//           script.
//!---------------------------------------------------------
Class JobParameterSetting extends ParameterSetting {
	//!---------------------------------------------------------
	// @function    JobParameterSetting::table()
	// @desc        Answer the name of the database table in 
	//              which the job parameter settings are stored. The 
	//              table contains the setting's name the owner 
	//              and the standard (default) flag.
	// @return      String
	//!---------------------------------------------------------    
	function table() {
		return "job_parameter_setting";
	}
	//!---------------------------------------------------------
	// @function    JobParameterSetting::parameterTable()
	// @desc        Answer the name of the database table in
	//              which the parameters for settings of the 
	//              receiver's kind are stored. 
	// @return      String
	//!---------------------------------------------------------    
	function parameterTable() {
		return "job_parameter";
	}

	function putScriptOn($script) {
		$newScript = $script;
		$newScript = $newScript . '$imageName setp ';
		$newScript = $newScript . $this->microscopeParameterString();
		$newScript = $newScript . $this->microscopeTypeString();
		$newScript = $newScript . $this->numericalApertureString();
		$newScript = $newScript . $this->lensRefractiveIndexString();
		$newScript = $newScript . $this->mediumRefractiveIndexString();
		// Support for Nipkow spinning disk
		if ($this->isNipkowDisk()) {
			$newScript = $newScript . $this->pinholeSpacingString();
		}
		$newScript = $newScript . $this->photonCountString();
		$newScript = $newScript . "\n";
		$newScript = $newScript . $this->excitationWavelengthsString();
		$newScript = $newScript . $this->emissionWavelengthsString();
		if ($this->isMultiPointOrSinglePointConfocal()) {
			$newScript = $newScript . $this->pinholeRadiusString();
		}
		return $newScript;
	}

	function excitationWavelengthsString() {
		$result = $this->multiChannelParameterString('ExcitationWavelength', 'ex');
		return $result;
	}

	function emissionWavelengthsString() {
		$result = $this->multiChannelParameterString('EmissionWavelength', 'em');
		return $result;
	}

	function multiChannelParameterString($name, $command) {
		$parameter = $this->parameter($name);
		$values = $parameter->value();
		$result = "";
		$currentChannel = 0;
			for ($i = 0; $i < 1 /*$this->numberOfChannels()*/; $i++) {
			//error_log("GENERATING SCRIPT (Setting): ".$name." -> ".$values[$i]);
			if (!empty ($values[$i])) {
				$result = $result . '$imageName setp -chan ';
				$chan = (int) $currentChannel;
				$result = $result . $chan;
				$result = $result . ' -' . "$command ";
				$result = $result . $values[$i];
				$result = $result . "\n";
				// Update $currentChannel
				$currentChannel++;
			}
		}
		return $result;
	}

	function microscopeParameterString() {
		$sampleSizesString = $this->sampleSizesString();
		$string = '-s {' . $sampleSizesString . '} ';
		return $string;
	}

	function microscopeTypeString() {
		$param = $this->parameter('MicroscopeType');
		$value = $param->translatedValue();
		$result = "-micr $value ";
		return $result;
	}

	// sample size adaption for all microscopes
	function numericalApertureString() {
		//if ($this->isMultiPointOrSinglePointConfocal()) {
		$param = $this->parameter('NumericalAperture');
		$value = $param->translatedValue();
		//} else {
		//    $value = $this->adaptedNumericalApertureFor(1);
		//} 
		$result = "-na $value ";
		return $result;
	}

	function pinholeRadiusString() {
		$result = $this->multiChannelParameterString('PinholeSize', 'pr');
		return $result;
	}

	// Support for Nipkow spinning disk
	function pinholeSpacingString() {
		$param = $this->parameter('PinholeSpacing');
		$value = $param->translatedValue();
		$result = "-ps $value ";
		return $result;
	}

	function sampleSizesString() {
		$result = '';
		$result = $result . (string) $this->sampleSizeX() . ' ';
		$result = $result . (string) $this->sampleSizeY();
		if ($this->isThreeDimensional()) {
			$result = $result . ' ' . (string) $this->sampleSizeZ();
		} else {
			// Use current channel
			//$result = $result . ' ' . (string) ($this->idealSampleSizeZFor(1));
		}
		if ($this->isTimeSeries()) {
			$result = $result . ' ' . (string) $this->sampleSizeT();
		}
		return $result;
	}

	function originalSampleSizesString() {
		$result = '';
		$result = $result . (string) ($this->sampleSizeX() / $this->sampleSizeAdaptionFactor()) . ' ';
		$result = $result . (string) ($this->sampleSizeY() / $this->sampleSizeAdaptionFactor());
		if ($this->isThreeDimensional()) {
			$result = $result . ' ' . (string) ($this->sampleSizeZ() / $this->sampleSizeAdaptionFactor());
		} else {
			// Use current channel
			$result = $result . ' ' . (string) ($this->idealSampleSizeZFor(1));
		}
		if ($this->isTimeSeries()) {
			$result = $result . ' ' . (string) $this->sampleSizeT();
		}
		return $result;
	}

	function lensRefractiveIndexString() {
		$param = $this->parameter('ObjectiveType');
		$value = $param->translatedValue();
		$result = "-ril $value ";
		return $result;
	}

	function mediumRefractiveIndexString() {
		$param = $this->parameter('SampleMedium');
		$value = $param->translatedValue();
		$result = "-ri $value ";
		return $result;
	}

	function photonCountString() {
		$result = "-pcnt ";
		if ($this->isTwoPhoton()) {
			$result = $result . "2";
		} else {
			$result = $result . "1";
		}
		return $result;
	}
}

//!---------------------------------------------------------
// @class    JobTaskSetting
// @desc     A job task setting is a complete set of 
//           image processing parameters that is used when
//           a job is processed by the queue manager.
//!---------------------------------------------------------
Class JobTaskSetting extends TaskSetting {
	//!---------------------------------------------------------
	// @function    JobTaskSetting::table()
	// @desc        Answer the name of the database table in 
	//              which the job task settings are stored. The 
	//              table contains the setting's name the owner 
	//              and the standard (default) flag.
	// @return      String
	//!---------------------------------------------------------    
	function table() {
		return "job_task_setting";
	}

	//!---------------------------------------------------------
	// @function    JobTaskParameterSetting::parameterTable()
	// @desc        Answer the name of the database table in
	//              which the parameters for settings of the 
	//              receiver's kind are stored. 
	// @return      String
	//!---------------------------------------------------------    
	function parameterTable() {
		return "job_task_parameter";
	}

  	function putScriptOn($script, $coverslipRelativePosition) {
		$step = 1;
		$newScript = $script;
		$parameter = $this->parameter('RemoveNoise');
		if ($parameter->isTrue()) {
			$source = $this->sourceForStep($step);
			$dest = $this->destinationForStep($step);
			$newScript = $newScript . $this->removeNoiseString($source, $dest);
			$newScript = $newScript . $this->destroyImageString($source);
			$step++;
		}
		
		// The psf computation string depends on the position of the coverslip
		// selected by the user. If it is either 'closest' or 'farthest' we let
		// Huygens adapt the PSF, if it is 'ignore' we ask to create a fixed
		// PSF with the given parameters
		$constant = 0;
		if ( $coverslipRelativePosition == 'ignore' ) {
			$constant = 1;
		}
		$parameter = $this->parameter('RemoveBackground');
		if ($parameter->isTrue()) {
			$source = $this->sourceForStep($step);
			$dest = $this->destinationForStep($step);
			$newScript = $newScript . $this->psfComputationString($source, $constant, 0);
			$newScript = $newScript . $this->removeBackgroundString($source, $dest);
			$newScript = $newScript . $this->destroyImageString($source);
			$step++;
		}
		// effective deconvolution script; step = 3 -> $source = b; $dest = c;
		$parameter = $this->parameter('FullRestoration');
		if ($parameter->isTrue()) {
			$source = $this->sourceForStep($step);
			$dest = $this->destinationForStep($step);
			$newScript = $newScript . $this->psfComputationString($source, $constant, 0);
			$newScript = $newScript . $this->fullRestorationString($source, $dest);
			$newScript = $newScript . $this->destroyImageString($source);
			$step++;
		}
		return $newScript;
	}

	// manage measured PSF
	function putScriptForMeasuredPointSpreadFunctionOn($script) {
		$step = 1;
		$newScript = $script;
		$parameter = $this->parameter('RemoveNoise');
		if ($parameter->isTrue()) {
			$source = $this->sourceForStep($step);
			$dest = $this->destinationForStep($step);
			$newScript = $newScript . $this->removeNoiseString($source, $dest);
			$newScript = $newScript . $this->destroyImageString($source);
			$step++;
		}
		$parameter = $this->parameter('RemoveBackground');
		if ($parameter->isTrue()) {
			$source = $this->sourceForStep($step);
			$dest = $this->destinationForStep($step);
			$newScript = $newScript . $this->removeBackgroundForMeasuredPointSpreadFunctionString($source, $dest);
			$newScript = $newScript . $this->destroyImageString($source);
			$step++;
		}
		$parameter = $this->parameter('FullRestoration');
		if ($parameter->isTrue()) {
			$source = $this->sourceForStep($step);
			$dest = $this->destinationForStep($step);
			$newScript = $newScript . $this->fullRestorationForMeasuredPointSpreadFunctionString($source, $dest);
			$newScript = $newScript . $this->destroyImageString($source);
			$step++;
		}
		return $newScript;
	}

	function destroyImageString($imageName) {
		$result = '';
		$result = $result . $imageName . ' del';
		$result = $result . "\n";
		return $result;
	}

	function getNumberOfOperations() {
		$result = 0;
		$parameter = $this->parameter('RemoveNoise');
		if ($parameter->isTrue()) {
			$result++;
		}
		$parameter = $this->parameter('RemoveBackground');
		if ($parameter->isTrue()) {
			$result++;
		}
		$parameter = $this->parameter('FullRestoration');
		if ($parameter->isTrue()) {
			$result++;
		}
		return $result;
	}

	function sourceForStep($stepNumber) {
		$stepSources = array (
			'$imageName',
			'a',
			'b'
		);
		$index = $stepNumber -1;
		return $stepSources[$index];
	}

	function destinationForStep($stepNumber) {
		$maxSteps = $this->getNumberOfOperations();
		$stepDestinations = array (
			array (
				'c',
				'a',
				'a'
			),
			array (
				'x',
				'c',
				'b'
			),
			array (
				'x',
				'x',
				'c'
			)
		);
		$index = $stepNumber -1;
		$row = $stepDestinations[$index];
		$index = $maxSteps -1;
		return $row[$index];
	}

	function psfComputationString($source, $constant = 0, $position = 0) {
		// To calculate a theoretical PSF, the best thing is to provide an empty
        // one. Huygens will take the necessary decissions, see
        // http://support.svi.nl/wiki/TheoreticalPsf
        if (!$constant ) {
            return "catch { psf clear }\n";
        }
        // Alternatively, this calculates a constant PSF for the provided
        // parameters, but it won't be adapted to the sample depth if spherical
        // aberration is present.
		return 'catch { ' . $source . ' genpsf -> psf -dims auto -zPos '.
			$position .' }' . "\n";
	}

	function removeNoiseString($source, $dest) {
		$result = '';
		$result = $result . $source . ' gauss -> ' . $dest . ' -sigma ';
		$result = $result . $this->noiseLevelString();
		$result = $result . ' -units s';
		$result = $result . "\n";
		return $result;
	}

	// never called: to remove
	function removeBackgroundString($source, $dest) {
		$result = '';
		$result = $result . $source . ' cmle psf -> ' . $dest . ' ';
		$result = $result . "-it 3 ";
		$result = $result . '-bgMode object ';
		$result = $result . '-blMode auto ';
		$result = $result . '-q 0 ';
		$result = $result . '-mode fast ';
		$result = $result . '-pad auto';
		$result = $result . "\n";
		return $result;
	}

	// manage measured PSF
	// never called: to remove
	function removeBackgroundForMeasuredPointSpreadFunctionString($source, $dest) {
		$result = '';
		$result = $result . $source . ' cmle $psf -> ' . $dest . ' ';
		$result = $result . "-it 3 ";		// !!
		$result = $result . '-bgMode object ';
		$result = $result . $this->removeBackgroundPercentString();
		$result = $result . '-blMode auto ';
		$result = $result . '-q 0 ';
		$result = $result . '-mode fast ';
		$result = $result . '-pad auto';
		$result = $result . "\n";
		return $result;
	}

	function fullRestorationString($source, $dest) {
		$result = '';

$parameter = $this->parameter('DeconvolutionAlgorithm');
$string = $parameter->possibleValuesString();
$string = $parameter->name();
$value = $parameter->value();

		// This restores the default behavior in case the entry "DeconvolutionAlgorithm"
		// is not in the database
		if ( empty( $value ) == true )
		  $value = "cmle";

		$result = $result . $source . ' ' . $value . ' psf -> ' . $dest . ' ';	
		//$result = $result . $source . ' cmle psf -> ' . $dest . ' ';
		$result = $result . $this->signalNoiseRatioString();
		$result = $result . $this->maxIterationsString();
		// background estimation manual/auto
		$parameter = $this->parameter("BackgroundOffsetPercent");
        #printDebug("fullRestorationString", $parameter);
        #printDebug(debug_backtrace()); exit;
		$value = $parameter->value();
		$internalValue = $parameter->internalValue();
		//$result = $result . '-bgMode object ';
		//error_log("GENERATING SCRIPT (Setting): background offset percent -> ".$value[0].", ".$value[1]." | ".$internalValue[0].", ".$internalValue[1]." |");
		if ($value[0] == "auto" || $internalValue[0] == "auto") {
			$result = $result . '-bgMode auto ';
		} else if ($value[0] == "object" || $internalValue[0] == "object") {
			$result = $result . '-bgMode object ';
		} else {
			$result = $result . '-bgMode manual ';
			$result = $result . $this->backgroundPerChannelString();
		}
		$result = $result . '-blMode auto ';
		$result = $result . '-q ';
		$parameter = $this->parameter("QualityChangeStoppingCriterion");
		$result = $result . $parameter->value() . " ";
		$result = $result . '-mode fast ';
		$result = $result . '-pad auto';
		$result = $result . "\n";
		return $result;
	}

	// manage measured PSF
	function fullRestorationForMeasuredPointSpreadFunctionString($source, $dest) {
		$result = '';
		
		// This restores the default behavior in case the entry "DeconvolutionAlgorithm"
		// is not in the database
		$value = $this->decAlgorithmString();
		if ( empty( $value ) == true )
			$value = "cmle";
		$result = $result . $source . ' ' .$value . ' psf -> ' . $dest . ' ';

		//$result = $result . $source . $this->decAlgorithmString() . ' psf -> ' . $dest . ' ';
		//$result = $result . $source . ' cmle $psf -> ' . $dest . ' ';
		$result = $result . $this->signalNoiseRatioString();
		$result = $result . $this->maxIterationsString();
		// background estimation manual/auto
		$parameter = $this->parameter("BackgroundOffsetPercent");
		$value = $parameter->value();
		$internalValue = $parameter->internalValue();
		//$result = $result . '-bgMode object ';
		if ($value[0] == "auto" || $internalValue[0] == "auto") {
			$result = $result . '-bgMode auto ';
		} else if ($value[0] == "object" || $internalValue[0] == "object") {
			$result = $result . '-bgMode object ';
		} else {
			$result = $result . '-bgMode manual ';
			$result = $result . $this->backgroundPerChannelString();
		}
		$result = $result . '-blMode auto ';
		$result = $result . '-q ';
		$parameter = $this->parameter("QualityChangeStoppingCriterion");
		$result = $result . $parameter->value() . " ";
		$result = $result . '-mode fast ';
		$result = $result . '-pad auto';
		$result = $result . "\n";
		return $result;
	}

	function noiseLevelString() {
		$parameter = $this->parameter('RemoveNoiseEffectiveness');
		$value = $parameter->value();
		$result = '{';
		$result = $result . "$value $value 0 0 }";
		return $result;
	}

	function backgroundPerChannelString() {
		$parameter = $this->parameter('BackgroundOffsetPercent');
		$values = $parameter->value();
		/*if (!is_array($values)) {
			$channels = $this->numberOfChannels();
			$values = array_fill(1, $channels, $values);
		}
		$result = '-bg {';
		for ($i = 0; $i < $this->numberOfChannels(); $i++) {
			$newValue = -1 * (100 - $values[$i]);
			$result = $result . $newValue;
			if ($i < $this->numberOfChannels()) {
				$result = $result . " ";
			}
		}
		$result = $result . '} ';*/

        /* TODO: why does this return channel zero's? */
		$result = '-bg ' . $values[0] . " ";
		return $result;
	}

	function removeBackgroundPercentString() {
		$parameter = $this->parameter('RemoveBackgroundPercent');
		$values = $parameter->value();
		if (!is_array($values)) {
			$channels = $this->numberOfChannels();
			$values = array_fill(1, $channels, $values);
		}
		$result = '-bg {';
		for ($i = 0; $i < $this->numberOfChannels(); $i++) {
			$newValue = -1 * (100 - $values[$i]);
			$result = $result . $newValue;
			if ($i < $this->numberOfChannels()) {
				$result = $result . " ";
			}
		}
		$result = $result . '} ';
		return $result;
	}

	function signalNoiseRatioString() {
		$parameter = $this->parameter('SignalNoiseRatio');
		$values = $parameter->value();
		//if (!is_array($values)) {
		//	$values = array_fill(1, $this->numberOfChannels(), $values);
		//}
		//$result = '-sn {';
		//for ($i = 1; $i <= $this->numberOfChannels(); $i++) {
		//	$result = $result . $values[$i];
		//	if ($i < $this->numberOfChannels()) {
		//		$result = $result . " ";
		//	}
		//}
		//$result = $result . '} ';
		
		$parameter = $this->parameter('DeconvolutionAlgorithm');
		$deconvolutionAlgorithm = $parameter->value();
		
		$value = $values[0];
		if ($deconvolutionAlgorithm == "qmle") {
			if ($value == "1")	$value = "low";
			else if ($value == "2")	$value = "fair";
			else if ($value == "3")	$value = "good";
            else if ($value == "4")	$value = "inf";
			else			$value = "fair";
		}
		
		$result = '-sn ' . $value . " ";
		return $result;
	}

	function maxIterationsString() {
		$parameter = $this->parameter('NumberOfIterations');
		$value = $parameter->value();
		$result = "-it $value ";
		return $result;
	}
	
	function decAlgorithmString() {
		$parameter = $this->parameter('DeconvolutionAlgorithm');
		$value = $parameter->value();
		$result = " $value";
		return $result;
	}
}
