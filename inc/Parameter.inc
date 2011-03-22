<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once ("Database.inc");

/*!
 \class	Parameter
 \brief	Base class for all Parameter types in the HRM
 \todo	Implement the displayStr() method in each Parameter subclass
*/
abstract class Parameter {

	/*!
		\var 	PADSIZE
		\brief	The pad size for the Parameter names
	*/
    const PADSIZE      = 38;
	
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
		\var	$message
		\brief	Error message in case the check on the Parameter values fails
	*/
	protected $message;

	/*!
		\brief	$confidenceLevel
		\param	Confidence level for the Parameter
	*/
	protected $confidenceLevel;

	/*!
		\brief	Protected constructor: creates an empty Parameter
		\param	$name	Name of the new Parameter
	*/
	protected function __construct($name) {
		$this->name            = $name;
		$this->value           = null;
		$this->message         = '';
		$this->confidenceLevel = '';
	}

	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	abstract public function check();

	/*!
		\brief	Returns the error message that was set by check()
		\return	error message
	*/
	public function message() {
		return $this->message;
	}

	/*!
		\brief	Returns the confidence level for the Parameter
		\return	Confidence level for the Parameter
	*/
	public function confidenceLevel( ) {
		return $this->confidenceLevel;
	}

	/*!
		\brief	Returns the interpreted HuCore confidence level for the Parameter
                \param  $fileFormat The file extension of the raw image
                \param  $channel    A channel (currently unused)
		
		This is to be used to create the template. The returned confidence
		level is a function of the original confidence level and the actions
		taken by the user. To get the raw confidence level associated to a
		Parameter as returned by hucore (to be used for instance to color
		code the Parameters in the user interface), please use the corresponding
		Database methods.
		
		\return	Interpreted confidence level for the Parameter (in HuCore notation)
	*/
	public function interpretedHuCoreConfidenceLevel( $fileFormat,$channel ) {
        $db = new DatabaseConnection();
        $cl = $db->getParameterConfidenceLevel($fileFormat, $this->name);
        $this->setConfidenceLevel($cl);
		return $cl;	
	}
	
	/*!
		\brief	Sets the confidence level for the Parameter
		\param	Confidence level for the Parameter
	*/
	public function setConfidenceLevel( $confidenceLevel ) {
		$this->confidenceLevel = $confidenceLevel;
	}

	/*!
		\brief	Checks whether the Parameter must have a (valid) value
		\todo 	A complete framework is in place for having Parameters allowed
				to be left unset if their confidence level allows for it.
				In current implementation, mustProvide() always returns true.
				Later, the function might return false for some confidence
				levels such as 'verified' or 'reported'.		
		\return true always
	*/
	public function mustProvide( ) {
		return true;
	}


	/*!
		\brief	Sets the Parameter value(s) to empty
	*/
	public function reset( ) {
		$this->value = null;
	}

	/*!
		\brief	Checks whether the value is not set
		\return	true if the value is *not set* (i.e. null)
	*/
	public function notSet( ) {
		if ( is_array( $this->value ) ) {
			return( $this->value[ 0 ] == null );
		}
		return( $this->value == null );
	}

	/*!
		\brief	Returns the default value for the Parameters that have a default
		        value ot NULL for those that don't
		
		This function should be <b>overloaded</b> by the subclasses
		
		\return	tyhe default value or NULL
	*/
	public function defaultValue() {
		return NULL;
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
		\brief	Checks whether the Parameter is used for calculating the Pixel
				Size from the CCD pixel size and the toal microscope magnification
		
		This function should be <b>overloaded</b> by the subclasses
		
		\return	true if the Parameter is a Calculation Parameter, false otherwise
	*/
	public function isForPixelSizeCalculation() {
		return False;
	}

	/*!
		\brief	Checks whether the Parameter is a Task Parameter
		
		This function should be <b>overloaded</b> by the subclasses
		
		\return	true if the Parameter is a Task Parameter, false otherwise
	*/
	public function isTaskParameter() {
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
		\todo	This function should NOT be in the base class!
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
		\brief	Returns the formatted Parameter name to be used with displayString()
		\return	formatted Parameter name
	*/
	protected function formattedName( ) {
		$name = $this->decomposeCamelCaseString( $this->name );
		$name = $name . ':';
		$result = str_pad($name, self::PADSIZE, ' ', STR_PAD_RIGHT);
		return $result;
	}

	/*!
		\brief	Returns the string representation of the Parameter
		
		Each Parameter that inherits from this function should reimplement it.
		The function is not abstract since some children will need the
		$numberOfChannels input parameter, while others won't.
		
		\param	$numberOfChannels Number of channels (default 0)
		\return	string representation of the Parameter
	*/
	protected function displayString( $numberOfChannels = 0 ) {
		// Reimplement this!
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
	
    /*!
		\brief  Returns the translated value for a given possible value
		\param	$possibleValue	The possible value for which a translation is needed 
		\return	Translated possible value
	*/
	public function translatedValueFor( $possibleValue ) {
		$db = new DatabaseConnection();
		return $db->translationFor( $this->name, $possibleValue );
	}
	
	
	/*!
		\brief  Separates composed in using camel-case notation into individual words
  
		This function takes an input such a 'PointSpreadFunction' and returns ' point spread function'
		(notice the initial blank space!)
  
		\param  $string  String to be converted
		\return $output Converted string
	*/
	protected final function decomposeCamelCaseString( $string ) {
		
		$uppercase = array ( 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
		'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
		'Y', 'Z' );
  
		$lowercase = array ( ' a', ' b', ' c', ' d', ' e', ' f', ' g', ' h',
		' i', ' j', ' k', ' l', ' m', ' n', ' o', ' p', ' q', ' r', ' s',
		' t', ' u', ' v', ' w', ' x', ' y', ' z' );
  
		return ( str_replace( $uppercase, $lowercase, $string ) );
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
abstract class ChoiceParameter extends Parameter {
	
	/*!
		\var	$possibleValues
		\brief	Possible values for the ChoiceParameter
	*/	
	protected $possibleValues;

	/*!
		\brief	Protected constructor: creates an empty Parameter
		\param	$name	Name of the new Parameter
	*/
	protected function __construct($name) {
		parent::__construct($name);
		$possibleValues = array ();
		
		// Get and set the Parameter possible values
		$db = new DatabaseConnection;		
		$values = $db->readPossibleValues($this);
		$this->possibleValues = $values;
		
		// Get and set the Parameter default value
		$defaultValue = $this->defaultValue();
		if ($defaultValue != NULL) {
			$this->value = $defaultValue;
		}		
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
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$this->message = '';
		$result = in_array( $this->value, $this->possibleValues() );
		if ( $result == False ) {
			$this->message = 'Bad value ' . $this->value() . ' for ' . $this->name();
		}
		return $result;
	}
	
	/*!
		\brief	Returns the string representation of the Parameter
		\param	$numberOfChannels Number of channels (default 0)
		\return	string representation of the Parameter
		\todo	It should be responsibility of the various Parameters to
				overload this function!
	*/
	public function displayString( $numberOfChannels = 0 ) {
		$result = $this->formattedName( $this->name );
		if ( $this->notSet() ) {
			$result = $result . "*not set*" . "\n";
		} else {
			$result = $result . $this->value . "\n";
		}
		return $result;
	}
	
	/*!
		\brief	Returns the default value for the Parameters that have a default
		        value ot NULL for those that don't
		
		This function should be <b>overloaded</b> by the subclasses
		
		\return	tyhe default value or NULL
	*/
	public function defaultValue() {
		$db = new DatabaseConnection;	
		$name = $this->name( );
		$default = $db->defaultValue( $name );
		return ( $default );
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
		$this->value = 'False';
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
		\brief	Returns the string representation of the BooleanParameter
		\return	string representation of the Parameter
	*/
	public function displayString() {
		if ($this->value() == True ) {
			$value = 'yes';
		} else {
			$value = 'no';
		}
		$result = $this->formattedName( $this->name );
		$result = $result . $value . "\n";
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
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$this->message = '';
		$result = in_array( $this->value, array( 'True', 'False' ) );
		if ( $result == False ) {
			$this->message = 'Bad value ' . $this->value() . ' for ' . $this->name();
		}
		return $result;
	}

}

/*
	============================================================================
*/

/*!
	\class	NumericalParameter
	\brief	Class for a Parameter that has a scalar number as possible value
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
		
		// Gets the Parameter's possible values, default value and all
		// boundary values from the database and sets them
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
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$this->message = '';
		return ( $this->checkValue( $this->value ) );
	}
	
	/*!
		\brief 	Checks whether the value is valid
		
		The value of a NumericalParameter must be a number and might optionally
		have to be larger than or equal to a given minum value and smaller than
		or equal to a given maximum.
		\param	$value	Value to be checked
		\return true if the value is valid, false otherwise
	*/	
	protected function checkValue($value) {
	    if ( is_array( $value ) )
	    {
	        $this->message = "Scalar expected.\n";
	        return False;
	    }
		if ( is_numeric( $value ) == 0 ) {
			$this->message = "The value must be numeric.\n";
			return False;
		}
		if ($this->isMinIncluded) {
			if ($this->checkMin && !((float) $value >= $this->min)) {
				$this->message = "The value must be >= $this->min.";
				return False;
			}
		}
		if (!$this->isMinIncluded) {
			if ($this->checkMin && !((float) $value > $this->min)) {
				$this->message = "The value must be > $this->min.";
				return False;
			}
		}
		if ($this->isMaxIncluded) {
			if ($this->checkMax && !((float) $value <= $this->max)) {
				$this->message = "The value must be <= $this->max.";
				return False;
			}
		}
		if (!$this->isMaxIncluded) {
			if ($this->checkMax && !((float) $value < $this->max)) {
				$this->message = "The value must be < $this->max.";
				return False;
			}
		}
		return True;
	}
	
	/*!
		\brief	Sets the value of the parameter
		
		The value must be a scalar.
		
		\param	$value	Value for the parameter		
	*/
	public function setValue($value) {
	    if ( is_array( $value ) ) {
	        $value = $value[ 0 ];
	    }
	    $this->value = $value;
	}
	
	/*!
		\brief	Returns the string representation of the Parameter
		\return	string representation of the Parameter
	*/
	public function displayString( $numberOfChannels = 0 ) {
		$result = $this->formattedName( $this->name );
		if ( $this->notSet() ) {
			$result = $result . "*not set*" . "\n";
		} else {
			$result = $result . $this->value . "\n";
		}
		return $result;
	}

}

/*
	============================================================================
*/

/*!
	\class	NumericalArrayParameter
	\brief	Class for a Parameter that has an array of numbers as possible value, 
			where each entry represents a channel.
*/

class NumericalArrayParameter extends NumericalParameter {

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
		$this->reset( );
	}

	/*!
		\brief	Confirms that the Parameter can have a variable number of channels
		This overloads the base function.
		\return true		
	*/
	public function isVariableChannel() {
		return True;
	}

	/*!
		\brief	Sets the Parameter value(s) to empty
	*/
	public function reset( ) {
		$this->value = array ( 
		    0 => NULL,
		    1 => NULL,
		    2 => NULL,
		    3 => NULL,
		    4 => NULL );
		$this->numberOfChannels = 1;
	}
	
	/*!
		\brief	 Sets the number of channels
		\param	$number	Number of channels
	*/
	public function setNumberOfChannels($number) {
	    if ( $number == $this->numberOfChannels ) {
	        return;
	    }
	    if ( $number < 1 ) { 
	        $number = 1;
	    }
	    if ( $number > 5 ) { 
	        $number = 5; 
	    }
	    for ( $i = $number; $i < 5; $i++ ) {
	        $this->value[ $i ] = NULL;
	    }
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
		\brief 	Checks whether all values in the array are valid
		
		Each value in the array must be a number and might optionally
		have to be larger than or equal to a given minum value and smaller than
		or equal to a given maximum.
		\return true if all values are valid, false otherwise
	*/	
	public function check() {
	    $this->message = '';
		$result = True;
		for ( $i = 0; $i < $this->numberOfChannels; $i++ ) {
	        $result = $result && parent::checkValue( $this->value[ $i ] );
	    }
		return $result;
	}

	/*!
		\brief	Sets the value of the parameter
		
		The value must be an array with 5 values (those who refer to
		non-existing channels should be null).
		
		\param	$value	Array of values for the parameter		
	*/
	public function setValue($value) {
		$n = count( $value );
		for ( $i = 0; $i < 5; $i++ ) {
			if ( $i < $n ) {
				$this->value[ $i ] = $value[ $i ];
			} else {
				$this->value[ $i ] = null;
			}
		}
	}

	/*!
		\brief	Returns the string representation of the Parameter
		\return	string representation of the Parameter
	*/
	public function displayString( $numberOfChannels = 0 ) {
		$value = array_slice( $this->value, 0, $numberOfChannels );
		$value = implode( $value, ', ' );
		$result = $this->formattedName( $this->name );
		if ( $this->notSet() ) {
			$result = $result . "*not set*" . "\n";
		} else {
			$result = $result . $value . "\n";
		}
		return $result;
	}

}

/*
	============================================================================
*/

/*!
	\class    AnyTypeArrayParameter
	\brief    Class for a Parameter that has an array of variable of any type
				as possible value. It inherits from NumericalArrayParameter and
				relaxes the condition that the values must be integers.
*/
class AnyTypeArrayParameter extends NumericalArrayParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
		\param	$name	Name of the new Parameter
	*/
	public function __construct($name) {
		parent::__construct($name);
	}

	/*!
		\brief	Returns the internal value of the AnyTypeArrayParameter
		
		This function should be <b>overloaded</b> by the subclasses if the
		internal and external representations differ.
		
		\return	the internal value of the Parameter
	*/
	public function internalValue() {
		return $this->value;
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
 \class	PSF
 \brief	An AnyTypeArrayParameter that handles the file names of the PSF files per channel.
*/
class PSF extends AnyTypeArrayParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct('PSF');
	}

	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
	  for ( $i = 0; $i < $this->numberOfChannels(); $i++) {
		  if ( $this->value[ $i ] == NULL ) {
		    $this->message = "Please select a PSF file for channel $i!";
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
 \class	IsMultiChannel
 \brief	A ChoiceParameter that distinguishes between single- and multi-channel
		images
*/
class IsMultiChannel extends BooleanParameter {
	
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("IsMultiChannel");
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
	
	/*!
		\brief	Returns the string representation of the Parameter
		\param	$numberOfChannels Number of channels (default 0)
		\return	string representation of the Parameter
		\todo	It should be responsibility of the various Parameters to
				overload this function!
	*/
	public function displayString( $numberOfChannels = 0 ) {
		$value = $this->translatedValueFor( $this->value( ) );
		$result = $this->formattedName( $this->name );
		if ( $this->notSet() ) {
			$result = $result . "*not set*" . "\n";
		} else {
			$result = $result . $value . "\n";
		}
		return $result;
	}
	
}

/*
	============================================================================
*/

/*!
 \class	NumberOfChannels
 \brief	A ChoiceParameter to represent the number of channels
 \todo	This might become a NumericalParameter since the possibleValues in the
		database might be removed.
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
	
	/*!
		\brief	Returns the string representation of the Parameter
		\return	string representation of the Parameter
	*/
	public function displayString( $numberOfChannels = 0 ) {
		$result = $this->formattedName( $this->name );
		if ( $this->notSet() ) {
			$result = $result . "*not set*" . "\n";
		} else {
			$result = $result . $this->value . "\n";
		}
		return $result;
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
		$db = new DatabaseConnection();
		$result = $db->translationFor($this->name, $this->value);
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
	
	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$result = parent::check( );
		if ( $result == false ) {
			$this->message = "Numerical Aperture: " . $this->message;
		}
		return $result;
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
		\brief	Confirms that this is a Calculation Parameter.
		\return true
	*/
	public function isForPixelSizeCalculation() {
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
		$db = new DatabaseConnection();
		$result = $db->translationFor($this->name, $this->value);
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
		if ( in_array( $this->value, $this->possibleValues ) ) {
			$db = new DatabaseConnection();
			$result = $db->translationFor($this->name, $this->value);
			return $result;
		} else {
			return $this->value;
		}
	}
	
	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$this->message = '';
		$result = in_array( $this->value, $this->possibleValues() );
		if ( $result == False ) {
			// No preset selected: the value must then be numeric
			if ( !is_numeric( $this->value ) ) {
				$this->message = "The refractive index of the sample medium ".
					"must be a number!";
			} else {
				$result = True;
			}
		}
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
		\brief	Confirms that this is a Calculation Parameter.
		\return true
	*/
	public function isForPixelSizeCalculation() {
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
class ExcitationWavelength extends NumericalArrayParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("ExcitationWavelength");
	}
	
	/*!
		\brief	Confirms that this is a Microscope Parameter.
		\return true
	*/
	public function isForMicroscope() {
		return True;
	}
	
	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$result = parent::check( );
		if ( $result == false ) {
			$this->message = "Excitation Wavelength: " . $this->message;
		}
		return $result;
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
class EmissionWavelength extends NumericalArrayParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("EmissionWavelength");
	}

	/*!
		\brief	Confirms that this is a Microscope Parameter.
		\return true
	*/
	public function isForMicroscope() {
		return True;
	}
	
	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$result = parent::check( );
		if ( $result == false ) {
			$this->message = "Emission Wavelength: " . $this->message;
		}
		return $result;
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
		\brief	Confirms that this is a Calculation Parameter.
		\return true
	*/
	public function isForPixelSizeCalculation() {
		return True;
	}
	
	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$result = parent::check( );
		if ( $result == false ) {
			$this->message = "C-mount: " . $this->message;
		}
		return $result;
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
		\brief	Confirms that this is a Calculation Parameter.
		\return true
	*/
	public function isForPixelSizeCalculation() {
		return True;
	}
	
	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$result = parent::check( );
		if ( $result == false ) {
			$this->message = "Tube Factor: " . $this->message;
		}
		return $result;
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
		$result = $this->formattedName( ' pixel size:' );
		if ( $this->notSet() ) {
			$result = $result . "*not set*" . "\n";
		} else {
			$result = $result . $this->value . "\n";
		}
		return $result;
	}
}

/*
	============================================================================
*/

/*!
 \class	CCDCaptorSize
 \brief	A NumericalParameter to represent the x-size of the CCD pixel
*/
class CCDCaptorSize extends NumericalParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
		
		This is use to calculate the pixel size (i.e. CCDCaptorSizeX) from the
		camera and magnification of the microscope)
	*/
	public function __construct() {
		parent::__construct("CCDCaptorSize");
	}

	/*!
		\brief	Confirms that this is a Calculation Parameter.
		\return true
	*/
	public function isForPixelSizeCalculation() {
		return True;
	}
	
	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$result = parent::check( );
		if ( $result == false ) {
			$this->message = "CCD element size: " . $this->message;
		}
		return $result;
	}

	
	/*!
		\brief	This Parameter should not display anything
		\return	empty string
	*/	
	public function displayString( ) {
		$result = '';
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
class PinholeSize extends NumericalArrayParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("PinholeSize");
	}

	/*!
		\brief	Confirms that this is a Capture Parameter.
		\return true
	*/
	public function isForCapture() {
		return True;
	}
	
	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$result = parent::check( );
		if ( $result == false ) {
			$this->message = "Pinhole size: " . $this->message;
		}
		return $result;
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
	
		
	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$result = parent::check( );
		if ( $result == false ) {
			$this->message = "Pinhole Spacing: " . $this->message;
		}
		return $result;
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

	/*!
		\brief	Checks whether the Parameter is a Task Parameter
		\return	true if the Parameter is a Task Parameter, false otherwise
	*/
	public function isTaskParameter() {
		return True;
	}
	
	/*!
		\brief	This Parameter should not display anything
		\return	empty string
	*/
	public function displayString() {
		$result = '';
		return $result;
	}

}

/*
	============================================================================
*/

/*!
 \class	SignalNoiseRatio
 \brief	A NumericalParameter to represent the SNR per channel
*/
class SignalNoiseRatio extends NumericalArrayParameter {

	/*!
		\var	$algorithm
		\brief	The deconvolution algorithm chosen
	*/
	private $algorithm;
	
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("SignalNoiseRatio");
		$this->algorithm = 'cmle';
	}

	/*!
		\brief	Checks whether the Parameter is a Task Parameter
		\return	true if the Parameter is a Task Parameter, false otherwise
	*/
	public function isTaskParameter() {
		return True;
	}

	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$result = parent::check( );
		if ( $result == false ) {
			$this->message = "SNR: " . $this->message;
		}
		return $result;
	}

	/*!
		\brief	Sets the deconvolution algorithm
		\param	$algorithm	Sets the algorithm, either 'cmle' or 'qmle'
	*/
	public function setAlgorithm( $algorithm ) {
		$this->algorithm = $algorithm;
	}
	
	/*!
		\brief	Returns the string representation of the Parameter for the cmle or qmle algorithm
		
		The algorithm is stored internally in the Parameter and is either 'cmle'
		(default, set when the Parameter is instantiated),or 'qmle'.
		\param	$numberOfChannels Number of channels (default 0)
		\return	string representation of the Parameter for the cmle or qmle algorithm
	*/
	public function displayString($numberOfChannels = 0) {
		switch ( $this->algorithm ) {
			case "qmle":
				$snr = array("1" => "low", "2" => "fair", "3" => "good", "4" => "inf");
				$value = array_slice( $this->value, 0, $numberOfChannels );
				$val = array();
				for ($i = 0; $i < $numberOfChannels; $i++) {
					$val[$i] = $snr[$value[$i]];
				}
				$value = implode(", ", $val);
				$result = $this->formattedName( $this->name );
				return $result . $value . "\n";
				break;
			case "cmle" :
			default:
				return ( parent::displayString( $numberOfChannels ) );
				break;
		}
	}
		
}

/*
	============================================================================
*/

/*!
 \class	BackgroundOffsetPercent
 \brief	An AnyTypeArrayParameter to represent the background offset in percent
*/
class BackgroundOffsetPercent extends AnyTypeArrayParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("BackgroundOffsetPercent");
	}

	/*!
		\brief	Checks whether the Parameter is a Task Parameter
		\return	true if the Parameter is a Task Parameter, false otherwise
	*/
	public function isTaskParameter() {
		return True;
	}

	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check() {
		$this->message = '';
		$value = $this->internalValue();
		$result = True;
		if ($value[0] == "auto" || $value[0] == "object")
			return True;
		for ($i = 0; $i < $this->numberOfChannels; $i++) {
			$result = $result && $this->checkValue($value[$i]);
		}
		if ( $result == False ) {
			$this->message = 'Background offset: ' . $this->message;
		}
		return $result;
	}
	
	public function displayString( ) {
		if ( $this->value[ 0 ] == 'auto' ) {
			$name = ' background estimation';
			$value = 'auto';
		} elseif ( $this->value[ 0 ] == 'object' ) {
			$name = ' background estimation';
			$value = 'in/near object';
		} else {
			if ( $this->numberOfChannels == 1 ) {
				$name = 'background absolute value: ';
				$value = $this->value[ 0 ];
			} else {
				$name = ' background absolute values: ';
				$value = array_slice( $this->value, 0, $this->numberOfChannels);
				$value = implode( $value, ", " );
			}
		}
		$result  = $this->formattedName( $name ) . $value . "\n";
		return $result;
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

	/*!
		\brief	Checks whether the Parameter is a Task Parameter
		\return	true if the Parameter is a Task Parameter, false otherwise
	*/
	public function isTaskParameter() {
		return True;
	}

	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$result = parent::check( );
		if ( $result == false ) {
			$this->message = "Number of iterations: " . $this->message;
		}
		return $result;
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
	
	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$result = parent::check( );
		if ( $result == false ) {
			$this->message = "PSF generation depth: " . $this->message;
		}
		return $result;
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

	/*!
		\brief	Checks whether the Parameter is a Task Parameter
		\return	true if the Parameter is a Task Parameter, false otherwise
	*/
	public function isTaskParameter() {
		return True;
	}

	/*!
		\brief	Checks whether the Parameter is valid
		\return	true if the Parameter is valid, false otherwise
	*/
	public function check( ) {
		$result = parent::check( );
		if ( $result == false ) {
			$this->message = "Quality change: " . $this->message;
		}
		return $result;
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
		\brief	Checks whether the Parameter is a Task Parameter
		\return	true if the Parameter is a Task Parameter, false otherwise
	*/
	public function isTaskParameter() {
		return True;
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
		$result = $db->translationFor($this->name, $this->value);
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

	/*!
		\brief	Checks whether the Parameter is a Task Parameter
		\return	true if the Parameter is a Task Parameter, false otherwise
	*/
	public function isTaskParameter() {
		return True;
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

	/*!
		\brief	Returns the string representation of the Parameter
		\param	$numberOfChannels	This is ignored
		\return	string representation of the Parameter
	*/
	public function displayString( $numberOfChannels = 0 ) {
		if ($this->value( ) == 0 ) {
			$value = "no";
		} else {
			$value = "yes";
		}
		$result = $this->formattedName( $this->name );
		$result = $result . $value . "\n";
		return $result;
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
	
	/*!
		\brief	Returns the string representation of the Parameter
		\param	$numberOfChannels	This is ignored
		\return	string representation of the Parameter
	*/
	public function displayString( $numberOfChannels = 0 ) {
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
		$name = $this->formattedName( $this->name );
		$result = $name . $value . "\n";
		return $result;
	}

}

/*
	============================================================================
*/

/*!
 \class	AberrationCorrectionNecessary
 \brief	A BooleanParameter to indicate whether aberration correction is necessary
*/
class AberrationCorrectionNecessary extends BooleanParameter {
				
	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("AberrationCorrectionNecessary");
	}

}

/*
	============================================================================
*/

/*!
 \class	OverrideConfidence
 \brief	A ChoiceParameter to represent the minimum confidence level for overriding template parameters
*/
class OverrideConfidence extends ChoiceParameter {

	/*!
		\brief	Constructor: creates an empty Parameter
	*/
	public function __construct() {
		parent::__construct("OverrideConfidence");
		
		// We initialize the value to the default value
		$this->value = $this->defaultValue();
	}

	/*!
		\brief	Returns the Parameter translated value
		
		The translated form of the Parameter value is then one used in
		the Tcl script. The translation of the microscope yype is read from
		the database.
		
		\return translated value
	*/
	public function translatedValue() {
		$db = new DatabaseConnection();
		$result = $db->translationFor($this->name, $this->value);
		return $result;
	}
	
	/*!
		\brief	Returns the string representation of the Parameter
		\param	$numberOfChannels	This is ignored
		\return	string representation of the Parameter
	*/
	public function displayString( $numberOfChannels = 0 ) {
		switch ( $this->value( ) ) {
			case 1:
				$value = "do not override";
				break;
			case 2:
				$value = "default and above";
				break;
			case 3:
				$value = "estimated and above";
				break;
			case 4:
				$value = "reported and above";
				break;
			case 5:
				$value = "verified";
				break;
		}
		$name = $this->formattedName( $this->name );
		$result = $name . $value . "\n";
		return $result;
	}

}

