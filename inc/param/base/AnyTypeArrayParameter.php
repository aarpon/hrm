<?php
/**
 * AnyTypeArrayParameter
 *
 * @package hrm
 * @subpackage param\base
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param\base;

use hrm\DatabaseConnection;

require_once dirname(__FILE__) . '/../../bootstrap.inc.php';


/**
 * Class for a Parameter that has an array of variable of any type
 * as possible value.
 *
 * It inherits from NumericalArrayParameter and relaxes the condition that
 * the values must be integers.
 *
 * @package hrm
 */
class AnyTypeArrayParameter extends NumericalArrayParameter
{

    /**
     * Possible values for AnyTypeArrayParameter.
     * @var array
     */
    protected $possibleValues;

    /**
     * AnyTypeArrayParameter constructor: creates an empty AnyTypeArrayParameter.
     * @param string $name Name of the AnyTypeArrayParameter.
     */
    public function __construct($name)
    {

        parent::__construct($name);

        $this->possibleValues = array();

        // Get and set the Parameter possible values
        $db = new DatabaseConnection;
        $this->possibleValues = $db->readPossibleValues($this);
    }

    /**
     * Returns the possible values for the AnyTypeArrayParameter.
     * @return array The possible values.
     */
    public function possibleValues()
    {
        return $this->possibleValues;
    }

    /**
     * Returns the internal value of the AnyTypeArrayParameter.
     *
     * This function should be <b>overloaded</b> by the subclasses if the
     * internal and external representations differ.
     *
     * @return array The internal value of the Parameter
     */
    public function internalValue()
    {
        return $this->value;
    }

}
