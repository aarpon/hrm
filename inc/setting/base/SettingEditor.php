<?php
/**
 * SettingEditor
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\setting\base;

use hrm\DatabaseConnection;
use hrm\HuygensTools;
use hrm\user\UserV2;

require_once dirname(__FILE__) . '/../../bootstrap.php';


/**
 * Abstract class for a SettingEditor.
 *
 * @package hrm
 */
abstract class SettingEditor
{

    /**
     * Current user.
     * @var UserV2
     */
    protected $user;

    /**
     * Error message from last operation.
     * @var string
     */
    protected $message;

    /**
     * Name of the currently selected Setting.
     * @var string
     */
    protected $selected;

    /**
     * SettingEditor constructor.
     *
     * Selects the default Setting if a default Setting exists.
     * @param UserV2 $user Current User.
     */
    protected function __construct(UserV2 $user)
    {
        $this->user = $user;
        $this->message = '';
        $this->selected = NULL;
        foreach ($this->settings() as $setting) {
            if ($setting->isDefault()) {
                $this->selected = $setting->name();
            }
        }
    }

    /**
     * Creates and returns a new Setting
     *
     * This is an abstract function and must be reimplemented.
     * @return SettingEditor A SettingEditor (i.e. one of the specializations).
     */
    abstract public function newSettingObject();

    /**
     * Returns the name of the database table in which the Settings are stored.
     *
     * This must be reimplemented.
     *
     * @return string The table name.
     */
    abstract function table();

    /**
     * Loads and returns all the Settings for current user (but does not load
     * the Parameter values)
     * @return array The array of Settings.
     */
    public function settings()
    {
        $settings = array();
        $user = $this->user;
        $db = DatabaseConnection::get();
        $results = $db->getSettingList($user->name(), $this->table());
        foreach ($results as $row) {
            /** @var Setting $setting */
            $setting = $this->newSettingObject();
            $setting->setName($row['name']);
            $setting->setOwner($user);
            if ($row['standard'] == 't') {
                $setting->beDefault();
            }
            $settings[$row['name']] = $setting;
        }
        return $settings;
    }

    /**
     * Returns the Setting with given name.
     * @param string $name Name of the Setting.
     * @return Setting The Setting.
     */
    public function setting($name)
    {
        $user = $this->user;
        $db = DatabaseConnection::get();
        $results = $db->getSettingList($user->name(), $this->table());
        foreach ($results as $row) {
            if ($row['name'] == $name) {
                /** @var Setting $setting */
                $setting = $this->newSettingObject();
                $setting->setName($row['name']);
                $setting->setOwner($user);
                $setting = $setting->load();
                return $setting;
            }
        }
        return null;
    }

    /**
     * Returns the name of the currently selected Setting or NULL if none is
     * selected.
     * @return string|null The name of the selected Setting or NULL.
     */
    public function selected()
    {
        return $this->selected;
    }

    /**
     * Sets the Setting with given name as selected.
     * @param string $name Name of the Setting to be selected.
     */
    public function setSelected($name)
    {
        $this->selected = $name;
    }

    /**
     * Returns the name of the User.
     * @return string The name of the User.
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * Create and set a new Setting with given name.
     *
     * If a Setting with the same name already exists, return NULL. Otherwise,
     * the new Setting is set into the Editor and also returned.
     *
     * @param string $name Name of the Setting to be created.
     * @return Setting The created Setting object, or NULL if a Setting with
     * the same name already exists.
     */
    public function createNewSetting($name)
    {
        if (!$this->checkNewSettingName($name)) {
            return NULL;
        }
        /** @var Setting $setting */
        $setting = $this->newSettingObject();
        $setting->setName($name);
        $setting->setOwner($this->user);
        $this->setSelected($name);
        return $setting;
    }

    /**
     * Creates a new Setting with the given new name in the database and copies
     * the Parameter values of the existing Parameter to it.
     * @param string $newName The name of the new Setting.
     * @return bool True if the copy was successful, false otherwise.
     */
    public function copySelectedSetting($newName)
    {
        if (!$this->checkSelectedSetting()) {
            return False;
        }
        $settings = $this->settings();
        $oldSettingName = $this->selected();
        $oldSetting = $settings[$oldSettingName];
        $oldSetting = $oldSetting->load();
        $newSetting = $this->createNewSetting($newName);
        if ($newSetting == NULL) {
            return False;
        }
        $newSetting->copyParameterFrom($oldSetting);
        $result = $newSetting->save();
        $this->message = $newSetting->message();
        return $result;
    }

    /**
     * Populates a setting based on parsing the raw file string of a Huygens
     * template.
     * @param Setting $setting The setting object to fill.
     * @param string $huTemplate The raw contents of the template file.
     * @return bool True if the new template creation was successful, false
     * otherwise.
     * @todo This method always returns false: it should return true at the end
     * and also interpret the output of the call to HuygensTools::askHuCore()
     * to decide whether it was successful or not!
     */
    public function huTemplate2hrmTemplate($setting, $huTemplate)
    {

        $result = False;

        if ($setting == NULL) {
            return $result;
        }

        $opts = "-huTemplate \"" . $huTemplate . "\"";

        $data = HuygensTools::askHuCore('getMetaDataFromHuTemplate', $opts);
        if ($data == null) {
            $this->message = "Could not create settings from raw HuCore template!";
            return False;
        }


        // @todo Not all settings have this method!
        $setting->parseParamsFromHuCore($data);
        $this->message = $setting->message();

        return $result;
    }

    /**
     * Copies the selected setting to the share table for the given recipients.
     * @param string $templateName Name of the template to copy,
     * @param array $recipients Array of user names.
     * @return bool True if the copy was successful, false otherwise.
     */
    public function shareSelectedSetting($templateName, $recipients)
    {
        $settings = $this->settings();
        if (!array_key_exists($templateName, $settings)) {
            return False;
        }
        foreach ($recipients as $recipient) {
            $setting = clone $settings[$templateName];
            $result = $setting->shareWith($recipient);
        }
        $this->message = $setting->message();
        return $result;
    }

    /**
     * Creates a new Setting in the database and copies the values from a
     * public Setting.
     *
     * The new Setting will have the same name as the old Setting.
     * This is because this function is used to copy a preset (public
     * Setting) created by the admin into the user list of Setting's.
     *
     * @param Setting $setting An existing Setting.
     * @return bool True if the copy was successful, false otherwise.
     */
    public function copyPublicSetting(Setting $setting)
    {
        $success = False;
        $newName = $this->user->name() . ' - ' . $setting->name();
        $nTrials = 0;
        while ($success == False) {
            if ($nTrials >= 10) {
                $this->message = 'A setting with the name ' .
                    $setting->name() . ' (and all variations ' .
                    $setting->name() . '1, ...) already exists! ' .
                    'Please delete or rename some of your settings.';
                return False;
            }
            $newSetting = $this->createNewSetting($newName);
            if ($newSetting == null) {
                // Try with another name
                $nTrials++;
                $newName = $setting->name() . $nTrials;
            } else {
                $success = True;
                $this->message = '';
            }
        }
        /** @var Setting $newSetting */
        $newSetting->copyParameterFrom($setting);
        $result = $newSetting->save();
        $this->message = $newSetting->message();
        return $result;
    }

    /**
     * Loads the values for the selected Setting and returns the Setting object.
     * @return Setting The loaded, selected Setting if successful, false
     * otherwise.
     */
    public function loadSelectedSetting()
    {
        if (!$this->checkSelectedSetting()) {
            return False;
        }
        $name = $this->selected();
        $settings = $this->settings();
        $setting = $settings[$name];
        $setting = $setting->load();
        $this->setSelected($name);
        return $setting;
    }

    /**
     * Make the selected Setting the default one.
     *
     * The selection will be stored in the database.
     *
     * @return bool True if it worked, false otherwise.
     */
    public function makeSelectedSettingDefault()
    {
        if (!$this->checkSelectedSetting()) {
            $this->message =
                "Please select a setting in the list before pressing the button!";
            return False;
        }
        $name = $this->selected();
        foreach ($this->settings() as $setting) {
            if ($setting->name() == $name) {
                if (!$setting->isDefault()) {
                    $setting->beDefault();
                } else {
                    // If it already was the default setting,
                    // we reset it
                    $setting->resetDefault();
                }
            } else {
                $setting->resetDefault();
            }
            // Update the database
            $db = DatabaseConnection::get();
            $db->updateDefault($setting);
        }
        return true;
    }

    /**
     * Delete the Setting the selected Setting the default one.
     *
     * The selection will be stored in the database.
     *
     * @return bool True if it worked, false otherwise.
     */
    public function deleteSelectedSetting()
    {
        if (!$this->checkSelectedSetting()) {
            return False;
        }
        $name = $this->selected();
        $settings = $this->settings();
        if (!isset($settings[$name])) {
            return False;
        }
        $db = DatabaseConnection::get();
        if (!$db->deleteSetting($settings[$name])) {
            $this->message = "delete setting - database error";
            return False;
        }
        return True;
    }

    /**
     * Returns the error message that was set by last operation.
     *
     * The message string will be empty if the last operation was successful.
     *
     * @return string Error message.
     */
    public function message()
    {
        return $this->message;
    }

    /**
     * Checks that the given name for the new Setting is not empty and that and
     * there does not exist already a Setting with that name.
     * @param string $name Name for the new Setting.
     * @return bool True if the name is valid, false otherwise.
     */
    public function checkNewSettingName($name)
    {
        $this->message = '';
        $names = array();
        foreach ($this->settings() as $setting) {
            $names[] = $setting->name();
        }
        if (trim($name) == '') {
            $this->message =
                "Please enter a name for the setting and try again!";
            return False;
        }
        if (in_array($name, $names)) {
            $this->message =
                "A setting with the name $name already exists. " .
                "Please enter another name!";
            return False;
        }
        return True;
    }

    /**
     * Check if a Setting with given name exists. If it does not, return the
     * same name. Otherwise append numerical suffixes until a name that does not
     * exist is found. Return this modified name.
     *
     * @param string $name Name of the Setting to try.
     * @return string Setting name with optional numeric suffix that does not yet exist in the system.
     */
    public function getValidNewSettingName($name)
    {
        $numIndex = "";
        $i = 0;
        while (array_key_exists($name . $numIndex, $this->settings())) {
            // A setting with this name exists already, try appending a numerical index
            $i = $i + 1;
            $numIndex = "$i";
        }
        return ($name . $numIndex);
    }

    /**
     * Checks whether a Setting is selected and whether the selection points to
     * an actually existing Setting.
     * @return bool True if an existing Setting is selected, false otherwise.
     */
    public function checkSelectedSetting()
    {
        $this->message = '';
        $nameOfSelectedSetting = $this->selected();
        if ($nameOfSelectedSetting == '') {
            return False;
        }
        $settings = $this->settings();
        if (!isset($settings[$nameOfSelectedSetting])) {
            return False;
        }
        return True;
    }

}
