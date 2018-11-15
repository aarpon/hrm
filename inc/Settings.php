<?php
/**
 * Settings
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm;

require_once dirname(__FILE__) . '/bootstrap.php';

/**
 * Setting class.
 *
 * @package hrm
 */
class Settings
{
    /**
     * Static instance (singleton)
     * @var Settings singleton
     */
    protected static $instance;

    /**
     * Keep a reference to the ID of the settings in the table
     */
    private $id = -1;

    /**
     * @var array Table of settings
     */
    private $settings_table = null;

    /**
     * Keep track of whether the table has unsaved changes.
     */
    private $is_dirty = false;

    /**
     * Return the singleton instance of the class.
     * @return Settings The singleton instance.
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new Settings();
        }
        return self::$instance;
    }

    /**
     * Save settings to the database.
     * @return boolean True if the settings could be stored in the database, false otherwise.
     */
    public function save()
    {
        // Have we loaded the settings already?
        if (null === $this->settings_table) {
            return false;
        }

        // Connect to the database
        $db = new DatabaseConnection();

        // Store the table
        $ok = $db->connection()->AutoExecute(
            "instance_settings",
            $this->settings_table,
            "UPDATE",
            "id = '$this->id'");

        // All changes have been saved
        if ($ok == true) {
            $this->is_dirty = false;
        }

        // return the result
        return $ok;
    }

    /**
     * Get a specific setting.
     * @param string $settingName Name of the setting to return.
     * @return string|boolean|array Setting value.
     * @throws \Exception If the requested setting is not found in the table.
     */
    public function get($settingName) {

        // Did we load the settings already?
        if (is_null($this->settings_table)) {

            // Load them
            $this->load();
        }

        // We combine the various authentication mechanism options into one
        // custom setting
        if ($settingName === "authenticate_against") {
            $value = array();
            $value[0] = $this->settings_table["default_authentication"];
            $alt_authentication_1 = $this->settings_table["alt_authentication_1"];
            if ($alt_authentication_1 !== "none" || $alt_authentication_1 != "") {
                array_push($value, $alt_authentication_1);
            }
            $alt_authentication_2 = $this->settings_table["alt_authentication_2"];
            if ($alt_authentication_2 !== "none" || $alt_authentication_2 != "") {
                array_push($value, $alt_authentication_2);
            }
            $alt_authentication_3 = $this->settings_table["alt_authentication_3"];
            if ($alt_authentication_3 !== "none" || $alt_authentication_3 != "") {
                array_push($value, $alt_authentication_3);
            }
            $alt_authentication_4 = $this->settings_table["alt_authentication_4"];
            if ($alt_authentication_4 !== "none" || $alt_authentication_4 != "") {
                array_push($value, $alt_authentication_4);
            }
            return $value;
        }

        // Does the requested setting exist?
        if (array_key_exists($settingName, $this->settings_table)) {

            // Return the value
            $value = $this->settings_table[$settingName];

            // Convert to boolean, if needed
            if ($value === 't') {
                $value = true;
            }
            if ($value === 'f') {
                $value = false;
            }

            // Return the value
            return $value;
        }

        // Failure
        throw new \Exception("Parameter $settingName not found in the Settings table.");
    }

    /**
     * Set a specific setting
     * @param string $settingName Name of the setting to set.
     * @param string $settingValue New value of the setting.
     * @return boolean True if the setting could be updated successfully, false otherwise.
     */
    public function set($settingName, $settingValue) {

        // Did we load the settings already?
        if (is_null($this->settings_table)) {
            return false;
        }

        // Does the requested setting exist?
        if (array_key_exists($settingName, $this->settings_table)) {

            // Store the value
            $this->settings_table[$settingName] = $settingValue;

            // There are now unsaved changes
            $this->is_dirty = true;

            // Return success
            return true;
        }

        // Return failure
        return false;
    }

    /**
     * Set an array of Settings at once.
     * @param array $arrayOfSettings Array of settings (keys := settings names,
     * values := settings values). If any of the keys does not exist in the
     * settings table, no settings are updated; and the function returns false.
     *
     * The Settings are not persisted into the database. Use Settings::store() to persist.
     * @param string $message Reference to a string that holds possible error messages.
     * @return bool True if all Settings could be stored successfully, false otherwise.
     * @throws \Exception It the Settings could not be loaded from the database.
     */
    public function setMany($arrayOfSettings, &$message) {

        // The input argument must be an array
        if (! is_array($arrayOfSettings)) {
            $message = "The input argument to Settings->setAll() must be an array.";
            return false;
        }

        // Did we load the settings already?
        if (null === $this->settings_table) {
            $this->load();
        }

        // Check that all keys exist
        foreach ($arrayOfSettings as $key => $value) {
            if (! array_key_exists($key, $this->settings_table)) {
                $message = "Unknown setting $key.";
                return false;
            }
        }

        // Now proceed with storing the values
        foreach ($arrayOfSettings as $key => $value) {

            // For some settings we need to do some additional work
            switch ($key) {

                case 'image_folder':
                    if ($this->set($key, $value) === false) {
                        $message = "Could not set image_folder setting.";
                        return false;
                    }
                    if ($this->set("http_download_temp_files_dir", $value . "/.hrm_downloads") === false) {
                        $message = "Could not set http_download_temp_files_dir setting.";
                        return false;
                    }
                    if ($this->set("http_upload_temp_chunks_dir", $value . "/.hrm_chunks") === false) {
                        $message = "Could not set http_upload_temp_chunks_dir setting.";
                        return false;
                    }
                    if ($this->set("http_upload_temp_files_dir", $value . "/.hrm_files") === false) {
                        $message = "Could not set http_upload_temp_files_dir setting.";
                        return false;
                    }
                    break;
                default:
                    if ($this->set($key, $value) === false) {
                        $message = "Could not set $key setting.";
                        return false;
                    }
                    break;
            }
        }

        // Return success
        $message = "";
        return true;
    }

    /**
     * Returns true if there are unsaved changes in the Settings.
     * @return bool True if there are unsaved changes, false otherwise.
     */
    public function haveUnsavedChanges() {
        return $this->is_dirty;
    }

    /**
     * Load settings from the database (private metohd).
     */
    private function load()
    {
        // Connect to the database
        $db = new DatabaseConnection();

        // Load the table
        $rows = $db->query("SELECT * FROM instance_settings;");
        if (!$rows) {
            throw new \Exception("Could not retrieve settings from database!");
        } elseif (count($rows) > 1) {
            throw new \Exception("Found more than one row of settings found in the database!");
        } else {

            // Store the table
            $this->settings_table = $rows[0];

            // Store the ID
            $this->id = $this->settings_table['id'];

            // There are no unsaved changes
            $this->is_dirty = false;
        }
    }

    /**
     * Private Singleton constructor.
     */
    protected function __construct() {

        // Load the settings from the database
        $this->load();
    }

    /**
     * Private __clone() method.
     */
    protected function __clone() {}

    /**
     * Private __wakeup() method.
     */
    protected function __wakeup() {}

}