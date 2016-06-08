<?php
/**
 * Parameter
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\param\base;

use hrm\DatabaseConnection;

require_once dirname(__FILE__) . '/../../bootstrap.inc.php';


/**
 * (Abstract) base class for all Parameter types in the HRM.
 */
abstract class Parameter {

    /**
     * The pad size for the Parameter names.
     * const int
     */
    const PADSIZE      = 38;

    /**
     * Name of the parameter.
     * @var string
     */
    protected $name;

    /**
     * Value of the parameter.
     * @var mixed
     */
    protected $value;

    /**
     * Error message in case the check on the Parameter values fails.
     * @var string
     */
    protected $message;

    /**
     * Confidence level for the Parameter.
     * @var string
     */
    protected $confidenceLevel;

    /**
     * Parameter constructor.
     * @param string $name Name of the Parameter (it must match the Parameter
     * class name)
     */
    protected function __construct($name) {
        $this->name            = $name;
        $this->value           = null;
        $this->message         = '';
        $this->confidenceLevel = '';
    }

    /**
     * Checks whether the Parameter is valid.
     * @return bool True if the Parameter is valid, false otherwise.
     */
    abstract public function check();

    /**
     * Returns the error message that was set by check()
     * @return string
     */
    public function message() {
        return $this->message;
    }

    /**
     * Returns the confidence level for the Parameter.
     * @return string Confidence level for the Parameter.
     */
    public function confidenceLevel( ) {
        return $this->confidenceLevel;
    }

    /**
     * Sets the confidence level for the Parameter.
     * @param string $confidenceLevel
     */
    public function setConfidenceLevel($confidenceLevel) {
        $this->confidenceLevel = $confidenceLevel;
    }

    /**
     * Checks whether the Parameter must have a (valid) value.
     * @return bool True if the confidence level is lower than 'reported',
     * false otherwise.
     */
    public function mustProvide( ) {
        return !( $this->confidenceLevel == "reported"
                  || $this->confidenceLevel == "verified"
                  || $this->confidenceLevel == "asIs" );
    }

    /**
     * Return true of the value is not set.
     * @return bool True if the value is *not set* (i.e. null), false otherwise.
     */
    public function notSet( ) {
        if (is_array($this->value)) {
            foreach ($this->value as $value) {
                if ($value != null) {
                    return false;
                }
            }
            return true;
        }
        return($this->value == null);
    }

    /**
     * Returns the default value for the Parameters that have a default
     * value or NULL for those that don't.
     *
     * This function should be <b>overloaded</b> by the subclasses
     *
     * @return null
     */
    public function defaultValue() {
        return NULL;
    }

    /**
     * Checks whether the Parameter is an Image Parameter.
     *
     * This function should be <b>overloaded</b> by the subclasses.
     *
     * @return bool True if the Parameter is an Image Parameter, false otherwise.
    */
    public function isForImage() {
        return False;
    }

    /**
     * Checks whether the Parameter is a Microscope Parameter.
     *
     * This function should be <b>overloaded</b> by the subclasses.
     *
     * @return bool True if the Parameter is a Microscope Parameter, false otherwise.
     */
    public function isForMicroscope() {
        return False;
    }

    /**
     * Checks whether the Parameter is a Capture Parameter.
     *
     * This function should be <b>overloaded</b> by the subclasses.
     *
     * @return bool True if the Parameter is a Capture Parameter, false otherwise.
     */
    public function isForCapture() {
        return False;
    }

    /**
     * Checks whether the Parameter is a Sted Parameter.
     *
     * This function should be <b>overloaded</b> by the subclasses.
     *
     * @return bool True if the Parameter is a Sted Parameter, false otherwise.
     */
    public function isForSted() {
        return False;
    }

    /**
     * Checks whether the Parameter is a Spim Parameter.
     *
     * This function should be <b>overloaded</b> by the subclasses.
     *
     * @return bool True if the Parameter is a Spim Parameter, false otherwise.
     */
    public function isForSpim() {
        return False;
    }
    
    /**
     * Checks whether the Parameter is a Variable Channel Parameter.
     *
     * This function should be <b>overloaded</b> by the subclasses.
     *
     * @return bool True if the Parameter is a Variable Channel Parameter,
     * false otherwise.
     */
    public function isVariableChannel() {
        return False;
    }

    /**
     * Checks whether the Parameter is a Correction Parameter.
     *
     * This function should be <b>overloaded</b> by the subclasses.
     *
     * @return bool True if the Parameter is a Correction Parameter,
     * false otherwise.
     */
    public function isForCorrection() {
        return False;
    }

    /**
     * Checks whether the Parameter is used for calculating the Pixel Size from
     * the CCD pixel size and the total microscope magnification.
     *
     * This function should be <b>overloaded</b> by the subclasses.
     *
     * @return bool True if the Parameter is a Calculation Parameter,
     * false otherwise.
     */

    public function isForPixelSizeCalculation() {
        return False;
    }

    /**
     * Checks whether the Parameter is a Task Parameter.
     *
     * This function should be <b>overloaded</b> by the subclasses.
     *
     * @return bool True if the Parameter is a Task Parameter,
     * false otherwise.
     */
    public function isTaskParameter() {
        return False;
    }

    /**
     * Returns the name of the Parameter.
     * @return string The name of the Parameter.
     */
    public function name() {
        return $this->name;
    }

    /**
     * Returns the value of the Parameter.
     * @return mixed The value of the Parameter.
     */
    public function value() {
        return $this->value;
    }

    /**
     * Returns the internal value of the Parameter.
     *
     * This function should be <b>overloaded</b> by the subclasses if the
     * internal and external representations differ.
     *
     * @return mixed The internal value of the Parameter
    */
    public function internalValue() {
        return $this->value();
    }

    /**
     * Returns the possible values for the Parameter.
     *
     * This function should be <b>overloaded</b> by the subclasses if the
     * internal and external representations differ.
     *
     * @return array The possibles values of the Parameter in their internal
     * representation.
     */
    public function possibleValues() {
        return null;
    }

    /**
     * Returns the possible values for the Parameter in their internal
     * representation.
     *
     * This function should be <b>overloaded</b> by the subclasses if the
     * internal and external representations differ.
     *
     * @return array The possibles values of the Parameter in their internal
     * representation.
    */
    public function internalPossibleValues() {
        return $this->possibleValues();
    }

    /**
     * Sets the value of the Parameter
     * @param mixed $value Value for the Parameter.
    */
    public function setValue($value) {
        $this->value = $value;
    }

    /**
     * Returns true if boolean.
     *
     * This function should be <b>overloaded</b> by the subclasses.
     *
     * @return bool always false for a base Parameter.
    */
    public function isBoolean() {
        return False;
    }

    /**
     * Returns the formatted Parameter name to be used with displayString()
     * @param string|null $name (Optional) If specified, overrides the Parameter
     * name; if not, the actual Parameter name is used.
     * @return string Formatted Parameter name.
    */
    protected function formattedName(  $name = NULL ) {
        if ( $name === NULL ) {
            $name = $this->decomposeCamelCaseString( $this->name );
        }
        if ( $name[ 0 ] != " " ) {
            $name = " " . $name;
        }
        $name = $name . ':';
        $result = str_pad($name, self::PADSIZE, ' ', STR_PAD_RIGHT);
        return $result;
    }

    /**
     * Returns the string representation of the Parameter.
     *
     * Each Parameter that inherits from this function should re-implement it.
     * The function is not abstract since some children will need the
     * $numberOfChannels input parameter, while others won't.
     *
     * @param int $numberOfChannels Number of channels (default 0).
     * @return string String representation of the Parameter.
    */
    protected function displayString($numberOfChannels = 0) {
        // Reimplement this!
        return "";
    }

    /**
     * Returns the value of the Parameter in the translated form that is used
     * in the Tcl script.
     *
     * By default the translated value is just the value, but this can be
     * changed in subclasses when necessary.
     *
     * @return mixed The translated value.
    */
    public function translatedValue() {
        return $this->value();
    }

    /**
     * Returns the translated value for a given possible value.
     * @param mixed $possibleValue The possible value for which a translation is
     * needed.
     * @return mixed Translated possible value.
    */
    public function translatedValueFor($possibleValue) {
        $db = new DatabaseConnection();
        return $db->translationFor($this->name, $possibleValue);
    }


    /**
     * Breaks composed Parameter names (using camel-case notation) into
     * individual words.
     *
     * This function takes an input such a 'PointSpreadFunction' and returns
     * ' point spread function' (notice the initial blank space!)
     *
     * @param string $string String to be processed.
     * @return string $output Processed string.
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

    /**
     * Serialized, JSON-encoded Parameter.
     * @return string JSON-encoded Parameter string.
    */
    public function getJsonData(){
        $var = get_object_vars($this);
        foreach($var as &$value){
            if(is_object($value) && method_exists($value,'getJsonData')){
                $value = $value->getJsonData();
            }
        }
        return $var;
    }

}
