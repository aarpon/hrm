<?php
/**
 * Setting
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\setting\base;

use hrm\DatabaseConnection;
use hrm\param\base\Parameter;
use hrm\user\UserV2;

/**
 * (Abstract) base class for all specific Setting classes.
 *
 * @package hrm
 */
abstract class Setting {

    /**
     * Array of Parameter objects.
     * @var array
     */
    protected $parameter;

    /**
     * Last (error) message (e.g. from invalid Parameter check).
     * @var string
     */
    protected $message;

    /**
     * The User that owns the Setting.
     * @var UserV2
     */
    protected $owner;

    /**
     * The name of the Setting.
     * @var string
     */
    protected $name;

    /**
     * If true, the Setting is the default.
     * @var bool
     */
    protected $isDefault;

    /**
     * Number of channels associated with the Setting.
     * @var int
     */
    protected $numberOfChannels;

    /**
     * Setting constructor.
     */
    protected function __construct() {
        $this->parameter = array ();
        $this->isDefault = False;
    }

    /**
     * Deep copy method.
     *
     * Call:
     *
     *    $settingClone = clone $setting
     *
     * to get a deep copy of the original object.
     */
    public function __clone() {
        foreach($this as $key => $val) {
            if (is_object($val) || (is_array($val))) {
                $this->{$key} = unserialize(serialize($val));
            }
        }
    }

    /**
     * Returns the Parameter of given name.
     * @param string $name Name of the Parameter to return.
     * @return Parameter|null A Parameter object or NULL if the Parameter does
     * not exist.
    */
    public function parameter($name) {
        if (isset($this->parameter[$name])) {
            return $this->parameter[$name];
        } else {
            return NULL;
        }
    }

    /**
     * Sets a Parameter. The Parameter is stored in the Setting under its name.
     * @param Parameter $parameter Parameter to be stored.
     */
    public function set(Parameter $parameter) {
        $this->parameter[$parameter->name()] = $parameter;
    }

    /**
     * Returns the last (error) message.
     * @return string (Error) message.
     */
    public function message() {
        return $this->message;
    }

    /**
     * Return all Parameter names
     * @return array Array of parameter names
     */
    public function parameterNames() {
        $names = array();
        foreach ($this->parameter as $parameter) {
            $names[] = $parameter->name();
        }
        return $names;
    }

    /**
     * Returns the User owner of the Setting.
     * @return UserV2 Owner of the Setting.
     */
    public function owner() {
        return $this->owner;
    }

    /**
     * Sets the User owner of the Setting.
     * @param UserV2 $owner The owner of the Setting.
     */
    public function setOwner(UserV2 $owner) {
        $this->owner = $owner;
    }

    /**
     * Returns the name of the Setting.
     * @return string The name of the Setting.
     */
    public function name() {
        return $this->name;
    }

    /**
     * Sets the name of the Setting,
     * @param string $name Name of the Setting.
    */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * Checks whether the Setting is the user's default setting.
     * @return bool True if the Setting is the default Setting, false otherwise.
    */
    public function isDefault() {
        return $this->isDefault;
    }

    /**
     * Sets current Setting as the default Setting for the user.
    */
    public function beDefault() {
        $this->isDefault = True;
    }

    /**
     * Resets current Setting as no longer the default Setting for the user.
    */
    public function resetDefault() {
        $this->isDefault = False;
    }

    /**
     * Copies the Parameter values from another Setting.
     * @param Setting $setting The other Setting object from which the values
     * are copied.
    */
    public function copyParameterFrom(Setting $setting) {
        foreach ($setting->parameterNames() as $name) {
            $parameter = $this->parameter[$name];
            $otherParameter = $setting->parameter($name);
            $newValue = $otherParameter->internalValue();
            $parameter->setValue($newValue);
            $this->parameter[$name] = $parameter;
        }
    }

    /**
     * Returns the name of the database table in which the list of Setting
     * names are stored.
     *
     * Besides the name, the table contains the Setting's name, owner and
     * the standard (default) flag.
     *
     * This method must be reimplemented.
     *
     * @return string Table name.
     * @throws \Exception This method must be reimplemented.
     */
    static function table() {
        throw new \Exception('This method must be reimplemented!');
    }

    /**
     * Returns the name of the database table in which all the Parameters
     * for the Settings stored in the table specified in table().
     *
     * This method must be reimplemented.
     *
     * @return string Table name.
     * @throws \Exception This method must be reimplemented.
     *
     * @see table()
    */
    static function parameterTable() {
        throw new \Exception('This method must be reimplemented!');
    }

    /**
     * Loads the Parameter values into current Setting.
     * @return Setting The loaded Setting.
     */
    public function load() {
        $db = DatabaseConnection::get();
        $result = $db->loadParameterSettings($this);
        if (!$result) {
            $this->message = "Could not load settings!";
        }
        return $result;
    }

    /**
     * Saves all Parameter values from current Setting to the database.
     * @return bool True if saving was successful, false otherwise.
    */
    public function save() {
        $db = DatabaseConnection::get();
        $result = $db->saveParameterSettings($this);
        if (!$result) {
            $this->message = "Could not save settings!";
        }
        return $result;
    }

    /**
     * Shares the selected setting with the given user.
     * @param string $username Name of the user to share with.
     * @return bool True if sharing was successful, false otherwise.
    */
    public function shareWith($username) {
        $db = DatabaseConnection::get();
        $settings = $db->loadParameterSettings($this);
        $result = $db->saveSharedParameterSettings($settings, $username);
        if (!$result) {
            $this->message = "Sharing template failed!" ;
        } else {
            $this->message = "Template successfully shared!";
        }
        return $result;
    }

    /**
     * Sets the number of channels for the Setting.
     *
     * All variable channels Parameter objects in the Setting will updated.
     * @param int $channels Number of channels (between 1 and 6).
    */
    public function setNumberOfChannels($channels) {
        $this->numberOfChannels = $channels;
        foreach ($this->parameter as $parameter) {
            if ($parameter->isVariableChannel()) {
                $parameter->setNumberOfChannels($this->numberOfChannels);
                $this->set($parameter);
            }
        }
    }

}
