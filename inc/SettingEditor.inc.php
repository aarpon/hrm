<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("Setting.inc.php");
require_once("Database.inc.php");
require_once("User.inc.php");
require_once("Util.inc.php");

/*!
  \class    BaseSettingEditor
	\brief    Abstract class for a SettingEditor
*/
abstract class BaseSettingEditor {

    /*!
      \var	$user
      \brief	Current User
    */
    protected $user;

    /*!
      \var	$message
      \brief	Error message from last operation
    */
    protected $message;

    /*!
      \var	$selected
      \brief	Name of the currently selected Setting
    */
    protected $selected;

    /*!
      \brief	Protected constructor: creates a new SettingEditor and
              selects the default Setting if a default Setting exists.
      \param	$user	Current User
    */
    protected function __construct(User $user) {
        $this->user = $user;
        $this->message = '';
        $this->selected = NULL;
        foreach ($this->settings() as $setting) {
            if ($setting->isDefault()) {
                $this->selected = $setting->name();
            }
        }
    }

    /*!
      \brief	Abstract function: creates and returns a new Setting

      This must be reimplemented.

      \return	a new Setting
    */
    abstract public function newSettingObject();

    /*!
      \brief	Returns the name of the database table in which the
              ParameterSetting's are stored

      This must be reimplemented.

      \return	table name
    */
    abstract function table();

    /*!
      \brief	Loads and returns all the Setting's for current user (does not
              load the Parameter values)
      \return	the array of Setting's
    */
    public function settings() {
        $settings = array();
        $user = $this->user;
        $db = new DatabaseConnection();
        $results = $db->getSettingList($user->name(), $this->table());
        foreach ($results as $row) {
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

    /*!
      \brief	Returns the Setting with given name
      \param	$name	name of the ParameterSetting
      \return	the ParameterSetting
    */
    public function setting($name) {
        $user = $this->user;
        $db = new DatabaseConnection();
        $results = $db->getSettingList($user->name(), $this->table());
        foreach ($results as $row) {
            if ($row['name'] == $name) {
                $setting = $this->newSettingObject();
                $setting->setName($row['name']);
                $setting->setOwner($user);
                $setting = $setting->load();
                return $setting;
            }
        }
        return null;
    }

    /*!
      \brief	Returns the name of the currently selected Setting
              or NULL if none is selected
      \return	the name of the selected Setting or NULL
    */
    public function selected() {
        return $this->selected;
    }

    /*!
      \brief	Sets the Setting with given name as selected
      \param	$name	Name of the Setting to be selected
    */
    public function setSelected($name) {
        $this->selected = $name;
    }

    /*!
      \brief	Returns the name of the User
      \return	the name of the User
    */
    public function user() {
        return $this->user;
    }

    /*!
      \brief	Create and set a new Setting with given name.

      If a Setting with the same name already exists, return NULL. Otherwise,
      the new Setting is set into the Editor and also returned.

      \param	$name	Name of the Setting to be created
      \return	the created Setting object, or NULL if a Setting
              with the same name already exists
    */
    public function createNewSetting($name) {
        if (!$this->checkNewSettingName($name)) {
            return NULL;
        }
        $setting = $this->newSettingObject();
        $setting->setName($name);
        $setting->setOwner($this->user);
        $this->setSelected($name);
        return $setting;
    }

    /*!
      \brief	Creates a new Setting with the given new name in the
              database and copies the Parameter values of the existing
              Parameter to it.
      \param  $newName    The name of the new Setting
      \return	true if the copy was successful, false otherwise
    */
    public function copySelectedSetting($newName) {
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

    /*!
      \brief    Creates a new setting based on parsing
                the given file through HuCore
      \param    $setting  The setting object to fill
      \param    $filename File name without path
      \return   True if the new template creation was successful,
                false otherwise
    */
    public function generateTemplateFromImageFile($setting, $filename)
    {
        /* It should not matter whether it works or not, in case there is no
           param just do the same as create new.
        */
        if ($setting != NULL) {

            $opts = "-path \"" . $_SESSION['fileserver']->sourceFolder() .
                "\" -filename \"$filename\"";

            $data = askHuCore('getDataFromFile', $opts);

            $setting->parseParamsFromHuCore($data);
            $result = $setting->save();
            $this->message = $setting->message();
            return $result;
        }
    }

        /*!
      \brief	populates a setting based on parsing
                the raw file string of a Huygens template
      \param    $setting    The setting object to fill
      \param    $rawstring    The raw contents of the template file
      \return	true if the new template creation was successful,
                false otherwise
    */
    public function generateTemplateFromHuygensTemplate($setting, $rawstring) {
        /*
        Basically we need to make it the same format as $data in generateTemplateFromImageFile()
        For this we will use some regexp to find the key value pairs we need
        */

        if ($setting != NULL) {
            // First only grab the contents of the setp element
            preg_match("/.*setp {(.+)}/", $rawstring, $data);
            preg_match_all("/([^ ]+) { ?([^}]+)}/", $data[1], $r);
            $result = array_combine($r[1], $r[2]);

            // Rename them so that they match their counterparts
            // for parseParamsFromHuCore
            $renaming = array (
                's' => 'sampleSizes',
                'micr' => 'mType',
                'pr' => 'pinhole',
                'ex' => 'lambdaEx',
                'em' => 'lambdaEm',
                'na' => 'NA',
                'ri' => 'RIMedia',
                'ril' => 'RILens',
                'ps' => 'pinholeSpacing'
            );
            $final = $result;
            foreach ($renaming as $currentKey => $newKey) {
                $final[$newKey] = $final[$currentKey];
                unset($final[$currentKey]);
            }

            // Make sure that all settings are set to verified.
            foreach ($final as $key => $value) {
                $final['parState,' . $key] = 'verified';
            }
            $setting->parseParamsFromHuCore($final);
            $result = $setting->save();
            $this->message = $setting->message();
            return $result;
        }
    }

    /*!
      \brief  Copies the selected setting to the share table for the given
              recipients.
      \param  $templateName Name of the template to copy,
      \param  $recipients Array of user names.
      \return	true if the copy was successful, false otherwise
    */
    public function shareSelectedSetting($templateName, $recipients) {
        $settings = $this->settings();
        if (! array_key_exists($templateName, $settings)) {
            return False;
        }
        foreach ($recipients as $recipient) {
            $setting = clone $settings[$templateName];
            $result = $setting->shareWith($recipient);
        }
        $this->message = $setting->message();
        return $result;
    }

    /*!
      \brief	Creates a new Setting in the database and copies
              the values from a public Setting

      The new Setting will have the same name as the old Setting.
      This is because this function is used to copy a preset (public
      Setting) created by the admin into the user list of Setting's.

      \param	$setting	An existing Setting
      \return	true if the copy was successful, false otherwise
    */
    public function copyPublicSetting(Setting $setting) {
        $success = False;
        $newName = $this->user->name(). ' - '.$setting->name();
        $nTrials = 0;
        while ( $success == False ) {
            if ( $nTrials >= 10 ) {
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
        $newSetting->copyParameterFrom($setting);
        $result = $newSetting->save();
        $this->message = $newSetting->message();
        return $result;
    }

    /*!
      \brief	Loads the values for the selected Setting and returns
              the Setting object
      \return	the loaded, selected Setting if successful, false otherwise
    */
    public function loadSelectedSetting() {
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

    /*!
      \brief	Make the selected Setting the default one

      The selection will be stored in the database.

      \return	true if it worked, false otherwise
    */
    public function makeSelectedSettingDefault() {
        if (!$this->checkSelectedSetting()) {
            $this->message =
                "Please select a setting in the list before pressing the button!";
            return False;
        }
        $name = $this->selected();
        foreach ($this->settings() as $setting) {
            if ($setting->name() == $name) {
                if ( !$setting->isDefault() ) {
                    $setting->beDefault();
                } else {
                    // If it alreay was the default setting,
                    // we reset it
                    $setting->resetDefault();
                }
            } else {
                $setting->resetDefault();
            }
            // Update the database
            $db = new DatabaseConnection();
            $db->updateDefault($setting);
        }
        return true;
    }

    /*!
      \brief	Delete the Setting the selected Setting the default one

      The selection will be stored in the database.

      \return	true if it worked, false otherwise
    */
    public function deleteSelectedSetting() {
        if (!$this->checkSelectedSetting()) {
            return False;
        }
        $name = $this->selected();
        $settings = $this->settings();
        if (!isset($settings[$name])) {
            return False;
        }
        $db = new DatabaseConnection();
        if (!$db->deleteSetting($settings[$name])) {
            $this->message = "delete setting - database error";
            return False;
        }
        return True;
    }

    /*!
      \brief	Returns the error message that was set by last operation

      The message string will be empty if the last operation was successful.

      \return	error message
    */
    public function message() {
        return $this->message;
    }

    /*!
      \brief	Checks that the given name for the new Setting is not empty and
              that and there does not exist already a Setting with that name
      \param	$name	Name for the new Setting
      \return	true if the name is valid, false otherwise
    */
    public function checkNewSettingName($name) {
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

    /*!
      \brief	Checks whether a Setting is selected and whether the selection
              points to an actually existing Setting
      \return	true if an existing Setting is selected, false otherwise
    */
    public function checkSelectedSetting() {
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

// End of SettingEditor class

/*
	============================================================================
*/

/*!
  \class	SettingEditor
  \brief	Implements an Editor for ParameterSetting
*/
class SettingEditor extends BaseSettingEditor {

    /*!
      \brief	Constructor: creates a new SettingEditor and selects the default
              Setting if a default Setting exists.
      \param	$user	Current User
    */
    public function __construct(User $user) {
        parent::__construct($user);
    }

    /*!
      \brief	Returns the name of the database table in which the
              ParameterSetting's are stored
      \return table name
    */
    function table() {
        return "parameter_setting";
    }

    /*!
      \brief	Creates and returns a new ParameterSetting
      \return	a new PatameterSetting
    */
    public function newSettingObject() {
        return (new ParameterSetting());
    }

}

/*
	============================================================================
*/

/*!
  \class	TaskSettingEditor
  \brief	Implements an Editor for TaskSetting
*/
class TaskSettingEditor extends BaseSettingEditor {

    /*!
      \brief	Constructor: creates a new SettingEditor and selects the default
              Setting if a default Setting exists.
      \param	$user	Current User
    */
    public function __construct(User $user) {
        parent::__construct($user);
    }

    /*!
      \brief	Returns the name of the database table in which the
              TaskSetting's are stored
      \return	table name
    */
    function table() {
        return "task_setting";
    }

    /*!
      \brief	Creates and returns a new TaskSetting
      \return	a new TaskSetting
    */
    function newSettingObject() {
        return (new TaskSetting());
    }
}


/*
============================================================================
*/

/*!
  \class	AnalysisSettingEditor
  \brief	Implements an Editor for AnalysisSetting
*/
class AnalysisSettingEditor extends BaseSettingEditor {

    /*!
      \brief	Constructor: creates a new SettingEditor and selects the default
              Setting if a default Setting exists.
      \param	$user	Current User
    */
    public function __construct(User $user) {
        parent::__construct($user);
    }

    /*!
      \brief	Returns the name of the database table in which the
                AnalysisSetting's are stored
      \return	table name
    */
    function table() {
        return "analysis_setting";
    }

    /*!
      \brief	Creates and returns a new AnalysisSetting
      \return	a new AnalysisSetting
    */
    function newSettingObject() {
        return (new AnalysisSetting());
    }
}
