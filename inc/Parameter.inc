<?php

// Module Parameter.inc

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

require_once ("Database.inc");

//!---------------------------------------------------------
// @class    Parameter
// @desc     A single parameter of a parameter setting
//           Superclass of specific parameter classes
//!---------------------------------------------------------
Class Parameter {
	var $value; // @var value Object  The current value of the parameter
	var $name; // @var name  String  The name of the parameter

	//!---------------------------------------------------------
	// @function    Parameter::Parameter
	// @desc        Set a parameter. The parameter is stored
	//              in the setting under its name.
	// @param       name  String  The name of the new parameter
	// @return      Void
	//!---------------------------------------------------------  
	function Parameter($name) {
		$this->name = $name;
		$this->value = '';
	}

	//!---------------------------------------------------------
	// @function    Parameter::numberOfValues
	// @desc        Values may be single values or arrays of 
	//              values. Answers the number of values for
	//              the receiver.
	// @return      Integer
	//!---------------------------------------------------------  
	function numberOfValues() {
		if (!is_array($this->value()))
			return 1;
		return count(array_filter($this->values()));
	}

	//!---------------------------------------------------------
	// @function    Parameter::check
	// @desc        Answer true if the parameter is set to a valid
	//              value and false otherwise. 
	// @return      Boolean
	//!---------------------------------------------------------  
	function check() {
		return $this->checkValue($this->value());
	}

	//!---------------------------------------------------------
	// @function    Parameter::checkValue
	// @desc        Answer true if value is a valid value for the
	//              receiver and false otherwise. Subclasses may
	//              override the method to do something appropriate.
	// @param       value  String   The value to be checked  
	// @return      Boolean
	//!---------------------------------------------------------  
	function checkValue($value) {
		return $value != '';
	}

	function isForImage() {
		return False;
	}

	function isForMicroscope() {
		return False;
	}

	function isForCapture() {
		return False;
	}

	function isRangeParameter() {
		return False;
	}

	function isVariableChannel() {
		return False;
	}

	function name() {
		return $this->name;
	}

	//!---------------------------------------------------------
	// @function    Parameter::value
	// @desc        Answers the value of the parameter.
	// @return      Object
	//!---------------------------------------------------------  
	function value() {
		return $this->value;
	}

	//!---------------------------------------------------------
	// @function    Parameter::internalValue
	// @desc        Answers the internal representation of the
	//              parameters value. 
	// @return      Object
	//!---------------------------------------------------------  
	function internalValue() {
		return $this->value();
	}

	//!---------------------------------------------------------
	// @function    Parameter::internalPossibleValues
	// @desc        Answer the possible values for the parameter
	//              in their internal representation. This method
	//              will be overriden when the internal 
	//              representation differs from the external
	//              representation.
	// @return      array
	//!---------------------------------------------------------  
	function internalPossibleValues() {
		return $this->possibleValues();
	}

	//!---------------------------------------------------------
	// @function    Parameter::setValue
	// @desc        Set the value of the parameter to value.
	//              value is usually a string but it might be
	//              an array in some cases
	// @param       value  String  The new value of the parameter
	// @return      Void
	//!---------------------------------------------------------  
	function setValue($value) {
		$this->value = $value;
	}

	//!---------------------------------------------------------
	// @function    Parameter::printRadioButtonForValue
	// @desc        print one radio button for this parameter 
	//              for the value value. The button will be
	//              checked if value is the internal value of
	//              the parameter.
	// @param       value       Object  the value for the radio
	//                                  button
	// @param       customHTML  String  further html options to 
	//                                  be used for the radio 
	//                                  button
	// @return      Void
	//!--------------------------------------------------------- 
	function printRadioButtonForValue($value, $customHTML) {
		$result = '<input type="radio" name="' . $this->name() . '" value="' . $value . '" ';
		if ($value == $this->internalValue()) {
			$result = $result . 'checked="checked" ';
		}
		$result = $result . $customHTML . ' />';
		print $result;
	}

	function isBoolean() {
		return False;
	}

	function displayString() {
		$result = '';
		$value = $this->value();
		if ($value == NULL) {
			return '';
		}
		if ($value == '') {
			return '';
		}
		if ($value == 'False' && $this->isBoolean()) {
			return '';
		}
		$internalValue = $this->internalValue();	// answers the internal representation of the parameters value.
		if ($this->name() == "BackgroundOffsetPercent" && $internalValue[0] == "auto") {
			$value = "auto";
		}
		else if ($this->name() == "BackgroundOffsetPercent" && $internalValue[0] == "object") {
			$value = "in/near object";
		} else {
			if (is_array($value)) {
				# array_shift($value);
				$value = array_filter($value);
				$value = implode(", ", $value);
			}
			$split = explode("_", $value);
			if (count($split) > 1) {
				$value = $split[0];
			}
		}
		$name = $this->name();
		if (!$this->isBoolean()) {
			$name = $name . ":";
		}
		$name = str_replace('CMount', 'Cmount', $name);
		$uppercase = array (
			'A',
			'B',
			'C',
			'D',
			'E',
			'F',
			'G',
			'H',
			'I',
			'J',
			'K',
			'L',
			'M',
			'N',
			'O',
			'P',
			'Q',
			'R',
			'S',
			'T',
			'U',
			'V',
			'W',
			'X',
			'Y',
			'Z'
		);
		$lowercase = array (
			' a',
			' b',
			' c',
			' d',
			' e',
			' f',
			' g',
			' h',
			' i',
			' j',
			' k',
			' l',
			' m',
			' n',
			' o',
			' p',
			' q',
			' r',
			' s',
			' t',
			' u',
			' v',
			' w',
			' x',
			' y',
			' z'
		);
		$name = str_replace($uppercase, $lowercase, $name);
		
		if (strcmp($this->name,"BackgroundOffsetPercent") == 0) {
			$tempstring = " background absolute offset:";
			$result = $result . str_pad($tempstring, 35, ' ', STR_PAD_RIGHT);
		}
		else {
			$result = $result . str_pad($name, 35, ' ', STR_PAD_RIGHT);
		}
		
		if (!$this->isBoolean()) {
			$result = $result . $value;
		}
		$result = $result . "\n";
		return $result;
	}

	//!---------------------------------------------------------
	// @function    Parameter::translatedValue
	// @desc        Answer the value of the parameter in a 
	//              translated form that is in the form that
	//              is used in the script. Per default it is
	//              just the value but this will be changed
	//              in subclasses when neccessary.
	// @return      string
	//!---------------------------------------------------------    
	function translatedValue() {
		return $this->value();
	}
}

Class ChoiceParameter extends Parameter {
	var $possibleValues; // @var possibleValues array  the possible values for the choice parameter

	function ChoiceParameter($name) {
		$this->Parameter($name);
		$possibleValues = array ();
	}

	function setupAllowedValues($db) {
		$values = $db->readPossibleValues($this);
		$this->setPossibleValues($values);
		$defaultValue = $db->defaultValue($this->name());
		if ($defaultValue != NULL) {
			$this->setValue($defaultValue);
		}
	}

	//!---------------------------------------------------------
	// @function    ChoiceParameter::setPossibleValues
	// @desc        Set the possible values of this parameter
	//              to values.
	// @param       values  array  the possible values to be set
	// @return      void
	//!---------------------------------------------------------    
	function setPossibleValues($values) {
		$this->possibleValues = $values;
	}

	//!---------------------------------------------------------
	// @function    ChoiceParameter::possibleValues
	// @desc        Answer all values that are possible for this
	//              choice parameter.
	// @return      array
	//!---------------------------------------------------------  
	function possibleValues() {
		return $this->possibleValues;
	}

	//!---------------------------------------------------------
	// @function    ChoiceParameter::possibleValuesString
	// @desc        Answer a text containing the possible values
	//              for this parameter seperated by ',' 
	// @return      string
	//!---------------------------------------------------------  
	function possibleValuesString() {
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

	function printRadioButtonsEmbeddedIn($htmlPrefix, $htmlPostfix) {
		$possibleValues = $this->possibleValues();
		foreach ($this->internalPossibleValues() as $internalPossibleValue) {
			$possibleValue = current($possibleValues);
			print $htmlPrefix;
			$this->printRadioButtonForValue($internalPossibleValue, '');
			print $possibleValue;
			print $htmlPostfix;
			print "\n";
			next($possibleValues);
		}
	}

	//!---------------------------------------------------------
	// @function    ChoiceParameter::printRadioButtons
	// @desc        Print radio buttons for the possible values
	//              of the parameter, one in a line. The button
	//              for the current value of the parameter will
	//              be checked.
	// @return      void
	//!---------------------------------------------------------  
	function printRadioButtons() {
		$possibleValues = $this->possibleValues();
		foreach ($this->internalPossibleValues() as $internalPossibleValue) {
			$possibleValue = current($possibleValues);
			$this->printRadioButtonForValue($internalPossibleValue, '');
			print $possibleValue;
			print "<br>\n";
			next($possibleValues);
		}
	}

	//!---------------------------------------------------------
	// @function    ChoiceParameter::printSingleLineRadioButtons
	// @desc        Print radio buttons for the possible values
	//              of the parameter, all in the same line. The 
	//              button for the current value of the parameter 
	//              will be checked.
	// @return      void
	//!---------------------------------------------------------  
	function printSingleLineRadioButtons() {
		foreach ($this->internalPossibleValues() as $possibleValue) {
			$this->printRadioButtonForValue($possibleValue, '');
			print $possibleValue;
		}
	}

	function printCheckBox($valueChecked, $customHTML) {
		$result = '<input type="checkbox" name="' . $this->name() . '" value="' . $valueChecked . '" ';
		if ($valueChecked == $this->value()) {
			$result = $result . 'checked="checked" ';
		}
		$result = $result . $customHTML . ' />';
		print $result;
	}

	//!---------------------------------------------------------
	// @function    ChoiceParameter::checkValue
	// @desc        Answer true if value is a valid value for the
	//              receiver and false otherwise. The value of a
	//              choice parameter must be one of its possible
	//              values.
	// @param       value  String   The value to be checked  
	// @return      Boolean
	//!---------------------------------------------------------  
	function checkValue($value) {
		$result = parent :: checkValue($value);
		$result = $result && (in_array($value, $this->possibleValues()));
		return $result;
	}
}

Class BooleanParameter extends ChoiceParameter {

	function BooleanParameter($name) {
		$this->ChoiceParameter($name);
		$this->possibleValues[] = 'True';
		$this->possibleValues[] = 'False';
	}

	function isTrue() {
		return ($this->value == 'True');
	}

	function isBoolean() {
		return True;
	}

	function setupAllowedValues($db) {
		$this->setValue('False');
	}

	function displayString() {
		$result = '';
		if ($this->value() == 'True') {
			$result = $result . parent :: displayString();
		}
		return $result;
	}
	
	function printCheckBox($customHTML) {
		$result = '<input type="checkbox" name="' . $this->name() . '" value="True" ';
		if ($this->value == "True") {
			$result = $result . 'checked="checked" ';
		}
		$result = $result . $customHTML . ' />';
		return $result;
	}

	function set() {
		$this->value = 'True';
	}

	function reset() {
		$this->value = 'False';
	}
}

Class NumericalParameter extends Parameter {
	var $min;
	var $max;
	var $checkMin;
	var $checkMax;
	var $isMinIncluded;
	var $isMaxIncluded;

	function NumericalParameter($name) {
		$this->Parameter($name);
		$min = NULL;
		$max = NULL;
		$checkMin = False;
		$checkMax = False;
		$isMinIncluded = True;
		$isMaxIncluded = True;
	}

	function setupAllowedValues($db) {
		$values = $db->readNumericalValueRestrictions($this);
		$min = $values[0];
		$max = $values[1];
		$minIncluded = $values[2];
		$maxIncluded = $values[3];
		$default = $values[4];
		if ($min != NULL) {
			$this->setMin($min);
		}
		if ($max != NULL) {
			$this->setMax($max);
		}
		if ($minIncluded == 't') {
			$this->isMinIncluded = True;
		} else {
			$this->isMinIncluded = False;
		}
		if ($maxIncluded == 't') {
			$this->isMaxIncluded = True;
		} else {
			$this->isMaxIncluded = False;
		}
		if ($default != NULL) {
			$this->setValue($default);
		}
	}

	//!---------------------------------------------------------
	// @function    NumericalParameter::setMin
	// @desc        Set the minimal allowed value for the 
	//              parameter (the value itself may be allowed
	//              or not).
	// @return      void
	//!---------------------------------------------------------  
	function setMin($value) {
		$this->min = $value;
		$this->checkMin = True;
	}

	//!---------------------------------------------------------
	// @function    NumericalParameter::setMax
	// @desc        Set the maximal allowed value for the 
	//              parameter (the value itself may be allowed
	//              or not).
	// @return      void
	//!---------------------------------------------------------  
	function setMax($value) {
		$this->max = $value;
		$this->checkMax = True;
	}

	//!---------------------------------------------------------
	// @function    NumericalParameter::checkMin
	// @desc        Answer whether the parameter should be 
	//              checked against a minimum value.
	// @return      Bool
	//!---------------------------------------------------------  
	function checkMin() {
		return $this->checkMin;
	}

	//!---------------------------------------------------------
	// @function    NumericalParameter::checkMax
	// @desc        Answer whether the parameter should be 
	//              checked against a maximum value.
	// @return      Bool
	//!---------------------------------------------------------  
	function checkMax() {
		return $this->checkMax;
	}

	//!---------------------------------------------------------
	// @function    NumericalParameter::min
	// @desc        Answer the minimal allowed value for the 
	//              parameter (the value itself may be allowed
	//              or not).
	// @return      Number
	//!---------------------------------------------------------  
	function min() {
		return $this->min;
	}

	//!---------------------------------------------------------
	// @function    NumericalParameter::max
	// @desc        Answer the maximal allowed value for the 
	//              parameter (the value itself may be allowed
	//              or not).
	// @return      Number
	//!---------------------------------------------------------  
	function max() {
		return $this->max;
	}

	//!---------------------------------------------------------
	// @function    NumericalParameter::checkValue
	// @desc        Answer true if value is a valid value for the
	//              receiver and false otherwise. The value of a
	//              numerical parameter must be a number. It must
	//              possibly be larger than a minimum or smaller
	//              than a maximum.
	// @param       value  String   The value to be checked  
	// @return      Boolean
	//!---------------------------------------------------------  
	function checkValue($value) {
		if (preg_match('/^[+|-]?\d+(\.\d+)?$/', $value) == 0) {
			return False;
		}
		if ($this->isMinIncluded) {
			if ($this->checkMin && !((float) $value >= $this->min)) {
				return False;
			}
		}
		if (!$this->isMinIncluded) {
			if ($this->checkMin && !((float) $value > $this->min)) {
				return False;
			}
		}
		if ($this->isMaxIncluded) {
			if ($this->checkMax && !((float) $value <= $this->max)) {
				return False;
			}
		}
		if (!$this->isMaxIncluded) {
			if ($this->checkMax && !((float) $value < $this->max)) {
				return False;
			}
		}
		return True;
	}
}
Class VariableChannelsParameter extends NumericalParameter {
	var $numberOfChannels;

	function VariableChannelsParameter($name) {
		$this->NumericalParameter($name);
		$this->value = array (
			0 => NULL,
			1 => NULL,
			2 => NULL,
			3 => NULL,
			4 => NULL
		);
		$numberOfChannels = 1;
	}

	function setNumberOfChannels($number) {
		$this->numberOfChannels = $number;
	}

	function numberOfChannels() {
		return $this->numberOfChannels;
	}

	//!---------------------------------------------------------
	// @function    VariableChannelsParameter::setValue
	// @desc        Set the value of the parameter to value.
	//              If value is not an array the value of the 
	//              parameter is set to an array with the value
	//              at index 0. 
	// @param       value  Object  The new value of the parameter
	// @return      Void
	//!---------------------------------------------------------  
	function setValue($value) {
		$theValue = $value;
		if (!is_array($value)) {
			$theValue = array (
				0 => $value,
				1 => NULL,
				2 => NULL,
				3 => NULL,
				4 => NULL
			);
			
		}
		//TODO: check if useful
		else if (is_array($value) && count($value) < 5) {
			$theValue = array(5);
			for ($i = 0; $i < count($value); $i++) {
				$theValue[] = $value[$i];
			}
		}
		/*else if (is_array($value) && count($value) == 1) {
			$theValue = array (
				0 => $value[0],
				1 => NULL,
				2 => NULL,
				3 => NULL,
				4 => NULL
			);
		}*/
		$this->value = $theValue;
	}

	function isVariableChannel() {
		return True;
	}

	//!---------------------------------------------------------
	// @function    VariableChannelsParameter::value
	// @desc        Answers an array of the values for the different 
	//              channels of the parameter. If a value
	//              for a channel is not valid set it to the value
	//              of the preceding channel.
	// @return      array
	//!---------------------------------------------------------  
	function value() {
		$value = $this->value;
		$newValue = array (
			NULL
		);
        # printDebug($this);
        # printDebug(debug_backtrace());
		for ($i = 0; $i < $this->numberOfChannels(); $i++) {
			if ($i < sizeof($value) && $this->checkValue($value[$i])) {
				$newValue[$i] = $value[$i];
			}
			/*else {
				$newValue[$i] = $newValue[$i -1];
			}*/
		}
		return $newValue;
	}

	function internalValue() {
		return $this->value;
	}

	//!---------------------------------------------------------
	// @function    VariableChannelsParameter::check
	// @desc        Answer true if the parameter is set to a valid
	//              value and false otherwise. The values are checked
	//              for each channel.
	// @return      Boolean
	//!---------------------------------------------------------  
	function check() {
		$result = True;
		for ($i = 0; $i < $this->numberOfChannels; $i++) {
			$value = $this->internalValue();
			$result = $result && $this->checkValue($value[$i]);
		}
		return $result;
	}
}

// manage parameter adaption
Class HasAdaptedValues extends ChoiceParameter {
	function HasAdaptedValues() {
		$this->ChoiceParameter("HasAdaptedValues");
	}
}

// manage measured PSF
Class PointSpreadFunction extends ChoiceParameter {
	function PointSpreadFunction() {
		$this->ChoiceParameter("PointSpreadFunction");
	}
}

// TODO refactor
Class PSF extends Parameter {
	function PSF() {
		$this->Parameter('PSF');
		$this->value = array (
			NULL,
			NULL,
			NULL,
			NULL,
			NULL
		);
	}

	function setupAllowedValues($db) {
		// TODO
	}
}

Class IsMultiChannel extends ChoiceParameter {
	function IsMultiChannel() {
		$this->ChoiceParameter("IsMultiChannel");
	}

	function isForImage() {
		return True;
	}

	function displayString() {
		$result = '';
		if ($this->value() == 'True') {
			$result = $result . " multichannel image\n";
		} else {
			$result = $result . " single channel image\n";
		}
		return $result;
	}
}

Class SingleOrMultiChannelParameter extends ChoiceParameter {
	var $isMultiChannel; // @var isMultiChannel  Boolean  tells whether this is a single or multi channel parameter

	//!---------------------------------------------------------
	// @function    SingleOrMultiChannelParameter::isMultiChannel
	// @desc        Answer true if the receiver is a multi channel
	//              parameter
	// @return      bool
	//!---------------------------------------------------------  
	function isMultiChannel() {
		return $this->isMultiChannel;
	}

	//!---------------------------------------------------------
	// @function    SingleOrMultiChannelParameter::isSingleChannel
	// @desc        Answer true if the receiver is a single channel
	//              parameter
	// @return      bool
	//!---------------------------------------------------------  
	function isSingleChannel() {
		return !$this->isMultiChannel();
	}

	//!---------------------------------------------------------
	// @function    SingleOrMultiChannelParameter::beMultiChannel
	// @desc        Make the receiver a multi channel parameter.
	// @return      void
	//!---------------------------------------------------------  
	function beMultiChannel() {
		$this->isMultiChannel = True;
	}

	//!---------------------------------------------------------
	// @function    SingleOrMultiChannelParameter::beSingleChannel
	// @desc        Make the receiver a single channel parameter.
	// @return      void
	//!---------------------------------------------------------    
	function beSingleChannel() {
		$this->isMultiChannel = False;
	}

	//!---------------------------------------------------------
	// @function    SingleOrMultiChannelParameter::setValue
	// @desc        Set the value of the parameter to value.
	//              If value contains the prefix single_ or multi_
	//              the parameter is set to single or multi and 
	//              the postfix of value is set as value of the 
	//              parameter.
	// @param       value  string  The new value of the parameter
	// @return      Void
	//!---------------------------------------------------------  
	function setValue($value) {
		if (!strstr($value, "_")) {
			$prefix = $this->prefix();
			$value = $prefix . "_" . $value;
		}
		$split = explode("_", $value);
		$fileFormat = $split[1];
		$this->value = $fileFormat;
		$prefix = $split[0];
		if ($prefix == 'multi') {
			$this->beMultiChannel();
		}
		if ($prefix == 'single') {
			$this->beSingleChannel();
		}
	}

	function prefix() {
		if ($this->isSingleChannel()) {
			$prefix = "single";
		} else {
			$prefix = "multi";
		}
		return $prefix;
	}

	function internalValue() {
		$result = $this->prefix() . "_" . $this->value();
		return $result;
	}

	function internalPossibleValues() {
		$result = array ();
		foreach ($this->possibleValues() as $possibleValue) {
			$result[] = $this->prefix() . "_" . $possibleValue;
		}
		return $result;
	}
}

Class ImageFileFormat extends SingleOrMultiChannelParameter {

	function ImageFileFormat() {
		$this->ChoiceParameter("ImageFileFormat");
	}

	function isForImage() {
		return True;
	}

	function fileExtensions() {
		$db = new DatabaseConnection();
		$result = $db->fileExtensions($this->value());
		return $result;
	}
}

Class NumberOfChannels extends ChoiceParameter {
	function NumberOfChannels() {
		$this->ChoiceParameter("NumberOfChannels");
	}

	function isForImage() {
		return True;
	}
}

Class ImageGeometry extends SingleOrMultiChannelParameter {
	function ImageGeometry() {
		$this->ChoiceParameter("ImageGeometry");
	}

	function isForImage() {
		return True;
	}
}

Class MicroscopeType extends ChoiceParameter {
	function MicroscopeType() {
		$this->ChoiceParameter("MicroscopeType");
	}

	function isForMicroscope() {
		return True;
	}

	//!---------------------------------------------------------
	// @function    MicroscopeType::translatedValue
	// @desc        Answer the value of the parameter in a 
	//              translated form that is in the form that
	//              is used in the script. The translation of
	//              the microscope type is read from the 
	//              database
	// @return      string
	//!---------------------------------------------------------    
	function translatedValue() {
		$value = $this->value;
		$db = new DatabaseConnection();
		$result = $db->translationFor($this);
		return $result;
	}
}

Class NumericalAperture extends NumericalParameter {
	function NumericalAperture() {
		$this->NumericalParameter("NumericalAperture");
	}

	function isForMicroscope() {
		return True;
	}
}

Class ObjectiveMagnification extends ChoiceParameter {
	function ObjectiveMagnification() {
		$this->ChoiceParameter("ObjectiveMagnification");
	}

	function isForMicroscope() {
		return True;
	}
}

Class ObjectiveType extends ChoiceParameter {
	function ObjectiveType() {
		$this->ChoiceParameter("ObjectiveType");
	}

	function isForMicroscope() {
		return True;
	}

	//!---------------------------------------------------------
	// @function    ObjectiveType::translatedValue
	// @desc        Answer the value of the parameter in a 
	//              translated form that is in the form that
	//              is used in the script. The translation of
	//              the objective type is read from the 
	//              database
	// @return      string
	//!---------------------------------------------------------    
	function translatedValue() {
		$value = $this->value;
		$db = new DatabaseConnection();
		$result = $db->translationFor($this);
		return $result;
	}
}

Class SampleMedium extends ChoiceParameter {
	function SampleMedium() {
		$this->ChoiceParameter("SampleMedium");
	}

	function isForMicroscope() {
		return True;
	}

	//!---------------------------------------------------------
	// @function    SampleMedium::translatedValue
	// @desc        Answer the value of the parameter in a 
	//              translated form that is in the form that
	//              is used in the script. The translation of
	//              the sample medium is read from the 
	//              database
	// @return      string
	//!---------------------------------------------------------    
	function translatedValue() {
		$value = $this->value;
		$db = new DatabaseConnection();
		$result = $db->translationFor($this);
		if ($result == 0)
			return $value;
		return $result;
	}
}

Class Binning extends ChoiceParameter {
	function Binning() {
		$this->ChoiceParameter("Binning");
	}

	function isForCapture() {
		return True;
	}
}

Class ExcitationWavelength extends NumericalParameter {
	function ExcitationWavelength() {
		$this->NumericalParameter("ExcitationWavelength");
		$this->value = array (
			0 => NULL,
			1 => NULL,
			2 => NULL,
			3 => NULL,
			4 => NULL
		);
	}
	
	function setValue($value) {
		if (is_array($value)) {
			for ($i = 0; $i < count($value) && $i <= count( $this->value ); $i++)
				$this->value[$i] = $value[$i];
		}
		else {
			$this->value[0] = $value;
		}
	}

	function isForMicroscope() {
		return True;
	}
}

Class EmissionWavelength extends NumericalParameter {
	function EmissionWavelength() {
		$this->NumericalParameter("EmissionWavelength");
		$this->value = array (
			0 => NULL,
			1 => NULL,
			2 => NULL,
			3 => NULL,
			4 => NULL
		);
	}
	
	function setValue($value) {
		if (is_array($value)) {
			for ($i = 0; $i < count($value) && $i <= count( $this->value ); $i++)
				$this->value[$i] = $value[$i];
		}
		else {
			$this->value[0] = $value;
		}
	}

	function isForMicroscope() {
		return True;
	}
}

Class CMount extends NumericalParameter {
	function CMount() {
		$this->NumericalParameter("CMount");
	}

	function isForMicroscope() {
		return True;
	}
}

Class TubeFactor extends NumericalParameter {
	function TubeFactor() {
		$this->NumericalParameter("TubeFactor");
	}

	function isForMicroscope() {
		return True;
	}
}

Class CCDCaptorSizeX extends NumericalParameter {
	function CCDCaptorSizeX() {
		$this->NumericalParameter("CCDCaptorSizeX");
	}

	function isForCapture() {
		return True;
	}

	function displayString() {
		$value = $this->value();
		$result = '';
		$name = ' pixel size:';
		$result = $result . str_pad($name, 35, ' ', STR_PAD_RIGHT);
		$result = $result . $value;
		$result = $result . "\n";
		return $result;
	}
}

Class ZStepSize extends NumericalParameter {
	function ZStepSize() {
		$this->NumericalParameter("ZStepSize");
	}

	function isForCapture() {
		return True;
	}
}

Class TimeInterval extends NumericalParameter {
	function TimeInterval() {
		$this->NumericalParameter("TimeInterval");
	}

	function isForCapture() {
		return True;
	}
}

Class PinholeSize extends NumericalParameter {
	function PinholeSize() {
		$this->NumericalParameter("PinholeSize");
		// manage one pinhole radius per channel
		$this->value = array (
			NULL,
			NULL,
			NULL,
			NULL,
			NULL
		);
	}
	
	function setValue($value) {
                if (is_array($value)) {
                        for ($i = 0; $i < count($value); $i++)
				$this->value[$i] = $value[$i];
                }
                else {
			$this->value[0] = $value;
                }
        }
	
	function isForCapture() {
		return True;
	}
}

// Support for Nipkow spinning disk
Class PinholeSpacing extends NumericalParameter {
	function PinholeSpacing() {
		$this->NumericalParameter("PinholeSpacing");
	}

	function isForCapture() {
		return True;
	}
}

Class MultiChannelOutput extends BooleanParameter {
	function MultiChannelOutput() {
		$this->BooleanParameter("MultiChannelOutput");
	}
}

Class RemoveNoise extends BooleanParameter {
	function RemoveNoise() {
		$this->BooleanParameter("RemoveNoise");
	}
}

Class StyleOfProcessing extends ChoiceParameter {
	function StyleOfProcessing() {
		$this->ChoiceParameter("StyleOfProcessing");
	}

	function displayString() {
		$name = $this->name() . ":";
		$uppercase = array (
			'A',
			'B',
			'C',
			'D',
			'E',
			'F',
			'G',
			'H',
			'I',
			'J',
			'K',
			'L',
			'M',
			'N',
			'O',
			'P',
			'Q',
			'R',
			'S',
			'T',
			'U',
			'V',
			'W',
			'X',
			'Y',
			'Z'
		);
		$lowercase = array (
			' a',
			' b',
			' c',
			' d',
			' e',
			' f',
			' g',
			' h',
			' i',
			' j',
			' k',
			' l',
			' m',
			' n',
			' o',
			' p',
			' q',
			' r',
			' s',
			' t',
			' u',
			' v',
			' w',
			' x',
			' y',
			' z'
		);
		$name = str_replace($uppercase, $lowercase, $name);
		$result = str_pad($name, 35, ' ', STR_PAD_RIGHT);
		$db = new DatabaseConnection();
		$translatedValue = $db->translationFor($this);
		$result = $result . $translatedValue;
		$result = $result . "\n";
		return $result;
	}
}

Class RemoveBackground extends BooleanParameter {
	function RemoveBackground() {
		$this->BooleanParameter("RemoveBackground");
	}
}

Class FullRestoration extends BooleanParameter {
	function FullRestoration() {
		$this->BooleanParameter("FullRestoration");
	}
}

Class RemoveNoiseEffectiveness extends ChoiceParameter {
	function RemoveNoiseEffectiveness() {
		$this->ChoiceParameter("RemoveNoiseEffectiveness");
	}
}

Class RemoveBackgroundPercent extends VariableChannelsParameter {
	function RemoveBackgroundPercent() {
		$this->VariableChannelsParameter("RemoveBackgroundPercent");
	}
}

Class SignalNoiseRatio extends NumericalParameter {
	function SignalNoiseRatio() {
		$this->NumericalParameter("SignalNoiseRatio");
		// manage one S/N per channel
		$this->value = array (
			0 => NULL,
			1 => NULL,
			2 => NULL,
			3 => NULL,
			4 => NULL
		);
	}
	
	function setValue($value) {
		if (is_array($value)) {
			for ($i = 0; $i < count($value); $i++)
				$this->value[$i] = $value[$i];
		}
		else {
			$this->value[0] = $value;
		}
	}
}

Class SignalNoiseRatioUseRange extends BooleanParameter {
	function SignalNoiseRatioUseRange() {
		$this->BooleanParameter("SignalNoiseRatioUseRange");
	}
}

Class BackgroundOffsetPercent extends VariableChannelsParameter {
	function BackgroundOffsetPercent() {
		$this->VariableChannelsParameter("BackgroundOffsetPercent");
	}

	function check() {
		$value = $this->internalValue();
		$result = True;
		if ($value[0] == "auto" || $value[0] == "object")
			return True;
		for ($i = 0; $i < $this->numberOfChannels; $i++) {
			$result = $result && $this->checkValue($value[$i]);
		}
		return $result;
	}
}

Class BackgroundOffsetUseRange extends BooleanParameter {
	function BackgroundOffsetUseRange() {
		$this->BooleanParameter("BackgroundOffsetUseRange");
	}
}

Class NumberOfIterations extends NumericalParameter {
	function NumberOfIterations() {
		$this->NumericalParameter("NumberOfIterations");
	}
}

Class QualityChangeStoppingCriterion extends NumericalParameter {
	function QualityChangeStoppingCriterion() {
		$this->NumericalParameter("QualityChangeStoppingCriterion");
	}
}

Class NumberOfIterationsUseRange extends BooleanParameter {
	function NumberOfIterationsUseRange() {
		$this->BooleanParameter("NumberOfIterationsUseRange");
	}
}

Class SignalNoiseRatioRange extends NumericalParameter {
	function SignalNoiseRatioRange() {
		$this->NumericalParameter("SignalNoiseRatioRange");
		$this->value = array (
			NULL,
			NULL,
			NULL,
			NULL,
			NULL
		);
	}

	function setupAllowedValues($db) {
		$this->name = 'SignalNoiseRatio';
		NumericalParameter :: setupAllowedValues($db);
		$this->name = 'SignalNoiseRatioRange';
	}

	function isRangeParameter() {
		return True;
	}

	function realParameterName() {
		return 'SignalNoiseRatio';
	}
	function useParameterName() {
		return 'SignalNoiseRatioUseRange';
	}
}

Class BackgroundOffsetRange extends NumericalParameter {
	function BackgroundOffsetRange() {
		$this->NumericalParameter("BackgroundOffsetRange");
		$this->value = array (
			NULL,
			NULL,
			NULL,
			NULL,
			NULL
		);
	}

	function setupAllowedValues($db) {
		$this->name = 'BackgroundOffsetPercent';
		NumericalParameter :: setupAllowedValues($db);
		$this->name = 'BackgroundOffsetRange';
	}

	function isRangeParameter() {
		return True;
	}

	function realParameterName() {
		return 'BackgroundOffsetPercent';
	}
	function useParameterName() {
		return 'BackgroundOffsetUseRange';
	}
}

Class NumberOfIterationsRange extends NumericalParameter {
	function NumberOfIterationsRange() {
		$this->NumericalParameter("NumberOfIterationsRange");
		$this->value = array (
			NULL,
			NULL,
			NULL,
			NULL,
			NULL
		);
	}

	function setupAllowedValues($db) {
		$this->name = 'NumberOfIterations';
		NumericalParameter :: setupAllowedValues($db);
		$this->name = 'NumberOfIterationsRange';
	}

	function isRangeParameter() {
		return True;
	}

	function realParameterName() {
		return 'NumberOfIterations';
	}
	function useParameterName() {
		return 'NumberOfIterationsUseRange';
	}
}

Class OutputFileFormat extends ChoiceParameter {
	function OutputFileFormat() {
		$this->ChoiceParameter('OutputFileFormat');
	}

	//!---------------------------------------------------------
	// @function    OutputFileFormat::translatedValue
	// @desc        Answer the value of the parameter in a 
	//              translated form that is in the form that
	//              is used in the script. The translation of
	//              the output file format is read from the 
	//              database
	// @return      string
	//!---------------------------------------------------------    
	function translatedValue() {
		$db = new DatabaseConnection();
		$result = $db->translationFor($this);
		return $result;
	}
	
	//!---------------------------------------------------------
	// @function    OutputFileFormat::extension
	// @desc        Returns the extension associated with a given
	//              output format
	//
	// @return      string
	//!---------------------------------------------------------    
	function extension( ) {
		$result = $this->translatedValue( );
		switch ( $result ) {
			case "tiff":
			case "tiff16":
				return "tif";
			case "imaris":
				return "ims";
			case "ome":
				return "ome";
			case "ics":
				return "ics";
			default:
				return "";
		}
	}
}
	
Class DeconvolutionAlgorithm extends ChoiceParameter {
	function DeconvolutionAlgorithm() {
		$this->ChoiceParameter('DeconvolutionAlgorithm');
		}
}
	

