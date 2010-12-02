<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once ("Database.inc");

/*!
 \class    Parameter
 \brief    Base class for all Parameter types in the HRM
*/
class Parameter {

	/*!
		\var	$name
		\brief	Name of the parameter
	*/
	protected $name;

	/*!
		\var	$value
		\brief	Value of the parameter (object)
	*/
	protected $value;

	/*!
		\brief	Constructor: creates an empty Parameter
		\param	$name	Name of the new Parameter
	*/
	public function __construct($name) {
		$this->name = $name;
		$this->value = '';
	}

	/*!
		\brief	Returns the number of values. Values may be scalars or arrays
		\return	number of values stored in the parameter
	*/
	public function numberOfValues() {
		if (!is_array($this->value()))
			return 1;
		return count(array_filter($this->values()));
	}

	/*!
		\brief	Checks whether the parameter is valid
		\return	true if the parameter is valid, false otherwise
	*/
	public function check() {
		return $this->checkValue($this->value());
	}

	/*!
		\brief	Checks whether the value is valid
		
		This function should be <b>overloaded</b> by the subclasses
		
		\param	$value	Value to be checked
		\return	true if the value is valid, false otherwise
	*/
	public function checkValue( $value ) {
		return $value != '';
	}

	/*!
		\brief	Checks whether the Parameter is an Image Parameter
		
		This function should be <b>overloaded</b> by the subclasses
		
		\return	true if the Parameter is an Image Parameter, false otherwise
	*/
	public function isForImage() {
		return False;
	}

	/*!
		\brief	Checks whether the Parameter is a Microscope Parameter
		
		This function should be <b>overloaded</b> by the subclasses
		
		\return	true if the Parameter is a Microscope Parameter, false otherwise
	*/
	public function isForMicroscope() {
		return False;
	}

	/*!
		\brief	Checks whether the Parameter is a Capture Parameter
		
		This function should be <b>overloaded</b> by the subclasses
		
		\return	true if the Parameter is a Capture Parameter, false otherwise
	*/
	public function isForCapture() {
		return False;
	}

	/*!
		\brief	Checks whether the Parameter is a Range Parameter
		
		This function should be <b>overloaded</b> by the subclasses
		
		\return	true if the Parameter is a Range Parameter, false otherwise
	*/
	public function isRangeParameter() {
		return False;
	}

	/*!
		\brief	Checks whether the Parameter is a Variable Channel Parameter
		
		This function should be <b>overloaded</b> by the subclasses
		
		\return	true if the Parameter is a Variable Channel, false otherwise
	*/
	public function isVariableChannel() {
		return False;
	}

	/*!
		\brief	Checks whether the Parameter is a Correction Parameter
		
		This function should be <b>overloaded</b> by the subclasses
		
		\return	true if the Parameter is a Correction Parameter, false otherwise
	*/
	public function isForCorrection() {
		return False;
	}
	
	/*!
		\brief	Returns the name of the Parameter
		\return	the name of the Parameter
	*/
	public function name() {
		return $this->name;
	}

	/*!
		\brief	Returns the value of the Parameter
		\return	the value of the Parameter
	*/
	public function value() {
		return $this->value;
	}

	/*!
		\brief	Returns the internal value of the Parameter
		
		This function should be <b>overloaded</b> by the subclasses if the
		internal and external representations differ.
		
		\return	the internal value of the Parameter
	*/
	public function internalValue() {
		return $this->value();
	}

	/*!
		\brief	Returns the possible values for the parameter in their
				internal representation. 
		
		This function should be <b>overloaded</b> by the subclasses if the
		internal and external representations differ.
		
		\return	the possibles values of the Parameter in their internal
				representation
	*/
	public function internalPossibleValues() {
		return $this->possibleValues();
	}

	/*!
		\brief	Sets the value of the parameter
		\param	$value	Value for the parameter		
	*/
	public function setValue($value) {
		$this->value = $value;
	}

	/*!
		\brief	Prints a radio button for current Parameter and the given value
				
				If the value matches the internal value, the radio button will
				be checked. Additional options can be added.
		
		\param	$value		Value for the Parameter
		\param	$customHTML	HTML code for additional options for the radio button
	*/
	public function printRadioButtonForValue($value, $customHTML) {
		$result = '<input type="radio" name="' . $this->name() . '" value="' . $value . '" ';
		if ($value == $this->internalValue()) {
			$result = $result . 'checked="checked" ';
		}
		$result = $result . $customHTML . ' />';
		print $result;
	}

	/*!
		\brief	Returns true if boolean
		
		This function should be <b>overloaded</b> by the subclasses
		
		\return	always false for a base Parameter
	*/
	public function isBoolean() {
		return False;
	}

	/*!
		\brief	Returns the string representation of the Parameter
		\param	$numberOfChannels Number of channels (default 0)
		\return	string representation of the Parameter
		\todo	It should be responsibility of the various Parameters to
				overload this function!
	*/
	public function displayString( $numberOfChannels = 0 ) {
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
		
		if ($this->name() == "ExcitationWavelength" ||
			$this->name() == "EmissionWavelength" ||
			$this->name() == "SignalNoiseRatio" ||
			$this->name() == "PinholeSize" ) {
			if ( $numberOfChannels > count( $value ) ) {
				$numberOfChannels = count( $value );
			}
			$value = array_slice( $value, 0, $numberOfChannels );
		}
		
		if ($this->name() == "BackgroundOffsetPercent" && $internalValue[0] == "auto") {
			$value = "auto";
		}
		else if ($this->name() == "BackgroundOffsetPercent" && $internalValue[0] == "object") {
			$value = "in/near object";
		}
		else if ($this->name() == "PerformAberrationCorrection") {
			if ($this->value( ) == 0 ) {
				$value = "no";
			} else {
				$value = "yes";
			}
		}
		else if ($this->name() == "AberrationCorrectionNecessary") {
			if ($this->value( ) == 0 ) {
				$value = "no";
			} else {
				$value = "yes";
			}
		}
		else if ($this->name() == "AdvancedCorrectionOptions") {
			switch ( $this->value( ) ) {
			case 'user':
				$value = "user-defined depth";
				break;
			case 'slice':
				$value = "slice by slice";
				break;
			case 'few':
				$value = "few bricks";
				break;
			}
		} elseif ( $this->name() == "SignalNoiseRatioRange" ) {
			$val = "";
			for ( $i = 0; $i < $numberOfChannels; $i++ ) {
				for ( $j = 0; $j < count( $value[ $i ] ); $j++ ) {
					$val .= $value[ $i ][ $j ];
					if ( $j < ( count( $value[ $i ] ) - 1 ) ) {
						$val .= "/";
					}
				}
				if ( $i < ( $numberOfChannels - 1 ) ) {
					$val .= ", ";
				}
			}
			$value = $val;
		} else if ( $this->name() == "NumberOfIterationsRange" ) {
			$val = "";
			$value = array_diff( $value, array( null ) );
			$strVal = implode( "/", $value );
			$value = $strVal;
		} else {
			if (is_array($value)) {
				# array_shift($value);
				if (count($value) > 0 && is_array($value[0])) {
					$val = "";
					for ($i = 0; $i < count($value); $i++) {
						$value[$i] = array_filter($value[$i]);
						if ($i > 0 && $value[$i] != null)
							$val .= ", ";
						$val .= implode("/", $value[$i]);
					}
					$value = $val;
				}
				else {
					$value = array_filter($value);
					$value = implode(", ", $value);
				}
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
		
		if (strcmp($this->name(),"BackgroundOffsetPercent") == 0) {
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

	/*!
		\brief	Returns the value of the parameter in a translated form that
				is in the form that is used in the Tcl script.
				
		By default the translated value is just the value, but this can be
		changed in subclasses when neccessary.
		
		\return the translated value
	*/
	public function translatedValue() {
		return $this->value();
	}
} // End of Parameter class

/*
	============================================================================
*/

/*!
 \class    ChoiceParameter
 \brief    Base class for all ChoiceParameter types
 
 The ChoiceParameter can assume a limited number of possible values.
*/
class ChoiceParameter extends Parameter {
	
	/*!
		\var	$possibleValues
		\brief	Possible values for the ChoiceParameter
	*/	
	protected $possibleValues;

	/*!
		\brief	Constructor: creates an empty Parameter
		\param	$name	Name of the new Parameter
	*/
	public function __construct($name) {
		parent::__construct($name);
		$possibleValues = array ();
	}

	/*!
		\brief	Gets the Parameter's possible values and default value
				from the database and sets them
	*/
	public function setupAllowedValues() {
		$db = new DatabaseConnection;		
		$values = $db->readPossibleValues($this);
		$this->setPossibleValues($values);
		$defaultValue = $db->defaultValue($this->name());
		if ($defaultValue != NULL) {
			$this->setValue($defaultValue);
		}
	}

	/*!
		\brief	Overrides the Parameter possible values
		\param	$values	New possible values
	*/
	public function setPossibleValues($values) {
		$this->possibleValues = $values;
	}

	/*!
		\brief	Returns the possible values for the Parameter
		\return	the possible values
	*/
	public function possibleValues() {
		return $this->possibleValues;
	}

	/*!
		\brief	Returns the possible values for the Parameter as a
				comma-separated string
		\return	the possible values as a comma-separated string
	*/
	public function possibleValuesString() {
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

	/*!
		\brief	Prints radio buttons for each value enbedded in HTML code
		\param	$htmlPrefix		HTML code to prepend to the radio buttons
		\param	$htmlPostfix	HTML code to append to the radio buttons
	*/
	public function printRadioButtonsEmbeddedIn($htmlPrefix, $htmlPostfix) {
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

	/*!
		\brief	Prints radio buttons for the possible values, one per line

		The button for the current value will be checked.
	*/
	public function printRadioButtons() {
		$possibleValues = $this->possibleValues();
		foreach ($this->internalPossibleValues() as $internalPossibleValue) {
			$possibleValue = current($possibleValues);
			$this->printRadioButtonForValue($internalPossibleValue, '');
			print $possibleValue;
			print "<br>\n";
			next($possibleValues);
		}
	}

	/*!
		\brief	Prints radio buttons for the possible values, all on one line

		The button for the current value will be checked.
	*/
	public function printSingleLineRadioButtons() {
		foreach ($this->internalPossibleValues() as $possibleValue) {
			$this->printRadioButtonForValue($possibleValue, '');
			print $possibleValue;
		}
	}

	/*!
		\brief	Prints a checkBox for current Parameter and the given value
				
				If the value matches the internal value, the checkBox will
				be checked. Additional options can be added.
		
		\param	$valueChecked	Value for the Parameter
		\param	$customHTML		HTML code for additional options for the checkBox
	*/
	public function printCheckBox($valueChecked, $customHTML) {
		$result = '<input type="checkbox" name="' . $this->name() . '" value="' . $valueChecked . '" ';
		if ($valueChecked == $this->value()) {
			$result = $result . 'checked="checked" ';
		}
		$result = $result . $customHTML . ' />';
		print $result;
	}

	/*!
		\brief	Checks whether the value is valid
		
		The value of a ChoiceParameter must be one of its possible values
		
		\param	$value	Value to be checked
		\return true of the value is valid, false otherwise
	*/	
	public function checkValue($value) {
		$result = parent :: checkValue($value);
		$result = $result && (in_array($value, $this->possibleValues()));
		return $result;
	}
}

/*
	============================================================================
*/

/*!
 \class    BooleanParameter
 \brief    Class for a Parameter that has only true and false as possible value
*/
class BooleanParameter extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
		\param	$name	Name of the new Parameter
	*/
	public function __construct($name) {
		parent::__construct($name);
		$this->possibleValues = array( 'True', 'False' );
	}

	/*!
		\brief	Checks whether the value is true
		\return	true if the value of the BooleanParameter is "True", false otherwise		
	*/
	public function isTrue() {
		return ($this->value == "True");
	}

	/*!
		\brief	Checks whether the Parameter is a BooleanParameter
		\return	true if the Parameter is a BooleanParameter, false otherwise		
	*/
	public function isBoolean() {
		return True;
	}

	/*!
		\brief	Sets the BooleanParameter's possible values and default value
		
		In reality, the possible values are set by the constructor. This function
		only sets the value to 'False'.

	*/
	public function setupAllowedValues() {
		$this->setValue('False');
	}

	/*!
		\brief	Returns the string representation of the BooleanParameter
		\return	string representation of the Parameter
	*/
	public function displayString() {
		$result = '';
		if ($this->value() == 'True') {
			$result = $result . parent :: displayString();
		}
		return $result;
	}

	/*!
		\brief	Prints a checkBox for current BooleanParameter and the given value
				
				If the value matches the internal value, the checkBox will
				be checked. Additional options can be added.
		
		\param	$customHTML		HTML code for additional options for the checkBox
	*/	
	public function printCheckBox($customHTML) {
		$result = '<input type="checkbox" name="' . $this->name() . '" value="True" ';
		if ($this->value == "True") {
			$result = $result . 'checked="checked" ';
		}
		$result = $result . $customHTML . ' />';
		return $result;
	}

	/*!
		\brief	Sets the value of current BooleanParameter to 'True'
	*/	
	public function set() {
		$this->value = 'True';
	}

	/*!
		\brief	Sets the value of current BooleanParameter to 'False'
	*/	
	public function reset() {
		$this->value = 'False';
	}
}

/*
	============================================================================
*/

/*!
	\class	NumericalParameter
	\brief	Class for a Parameter that has a scalar number as possible value
	\todo	Add a new class NumericalArrayParameter that extends
			NumericalParameter and stores array of values (one per channel)
			instead of individual scalars and make parameters like
			EmissionWavelength inherit from it. This new class should
			reimplement setValue(). The same could be done for the range
			parameters and the VariableChannelsParameter class.
*/
class NumericalParameter extends Parameter {

	/*!
		\var	$min
		\brief	Minimum possible value for the NumericalParameter
	*/
	protected $min;

	/*!
		\var	$max
		\brief	maximum possible value for the NumericalParameter
	*/
	protected $max;
	
	/*!
		\var	$checkMin
		\brief	If true, the value must be checked against the minimum
	*/	
	protected $checkMin;

	/*!
		\var	$checkMax
		\brief	If true, the value must be checked against the maximum
	*/	
	protected $checkMax;
	
	/*!
		\var	$isMinIncluded
		\brief	If true, the value must be >= than the minimum value, otherwise
				it must be > the minimum value
	*/	
	protected $isMinIncluded;

	/*!
		\var	$isMaxIncluded
		\brief	If true, the value must be <= than the maximum value, otherwise
				it must be < the maximum value
	*/	
	protected $isMaxIncluded;

	/*!
		\brief	Constructor: creates an empty Parameter
		\param	$name	Name of the new Parameter
	*/
	public function __construct($name) {
		parent::__construct($name);
		$this->min = NULL;
		$this->max = NULL;
		$this->checkMin = False;
		$this->checkMax = False;
		$this->isMinIncluded = True;
		$this->isMaxIncluded = True;
	}

	/*!
		\brief	Gets the Parameter's possible values, default value and all
				boundary values from the database and sets them
	*/
	public function setupAllowedValues() {
		$db = new DatabaseConnection;		
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

	/*!
		\brief	Set the minimum allowed value for the NumericalParameter
		
		The value itself may be allowed or not.
	*/
	public function setMin($value) {
		$this->min = $value;
		$this->checkMin = True;
	}

	/*!
		\brief	Set the maximum allowed value for the NumericalParameter
		
		The value itself may be allowed or not.
	*/
	public function setMax($value) {
		$this->max = $value;
		$this->checkMax = True;
	}

	/*!
		\brief	Checks whether the NumericalParameter value should be checked
				against its minimum value
		\return	true if the value has to be checked against its minimum value,
				false otherwise
	*/
	public function checkMin() {
		return $this->checkMin;
	}

	/*!
		\brief	Checks whether the NumericalParameter value should be checked
				against its maximum value
		\return	true if the value has to be checked against its maximum value,
				false otherwise
	*/
	public function checkMax() {
		return $this->checkMax;
	}

	/*!
		\brief	Returns the minimum allowed value for the NumericalParameter
		\return	the minimum allowed value
	*/
	public function min() {
		return $this->min;
	}

	/*!
		\brief	Returns the maximum allowed value for the NumericalParameter
		\return	the maximum allowed value
	*/
	public function max() {
		return $this->max;
	}

	/*!
		\brief 	Checks whether the value is valid
		
		The value of a NumericalParameter must be a number and might optionally
		have to be larger than or equal to a given minum value and smaller than
		or equal to a given maximum.
		\param	$value	Value to be checked
		\return true of the value is valid, false otherwise
	*/	
	public function checkValue($value) {
		if ( is_numeric( $value ) == 0 ) {
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

/*
	============================================================================
*/

/*!
	\class    VariableChannelsParameter
	\brief    Class for a Parameter that has an array of numbers as possible value
 
Each value in the array corresponds to one of the channels on the dataset.

	\todo	How exactly is this Parameter used?
*/
class VariableChannelsParameter extends NumericalParameter {

	/*!
		\var	$numberOfChannels
		\brief	Number of channels for which to provide Parameter values
	*/	
	protected $numberOfChannels;

	/*!
		\brief	Constructor: creates an empty Parameter
		\param	$name	Name of the new Parameter
	*/
	public function __construct($name) {
		parent::__construct($name);
		$this->value = array (
			0 => NULL,
			1 => NULL,
			2 => NULL,
			3 => NULL,
			4 => NULL
		);
		$numberOfChannels = 1;
	}

	/*!
		\brief	 Sets the number of channels
		\param	$number	Number of channels
	*/
	public function setNumberOfChannels($number) {
		$this->numberOfChannels = $number;
	}

	/*!
		\brief	 Returns the number of channels
		\return the umber of channels
	*/
	public function numberOfChannels() {
		return $this->numberOfChannels;
	}

	/*!
		\brief	Sets the value of the VariableChannelsParameter.
		
		The value can be either a scalar, and then it will be assigned to
		channel 0, or an array, and then it will replace the existing array
		of values in the VariableChannelsParameter. The number of passed values
		does not need to match the current number of channels, but then it is
		responibility of the user to correct the mismatch!
		
		\param	$value A scalar or an array of numbers to be set as value
		\todo	This function should be more robust and more versatile!
	*/
	public function setValue($value) {
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

	/*!
		\brief	Confirms that this is a VariableChannelsParameter.
		
		This overloads the base function.
		
		\return true		
	*/
	public function isVariableChannel() {
		return True;
	}

	/*!
		\brief	Returns the checked array of values for all channels
		
		The values are checked against their boundaries before they are returned.
		Please be careful: if a value is not valid it will be skipped, and the
		array will miss that entry!
		(Originally, a non-valid value was replaced by the value of the previous
		channel, but the code would fail if channel 0 were also invalid.)
		
		\return	checked array of values
		\todo	The behavior of this function is rather questionable!
	*/
	public function value() {
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

	/*!
		\brief	Returns the internal value of the VariableChannelsParameter
		
		This function should be <b>overloaded</b> by the subclasses if the
		internal and external representations differ.
		
		\return	the internal value of the Parameter
	*/
	public function internalValue() {
		return $this->value;
	}

	/*!
		\brief	Checks whether all values for all channels are valid
		\return	true if all values are valid, false otherwise
	*/
	public function check() {
		$result = True;
		for ($i = 0; $i < $this->numberOfChannels; $i++) {
			$value = $this->internalValue();
			$result = $result && $this->checkValue($value[$i]);
		}
		return $result;
	}
}

/*
	============================================================================
*/

/*!
 \class	HasAdaptedValues
 \brief	Class that manages parameter adaption - all use of this class has
		been disabled in the code.
 \todo	Remove this from the code
 \deprecated
*/
class HasAdaptedValues extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("HasAdaptedValues");
	}
}

/*
	============================================================================
*/

/*!
 \class	PointSpreadFunction
 \brief	Class that handles the type of PointSpreadFunction to be used,
		theoretical or measured.
*/
class PointSpreadFunction extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("PointSpreadFunction");
	}
}

/*
	============================================================================
*/

/*!
 \class	PSF
 \brief	Class that handles the file names of the PSF files per channel.
 \todo	This should be called PSFFiles or something similar
*/
class PSF extends Parameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct('PSF');
		$this->value = array (
			NULL,
			NULL,
			NULL,
			NULL,
			NULL
		);
	}

	/*!
		\brief	Gets the Parameter's possible values and default value
				from the database and sets them
	*/
	public function setupAllowedValues()  {
		/*!
			\todo	Implement this!
		*/
	}
}

/*
	============================================================================
*/

/*!
 \class	IsMultiChannel
 \brief	A ChoiceParameter that distinguishes between single- and multi-channel
		images
*/
class IsMultiChannel extends ChoiceParameter {
	
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("IsMultiChannel");
	}

	/*!
		\brief	Confirms that this is an Image Parameter.
		\return true
	*/
	public function isForImage() {
		return True;
	}
	
	/*!
		\brief	Returns the string representation of the isMultiChannel Parameter
		\return	string representation of the Parameter
	*/
	public function displayString() {
		if ($this->value() == 'True') {
			$result = " multichannel image\n";
		} else {
			$result = " single channel image\n";
		}
		return $result;
	}
}

/*
	============================================================================
*/

/*!
 \class	SingleOrMultiChannelParameter
 \brief	A ChoiceParameter that handles single- and multi-channel Parameters
		with prefixing.
\todo	Check why this class does not have a constructor
*/
class SingleOrMultiChannelParameter extends ChoiceParameter {
	
	/*!
		\var	$isMultiChannel
		\brief	Defines whether this is a single or multi channel parameter
	*/
	protected $isMultiChannel;

	/*!
		\brief	Checks whether the Parameter is multi-channel
		\return true if the Parameter is multi-channel
	*/
	public function isMultiChannel() {
		return $this->isMultiChannel;
	}

	/*!
		\brief	Checks whether the Parameter is single-channel
		\return true if the Parameter is single-channel
	*/
	public function isSingleChannel() {
		return !$this->isMultiChannel();
	}

	/*!
		\brief	Makes the Parameter multi-channel
	*/
	public function beMultiChannel() {
		$this->isMultiChannel = True;
	}

	/*!
		\brief	Makes the Parameter single-channel
	*/
	public function beSingleChannel() {
		$this->isMultiChannel = False;
	}

	/*!
		\brief	Sets the value of the Parameter
		\param	$value	New value for the Parameter
	
	If $value contains the prefix single_ or multi_,  the parameter is set to
	be single-channel or multi-channel, respectively, and the postfix of the
	value is set as final value of the Parameter.
	
		\see postfix
	*/
	public function setValue($value) {
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

	/*!
		\brief	Returns the prefix for the Parameter, either 'single' or 'multi'
		\return either 'single' or 'multi'
	*/
	public function prefix() {
		if ($this->isSingleChannel()) {
			$prefix = "single";
		} else {
			$prefix = "multi";
		}
		return $prefix;
	}

	/*!
		\brief	Returns the internal value of the SingleOrMultiChannelParameter
		
		This function should be <b>overloaded</b> by the subclasses if the
		internal and external representations differ.
		
		\return	the internal value of the Parameter, which is the value with the
				prefix prepended.
	*/
	public function internalValue() {
		$result = $this->prefix() . "_" . $this->value();
		return $result;
	}

	/*!
		\brief	Returns the internal possible values of the SingleOrMultiChannelParameter
		\return	the internal possible values of the Parameter, which are the
				<b>checked</b> values with the prefix prepended.
	*/
	public function internalPossibleValues() {
		$result = array ();
		foreach ($this->possibleValues() as $possibleValue) {
			$result[] = $this->prefix() . "_" . $possibleValue;
		}
		return $result;
	}
}

/*
	============================================================================
*/

/*!
 \class	ImageFileFormat
 \brief	A SingleOrMultiChannelParameter to represent the image file format
*/
class ImageFileFormat extends SingleOrMultiChannelParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("ImageFileFormat");
	}

	/*!
		\brief	Confirms that this is an Image Parameter.
		\return true
	*/
	public function isForImage() {
		return True;
	}

	/*!
		\brief	Returns all image file extensions
		\return array of file extensions
	*/
	public function fileExtensions() {
		$db = new DatabaseConnection();
		$result = $db->fileExtensions($this->value());
		return $result;
	}
}

/*
	============================================================================
*/

/*!
 \class	NumberOfChannels
 \brief	A ChoiceParameter to represent the number of channels
*/
class NumberOfChannels extends ChoiceParameter {
	
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
			parent::__construct("NumberOfChannels");
	}

	/*!
		\brief	Confirms that this is an Image Parameter.
		\return true
	*/
	public function isForImage() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	ImageGeometry
 \brief	A SingleOrMultiChannelParameter to represent the image geometry
*/
class ImageGeometry extends SingleOrMultiChannelParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("ImageGeometry");
	}

	/*!
		\brief	Confirms that this is an Image Parameter.
		\return true
	*/
	public function isForImage() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	MicroscopeType
 \brief	A ChoiceParameter to represent the microscope type
*/
class MicroscopeType extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("MicroscopeType");
	}

	/*!
		\brief	Confirms that this is a Microscope Parameter.
		\return true
	*/
	public function isForMicroscope() {
		return True;
	}

	/*!
		\brief	Returns the Parameter translated value
		
		The translated form of the Parameter value is then one used in
		the Tcl script. The translation of the microscope yype is read from
		the database.
		
		\return translated value
	*/
	public function translatedValue() {
		$value = $this->value;
		$db = new DatabaseConnection();
		$result = $db->translationFor($this);
		return $result;
	}
}

/*
	============================================================================
*/

/*!
 \class	NumericalAperture
 \brief	A NumericalParameter to represent the numerical aperture of the objective
*/
class NumericalAperture extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("NumericalAperture");
	}

	/*!
		\brief	Confirms that this is a Microscope Parameter.
		\return true
	*/
	public function isForMicroscope() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	ObjectiveMagnification
 \brief	A ChoiceParameter to represent the objective magnification
*/
class ObjectiveMagnification extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("ObjectiveMagnification");
	}

	/*!
		\brief	Confirms that this is a Microscope Parameter.
		\return true
	*/
	public function isForMicroscope() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	ObjectiveType
 \brief	A ChoiceParameter to represent the objective type
*/
class ObjectiveType extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("ObjectiveType");
	}

	/*!
		\brief	Confirms that this is a Microscope Parameter.
		\return true
	*/
	public function isForMicroscope() {
		return True;
	}

	/*!
		\brief	Returns the Parameter translated value
		
		The translated form of the Parameter value is then one used in
		the Tcl script. The translation of the objective type is read from
		the database.
		
		\return translated value
	*/
	public function translatedValue() {
		$value = $this->value;
		$db = new DatabaseConnection();
		$result = $db->translationFor($this);
		return $result;
	}
}

/*
	============================================================================
*/

/*!
 \class	SampleMedium
 \brief	A ChoiceParameter to represent the sample medium
*/
class SampleMedium extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("SampleMedium");
	}

	/*!
		\brief	Confirms that this is a Microscope Parameter.
		\return true
	*/
	public function isForMicroscope() {
		return True;
	}

	/*!
		\brief	Returns the Parameter translated value
		
		The translated form of the Parameter value is then one used in
		the Tcl script. The translation of the sample medium is read from
		the database.
		
		\return translated value
	*/
	public function translatedValue() {
		$value = $this->value;
		$db = new DatabaseConnection();
		$result = $db->translationFor($this);
		if ($result == 0)
			return $value;
		return $result;
	}
}

/*
	============================================================================
*/

/*!
 \class	Binning
 \brief	A ChoiceParameter to represent the binning
*/
class Binning extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("Binning");
	}

	/*!
		\brief	Confirms that this is a Capture Parameter.
		\return true
	*/
	public function isForCapture() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	ExcitationWavelength
 \brief	A NumericalParameter to represent the excitation wavelength
 
 The ExcitationWavelength class can store an array of numbers as value.
*/
class ExcitationWavelength extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("ExcitationWavelength");
		$this->value = array (
			0 => NULL,
			1 => NULL,
			2 => NULL,
			3 => NULL,
			4 => NULL 
		);
	}
	
	/*!
		\brief	 Sets the value for the Parameter
		
		The value can either be a scalar, and then it will be stored for channel
		0, or an array, and then will replace the previous array. It is
		responsibility of the user to make sure that the number of values
		matches the number of channels in the image.
		
		\param	$value	Single wavelength or array of wavelengths
	*/
	public function setValue($value) {
		if (is_array($value)) {
			for ($i = 0; $i < count($value) && $i <= count( $this->value ); $i++)
				$this->value[$i] = $value[$i];
		}
		else {
			$this->value[0] = $value;
		}
	}

	/*!
		\brief	Confirms that this is a Microscope Parameter.
		\return true
	*/
	public function isForMicroscope() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	EmissionWavelength
 \brief	A NumericalParameter to represent the emission wavelength
 
 The EmissionWavelength class can store an array of numbers as value.
*/
class EmissionWavelength extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("EmissionWavelength");
		$this->value = array (
			0 => NULL,
			1 => NULL,
			2 => NULL,
			3 => NULL,
			4 => NULL 
		);
	}
	
	/*!
		\brief	 Sets the value for the Parameter
		
		The value can either be a scalar, and then it will be stored for channel
		0, or an array, and then will replace the previous array. It is
		responsibility of the user to make sure that the number of values
		matches the number of channels in the image.
		
		\param	$value	Single wavelength or array of wavelengths
	*/
	public function setValue($value) {
		if (is_array($value)) {
			for ($i = 0; $i < count($value) && $i <= count( $this->value ); $i++)
				$this->value[$i] = $value[$i];
		}
		else {
			$this->value[0] = $value;
		}
	}

	/*!
		\brief	Confirms that this is a Microscope Parameter.
		\return true
	*/
	public function isForMicroscope() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	CMount
 \brief	A NumericalParameter to represent the c-mount
*/
class CMount extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("CMount");
	}

	/*!
		\brief	Confirms that this is a Microscope Parameter.
		\return true
	*/
	public function isForMicroscope() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	TubeFactor
 \brief	A NumericalParameter to represent the tube factor
*/
class TubeFactor extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("TubeFactor");
	}
	
	/*!
		\brief	Confirms that this is a Microscope Parameter.
		\return true
	*/
	public function isForMicroscope() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	CCDCaptorSizeX
 \brief	A NumericalParameter to represent the x-size of the CCD pixel
*/
class CCDCaptorSizeX extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("CCDCaptorSizeX");
	}

	/*!
		\brief	Confirms that this is a Capture Parameter.
		\return true
	*/
	public function isForCapture() {
		return True;
	}

	/*!
		\brief	Returns the string representation of the CCDCaptorSizeX Parameter
		\return	string representation of the Parameter
	*/
	public function displayString() {
		$value = $this->value();
		$result = '';
		$name = ' pixel size:';
		$result = $result . str_pad($name, 35, ' ', STR_PAD_RIGHT);
		$result = $result . $value;
		$result = $result . "\n";
		return $result;
	}
}

/*
	============================================================================
*/

/*!
 \class	ZStepSize
 \brief	A NumericalParameter to represent the z step (distance between planes)
*/
class ZStepSize extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("ZStepSize");
	}

	/*!
		\brief	Confirms that this is a Capture Parameter.
		\return true
	*/
	public function isForCapture() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	TimeInterval
 \brief	A NumericalParameter to represent the time interval in time series
*/
class TimeInterval extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("TimeInterval");
	}

	/*!
		\brief	Confirms that this is a Capture Parameter.
		\return true
	*/
	public function isForCapture() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	PinholeSize
 \brief	A NumericalParameter to represent the pinhole size (per channel)
*/
class PinholeSize extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("PinholeSize");
		// Manage one pinhole radius per channel
		$this->value = array (
			NULL,
			NULL,
			NULL,
			NULL,
			NULL
		);
	}

	/*!
		\brief	 Sets the value for the Parameter
		
		The value can either be a scalar, and then it will be stored for channel
		0, or an array, and then will replace the previous array. It is
		responsibility of the user to make sure that the number of values
		matches the number of channels in the image.
		
		\param	$value	Single pinhole size or array of pinhole sizes
	*/
	public function setValue($value) {
                if (is_array($value)) {
                        for ($i = 0; $i < count($value); $i++)
				$this->value[$i] = $value[$i];
                }
                else {
			$this->value[0] = $value;
                }
        }
	
	/*!
		\brief	Confirms that this is a Capture Parameter.
		\return true
	*/
	public function isForCapture() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	PinholeSpacing
 \brief	A NumericalParameter to represent the pinhole spacing per Nipkow spinning disks
*/
class PinholeSpacing extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("PinholeSpacing");
	}
	
	/*!
		\brief	Confirms that this is a Capture Parameter.
		\return true
	*/
	public function isForCapture() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	MultiChannelOutput
 \brief	A BooleanParameter to indicate whether the output is multi-channel
*/
class MultiChannelOutput extends BooleanParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("MultiChannelOutput");
	}
}

/*
	============================================================================
*/

/*!
 \class	RemoveNoise
 \brief	A BooleanParameter to indicate whether a noise suppression pre-processing
		step should be performed
 \deprecated
 \todo Remove this parameter and all the code that makes use of it
*/
class RemoveNoise extends BooleanParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("RemoveNoise");
	}
}

/*
	============================================================================
*/

/*!
 \class	StyleOfProcessing
 \brief	A ChoiceParameter to indicate the style of processing
 \deprecated
 \todo Remove this parameter and all the code that makes use of it
*/
class StyleOfProcessing extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("StyleOfProcessing");
	}

	/*!
		\brief	Returns the string representation of the StyleOfProcessing Parameter
		\return	string representation of the Parameter
	*/
	public function displayString() {
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

/*
	============================================================================
*/

/*!
 \class	RemoveBackground
 \brief	A BooleanParameter to indicate whether a background removal should be run
*/
class RemoveBackground extends BooleanParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("RemoveBackground");
	}
}

/*
	============================================================================
*/

/*!
 \class	FullRestoration
 \brief	A BooleanParameter to indicate whether a full restoration should be run
 \deprecated
 \todo Remove this parameter and all the code that makes use of it
*/
class FullRestoration extends BooleanParameter {
		
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("FullRestoration");
	}
}

/*
	============================================================================
*/

/*!
 \class	RemoveNoiseEffectiveness
 \brief	A ChoiceParameter to indicate the effectiveness of noise removal
 \deprecated
 \todo Remove this parameter and all the code that makes use of it
*/
class RemoveNoiseEffectiveness extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("RemoveNoiseEffectiveness");
	}
}

/*
	============================================================================
*/

/*!
 \class	RemoveBackgroundPercent
 \brief	A VariableChannelsParameter to indicate the percent of background removal
*/
class RemoveBackgroundPercent extends VariableChannelsParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("RemoveBackgroundPercent");
	}
}

/*
	============================================================================
*/

/*!
 \class	SignalNoiseRatio
 \brief	A NumericalParameter to represent the SNR per channel
*/
class SignalNoiseRatio extends NumericalParameter {


	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("SignalNoiseRatio");
		// Manage one S/N per channel
		$this->value = array (
			0 => NULL,
			1 => NULL,
			2 => NULL,
			3 => NULL,
			4 => NULL
		);
	}
	
	/*!
		\brief	 Sets the value for the Parameter
		
		The value can either be a scalar, and then it will be stored for channel
		0, or an array, and then will replace the previous array. It is
		responsibility of the user to make sure that the number of values
		matches the number of channels in the image.
		
		\param	$value	Single SNR or array of SNRs
	*/
	public function setValue($value) {
		if (is_array($value)) {
			for ($i = 0; $i < count($value); $i++)
				$this->value[$i] = $value[$i];
		}
		else {
			$this->value[0] = $value;
		}
	}

	/*!
		\brief	Returns the string representation of the Parameter for the cmle algorithm
		\param	$numberOfChannels Number of channels (default 0)
		\return	string representation of the Parameter for the cmle algorithm
		\todo	It should be responsibility of the various Parameters to
				overload this function!
	*/
    public function displayCMLEString($numberOfChannels = 0) {
        $result = '';

		$value = $this->value();
        if ( $numberOfChannels > count( $value ) ) {
            $numberOfChannels = count( $value );
        }
        $value = array_slice( $value, 0, $numberOfChannels );
        if (count($value) > 0 && is_array($value[0])) {
            $val = "";
            for ($i = 0; $i < count($value); $i++) {
                $value[$i] = array_filter($value[$i]);
                if ($i > 0 && $value[$i] != null)
                    $val .= ", ";
                $val .= implode("/", $value[$i]);
            }
            $value = $val;
        }
        else {
            $value = array_filter($value);
            $value = implode(", ", $value);
        }

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
		$result .= str_pad($name, 35, ' ', STR_PAD_RIGHT);

		return $result . $value . "\n";
    }

	/*!
		\brief	Returns the string representation of the Parameter for the qmle algorithm
		\param	$numberOfChannels Number of channels (default 0)
		\return	string representation of the Parameter for the qmle algorithm
		\todo	It should be responsibility of the various Parameters to
				overload this function!
	*/
    public function displayQMLEString($numberOfChannels = 0) {
        $snr = array("1" => "low", "2" => "fair", "3" => "good", "4" => "inf");
        
        $result = '';

		$value = $this->value();
        if ( $numberOfChannels > count( $value ) ) {
            $numberOfChannels = count( $value );
        }
        $value = array_slice( $value, 0, $numberOfChannels );
        $value = array_filter($value);
        for ($i = 0; $i < count($value); $i++) {
            $val[$i] = $snr[$value[$i]];
        }
        $value = implode(", ", $val);

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
		$result .= str_pad($name, 35, ' ', STR_PAD_RIGHT);

		return $result . $value . "\n";
    }
}

/*
	============================================================================
*/

/*!
 \class	SignalNoiseRatioUseRange
 \brief	A BooleanParameter to indicate whether the SNR Parameter uses ranges
*/
class SignalNoiseRatioUseRange extends BooleanParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("SignalNoiseRatioUseRange");
	}
}

/*
	============================================================================
*/

/*!
 \class	BackgroundOffsetPercent
 \brief	A VariableChannelsParameter to represent the background offset in percent
*/
class BackgroundOffsetPercent extends VariableChannelsParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("BackgroundOffsetPercent");
	}

	/*!
		\brief	Checks the value for the Parameter
		\return true if the value is valid, false otherwise
	*/
	public function check() {
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

/*
	============================================================================
*/

/*!
 \class	BackgroundOffsetUseRange
 \brief	A BooleanParameter to indicate whether the background offset Parameter uses ranges
*/
class BackgroundOffsetUseRange extends BooleanParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("BackgroundOffsetUseRange");
	}
}

/*
	============================================================================
*/

/*!
 \class	NumberOfIterations
 \brief	A NumericalParameter to represent the number of iterations
*/
class NumberOfIterations extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("NumberOfIterations");
	}
}

/*
	============================================================================
*/

/*!
 \class	PSFGenerationDepth
 \brief	A NumericalParameter to represent the depth of the PSF generation
*/
class PSFGenerationDepth extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("PSFGenerationDepth");
	}
}

/*
	============================================================================
*/

/*!
 \class	QualityChangeStoppingCriterion
 \brief	A NumericalParameter to represent the quality change stopping criterion
*/
class QualityChangeStoppingCriterion extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("QualityChangeStoppingCriterion");
	}
}

/*
	============================================================================
*/

/*!
 \class	NumberOfIterationsUseRange
 \brief	A BooleanParameter to indicate whether the number of iterations uses ranges
*/
class NumberOfIterationsUseRange extends BooleanParameter {
	
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("NumberOfIterationsUseRange");
	}
}

/*
	============================================================================
*/

/*!
 \class	SignalNoiseRatioRange
 \brief	A NumericalParameter to represent the range of SNRs
*/
class SignalNoiseRatioRange extends NumericalParameter {
	
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("SignalNoiseRatioRange");
		$this->value = array (
			NULL,
			NULL,
			NULL,
			NULL,
			NULL,
		);
	}

	/*!
		\brief	Gets the Parameter's possible values and default value
				from the database and sets them
	*/
	public function setupAllowedValues() {
		$this->name = 'SignalNoiseRatio';
		NumericalParameter :: setupAllowedValues();
		$this->name = 'SignalNoiseRatioRange';
	}

	/*!
		\brief	Confirm that this is a Range Parameter
		\return true
	*/
	public function isRangeParameter() {
		return True;
	}

	/*!
		\brief	Return the name of the Parameter to which the range version points
		\return name of the corresponding non-range Parameter
	*/
	public function realParameterName() {
		return 'SignalNoiseRatio';
	}

	/*!
		\brief	Return the name of the (range) Parameter
		\return name of the Parameter
	*/
	public function useParameterName() {
		return 'SignalNoiseRatioUseRange';
	}

	/*!
		\brief	 Sets the value for the Parameter
		
		The value can either be a scalar or an array.
		
		\param	$value	Single SNR or array of SNRs
	*/	
	public function setValue($value) {
		for ($i = 0; $i < count($value); $i++) {
			$this->value[$i] = $value[$i];
		}
    }

	/*!
		\brief 	Checks whether the value is valid
		
		Check the passed SNR value(s) against their boundaries for validity.
		\param	$value	Value to be checked
		\return true of the value is valid, false otherwise
	*/	
	public function checkValue($value) {
		$valid = True;
		for ($i = 0; $i < 5; $i++) {
			if ($value[$i] != NULL) {
				$range = $value[$i];
				for ($j = 0; $j < 4; $j++) {
					if (!empty($range[$j])) {
						$valid &= NumericalParameter :: checkValue($range[$j]);
					}
				}
			}
		}
		return $valid;
	}
}

/*
	============================================================================
*/

/*!
 \class	BackgroundOffsetRange
 \brief	A NumericalParameter to represent the range of background offsets
*/
class BackgroundOffsetRange extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("BackgroundOffsetRange");
		$this->value = array (
			NULL,
			NULL,
			NULL,
			NULL,
			NULL
		);
	}

	/*!
		\brief	Gets the Parameter's possible values and default value
				from the database and sets them
	*/
	public function setupAllowedValues() {
		$this->name = 'BackgroundOffsetPercent';
		NumericalParameter :: setupAllowedValues();
		$this->name = 'BackgroundOffsetRange';
	}

	/*!
		\brief	Confirm that this is a Range Parameter
		\return true
	*/
	public function isRangeParameter() {
		return True;
	}

	/*!
		\brief	Return the name of the Parameter to which the range version points
		\return name of the corresponding non-range Parameter
	*/
	public function realParameterName() {
		return 'BackgroundOffsetPercent';
	}

	/*!
		\brief	Return the name of the (range) Parameter
		\return name of the Parameter
	*/
	public function useParameterName() {
		return 'BackgroundOffsetUseRange';
	}
}

/*
	============================================================================
*/

/*!
 \class	NumberOfIterationsRange
 \brief	A NumericalParameter to represent the range of iteration numbers
*/
class NumberOfIterationsRange extends NumericalParameter {
	
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("NumberOfIterationsRange");
		$this->value = array (
			NULL,
			NULL,
			NULL,
			NULL,
		);
	}

	/*!
		\brief	Gets the Parameter's possible values and default value
				from the database and sets them
	*/
	public function setupAllowedValues() {
		$this->name = 'NumberOfIterations';
		NumericalParameter :: setupAllowedValues();
		$this->name = 'NumberOfIterationsRange';
	}

	/*!
		\brief	Confirm that this is a Range Parameter
		\return true
	*/
	public function isRangeParameter() {
		return True;
	}

	/*!
		\brief	Return the name of the Parameter to which the range version points
		\return name of the corresponding non-range Parameter
	*/
	public function realParameterName() {
		return 'NumberOfIterations';
	}
	
	/*!
		\brief	Return the name of the (range) Parameter
		\return name of the Parameter
	*/
	public function useParameterName() {
		return 'NumberOfIterationsUseRange';
	}
}

/*
	============================================================================
*/

/*!
 \class	OutputFileFormat
 \brief	A ChoiceParameter to represent the output file format
*/
class OutputFileFormat extends ChoiceParameter {
	
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("OutputFileFormat");
	}	

	/*!
		\brief	Returns the Parameter translated value
		
		The translated form of the Parameter value is then one used in
		the Tcl script. The translation of the output file format is read from
		the database.
		
		\return translated value
	*/
	public function translatedValue() {
		$db = new DatabaseConnection();
		$result = $db->translationFor($this);
		return $result;
	}
	
	/*!
		\brief	Returns the file extension associated with a given output
				format translated value
		\return the file extension
		\todo	This information is in the database!!!
	*/
	public function extension( ) {
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
			case "ics2":
				return "ics";
			case "hdf5":
				return "h5";
			default:
				return "";
		}
	}
}

/*
	============================================================================
*/

/*!
 \class	DeconvolutionAlgorithm
 \brief	A ChoiceParameter to represent the deconvolution algorithm
*/	
class DeconvolutionAlgorithm extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("DeconvolutionAlgorithm");
	}	
}
	
/*
	============================================================================
*/

/*!
 \class	CoverslipRelativePosition
 \brief	A ChoiceParameter to represent the relative position of plane 0 with
		respect to the coverslip
*/	
class CoverslipRelativePosition extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("CoverslipRelativePosition");
	}	
	
	/*!
		\brief	Confirms that this is a Correction Parameter
		\return true
	*/
	public function isForCorrection() {
		return True;
	}

}

/*
	============================================================================
*/

/*!
 \class	PerformAberrationCorrection
 \brief	A ChoiceParameter to indicate whether aberration correction should be
		performed
*/	
class PerformAberrationCorrection extends ChoiceParameter {
	
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("PerformAberrationCorrection");
	}	
	
	/*!
		\brief	Confirms that this is a Correction Parameter
		\return true
	*/
	public function isForCorrection() {
		return True;
	}
}

/*
	============================================================================
*/

/*!
 \class	AberrationCorrectionMode
 \brief	A ChoiceParameter to indicate the mode of aberration correction
*/
class AberrationCorrectionMode extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("AberrationCorrectionMode");
	}	
	
	/*!
		\brief	Confirms that this is a Correction Parameter
		\return true
	*/
	public function isForCorrection() {
		return True;
	}

}

/*
	============================================================================
*/

/*!
 \class	AdvancedCorrectionOptions
 \brief	A ChoiceParameter to indicate the options of aberration correction
*/
class AdvancedCorrectionOptions extends ChoiceParameter {
			
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("AdvancedCorrectionOptions");
	}	
	
	/*!
		\brief	Confirms that this is a Correction Parameter
		\return true
	*/
	public function isForCorrection() {
		return True;
	}

}

/*
	============================================================================
*/

/*!
 \class	AberrationCorrectionNecessary
 \brief	A ChoiceParameter to indicate whether aberration correction is necessary
*/
class AberrationCorrectionNecessary extends ChoiceParameter {
				
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("AberrationCorrectionNecessary");
	}	

	/*!
		\brief	Confirms that this is a Correction Parameter
		\return true
	*/
	public function isForCorrection() {
		return True;
	}

}
