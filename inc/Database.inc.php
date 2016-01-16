<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once(dirname(__FILE__) . "/hrm_config.inc.php" );
require_once(dirname(__FILE__) . "/Util.inc.php" );
require_once(dirname(__FILE__) . "/extern/adodb5/adodb.inc.php");

/*!
    \class  DatabaseConnection
    \brief  Manages the database connection through the ADOdb library

    This class abstracts the database back-end and should be used to handle all
    communication to and from it. Since there are some differences between the
    databases that still require specialized code, this class officially
    supports only MySQL and PostgreSQL.
*/
class DatabaseConnection {

    /*!
      \var    $connection
      \brief  Private ADOConnection object
    */
    private $connection;

    /*!
      \var    $parameterNameDictionary
      \brief  Private Maps the Parameter names between HRM and Huygens
    */
    private $parameterNameDictionary;

    /*!
      \brief  Constructor: creates a database connection
    */
    public function __construct() {
        global $db_type;
        global $db_host;
        global $db_name;
        global $db_user;
        global $db_password;

        $this->connection = ADONewConnection($db_type);
        $this->connection->Connect($db_host, $db_user, $db_password, $db_name);

        // Set the parameter name dictionary
        $this-> parameterNameDictionary = array(
            "CCDCaptorSizeX"       => "sampleSizesX",       // In HRM there is no distinction between x and y pixel size
            "ZStepSize"            => "sampleSizesZ",
            "TimeInterval"         => "sampleSizesT",
            "PinholeSize"          => "pinhole",
            "NumberOfChannels"     => "chanCnt",
            "PinholeSpacing"       => "pinholeSpacing",
            "ExcitationWavelength" => "lambdaEx",
            "EmissionWavelength"   => "lambdaEm",
            "MicroscopeType"       => "mType",
            "NumericalAperture"    => "NA",
            "ObjectiveType"        => "RILens",
            "SampleMedium"         => "RIMedia",
            "unused1"              => "iFacePrim",          // PSFGenerationDepth?
            "unused2"              => "iFaceScnd",
            "unused3"              => "imagingDir",
            "unused4"              => "objQuality",
            "unused5"              => "photonCnt",
            "unused6"              => "exBeamFill",
            "StedDepletionMode"    => "stedMode",
            "StedWavelength"       => "stedLambda",
            "StedSaturationFactor" => "stedSatFact",
            "StedImmunity"         => "stedImmunity",
            "Sted3D"               => "sted3D");
    }

    /*!
      \brief  Checks whether a connection to the DB is possible
      \return true if the connection is possible, false otherwise
    */
    public function isReachable() {
        global $db_type;
        global $db_host;
        global $db_name;
        global $db_user;
        global $db_password;
        $connection = ADONewConnection($db_type);
        $result = $connection->Connect($db_host, $db_user, $db_password, $db_name);
        return $result;
    }

    /*!
      \brief  Returns the type of the database (mysql, ...)
      \return type of the database (e.g. postgresql)
    */
    public function type() {
        global $db_type;
        return $db_type;
    }

    /*!
      \brief  Attempts to get the version of the underlying database
      \return version of the database (e.g. 2.2.14)
    */
    public function version() {
        try {
            $query = "SELECT version( );";
            $version = $this->queryLastValue($query);
        } catch (Exception $e) {
            $version = "Could not get version information.";
        }
        return $version;
    }

    /*!
      \brief  Returns the database host name
      \return name of the database host
    */
    public function host() {
        global $db_host;
        return $db_host;
    }

    /*!
      \brief  Returns the database name
      \return name of the database
    */
    public function name() {
        global $db_name;
        return $db_name;
    }

    /*!
      \brief  Returns the name of the database user
      \return name of the database user
    */
    public function user() {
        global $db_user;
        return $db_user;
    }

    /*!
      \brief  Returns the password of the database user
      \return password of the database user
    */
    public function password() {
        global $db_password;
        return $db_password;
    }

    /*!
      \brief  Returns the ADOConnection object
      \return the connection object
    */
    public function connection() {
        return $this->connection;
    }

    /*!
      \brief  Executes an SQL query
      \param  $query  SQL query
      \return query object
    */
    public function execute($query) {
        $connection = $this->connection();
        $result = $connection->Execute($query);
        return $result;
    }

    /*!
      \brief  Executes an SQL query and returns the results
      \param  $queryString    SQL query
      \return result of the query (array)
    */
    public function query($queryString) {
        $connection = $this->connection();
        $resultSet = $connection->Execute($queryString);
        if (!$resultSet) {
            return False;
        }
        $rows = $resultSet->GetRows();
        return $rows;
    }

    /*!
      \brief  Executes an SQL query and returns the last row of the results
      \param  $queryString    SQL query
      \return last row of the result of the query (array)
    */
    public function queryLastRow($queryString) {
        $rows = $this->query($queryString);
        if (!$rows) return False;
        $result = end($rows);
        return $result;
    }

    /*!
      \brief  Executes an SQL query and returns the value in the last column of
              the last row of the results
      \param  $queryString    SQL query
      \return value of the last column of the last row of the result of the query
    */
    public function queryLastValue($queryString) {
        $rows = $this->queryLastRow($queryString);
        if (!$rows) return False;
        $result = end($rows);
        return $result;
    }

    /*!
      \brief  Adds a new user to the database (all parameters are expected
              to be already validated!
      \param  $username   The name of the user
      \param  $password   Password (plain)
      \param  $email      E-mail address
      \param  $group      Research group
      \param  $status     Status or ID
      \return $success    True if success; false otherwise
    */
    public function addNewUser($username, $password, $email, $group, $status) {
        $query = "INSERT INTO username (name, password, email, research_group, status) ".
            "VALUES ('".$username."', ".
            "'".md5($password)."', ".
            "'".$email."', ".
            "'".$group."', ".
            "'".$status."')";
        $result = $this->execute($query);
        if ( $result ) {
            $query = "UPDATE username SET creation_date = CURRENT_TIMESTAMP WHERE name = '". $username . "'";
            $result = $this->execute($query);
        }
        if ( $result ) {
            return true;
        } else {
            return false;
        }
    }

    /*!
      \brief  Updates an existing user in the database (all parameters are
              expected to be already validated!)

      Only the password can be changed for the admin user. For a normal user,
      e-mail address, group and password can be updated.

      \param  $isadmin    True if the user is the HRM admin
      \param  $username   The name of the user
      \param  $password   Password (plain)
      \param  $email      E-mail address
      \param  $group      Research group
      \return $success    True if success; false otherwise
    */
    public function updateExistingUser( $isadmin, $username, $password, $email = "", $group = "" ) {
        // The admin user does not have a group and stores his password in the
        // configuration files. The only variable is the password.
        if ( $isadmin === True ) {
            $query = "UPDATE username SET password = '".md5($password)."' " .
                "WHERE name = '".$username."';";
        } else {
            $query = "UPDATE username SET email ='".$email."', " .
                "research_group ='".$group."', ".
                "password = '".md5($password)."' " .
                "WHERE name = '".$username."';";
        }
        $result = $this->execute($query);
        if ( $result ) {
            return true;
        } else {
            return false;
        }
    }

    /*!
    \brief  Updates an existing user in the database (all parameters are
            expected to be already validated!)

    The last access time will be updated as well.

    \param  $username   The name of the user (used to query)
    \param  $email      E-mail address
    \param  $group      Research group

    \return $success    True if success; false otherwise
    */
    public function updateUserNoPassword($username, $email, $group) {

        if ($email == "" || $group=="") {
            report("User data update: e-mail and group cannot be empty! " .
                "No changes to the database!", 0);
            return false;
        }

        // Build query
        $query = "UPDATE username SET email ='" . $email . "', " .
            "research_group ='" . $group . "' " .
            "WHERE name = '" . $username . "';";

        $result = $this->execute($query);
        if ( $result ) {
            return true;
        } else {
            return false;
        }
    }

    /*!
      \brief  Updates the status of an existing user in the database (username
              is expected to be already validated!
      \param  $username   The name of the user
      \param  $status     One of 'd', 'a', ...
      \return $success    True if success; false otherwise
    */
    public function updateUserStatus($username, $status) {
        $query = "UPDATE username SET status = '".$status."' WHERE name = '".$username."'";
        $result = $this->execute($query);
        if ( $result ) {
            return true;
        } else {
            return false;
        }
    }

    /*!
      \brief  Updates the status of all non-admin users in the database
      \param  $status     One of 'd', 'a', ...
      \return $success    True if success; false otherwise
    */
    public function updateAllUsersStatus($status) {
        $query = "UPDATE username SET status = '".$status."' WHERE name NOT LIKE 'admin'";
        $result = $this->execute($query);
        if ( $result ) {
            return true;
        } else {
            return false;
        }
    }

    /*!
      \brief  Deletes an user and all data from the database (username is
              expected to be already validated!
      \param  $username   One of 'd', 'a', ...
      \return $success    True if success; false otherwise
    */
    public function deleteUser($username) {
        if ( $username == 'admin' ) {
            return false;
        }
        $query = "DELETE FROM username WHERE name = '".$username."'";
        $result = $this->execute($query);
        if ($result) {
            // delete user's settings
            $query = "DELETE FROM parameter WHERE owner = '".$username."'";
            $this->execute($query);
            $query = "DELETE FROM parameter_setting WHERE owner = '".$username."'";
            $this->execute($query);
            $query = "DELETE FROM task_parameter WHERE owner = '".$username."'";
            $this->execute($query);
            $query = "DELETE FROM task_setting WHERE owner = '".$username."'";
            $this->execute($query);
            return true;
        } else {
            return false;
        }
    }

    /*!
      \brief  Returns the password of a given user name
      \param  $name   Name of the user
      \return password for the requested user
    */
    public function passwordQueryString($name) {
        $string = "select password from username where name='" . $name . "'";
        return $string;
    }

    /*!
      \brief  Returns the e-mail address of a given user name
      \param  $username   Name of the user
      \return e-mail address for the requested user
    */
    public function emailAddress($username) {
        $query = "select email from username where name = '" . $username . "'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /*!
      \brief  Saves the parameter values of the setting object into the database.
              If the setting already exists, the old values are overwritten,
              otherwise a new setting is created
      \param  $settings   Settings object to be saved
      \return true if saving was successful, false otherwise
    */
    public function saveParameterSettings($settings) {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        $settingTable = $settings->table();
        $table = $settings->parameterTable();
        if ($settings->isDefault())
            $standard = "t";
        else
            $standard = "f";
        $result = True;
        if (!$this->existsSetting($settings)) {
            $query  = "insert into $settingTable values ('" . $user."', '";
            $query .= $name . "', '" .$standard . "')";
            $result = $result && $this->execute($query);
        }
        $existsAlready = $this->existsParametersFor($settings);

        foreach ($settings->parameterNames() as $parameterName) {
            $parameter = $settings->parameter($parameterName);
            $parameterValue = $parameter->internalValue();

            if (is_array($parameterValue)) {
                /*! Before, # was used as a separator, but the first element
                  with index zero was always NULL because channels started
                  their indexing at one. To keep backwards compatibility with
                  the database, we use # now as a channel marker, and even the
                  first channel has a # in front of its value "/" separator is
                  used to mark range values for signal to noise ratio.
                */

                /*!
                  \todo Currently there are not longer "range values" (values
                  separated by /). In the future they will be reintroduced.
                  We leave the code in place.
                */
                if (is_array($parameterValue[0])) {
                    $maxChanCnt = $this->getMaxChanCnt();
                    for ($i = 0; $i < $maxChanCnt; $i++) {
                        if ($parameterValue[$i] != null) {
                            $parameterValue[$i] =
                                implode("/", array_filter($parameterValue[$i]));
                        }
                    }
                }
                $parameterValue = "#".implode("#", $parameterValue);
            }

            if (!$existsAlready) {
                $query  = "INSERT INTO $table VALUES ('" . $user . "', '";
                $query .= $name . "', '" . $parameterName . "', '";
                $query .= $parameterValue . "')";
            } else {
                /* Check that the parameter itself exists. */
                $query  = "SELECT name FROM $table WHERE owner='" . $user;
                $query .= "' AND setting='" . $name;
                $query .= "' AND name='" . $parameterName . "' LIMIT 1";
                $newValue = $this->queryLastValue($query);

                if ( $newValue != NULL ) {
                    $query  = "UPDATE $table SET value = '" . $parameterValue;
                    $query .= "' WHERE owner='" . $user;
                    $query .= "' AND setting='" . $name;
                    $query .= "' AND name='" . $parameterName . "'";
                } else {
                    $query  = "INSERT INTO $table VALUES ('" . $user;
                    $query .= "', '" . $name . "', '" . $parameterName;
                    $query .= "', '" . $parameterValue . "')";
                }
            }

            $result &= $this->execute($query);
        }


        return $result;
    }

    /*!
  \brief    Saves the parameter values of the setting object into the database.
          If the setting already exists, the old values are overwritten,
          otherwise a new setting is created
  \param    $settings   Settings object to be saved
  \param  $isShare    Boolean (default = False): True if the setting is to
                      be saved to the share table, False if it is a standard
                      save.
  \return   true if saving was successful, false otherwise
*/
    public function saveSharedParameterSettings($settings, $targetUserName) {
        $owner = $settings->owner();
        $original_user = $owner->name();
        $name = $settings->name();
        $new_owner = new User();
        $new_owner->setName($targetUserName);
        $settings->setOwner($new_owner);
        $settingTable = $settings->sharedTable();
        $table = $settings->sharedParameterTable();
        $result = True;
        if (!$this->existsSharedSetting($settings)) {
            $query = "insert into $settingTable " .
                "(owner, previous_owner, sharing_date, name) values " .
                "('$targetUserName', '$original_user', CURRENT_TIMESTAMP, '$name')";
            $result = $result && $this->execute($query);
        }

        if (!$result) {
            return False;
        }

        // Get the Id
        $query = "select id from $settingTable where " .
            "owner='$targetUserName' " .
            "AND previous_owner='$original_user' " .
            "AND name='$name'";
        $id = $this->queryLastValue($query);
        if (! $id) {
            return False;
        }

        // Get the parameter names
        $parameterNames = $settings->parameterNames();

        // Add the parameters
        foreach ($parameterNames as $parameterName) {

            $parameter = $settings->parameter($parameterName);
            $parameterValue = $parameter->internalValue();

            if (is_array($parameterValue)) {
                // Before, # was used as a separator, but the first element with
                // index zero was always NULL because channels started their indexing
                // at one. To keep backwards compatibility with the database, we use
                // # now as a channel marker, and even the first channel has a # in
                // front of its value.
                // "/" separator is used to mark range values for signal to noise ratio


                // Special treatment for the PSF parameter.
                if ($parameter->name() == "PSF") {

                    // Create hard links and update paths to the PSF files
                    // to point to the hard-links.
                    $fileServer = new Fileserver($original_user);
                    $parameterValue = $fileServer->createHardLinksToSharedPSFs(
                        $parameterValue, $targetUserName);

                }

                /*!
                  \todo Currently there are not longer "range values" (values
                  separated by /). In the future they will be reintroduced.
                  We leave the code in place.
                */
                if (is_array($parameterValue[0])) {
                    $maxChanCnt = $this->getMaxChanCnt();
                    for ($i = 0; $i < $maxChanCnt; $i++) {
                        if ($parameterValue[$i] != null) {
                            $parameterValue[$i] = implode("/", array_filter($parameterValue[$i]));
                        }
                    }
                }
                $parameterValue = "#".implode("#", $parameterValue);
            }

            $query = "insert into $table " .
                "(setting_id, owner, setting, name, value) " .
                "values ('$id', '$targetUserName', '$name', " .
                "'$parameterName', '$parameterValue')";
            $result = $result && $this->execute($query);
        }

        return $result;
    }

    /*!
      \brief    Loads the parameter values for a setting and returns a copy of
              the setting with the loaded parameter values. If a value starts
              with # it is considered to be an array with the first value at
              the index 0
      \param    $settings   Setting object to be loaded
      \return   $settings object with loaded values
    */
    public function loadParameterSettings($settings) {
        $user = $settings->owner();
        $user = $user->name();
        $name = $settings->name();
        $table = $settings->parameterTable();

        foreach ($settings->parameterNames() as $parameterName) {
            $parameter = $settings->parameter($parameterName);
            $query  = "SELECT value FROM $table WHERE owner='" . $user;
            $query .= "' AND setting='" . $name . "' AND name='";
            $query .= $parameterName . "'";
            $newValue = $this->queryLastValue($query);

            if ($newValue == NULL) {

                // See if the Parameter has a usable default
                $newValue = $parameter->defaultValue( );
                if ($newValue == NULL) {
                    continue;
                }
            }

            if ($newValue{0}=='#') {
                switch($parameterName) {
                case "ExcitationWavelength":
                case "EmissionWavelength":
                case "PinholeSize":
                case "PinholeSpacing":
                case "SignalNoiseRatio":
                case "BackgroundOffsetPercent":
                case "ChromaticAberration":
                case "StedDepletionMode":
                case "StedWavelength":
                case "StedSaturationFactor":
                case "StedImmunity":
                case "Sted3D":
                case "SpimExcMode":
                case "SpimGaussWidth":
                case "SpimCenterOffset":
                case "SpimFocusOffset":
                case "SpimNA":
                case "SpimFill":
                case "SpimDir":
                    /* Extract and continue to explode. */
                    $newValue = substr($newValue,1);
                default:
                    $newValues = explode("#", $newValue);
                }

                if (strcmp( $parameterName, "PSF" ) != 0
                    && strpos($newValue, "/")) {
                    $newValue = array();
                    for ($i = 0; $i < count($newValues); $i++) {
                        if (strpos($newValues[$i], "/")) {
                            $newValue[] = explode("/", $newValues[$i]);
                        }
                        else {
                            $newValue[] = array($newValues[$i]);
                        }
                    }
                }
                else {
                    $newValue = $newValues;
                }
            }

            $parameter->setValue($newValue);
            $settings->set($parameter);
        }

        return $settings;
    }

    /*!
      \brief    Loads the parameter values for a setting and returns a copy of
              the setting with the loaded parameter values. If a value starts
              with # it is considered to be an array with the first value at
              the index 0
      \param    $id Setting id
      \param    $id Setting id
      \return   $settings object with loaded values
    */
    public function loadSharedParameterSettings($id, $type) {

        // Get the correct objects
        switch ($type) {

            case "parameter":

                $settingTable = ParameterSetting::sharedTable();
                $table = ParameterSetting::sharedParameterTable();
                $settings = new ParameterSetting();
                break;

            case "task":

                $settingTable = TaskSetting::sharedTable();
                $table = TaskSetting::sharedParameterTable();
                $settings = new TaskSetting();
                break;

            case "analysis":

                $settingTable = AnalysisSetting::sharedTable();
                $table = AnalysisSetting::sharedParameterTable();
                $settings = new AnalysisSetting();
                break;

            default:

                throw new Exception("bad value for type!");
        }

        // Get the setting info
        $query = "select * from $settingTable where id=$id;";
        $response = $this->queryLastRow($query);
        if (!$response) {
            return NULL;
        }

        // Fill the setting
        $settings->setName($response["name"]);
        $user = new User();
        $user->setName($response["owner"]);
        $settings->setOwner($user);

        // Load from shared table
        foreach ($settings->parameterNames() as $parameterName) {
            $parameter = $settings->parameter($parameterName);
            $query = "select value from $table where setting_id=$id and name='$parameterName'";
            $newValue = $this->queryLastValue($query);
            if ($newValue == NULL) {
                // See if the Parameter has a usable default
                $newValue = $parameter->defaultValue( );
                if ($newValue == NULL) {
                    continue;
                }
            }
            if ($newValue{0}=='#') {
                switch($parameterName) {
                case "ExcitationWavelength":
                case "EmissionWavelength":
                case "SignalNoiseRatio":
                case "BackgroundOffsetPercent":
                case "ChromaticAberration":
                    /* Extract and continue to explode. */
                    $newValue = substr($newValue,1);
                default: 
                    $newValues = explode("#", $newValue);
                }
                
                if (strcmp( $parameterName, "PSF" ) != 0 && strpos($newValue, "/")) {
                    $newValue = array();
                    for ($i = 0; $i < count($newValues); $i++) {
                        //$val = explode("/", $newValues[$i]);
                        //$range = array(NULL, NULL, NULL, NULL);
                        //for ($j = 0; $j < count($val); $j++) {
                        //  $range[$j] = $val[$j];
                        //}
                        //$newValue[] = $range;
                        /*!
                          \todo Currently there are not longer "range values" (values
                                separated by /). In the future they will be reintroduced.
                                We leave the code in place.
                        */
                        if (strpos($newValues[$i], "/")) {
                            $newValue[] = explode("/", $newValues[$i]);
                        }
                        else {
                            $newValue[] = array($newValues[$i]);
                        }
                    }
                }
                else {
                    $newValue = $newValues;
                }
            }
            //$shiftedNewValue = array(1 => NULL, 2 => NULL, 3 => NULL, 4 => NULL, 5 => NULL);
            //if (is_array($newValue)) {
            //  // start array at 1
            //  for ($i = 1; $i <= count($newValue); $i++) {
            //    $shiftedNewValue[$i] = $newValue[$i - 1];
            //  }
            //}
            //else $shiftedNewValue = $newValue;
            $parameter->setValue($newValue);
            $settings->set($parameter);
        }
        return $settings;
    }

    /*!
      \brief    Returns the list of shared templates with the given user.
      \param    $username   Name of the user for whom to query for shared templates
      \param    $table      Shared table to query
      \return   list of shared jobs
    */
    public function getTemplatesSharedWith($username, $table) {
        $query = "SELECT * FROM $table WHERE owner='$username'";
        $result = $this->query($query);
        return $result;
    }

    /*!
      \brief    Returns the list of shared templates by the given user.
      \param    $username   Name of the user for whom to query for shared templates
      \param    $table      Shared table to query
      \return   list of shared jobs
    */
    public function getTemplatesSharedBy($username, $table) {
        $query = "SELECT * FROM $table WHERE previous_owner='$username'";
        $result = $this->query($query);
        return $result;
    }

    /*!
      \brief    Copies the relevant rows from shared- to user- tables.
      \param    $id          ID of the setting to be copied
      \param    $sourceSettingTable Setting table to copy from
      \param    $sourceParameterTable Parameter table to copy from
      \param    $destSettingTable Setting table to copy to
      \param    $destParameterTable Parameter table to copy to
      \return   True if copying was successful; false otherwise.
    */
    public function copySharedTemplate($id, $sourceSettingTable,
                                       $sourceParameterTable, $destSettingTable, $destParameterTable) {

        // Get the name of the previous owner (the one sharing the setting).
        $query = "select previous_owner, owner, name from $sourceSettingTable where id=$id";
        $rows = $this->queryLastRow($query);
        if (False === $rows) {
            return False;
        }
        $previous_owner = $rows["previous_owner"];
        $owner = $rows["owner"];
        $setting_name = $rows["name"];

        // Compose the new name of the setting
        $out_setting_name = $previous_owner  . "_" . $setting_name;

        // Check if a setting with this name already exists in the target tables
        $query = "select name from $destSettingTable where " .
            "name='$out_setting_name' and owner='$owner'";
        if ($this->queryLastValue($query)) {

            // The setting already exists; we try adding numerical indices
            $n = 1; $original_out_setting_name = $out_setting_name;
            while (1) {

                $test_name = $original_out_setting_name . "_" . $n++;
                $query = "select name from $destSettingTable where name='$test_name' and owner='$owner'";
                if (! $this->queryLastValue($query)) {
                    $out_setting_name = $test_name;
                    break;
                }
            }

        }

        // Get all rows from source table for given setting id
        $query = "select * from $sourceParameterTable where setting_id=$id";
        $rows = $this->query($query);
        if (count($rows) == 0) {
            return False;
        }

        // Now add the rows to the destination table
        $ok = True;
        $record = array();
        $this->connection->BeginTrans();
        foreach ($rows as $row) {
            $record["owner"] = $row["owner"];
            $record["setting"] = $out_setting_name;
            $record["name"] = $row["name"];

            // PSF files must be processed differently
            if ($record["name"] == "PSF") {

                // Instantiate a Fileserver object for the target user
                $fileserver = new Fileserver($owner);

                // Get the array of PSF names
                $values = $row["value"];
                if ($values[0] == "#") {
                    $values = substr($values, 1);
                }
                $psfFiles = explode('#', $values);

                // Create hard-links to the target user folder
                $newPSFFiles = $fileserver->createHardLinksFromSharedPSFs(
                    $psfFiles, $owner, $previous_owner);

                // Update the entries for the database
                $record["value"] = "#" . implode('#', $newPSFFiles);

            } else {

                $record["value"] = $row["value"];

            }

            $insertSQL = $this->connection->GetInsertSQL($destParameterTable,
                $record);
            $status = $this->connection->Execute($insertSQL);
            $ok &= !(false === $status);
            if (! $ok) {
                break;
            }
        }

        // If everything went okay, we commit the transaction; otherwise we roll
        // back
        if ($ok) {
            $this->connection->CommitTrans();
        } else {
            $this->connection->RollbackTrans();
            return False;
        }

        // Now add the setting to the setting table
        $query = "select * from $sourceSettingTable where id=$id";
        $rows = $this->query($query);
        if (count($rows) != 1) {
            return False;
        }

        $ok = True;
        $this->connection->BeginTrans();
        $record = array();
        $row = $rows[0];
        $record["owner"] = $row["owner"];
        $record["name"] = $out_setting_name;
        $record["standard"] = 'f';
        $insertSQL = $this->connection->GetInsertSQL($destSettingTable,
            $record);
        $status = $this->connection->Execute($insertSQL);
        $ok &= !(false === $status);

        if ($ok) {
            $this->connection->CommitTrans();
        } else {
            $this->connection->RollbackTrans();
            return False;
        }

        // Now we can delete the records from the source tables. Even if it
        // if it fails we do not roll back, since the parameters were copied
        // successfully.

        // Delete setting entry
        $query = "delete from $sourceSettingTable where id=$id";
        $status = $this->connection->Execute($query);
        if (false === $status) {
            return False;
        }

        // Delete parameter entries
        $query = "delete from $sourceParameterTable where setting_id=$id";
        $status = $this->connection->Execute($query);
        if (false === $status) {
            return False;
        }

        return True;
    }

    /*!
      \brief    Delete the relevant rows from the shared tables.
      \param    $id          ID of the setting to be deleted
      \param    $sourceSettingTable Setting table to copy from
      \param    $sourceParameterTable Parameter table to copy from
      \return   True if deleting was successful; false otherwise.
    */
    public function deleteSharedTemplate($id, $sourceSettingTable,
                                         $sourceParameterTable) {

        // Initialize success
        $ok = True;

        // Delete shared PSF files if any exist
        if ($sourceParameterTable == "shared_parameter") {
            $query = "select value from $sourceParameterTable where setting_id=$id and name='PSF'";
            $psfFiles = $this->queryLastValue($query);
            if (NULL != $psfFiles && $psfFiles != "#####") {
                if ($psfFiles[0] == "#") {
                    $psfFiles = substr($psfFiles, 1);
                }

                // Extract PSF file paths from the string
                $psfFiles = explode("#", $psfFiles);

                // Delete them
                Fileserver::deleteSharedFSPFilesFromBuffer($psfFiles);
            }
        }

        // Delete setting entry
        $query = "delete from $sourceSettingTable where id=$id";
        $status = $this->connection->Execute($query);
        $ok &= !(false === $status);

        // Delete parameter entries
        $query = "delete from $sourceParameterTable where setting_id=$id";
        $status = $this->connection->Execute($query);
        $ok &= !(false === $status);

        return $ok;
    }

    /*!
      \brief  Updates the default entry in the database according to the default
              value in the setting
      \param  $settings   Settings object to be used to update the default
      \return query result
    */
    public function updateDefault($settings) {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        if ($settings->isDefault())
            $standard = "t";
        else
            $standard = "f";
        $table = $settings->table();
        $query = "update $table set standard = '" . $standard . "' where owner='" . $user . "' and name='" . $name . "'";
        $result = $this->execute($query);
        return $result;
    }

    /*!
      \brief  Deletes the setting and all its parameter values from the database
      \param  $settings   Settings object to be used to delete all entries from
                          the database
      \return true if the setting and all parameters were deleted from the
              database; false otherwise
    */
    public function deleteSetting($settings) {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        $result = True;
        $table = $settings->parameterTable();
        $query = "delete from $table where owner='" . $user . "' and setting='" . $name ."'";
        $result = $result && $this->execute($query);
        if (!$result) {
            return FALSE;
        }
        $table = $settings->table();
        $query = "delete from $table where owner='" . $user . "' and name='" . $name ."'";
        $result = $result && $this->execute($query);
        return $result;
    }

    /*!
      \brief  Checks whether parameters are already stored for a given setting
      \param  $settings   Settings object to be used to check for existance in
                          the database
      \return true if the parameters exist in the database; false otherwise
    */
    public function existsParametersFor($settings) {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        $table = $settings->parameterTable();
        $query = "select name from $table where owner='" . $user . "' and setting='" . $name ."' LIMIT 1";
        $result = True;
        if (!$this->queryLastValue($query)) {
            $result = False;
        }
        return $result;
    }

    /*!
      \brief    Checks whether parameters are already stored for a given shared
                setting
      \param    $settings   Settings object to be used to check for existence in
                          the database
      \return   true if the parameters exist in the database; false otherwise
    */
    public function existsSharedParametersFor($settings) {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        $table = $settings->sharedParameterTable();
        $query = "select name from $table where owner='" . $user . "' and setting='" . $name ."' LIMIT 1";
        $result = True;
        if (!$this->queryLastValue($query)) {
            $result = False;
        }
        return $result;
    }

    /*!
      \brief  Checks whether settings exist in the database for a given owner
      \param  $settings   Settings object to be used to check for existance in
                          the database (the name of the owner must be set in the
                          settings)
      \return true if the settings exist in the database; false otherwise
    */
    public function existsSetting($settings) {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        $table = $settings->table();
        $query = "select standard from $table where owner='" . $user . "' and name='" . $name ."' LIMIT 1";
        $result = True;
        if (!$this->queryLastValue($query)) {
            $result = False;
        }
        return $result;
    }

    /*!
      \brief    Checks whether settings exist in the database for a given owner
      \param    $settings   Settings object to be used to check for existence in
                          the database (the name of the owner must be set in the
                          settings)
      \return   true if the settings exist in the database; false otherwise
    */
    public function existsSharedSetting($settings) {
        $owner = $settings->owner();
        $user = $owner->name();
        $name = $settings->name();
        $table = $settings->sharedTable();
        $query = "select standard from $table where owner='" . $user . "' and name='" . $name ."' LIMIT 1";
        $result = True;
        if (!$this->queryLastValue($query)) {
            $result = False;
        }
        return $result;
    }

    /*!
      \brief  Adds all files for a given job id and user to the database
      \param  $id     Job id
      \param  $owner  Name of the user that owns the job
      \param  $files  Array of file names
      \return true if the job files could be saved successfully; false otherwise
    */
    public function saveJobFiles($id, $owner, $files, $autoseries) {
        $result = True;
        $username = $owner->name();
        $sqlAutoSeries = "";
        foreach ($files as $file) {
            if (strcasecmp($autoseries, "TRUE") == 0 || strcasecmp($autoseries, "T") == 0) {
                $sqlAutoSeries = "T";
            }
            $query = "insert into job_files values ('" . $id ."', '" . $username ."', '" . addslashes($file) . "', '" . $sqlAutoSeries . "')";
            $result = $result && $this->execute($query);
        }
        return $result;
    }

    /*!
      \brief  Adds a job for a given job id and user to the database
      \param  $id         Job id
      \param  $username   Name of the user that owns the job
      \return query result
    */
    public function queueJob($id, $username) {
        $query = "insert into job_queue (id, username, queued, status) values ('" .$id . "', '" . $username . "', NOW(), 'queued')";
        return $this->execute($query);
    }

    /*!
      \brief  Assigns priorities to the jobs in the queue
      \return true if assigning priorities was successful
    */
    public function setJobPriorities( ) {

        $result = True;

        ////////////////////////////////////////////////////////////////////////////
        //
        // First we analyze the queue
        //
        ////////////////////////////////////////////////////////////////////////////

        // Get the number of users that currently have jobs in the queue
        $users    = $this->execute( "SELECT DISTINCT( username ) FROM job_queue;" );
        $row      = $this->execute( "SELECT COUNT( DISTINCT( username ) ) FROM job_queue;" )->FetchRow( );
        $numUsers = $row[ 0 ];

        // 'Highest' priority (i.e. lowest value) is 0
        $currentPriority = 0;

        // First, we make sure to give the highest priorities to paused and
        // broken jobs
        $rs = $this->execute( "SELECT id FROM job_queue WHERE status = 'broken' OR status = 'paused';" );
        if ( $rs ) {
            while ( $row = $rs->FetchRow( ) ) {

                // Update the priority for current job id
                $query = "UPDATE job_queue SET priority = " . $currentPriority++ .
                    " WHERE id = '" . $row[ 0 ] . "';";

                $rs = $this->execute( $query );
                if ( !$rs ) {
                    error_log( "Could not update priority for key " . $row[ 0 ] );
                    $result = False;
                    return $result;
                }

            }
        }

        // Then, we go through to running jobs
        $rs = $this->execute( "SELECT id FROM job_queue WHERE status = 'started';" );
        if ( $rs ) {
            while ( $row = $rs->FetchRow( ) ) {

                // Update the priority for current job id
                $query = "UPDATE job_queue SET priority = " . $currentPriority++ .
                    " WHERE id = '" . $row[ 0 ] . "';";

                $rs = $this->execute( $query );
                if ( !$rs ) {
                    error_log( "Could not update priority for key " . $row[ 0 ] );
                    $result = False;
                    return $result;
                }
            }
        }

        // Then we organize the queued jobs in a way that lets us then assign
        // priorities easily in a second pass
        $numJobsPerUser = array( );
        $userJobs = array( );
        for ( $i = 0; $i < $numUsers; $i++ ) {
            // Get current username
            $row = $users->FetchRow( );
            $username = $row[ 0 ];
            $query = "SELECT id
        FROM job_queue, job_files
        WHERE job_queue.id = job_files.job AND
          job_queue.username = job_files.owner AND
          job_queue.username = '" . $username . "' AND
          status = 'queued'
        ORDER BY job_queue.queued asc, job_files.file asc";
            $rs = $this->execute( $query );
            if ( $rs ) {
                $userJobs[ $i ] = array( );
                $counter = 0;
                while ( $row = $rs->FetchRow( ) ) {
                    $userJobs[ $i ][ $counter++ ] = $row[ 0 ];
                }
                $numJobsPerUser[ $i ] = $counter;
            }
        }

        // Now we can assign priorities to the queued jobs -- minimum priority is 1
        // above the priorities assigned to all other types of jobs
        $maxNumJobs = max( $numJobsPerUser );
        for ( $j = 0; $j < $maxNumJobs; $j++ ) {
            for ( $i = 0; $i < $numUsers; $i++ ) {
                if ( $j < count( $userJobs[ $i ] ) ) {
                    // Update the priority for current job id
                    $query = "UPDATE job_queue SET priority = " .
                        $currentPriority ." WHERE id = '" .
                        $userJobs[ $i ][ $j ] . "';";

                    $rs = $this->execute( $query );
                    if ( !$rs ) {
                        error_log( "Could not update priority for key " . $userJobs[ $i ][ $j ] );
                        $result = False;
                        return $result;
                    }
                    $currentPriority++;
                }
            }
        }

        // We can now return true
        return $result;
    }

    /*!
      \brief  Logs job information in the statistics table
      \param  $job        Job object whose information is to be logged in the
                          database
      \param  $startTime  Job start time
      \return void
    */
    public function updateStatistics($job, $startTime) {

        $desc = $job->description();
        $parameterSetting = $desc->parameterSetting();
        $taskSetting      = $desc->taskSetting();
        $analysisSetting  = $desc->analysisSetting();

        $stopTime = date("Y-m-d H:i:s");
        $id       = $desc->id();
        $user     = $desc->owner();
        $owner    = $user->name();
        $group    = $user->userGroup($owner);

        $parameter      = $parameterSetting->parameter('ImageFileFormat');
        $inFormat       = $parameter->value();
        $parameter      = $parameterSetting->parameter('PointSpreadFunction');
        $PSF            = $parameter->value();
        $parameter      = $parameterSetting->parameter('MicroscopeType');
        $microscope     = $parameter->value();
        $parameter      = $taskSetting->parameter('OutputFileFormat');
        $outFormat      = $parameter->value();
        $parameter      = $analysisSetting->parameter('ColocAnalysis');
        $colocAnalysis  = $parameter->value();

        $query = "insert into statistics values ('" . $id ."', '" . $owner ."', '" .
            $group . "','" . $startTime . "', '" . $stopTime . "', '" . $inFormat .
            "', '" . $outFormat . "', '" . $PSF . "', '" .
            $microscope . "', '" . $colocAnalysis . "')";

        $this->execute($query);

    }

    /*!
      \brief  Flattens a multi-dimensional array
      \param  $anArray    Multi-dimensional array
      \return flattened array
    */
    public function flatten($anArray) {
        $result = array();
        foreach ($anArray as $row) {
            $result[] = end($row);
        }
        return $result;
    }

    /*!
      \brief  Returns the possible values for a given parameter
      \param  $parameter  Parameter object
      \return Flattened array of possible values
    */
    public function readPossibleValues($parameter) {
        $name = $parameter->name();
        $query = "select value from possible_values where parameter = '" .$name . "'";
        $answer = $this->query($query);
        $result = $this->flatten($answer);
        return $result;
    }

    /*!
      \brief  Returns the translated possible values for a given parameter
      \param  $parameter  Parameter object
      \return Flattened array of translated possible values
    */
    public function readTranslatedPossibleValues($parameter) {
        $name = $parameter->name();
        $query = "select translation from possible_values where parameter = '" .$name . "'";
        $answer = $this->query($query);
        $result = $this->flatten($answer);
        return $result;
    }

    /*!
      \brief  Returns the translation of current value for a given parameter
      \param  $parameterName  Name of the Parameter object
      \param  $value          Value for which a thanslation should be returned
      \return Translated value
    */
    public function translationFor($parameterName, $value) {
        $query = "select translation from possible_values where parameter = '" .$parameterName . "' and value = '" . $value . "'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /*!
  \brief  Returns the translation of a hucore value
  \param  $parameterName  Name of the Parameter object
  \param  $hucorevalue          value name in HuCore
  \return Expected value by HRM
*/
    public function hucoreTranslation($parameterName, $hucorevalue) {
        $query = "select value from possible_values where parameter = '" .$parameterName . "' and translation = '" . $hucorevalue . "'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /*!
      \brief  Returns an array of all file extensions
      \return Array of file extensions
    */
    public function allFileExtensions( ) {
        $query = "select distinct extension from file_extension";
        $answer = $this->query($query);
        $result = $this->flatten($answer);
        return $result;
    }

    /*!
      \brief  Returns an array of all extensions for multi-dataset files
      \return Array of file extensions for multi-dataset files
    */
    public function allMultiFileExtensions( ) {
        $query = "SELECT name FROM file_format, file_extension
        WHERE file_format.name = file_extension.file_format
        AND file_format.ismultifile LIKE 't'";
        $answer = $this->query($query);
        $result = $this->flatten($answer);
        return $result;
    }

    /*!
      \brief  Returns an array of file extensions associated to a given file format
      \param  $imageFormat    File format
      \return Array of file extensions
    */
    public function fileExtensions($imageFormat) {
        $query = "select distinct extension from file_extension where file_format = '" . $imageFormat . "'";
        $answer = $this->query($query);
        $result = $this->flatten($answer);
        return $result;
    }

    /*!
      \brief  Returns all restrictions for a given numerical parameter
      \param  $parameter  Parameter (object)
      \return Array of restrictions
    */
    public function readNumericalValueRestrictions($parameter) {
        $name = $parameter->name();
        $query = "select min, max, min_included, max_included, standard from boundary_values where parameter = '" .$name . "'";
        $result = $this->queryLastRow($query);
        if ( !$result ) {
            $result = array( null, null, null, null, null );
        }
        return $result;
    }

    /*!
      \brief  Returns the file formats that fit the conditions expressed by the
              parameters.
      \param  $isSingleChannel    Set whether the file format must be single
                                  channel (True), multi channel (False) or if
                                  it doesn't matter (NULL)
      \param  $isVariableChannel  Set whether the number of channels must be
                                  variable (True), fixed (False) or if it
                                  doesn't matter (NULL)
      \param  $isFixedGeometry    Set whether the geometry (xyzt) must be fixed
                                  (True), variable (False) or if it doesn't
                                  matter (NULL).
      \return array of file formats
    */
    public function fileFormatsWith($isSingleChannel, $isVariableChannel, $isFixedGeometry) {
        $isSingleChannelValue = 'f';
        $isVariableChannelValue = 'f';
        $isFixedGeometryValue ='f';
        if ($isSingleChannel) {
            $isSingleChannelValue = 't';
        }
        if ($isVariableChannel) {
            $isVariableChannelValue = 't';
        }
        if ($isFixedGeometry) {
            $isFixedGeometryValue = 't';
        }
        $conditions = array();
        if ($isSingleChannel!=NULL) {
            $conditions['isSingleChannel'] = $isSingleChannelValue;
        }
        if ($isVariableChannel!=NULL) {
            $conditions['isVariableChannel'] = $isVariableChannelValue;
        }
        if ($isFixedGeometry!=NULL) {
            $conditions['isFixedGeometry'] = $isFixedGeometryValue;
        }
        return $this->retrieveColumnFromTableWhere('name', 'file_format', $conditions);
    }

    /*!
      \brief  Returns the geometries (XY, XY-time, XYZ, XYZ-time) fit the
              conditions expressed by the parameters
      \param  $isThreeDimensional True if 3D
      \param  $isTimeSeries       True if time-series
      \return array of geometries
    */
    public function geometriesWith($isThreeDimensional, $isTimeSeries) {
        $isThreeDimensionalValue = 'f';
        $isTimeSeriesValue = 'f';
        if ($isThreeDimensional) {
            $isThreeDimensionalValue = 't';
        }
        if ($isTimeSeries) {
            $isTimeSeriesValue = 't';
        }
        $conditions = array();
        if ($isThreeDimensional!=NULL) {
            $conditions['isThreeDimensional'] = $isThreeDimensionalValue;
        }
        if ($isTimeSeries!=NULL) {
            $conditions['isTimeSeries'] = $isTimeSeriesValue;
        }
        return $this->retrieveColumnFromTableWhere("name", "geometry", $conditions);
    }

    /*!
      \brief  Return all values from the column from the table where the condition
              evaluates to true
      \param    $column       Name of the column from which the values are taken
      \param    $table        Name of the table from which the values are taken
      \param    $conditions   Array of conditions that the result values must fullfil.
                              This is an array with column names as indices and
                              boolean values as content.
      \return array of values
    */
    public function retrieveColumnFromTableWhere($column, $table, $conditions) {
        $query = "select distinct $column from $table where ";
        foreach ($conditions as $eachName => $eachValue) {
            $query = $query . $eachName . " = '" . $eachValue . "' and ";
        }
        $query = $query . "1 = 1";
        $answer = $this->query($query);
        $result = array();

        if ( !empty($answer) ) {
            foreach ($answer as $row) {
                $result[] = end($row);
            }
        }

        return $result;
    }

    /*!
      \brief  Returns the default value for a given parameter
      \param  $parameterName  Name of the parameter
      \return Default value
    */
    public function defaultValue($parameterName) {
        $query = "SELECT value FROM possible_values WHERE parameter='";
        $query .= $parameterName . "' AND isDefault='t'";
        $result = $this->queryLastValue($query);                
        if ($result === False) {
            return NULL;
        }
        
        return $result;
    }

    /*!
      \brief  Returns the id for next job from the queue, sorted by priority
      \return Job id
    */
    public function getNextIdFromQueue() {
        // For the query we join job_queue and job_files, since we want to sort also by file name
        $query = "SELECT id
    FROM job_queue, job_files
    WHERE job_queue.id = job_files.job AND job_queue.username = job_files.owner
    AND job_queue.status = 'queued'
    ORDER BY job_queue.priority desc, job_queue.status desc, job_files.file desc";
        $result = $this->queryLastValue($query);
        if (!$result) {
            return NULL;
        }
        return $result;
    }

    /*!
      \brief  Returns all jobs from the queue, both compound and simple,
              ordered by priority
      \return all jobs
    */
    public function getQueueJobs() {
        // Get jobs as they are in the queue, compound or not, without splitting
        // them.
        $query = "SELECT id, username, queued, start, server, process_info, status
    FROM job_queue
    ORDER BY job_queue.priority asc, job_queue.queued asc, job_queue.status asc";
        $result = $this->query($query);
        return $result;
    }

    /*!
      \brief  Returns all jobs from the queue, both compund and simple,
              and the associated file names, ordered by priority
      \return all jobs
    */
    public function getQueueContents() {
        // For the query we join job_queue and job_files, since we want to sort also by file name
        $query = "SELECT id, username, queued, start, stop, server, process_info, status, file
    FROM job_queue, job_files
    WHERE job_queue.id = job_files.job AND job_queue.username = job_files.owner
    ORDER BY job_queue.priority asc, job_queue.queued asc, job_queue.status asc, job_files.file asc
    LIMIT 100";
        $result = $this->query($query);
        return $result;
    }

    /*!
      \brief  Returns all jobs from the queue for a given id (that must be
              univocal!)
      \param  $id String  Id of the job
      \return all jobs for the id
    */
    public function getQueueContentsForId($id) {
        $query = "select id, username, queued, start, server, process_info, status from job_queue where id='" . $id . "'";
        $result = $this->queryLastRow($query);  // it is supposed that just one job exists with a given id
        return $result;
    }

    /*!
      \brief  Returns all file names associated to a job with given id
      \param  $id Job id
      \return array of file names
    */
    public function getJobFilesFor($id) {
        $query = "select file from job_files where job = '" . $id . "'";
        $result = $this->query($query);
        $result = $this->flatten($result);
        return $result;
    }

    /*!
      \brief  Returns the file series mode of a job with given id
      \param  $id Job id
      \return true or false
    */
    public function getSeriesModeForId($id) {
        $query = "select autoseries from job_files where job = '" . $id . "'";
        $result = $this->queryLastValue($query);

        return $result;
    }

    /*!
      \brief  Returns the number of jobs currently in the queue for a
              given username
      \param  $username   Name of the user
      \return number of jobs in queue
    */
    public function getNumberOfQueuedJobsForUser($username) {
        $query = "SELECT COUNT(id) FROM job_queue WHERE username = '" . $username . "';";
        $row = $this->Execute( $query )->FetchRow( );
        return $row[ 0 ];
    }

    /*!
      \brief  Returns the total number of jobs currently in the queue
      \return total number of jobs in queue
    */
    public function getTotalNumberOfQueuedJobs() {
        $query = "SELECT COUNT(id) FROM job_queue;";
        $row = $this->Execute( $query )->FetchRow( );
        return $row[ 0 ];
    }

    /*!
      \brief  Returns the name of the user who created the job with given id
      \param  $id String  id of the job
      \return name of the user
    */
    public function userWhoCreatedJob($id) {
        $query = "select username from job_queue where id = '" . $id . "'";
        $result = $this->queryLastValue($query);
        if (!$result) {
            return NULL;
        }
        return $result;
    }

    /*!
      \brief  Deletes job with specified ID from all job tables
      \param  $id Id of the job
      \return true if success
    */
    public function deleteJobFromTables($id) {
        // TODO: Use foreign keys in the database!
        $result = True;
        $result = $result && $this->execute(
                "delete from job_analysis_parameter where setting='$id'");
        $result = $result && $this->execute(
                "delete from job_analysis_setting where name='$id'");
        $result = $result && $this->execute(
                "delete from job_files where job='$id'");
        $result = $result && $this->execute(
                "delete from job_parameter where setting='$id'");
        $result = $result && $this->execute(
                "delete from job_parameter_setting where name='$id'");
        $result = $result && $this->execute(
                "delete from job_queue where id='$id'");
        $result = $result && $this->execute(
                "delete from job_task_parameter where setting='$id'");
        $result = $result && $this->execute(
                "delete from job_task_setting where name='$id'");
        return $result;
    }

    /*!
      \brief  Returns the path to hucore on given host
      \param  $host   String  Host name
      \return full path to hucore
    */
    // TODO better management of multiple hosts
    function huscriptPathOn($host) {
        $query = "SELECT huscript_path FROM server where name = '" . $host . "'";
        $result = $this->queryLastValue($query);
        if (!$result) {
            return NULL;
        }
        return $result;
    }

    /*!
      \brief  Get the name of a free server
      \return name of the server
    */
    public function freeServer() {
        $query = "select name from server where status='free'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /*!
      \brief  Get the status (i.e. free, busy, paused) of server $name
      \param  $name   Name of the server
      \return status
    */
    public function statusOfServer($name) {
        $query = "select status from server where name='$name'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /*!
      \brief  Checks whether server is busy
      \param  $name     Name of the server
      \return true if the server is busy, false otherwise
    */
    public function isServerBusy($name) {
        $status = $this->statusOfServer($name);
        $result = ($status == 'busy');
        return $result;
    }

    /*!
      \brief  Checks whether the switch in the queue manager is 'on'
      \return true if on
    */
    public function isSwitchOn() {
        // Handle some back-compatibility issue
        if ($this->doGlobalVariablesExist()) {
            $query = "SELECT value FROM queuemanager WHERE field = 'switch'";
            $answer = $this->queryLastValue($query);
            $result = True;
            if ($answer == 'off') {
                $result = False;
                report("$query; returned '$answer'", 1);
                notifyRuntimeError("hrmd stopped",
                    "$query; returned '$answer'\n\nThe HRM queue manage will stop.");
            }
        }
        else {
            $query = "select switch from queuemanager";
            $answer = $this->queryLastValue($query);
            $result = True;
            if ($answer == 'off') {
                $result = False;
                report("$query; returned '$answer'", 1);
                notifyRuntimeError("hrmd stopped",
                    "$query; returned '$answer'\n\nThe HRM queue manage will stop.");
            }
        }

        return $result;
    }

    /*!
      \brief  Gets the status of the queue manager's switch
      \return 'on' or 'off'
    */
    public function getSwitchStatus() {
        if ($this->doGlobalVariablesExist()) {
            $query = "SELECT value FROM queuemanager WHERE field = 'switch'";
            $answer = $this->queryLastValue($query);
        }
        else {
            $query = "select switch from queuemanager";
            $answer = $this->queryLastValue($query);
        }
        return $answer;
    }

    /*!
      \brief  Sets the status of the queue manager's switch
      \param  $status String  Either 'on' or 'off'
      \return query result
    */
    public function setSwitchStatus( $status ) {
        $result = $this->execute("UPDATE queuemanager SET value = '". $status ."' WHERE field = 'switch'");
        return $result;
    }

    /*!
      \brief  Sets the state of the server to 'busy' and the pid for a running job
      \param  $name   String  Server name
      \param  $pid    String  Process identifier associated with a running job
      \return query result
    */
    public function reserveServer($name, $pid) {
        $query = "update server set status='busy', job='$pid' where name='$name'";
        $result = $this->execute($query);
        return $result;
    }

    /*!
      \brief  Sets the state of the server to 'free' and deletes the the pid
      \param  $name   String  Server name
      \param  $pid    Process identifier associated with a running job (UNUSED!)
      \return query result
    */
    public function resetServer($name, $pid) {
        $query = "update server set status='free', job=NULL where name='$name'";
        $result = $this->execute($query);
        return $result;
    }

    /*!
      \brief  Starts a job
      \param  $job    Job object
      \return query result
    */
    public function startJob($job) {
        $desc = $job->description();
        $id = $desc->id();
        $server = $job->server();
        $process_info = $job->pid();
        $query = "update job_queue set start=NOW(), server='$server', process_info='$process_info', status='started' where id='" .$id . "'";
        $result = $this->execute($query);
        return $result;
    }

    /*!
      \brief  Get all running jobs
      \return array of Job objects
    */
    public function getRunningJobs() {
        $result = array();
        $query = "select id, process_info, server from job_queue where status = 'started'";
        $rows = $this->query($query);
        if (!$rows) return $result;

        foreach ($rows as $row) {
            $desc = new JobDescription();
            $desc->setId($row['id']);
            $desc->load();
            $job = new Job($desc);
            $job->setServer($row['server']);
            $job->setPid($row['process_info']);
            $job->setStatus('started');
            $result[] = $job;
        }
        return $result;
    }

    /*!
      \brief  Get names of all processing servers (independent of their status)
      \return array of strings
    */
    public function availableServer() {
        $query = "select name from server";
        $result = $this->query($query);
        $result = $this->flatten($result);
        return $result;
    }

    /*/!
      \brief  Get the starting time of given job object
      \param  $job    Job object
      \return Start time (String)
    */
    public function startTimeOf( Job $job ) {
        $desc = $job->description();
        $id = $desc->id();
        $query = "select start from job_queue where id = '" .$id . "'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /*!---------------------------------------------------------
      \brief  Returns a formatted time from a unix timestamp
      \param  $timestamp  Unix timestamp
      \return formatted time string: YYYY-MM-DD hh:mm:ss
    */
    public function fromUnixTime($timestamp) {
        $query = "select FROM_UNIXTIME($timestamp)";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /*!
      \brief  Pauses a job of given id
      \param  $id Job id
      \return query result
    */
    public function pauseJob($id) {
        $query = "update job_queue set status='paused' where id='" . $id . "'";
        $result = $this->execute($query);
        return $result;
    }

    /*!
      \brief  Sets the end time of a job
      \param  $id     Job id
      \param  $date   Formatted date: YYYY-MM-DD hh:mm:ss
      \return query result
    */
    public function setJobEndTime($id, $date) {
        $query = "update job_queue set stop='".$date."' where id='" . $id . "'";
        $result = $this->execute($query);
        return $result;
    }

    /*!
      \brief  Changes status of 'paused' jobs to 'queued'
      \return query result
    */
    public function restartPausedJobs() {
        $query = "update job_queue set status='queued' where status='paused'";
        $result = $this->execute($query);
        return $result;
    }

    /*!
      \brief  Marks a job with given id as 'broken' (i.e. to be removed)
      \param  $id Job id
      \return query result
    */
    public function markJobAsRemoved($id) {
        $query = "update job_queue set status='broken' where (status='queued' or status='paused') and id='" . $id . "'";
        // $query = "update job_queue set status='broken' where id='" . $id . "'";
        $result = $this->execute($query);
        $query = "update job_queue set status='kill' where status='started' and id='" . $id . "'";
        $result = $this->execute($query);
        return $result;
    }

    /*!
      \brief  Set the server status to free
      \param  $server Server name
      \return query result
    */
    public function markServerAsFree($server) {
        $query = "update server set status='free', job=NULL where name='" . $server . "'";
        $result = $this->execute($query);
        return $result;
    }

    /*!
      \brief  Get all jobs with status 'broken'
      \return array of ids
    */
    public function getMarkedJobIds() {
        $conditions['status'] = 'broken';
        $ids = $this->retrieveColumnFromTableWhere('id', 'job_queue', $conditions);
        return $ids;
    }

    /*!
      \brief  Get all jobs with status 'kill' to be killed by the Queue Manager
      \return array of ids
    */
    public function getJobIdsToKill() {
        $conditions['status'] = 'kill';
        $ids = $this->retrieveColumnFromTableWhere('id', 'job_queue', $conditions);
        return $ids;
    }

    /*!
      \brief  Check whether a user exists
      \param  $name   Name of the user
      \return true if the user exists
    */
    public function checkUser($name) {
        $query = "select status from username where name = '" . $name . "'";
        $result = $this->queryLastValue($query);
        if ($result) $result = true;
        return $result;
    }

    /*!
      \brief  Get the status of a user
      \param  $name   Name of the user
      \return status ('a', 'd', ...)
    */
    public function getUserStatus($name) {
        $query = "select status from username where name = '" . $name . "'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /*!
      \brief  Return the list of known users.
      \param  String User name to filter out from the list (optional).
      \return Array of users.
      */
    public function getUserList($name) {
        $query = "select name from username where name != '" . $name . "' " .
            " and name != 'admin';";
        $result = $this->query($query);
        return $result;
    }

    /*!
      \brief  Get the name of the user who owns a job with given id
      \param  $id Job id
      \return name of the user who owns the job
    */
    public function getJobOwner($id) {
        $query = "select username from job_queue where id = '" . $id . "'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /*!
      \brief  Returns current date and time
      \return formatted date (YYYY-MM-DD hh:mm:ss)
    */
    public function now() {
        $query = "select now()";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /*!
      \brief  Returns the group to which the user belongs
      \param  $userName   Name of the user
      \return group name
    */
    public function getGroup($userName) {
        $query = "SELECT research_group FROM username WHERE name= '" . $userName . "'";
        $result = $this->queryLastValue($query);
        return $result;
    }

    /*!
      \brief  Updates the e-mail address of a user
      \param  $userName   Name of the user
      \param  $email      E-mail address
      \return query result
    */
    public function updateMail($userName, $email) {
        $cmd = "UPDATE username SET email = '". $email ."' WHERE name = '".$userName."'";
        $result = $this->execute($cmd);
        return $result;
    }

    /*!
      \brief  Updates the last access date of a user
      \param  $userName   Name of the user
      \return query result
    */
    public function updateLastAccessDate($userName) {
        $query = "UPDATE username SET last_access_date = CURRENT_TIMESTAMP WHERE name = '". $userName . "'";
        $result = $this->execute($query);
        return $result;
    }


    /*!
      \brief  Gets the maximum number of channels from the database.
      \return The number of channels.
    */
    public function getMaxChanCnt() {
        $query  = "SELECT MAX(value) as \"\" FROM possible_values ";
        $query .= "WHERE parameter='NumberOfChannels'";
        $result = trim($this->execute($query));

        if (!is_numeric($result)) {
            $result = 5;
        }
        
        return $result;
    }
    

    /*!
      \brief  Get the list of Setting's for the User

      The Parameter values are not loaded.

      \return array of Setting's
    */
    public function getSettingList( $username, $table ) {
        $query = "select name, standard from $table where owner ='" . $username . "' order by name";
        return ( $this->query( $query ) );
    }

    /*!
      \brief  Get the parameter confidence level for given file format
      \param  $fileFormat     File format for which the Parameter confidence level is queried
                              (not strictly necessary for the Parameters with confidence level 'Provide',
                              could be set to '' for those)
      \param  $parameterName  Name of the Paramater the confidence level should be returned
      \return parameter confidence level
    */
    public function getParameterConfidenceLevel( $fileFormat, $parameterName ) {
        // Some Parameters MUST be provided by the user and cannot be overridden
        // by the file metadata
        switch ( $parameterName ) {
            case 'ImageFileFormat' :
            case 'NumberOfChannels' :
            case 'PointSpreadFunction':
            case 'MicroscopeType' :
            case 'CoverslipRelativePosition':
            case 'PerformAberrationCorrection':
            case 'AberrationCorrectionMode':
            case 'AdvancedCorrectionOptions':
            case 'PSF' :
                return "provided";
            case 'Binning':
            case 'IsMultiChannel':
            case 'ObjectiveMagnification':
            case 'CMount':
            case 'TubeFactor':
            case 'AberrationCorrectionNecessary':
            case 'CCDCaptorSize':
            case 'PSFGenerationDepth':
                return "default";
            default:

                // For the other Parameters, the $fileFormat must be specified
                if ( ( $fileFormat == '' ) && ( $fileFormat == null ) ) {
                    exit( "Error: please specify a file format!" . "\n" );
                }

                // The wavelength and voxel size parameters have a common confidence in
                // the HRM but two independent confidences in hucore
                if ( ( $parameterName == "ExcitationWavelength" ) ||
                    ( $parameterName == "EmissionWavelength" ) ) {

                    $confidenceLevelEx = $this->huCoreConfidenceLevel(
                        $fileFormat, "ExcitationWavelength" );
                    $confidenceLevelEm = $this->huCoreConfidenceLevel(
                        $fileFormat, "EmissionWavelength" );
                    $confidenceLevel   = $this->minConfidenceLevel(
                        $confidenceLevelEx, $confidenceLevelEm );

                } elseif ( ( $parameterName == "CCDCaptorSizeX" ) ||
                    ( $parameterName == "ZStepSize" ) ) {

                    $confidenceLevelX = $this->huCoreConfidenceLevel(
                        $fileFormat, "CCDCaptorSizeX" );
                    $confidenceLevelZ = $this->huCoreConfidenceLevel(
                        $fileFormat, "ZStepSize" );
                    $confidenceLevel  = $this->minConfidenceLevel(
                        $confidenceLevelX, $confidenceLevelZ );

                } else {

                    $confidenceLevel = $this->huCoreConfidenceLevel(
                        $fileFormat, $parameterName );

                }

                // Return the confidence level
                return $confidenceLevel;

        }

    }

    /*!
     \brief   Finds out whether a Huygens module is supported by the license.
     \param   $feature The module to find out about. It can use (SQL) wildcards.
     \return  Boolean: true if the module is supported by the license.
    */
    public function hasLicense ( $feature ) {

        $query = "SELECT feature FROM hucore_license WHERE " .
            "feature LIKE '" . $feature . "' LIMIT 1;";

        if ( $this->queryLastValue($query) === FALSE ) {
            return false;
        } else {
            return true;
        }
    }

    /*!
        \brief  Checks whether Huygens Core has a valid license
        \return true if the license is valid, false otherwise
    */
    public function hucoreHasValidLicense( ) {

        // We (ab)use the hasLicense() method
        return ( $this->hasLicense("freeware") == false);
    }

    /*!
        \brief  Gets the licensed server type for Huygens Core.
        \return one of desktop, small, medium, large, extreme
        */
    public function hucoreServerType() {

        $query = "SELECT feature FROM hucore_license WHERE feature LIKE 'server=%';";
        $server = $this->queryLastValue($query);
        if ($server == false) {
            return "no server information";
        }
        return substr($server, 7);
    }

    /*!
     \brief    Updates the database with the current HuCore license details.
     \param    $licDetails A string with the supported license features.
     \return   Boolean: true if the license details were successfully saved.
    */
    public function storeLicenseDetails ( $licDetails ) {

        $licStored = true;

        // Make sure that the hucore_license table exists.
        $tables = $this->connection->MetaTables("TABLES");
        if (!in_array("hucore_license", $tables) ) {
            $msg = "Table hucore_license does not exist! " .
                "Please update the database!";
            report( $msg, 1 ); exit( $msg );
        }

        // Empty table: remove existing values from older licenses.
        $query = "DELETE FROM hucore_license";
        $result = $this->execute($query);

        if (!$result) {
            report("Could not store license details in the database!\n", 1);
            $licStored = false;
            return $licStored;
        }

        // Populate the table with the new license.
        $features = explode(" ", $licDetails);
        foreach ($features as $feature) {

            switch( $feature ) {
                case 'desktop':
                case 'small':
                case 'medium':
                case 'large':
                case 'extreme':
                    $feature = "server=" . $feature;
                    report("Licensed server: $feature", 1);
                default:
                    report("Licensed feature: $feature", 1);
            }

            $query = "INSERT INTO hucore_license (feature) ".
                "VALUES ('". $feature ."')";
            $result = $this->execute($query);

            if (!$result) {
                report("Could not store license feature
                    '$feature' in the database!\n", 1);
                $licStored = false;
                break;
            }
        }

        return $licStored;
    }

    /*!
      \brief  Store the confidence levels returned by huCore into the database for faster retrieval

      This is a rather low-level function that creates the table if needed.

      \param  $confidenceLevels       Array of confidence levels with file formats as keys
      \return true if storing (or updating) the database was successful, false otherwise
    */
    public function storeConfidenceLevels( $confidenceLevels ) {

        // Make sure that the confidence_levels table exists
        $tables = $this->connection->MetaTables("TABLES");
        if (!in_array("confidence_levels", $tables) ) {
            $msg = "Table confidence_levels does not exist! " .
                "Please update the database!";
            report( $msg, 1 ); exit( $msg );
        }

        // Get the file formats
        $fileFormats = array_keys( $confidenceLevels );

        // Get the keys of the parameter arrays from the first array (the others are the same)
        $parameters = array_keys( $confidenceLevels[ $fileFormats[ 0 ] ] );

        // Go over all $confidenceLevels and set the values
        foreach ( $fileFormats as $format ) {

            // If the row for current $fileFormat does not exist, INSERT a new
            // row with all parameters, otherwise UPDATE the existing one.
            $query = "SELECT fileFormat FROM confidence_levels WHERE " .
                "fileFormat = '" . $format . "' LIMIT 1;";

            if ( $this->queryLastValue($query) === FALSE ) {

                // INSERT
                if ( !$this->connection->AutoExecute( "confidence_levels",
                    $confidenceLevels[ $format ], "INSERT" ) ) {
                    $msg = "Could not insert confidence levels for file format $format!";
                    report( $msg, 1 ); exit( $msg );
                }

            } else {

                // UPDATE
                if ( !$this->connection->AutoExecute( "confidence_levels",
                    $confidenceLevels[ $format ], 'UPDATE',
                    "fileFormat = '$format'" ) ) {
                    $msg = "Could not update confidence levels for file format $format!";
                    report( $msg, 1 ); exit( $msg);
                }

            }

        }

        return true;

    }

    /*!
      \brief  Checks whether a user with a given seed exists in the database

      If a user requests an account, his username is added to the database with
      a random seed as status.

      \return true if a user with given seed exists, false otherwise
    */
    public function existsUserRequestWithSeed($seed) {
        $query = "SELECT status FROM username WHERE status = '" . $seed . "'";
        $value = $this->queryLastValue($query);
        if ($value == false) {
            return false;
        } else {
            return ( $value == $seed );
        }

    }


    public function switchGPUState( $newState ) {
       if ( $newState == "On" ) {
           $value = TRUE;
       } else if ( $newState == "Off" ) {
           $value = FALSE;
       } else {
           return "Impossible to change the GPU configuration. Unknown value.";
       }
               
        $query = "UPDATE global_variables SET value = '". $value ."' " .
                 "WHERE name = 'GPUenabled';";

        $result = $this->execute($query);
        if ( $result ) {
            return "GPU processing has been turned " .
                strtolower($newState) . ".";
        } else {
            return "Impossible to change the GPU configuration.";
        }
    }

    /*
                                PRIVATE FUNCTIONS
    */

    /*!
      \brief  Return the mapped HuCore file format corresponding to HRM's
      \param  $fileFormat HRM's file format
      \return mapped HuCore file format
    */
    private function minConfidenceLevel( $level1, $level2 ) {
        $levels = array( );
        $levels[ 'default' ]   = 0;
        $levels[ 'estimated' ] = 1;
        $levels[ 'reported' ]  = 2;
        $levels[ 'verified' ]  = 3;
        $levels[ 'asIs' ]      = 3;

        if ( $levels[ $level1 ] <= $levels[ $level2 ] ) {
            return $level1;
        } else {
            return $level2;
        }

    }

    /*!
      \brief  Return the raw HuCore confidence level
      \param  $fileFormat HRM's file format
      \param  $parameterName  Name of the HRM Paramater
      \return HuCore's raw confidence level
    */
    private function huCoreConfidenceLevel( $fileFormat, $parameterName ) {

        // Get the mapped file format
        $query = "SELECT hucoreName FROM file_format WHERE name = '" .
            $fileFormat . "' LIMIT 1";
        $hucoreFileFormat = $this->queryLastValue( $query );
        if ( !$hucoreFileFormat ) {
            report( "Could not get the mapped file name for " . $fileFormat ."!", 1 );
            return "default";
        }

        // Use the mapped file format to retrieve the
        if (!array_key_exists($parameterName, $this->parameterNameDictionary)) {
            return "default";
        }
        $query = "SELECT " . $this->parameterNameDictionary[ $parameterName ] .
            " FROM confidence_levels WHERE fileFormat = '" . $hucoreFileFormat .
            "' LIMIT 1;";
        $confidenceLevel = $this->queryLastValue( $query );
        if ( !$confidenceLevel ) {
            report( "Could not get the confidence level for " . $fileFormat ."!", 1 );
            return "default";
        }

        // return the confidence level
        return $confidenceLevel;
    }

    /*!
      \brief  Ugly hack to check for old table structure
      \return true if global variables exist
    */
    private function doGlobalVariablesExist() {
        global $db_type;
        global $db_host;
        global $db_name;
        global $db_user;
        global $db_password;

        $test = False;

        $dsn = $db_type."://".$db_user.":".$db_password."@".$db_host."/".$db_name;
        $db = ADONewConnection($dsn);
        if(!$db)
            return;
        $tables = $db->MetaTables("TABLES");
        if (in_array("global_variables", $tables))
            $test = True;

        return $test;
    }

}
?>
