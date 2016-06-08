<?php
/**
 * SingleOrMultiChannelParameter
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param\base;

require_once dirname(__FILE__) . '/../../bootstrap.inc.php';

/**
 * A ChoiceParameter that handles single- and multi-channel Parameters
 * with prefixing.
 *
 * @todo Check whether this is still used anywhere.
 * 
 * @package hrm
 */
class SingleOrMultiChannelParameter extends ChoiceParameter {

    /**
     * Defines whether this is a single or multi channel parameter.
     * @var bool
     */
    protected $isMultiChannel;

    /**
     * SingleOrMultiChannelParameter constructor: creates an empty
     * SingleOrMultiChannelParameter Parameter.
     * @param string $name name of the SingleOrMultiChannelParameter Parameter.
     */
    protected function __construct($name) {
        parent::__construct($name);
    }

    /**
     * Checks whether the Parameter is multi-channel.
     * @return bool True if the Parameter is multi-channel.
    */
    public function isMultiChannel() {
        return $this->isMultiChannel;
    }

    /**
     * Checks whether the Parameter is single-channel.
     * @return bool True if the Parameter is single-channel.
     */
    public function isSingleChannel() {
        return !$this->isMultiChannel();
    }

    /**
     * Makes the Parameter multi-channel.
     */
    public function beMultiChannel() {
        $this->isMultiChannel = True;
    }

    /**
     * Makes the Parameter single-channel.
    */
    public function beSingleChannel() {
        $this->isMultiChannel = False;
    }

    /**
     * Sets the value of the SingleOrMultiChannelParameter.
     *
     * If $value contains the prefix single_ or multi_,  the parameter is set
     * to be single-channel or multi-channel, respectively, and the postfix of
     * the value is set as final value of the SingleOrMultiChannelParameter.
     *
     * @param  mixed $value  New value for the SingleOrMultiChannelParameter.
     * @see postfix
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

    /**
     * Returns the prefix for the Parameter, either 'single' or 'multi'
     * @return string Either 'single' or 'multi'.
    */
    public function prefix() {
        if ($this->isSingleChannel()) {
            $prefix = "single";
        } else {
            $prefix = "multi";
        }
        return $prefix;
    }

    /**
     * Returns the internal value of the SingleOrMultiChannelParameter.
     *
     * This function should be <b>overloaded</b> by the subclasses if the
     * internal and external representations differ.
     *
     * @return string The internal value of the Parameter, which is the value
     * with the prefix prepended.
    */
    public function internalValue() {
        $result = $this->prefix() . "_" . $this->value();
        return $result;
    }

    /**
     * Returns the internal possible values of the SingleOrMultiChannelParameter
     * @return string The internal possible values of the Parameter, which are
     * the <b>checked</b> values with the prefix prepended.
    */
    public function internalPossibleValues() {
        $result = array ();
        foreach ($this->possibleValues() as $possibleValue) {
            $result[] = $this->prefix() . "_" . $possibleValue;
        }
        return $result;
    }
};
