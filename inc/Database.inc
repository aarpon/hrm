<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Location of /adodb/adodb.inc.php
$adodb = dirname(__FILE__) . "/extern/adodb5/adodb.inc.php";

include "hrm_config.inc";
include $adodb;

//!---------------------------------------------------------
// @class    DatabaseConnection
// @desc     Manages the database connection parameter
//!---------------------------------------------------------
 
Class DatabaseConnection {

  private $connection; // @private connection db_id  The database connection  
  
  //!---------------------------------------------------------
  // @function    DatabaseConnection::DatabaseConnection
  // @desc        Konstruktor. Creates a database connection
  // @return      void
  //!---------------------------------------------------------
  function DatabaseConnection() {
    global $db_type;
    global $db_host;
    global $db_name;
    global $db_user;
    global $db_password;
    
    $this->connection = ADONewConnection($db_type);
    $this->connection->Connect($db_host, $db_user, $db_password, $db_name);
  } 

  //!---------------------------------------------------------
  // @function    DatabaseConnection::isReachable
  // @desc        Checks whether a connection to the DB is possible
  // @return      Boolean
  //!---------------------------------------------------------
  function isReachable() {
    global $db_type;
    global $db_host;
    global $db_name;
    global $db_user;
    global $db_password;
    $connection = ADONewConnection($db_type);
    $result = $connection->Connect($db_host, $db_user, $db_password, $db_name);    
    return $result;
  } 
  
  //!---------------------------------------------------------
  // @function    DatabaseConnection::type
  // @desc        Answer the type of the database (mysql, ...)
  // @return      String
  //!---------------------------------------------------------
  function type() {
  	global $db_type;
    return $db_type;
  }  

  //!---------------------------------------------------------
  // @function    DatabaseConnection::version
  // @desc        Attempts to get the version of the underlying database
  // @return      String
  //!---------------------------------------------------------
  function version() {
    try {
      $query = "SELECT version( );";
      $version = $this->queryLastValue($query);
    } catch (Exception $e) {
      $version = "Could not get version information.";
    }
    return $version;
  }  

  //!---------------------------------------------------------
  // @function    DatabaseConnection::host
  // @desc        Answer the name of the database host
  // @return      String
  //!---------------------------------------------------------
  function host() {
	global $db_host;
    return $db_host;
  }

  //!---------------------------------------------------------
  // @function    DatabaseConnection::name
  // @desc        Answer the name of the database
  // @return      String
  //!---------------------------------------------------------  
  function name() {
  	global $db_name;
    return $db_name;
  }

  //!---------------------------------------------------------
  // @function    DatabaseConnection::user
  // @desc        Answer the name of the database user
  // @return      String
  //!---------------------------------------------------------   
  function user() {
  	global $db_user;
    return $db_user;
  }

  //!---------------------------------------------------------
  // @function    DatabaseConnection::password
  // @desc        Answer the password of the database user
  // @return      String
  //!---------------------------------------------------------  
  function password() {
  	global $db_password;
    return $db_password;
  }

  //!---------------------------------------------------------
  // @function    DatabaseConnection::connection
  // @desc        Answer a connection object
  // @return      connection
  //!---------------------------------------------------------  
  function connection() {
    return $this->connection;
  }  

  //!---------------------------------------------------------
  // @function    DatabaseConnection::execute
  // @desc        Executes an sql query
  // @return      Result of the query
  //!---------------------------------------------------------  
  function execute($query) {
    $connection = $this->connection();
    $result = $connection->Execute($query); 
    return $result;
  } 

  //!---------------------------------------------------------
  // @function    DatabaseConnection::query
  // @desc        Execute query string and answer the results
  // @param       queryString    string    The sql query string 
  // @return      Array
  //!---------------------------------------------------------  
  function query($queryString) {
    $connection = $this->connection();
    $resultSet = $connection->Execute($queryString);
    if (!$resultSet) {
      return False;
    } 
    $rows = $resultSet->GetRows();
    return $rows;
  }

  //!---------------------------------------------------------
  // @function    DatabaseConnection::queryLastRow
  // @desc        Execute query string and answer the last row 
  //              of the results.
  // @param       queryString    string    The sql query string 
  // @return      Array
  //!---------------------------------------------------------  
  function queryLastRow($queryString) { 
    $rows = $this->query($queryString);
    if (!$rows) return False;
    $result = end($rows);
    return $result;
  } 

  //!---------------------------------------------------------
  // @function    DatabaseConnection::queryLastValue
  // @desc        Execute query string and answer the value in 
  //              the last column of the last row of the 
  //              results.
  // @param       queryString    string    The sql query string 
  // @return      String
  //!---------------------------------------------------------  
  function queryLastValue($queryString) { 
    $rows = $this->queryLastRow($queryString);
    if (!$rows) return False;
    $result = end($rows);
    return $result;
  }

  ////////////////////////////////////////////////////////////
  // user management 
  ////////////////////////////////////////////////////////////

  //!---------------------------------------------------------
  // @function    DatabaseConnection::addNewUser
  // @desc        Adds a new user to the database (all parameters are expected
  //              to be already validated!
  // @param       username  string    The name of the user
  // @param       password  string    Password (plain)
  // @param       email  	string    E-mail address
  // @param       group  	string    Research group
  // @param       status  	string    Status or ID
  // @return      success	boolean	  True if success; false otherwise
  //!---------------------------------------------------------  
  function addNewUser($username, $password, $email, $group, $status) {
	$query = "INSERT INTO username (name, password, email, research_group, status) ".
                        "VALUES ('".$username."', ".
                                "'".md5($password)."', ".
                                "'".$email."', ".
                                "'".$group."', ".
                                "'".$status."')";
	$result = $this->execute($query);
	if ( $result ) {
	  return true;
	} else {
	  return false;
	}
  }

  //!---------------------------------------------------------
  // @function    DatabaseConnection::updateExistingUser
  // @desc        Updates an existing user in the database (all parameters are
  //              expected to be already validated!
  // @param       username  string    The name of the user
  // @param       password  string    Password (plain)
  // @param       email  	string    E-mail address
  // @param       group  	string    Research group
  // @return      success	boolean	  True if success; false otherwise
  //!---------------------------------------------------------  
  function updateExistingUser($username, $password, $email, $group) {
	$query = "UPDATE username SET email ='".$email."', " .
	  "research_group ='".$group."', ".
	  "password = '".md5($password)."' " .
	  "WHERE name = '".$username."';";
	$result = $this->execute($query);
	if ( $result ) {
	  return true;
	} else {
	  return false;
	}
  }
  
  //!---------------------------------------------------------
  // @function    DatabaseConnection::updateUserStatus
  // @desc        Updates the status of an existing user in the database
  //              (username is expected to be already validated!
  // @param       username  string    The name of the user
  // @param       status    string    One of 'd', 'a', ...
  // @return      success	boolean	  True if success; false otherwise
  //!---------------------------------------------------------  
  function updateUserStatus($username, $status) {
    $query = "UPDATE username SET status = '".$status."' WHERE name = '".$username."'";
	$result = $this->execute($query);
	if ( $result ) {
	  return true;
	} else {
	  return false;
	}
  }

  //!---------------------------------------------------------
  // @function    DatabaseConnection::updateAllUsersStatus
  // @desc        Updates the status of all non-admin users in the database
  // @param       status    string    One of 'd', 'a', ...
  // @return      success	boolean	  True if success; false otherwise
  //!---------------------------------------------------------  
  function updateAllUsersStatus($status) {
    $query = "UPDATE username SET status = '".$status."' WHERE name NOT LIKE 'admin'";
	$result = $this->execute($query);
	if ( $result ) {
	  return true;
	} else {
	  return false;
	}
  }

  //!---------------------------------------------------------
  // @function    DatabaseConnection::deleteUser
  // @desc        Deletes an user and all data from the database
  //              (username is expected to be already validated!
  // @param       username  string    The name of the user
  // @return      success	boolean	  True if success; false otherwise
  //!---------------------------------------------------------  
  function deleteUser($username) {
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
  
  //!---------------------------------------------------------
  // @function    DatabaseConnection::passwordQueryString
  // @desc        Answer the sql query string that yields the
  //              password of the user name
  // @param       name    string    The name of the user
  // @return      String
  //!---------------------------------------------------------  
  function passwordQueryString($name) {
    $string = "select password from username where name='" . $name . "'";
    return $string;
  }

  //!---------------------------------------------------------
  // @function    DatabaseConnection::emailAddress
  // @desc        Answer the email address of the user with
  //              the username username
  // @param       username    string    The name of the user
  // @return      String
  //!---------------------------------------------------------  
  function emailAddress($username) {
    $query = "select email from username where name = '" . $username . "'";
    $result = $this->queryLastValue($query);
    return $result;
  }

  //!-------------------------------------------------------------------
  // @function    DatabaseConnection::emailAddressFromReservationSystem
  // @desc        Answer the email address of the user with the name 
  //              name from the microscope reservation system.
  // @param       name    string    The name of the user
  // @return      String
  //!---------------------------------------------------------------
  function emailAddressFromReservationSystem($name) {
    global $db_type;
    global $reservation_db_host;
    global $reservation_db_user;
    global $reservation_db_password;
    global $reservation_db_name;

    $connection = ADONewConnection($db_type);
    $isConnected = $connection->Connect($reservation_db_host, $reservation_db_user, $reservation_db_password, $reservation_db_name);
    $normalConnection = $this->connection;
    $this->connection = $connection;
    if (!$isConnected) return NULL;
    $query = "select email from utilisateurs where login='" . $name . "'"; 
    $result = $this->queryLastValue($query);
    $this->connection = $normalConnection;
    if (!$result) return NULL;
    return $result; 
  }

  //!---------------------------------------------------------
  // @function    DatabaseConnection::settingsQueryString
  // @desc        Answer a query to get a list of setting names
  //              and standard flags of the user name from the 
  //              database table table
  // @param       name    string    The name of the user
  // @param       table   string    The name of the database table
  // @return      String
  //!---------------------------------------------------------  
  function settingsQueryString($name, $table) {
    $string = "select name, standard from $table where owner ='" . $name . "' order by name";
    return $string;
  }

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::saveParameterSettings
  // @desc        Save the parameter values of the setting into the
  //              database. If the setting already exists the old 
  //              values are over otherwise a new setting is created.
  // @param       settings    Setting      The setting to  be saved.
  // @return      bool
  //!---------------------------------------------------------------
  function saveParameterSettings($settings) { 
    $owner = $settings->owner();
    $user = $owner->name();
    $name = $settings->name();
    $settingTable = $settings->table();
    $table = $settings->parameterTable();
    if ($settings->isDefault) 
      $standard = "t";
    else   
      $standard = "f";
    $result = True;
    if (!$this->existsSetting($settings)) { 
      $query = "insert into $settingTable values ('" . $user."', '" . $name . "', '" .$standard . "')";
      $result = $result && $this->execute($query);
    } 
    $existsAlready = $this->existsParametersFor($settings);
    foreach ($settings->parameterNames() as $parameterName) {
      $parameter = $settings->parameter($parameterName);
      $parameterValue = $parameter->internalValue();
      if (is_array($parameterValue)) { 
          // Before, # was used as a separator, but the first element with
          // index zero was always NULL because channels started their indexing
          // at one. To keep backwards compatibility with the database, we use
          // # now as a channel marker, and even the first channel has a # in
          // front of its value.
          // "/" separator is used to mark range values for signal to noise ratio
          if (is_array($parameterValue[0])) {
            for ($i = 0; $i < 5; $i++) {
	      if ($parameterValue[$i] != null) {
		$parameterValue[$i] = implode("/", array_filter($parameterValue[$i]));
	      }
            }
          }
          $parameterValue = "#".implode("#", $parameterValue);
      }
      if (!$existsAlready) {
        $query = "insert into $table values ('" . $user . "', '" . $name . "', '" . $parameterName . "', '" . $parameterValue . "')";
      } else {
        // Check that the parameter itself exists
        $query = "select name from $table where owner='" . $user . "' and setting='" . $name . "' and name='" . $parameterName . "' limit 1";
        $newValue = $this->queryLastValue($query);
        if ( $newValue != NULL ) {
          $query = "update $table set value = '" . $parameterValue . "' where owner='" . $user . "' and setting='" . $name . "' and name='" . $parameterName . "'"; 
        } else {
          $query = "insert into $table values ('" . $user . "', '" . $name . "', '" . $parameterName . "', '" . $parameterValue . "')";
        }
      } 
      $result = $result && $this->execute($query);
    } 
    return $result;
  } 

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::loadParameterSettings
  // @desc        Load the parameter values for aSetting and answer
  //              a copy of aSetting with the loaded parameter 
  //              values. If a value starts with # the value is
  //              considered to be an array with the first value at
  //              the index 0.
  // @param       settings    Setting      The setting for which the
  //                                       parameter values will be 
  //                                       loaded.
  // @return      Setting
  //!---------------------------------------------------------------
  function loadParameterSettings($aSetting) {
    $user = $aSetting->owner();
    $user = $user->name();
    $name = $aSetting->name();
    $table = $aSetting->parameterTable();
    foreach ($aSetting->parameterNames() as $parameterName) {
      $parameter = $aSetting->parameter($parameterName);
      $query = "select value from $table where owner='" . $user . "' and setting='" . $name . "' and name='" . $parameterName . "'";
      $newValue = $this->queryLastValue($query);
      if ($newValue == NULL) { 
          continue;
      }
      if ($newValue{0}=='#') {
        if ( strcmp( $parameterName, "ExcitationWavelength" ) != 0 ||
          strcmp( $parameterName, "EmissionWavelength" ) != 0 ||
          strcmp( $parameterName, "SignalNoiseRatio" ||
          strcmp( $parameterName, "BackgroundOffsetPercent" ) != 0) != 0 ) {
            $newValue = substr($newValue,1);
        }
        $newValues = explode("#", $newValue);
        if (strcmp( $parameterName, "PSF" ) != 0 && strpos($newValue, "/")) {
          $newValue = array();
          for ($i = 0; $i < count($newValues); $i++) {
            //$val = explode("/", $newValues[$i]);
            //$range = array(NULL, NULL, NULL, NULL);
            //for ($j = 0; $j < count($val); $j++) {
            //  $range[$j] = $val[$j];
            //}
            //$newValue[] = $range;
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
      $aSetting->set($parameter);
    }
    return $aSetting;
  }
  
  //!---------------------------------------------------------------
  // @function    DatabaseConnection::updateDefault
  // @desc        Update the default entry in the database according
  //              to the default value in the setting
  // @param       settings    Setting      The setting for which the
  //                                       default value is updated
  //                                       in the database
  // @return      bool
  //!---------------------------------------------------------------
  function updateDefault($settings) { 
    $owner = $settings->owner();
    $user = $owner->name();
    $name = $settings->name();
    if ($settings->isDefault) 
      $standard = "t";
    else 
      $standard = "f";
    $table = $settings->table();
    $query = "update $table set standard = '" . $standard . "' where owner='" . $user . "' and name='" . $name . "'";
    $result = $this->execute($query);
    return $result;
  }

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::deleteSetting
  // @desc        delete the setting and all its parameter values
  //              from the database. Answer true if the setting
  //              existed in the database and false otherwise.
  // @param       settings    Setting      The setting to be deleted
  //                                       from the database.
  // @return      bool
  //!---------------------------------------------------------------
  function deleteSetting($settings) {
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

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::existsParametersFor
  // @desc        Answer true if there are already parameters stored
  //              for the setting.
  // @param       settings    Setting      The setting for which the
  //                                       check is performed.
  // @return      bool
  //!---------------------------------------------------------------
  function existsParametersFor($settings) {
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

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::existsSetting
  // @desc        Answer true if for the owner of the setting a 
  //              setting with the name of the setting already exists
  //              in the database and false otherwise.
  // @param       settings   Setting   the setting for which the check
  //                                   is performed.
  // @return      bool
  //!---------------------------------------------------------------
  function existsSetting($settings) {
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

  //!---------------------------------------------------------
  // @function    DatabaseConnection::saveJobFiles
  // @desc        Adds all files for a given job id and user to the database
  // @param       id   		Job id
  // @param       owner   	User name
  // @param       files		Array of file names
  // @return      Boolean
  //!---------------------------------------------------------  
  function saveJobFiles($id, $owner, $files) {
    $result = True;
    $username = $owner->name();
    foreach ($files as $file) {
      $query = "insert into job_files values ('" . $id ."', '" . $username ."', '" . addslashes($file) . "')";
      $result = $result && $this->execute($query);
    } 
    return $result;
  } 
  
  //!---------------------------------------------------------
  // @function    DatabaseConnection::queueJob
  // @desc        Adds all files for a given job id and user to the database
  // @param       id   		Job id
  // @param       username	User name
  // @return      Boolean
  //!---------------------------------------------------------  
  function queueJob($id, $username) {   
    $query = "insert into job_queue (id, username, queued, status) values ('" .$id . "', '" . $username . "', NOW(), 'queued')";
    return $this->execute($query);
  }

  //!---------------------------------------------------------
  // @function  DatabaseConnection::setJobPriorities
  // @desc      Assigns priorities to the jobs in the queue
  // @return	Boolean: true if assigning priorities was possible
  //!---------------------------------------------------------  
  function setJobPriorities( ) {

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
  

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::updateStatistics
  // @desc        Log job information in the statistics table 
  // @param       job   Job   the job whose information is logged in the database
  // @return      void
  //!---------------------------------------------------------------
  function updateStatistics($job, $startTime) {
      
    $desc = $job->description();
    $parameterSetting = $desc->parameterSetting();
    $taskSetting = $desc->taskSetting();
    
    $stopTime = date("Y-m-d H:i:s");
    $id = $desc->id();
    $user = $desc->owner();
    $owner = $user->name();
    $group = $user->userGroup($owner);
    $parameter = $parameterSetting->parameter('ImageFileFormat');
    $inFormat = $parameter->value();
    $parameter = $parameterSetting->parameter('PointSpreadFunction');
    $PSF = $parameter->value();
    $parameter = $parameterSetting->parameter('ImageGeometry');
    $geometry = $parameter->value();
    $parameter = $parameterSetting->parameter('MicroscopeType');
    $microscope = $parameter->value();
    $parameter = $taskSetting->parameter('OutputFileFormat');
    $outFormat = $parameter->value();
    
    $query = "insert into statistics values ('" . $id ."', '" . $owner ."', '" . $group . "','"
              . $startTime . "', '" . $stopTime . "', '" . $inFormat . "', '" . $outFormat . "', '"
              . $PSF . "', '" . $geometry . "', '" . $microscope . "')";
    $this->execute($query);
    
  }

  //!---------------------------------------------------------
  // @function  DatabaseConnection::flatten
  // @desc		Flattens a multi-dimensional array
  // @param		anArray		Array
  // @return	flattened array
  //!---------------------------------------------------------  
  function flatten($anArray) {
    $result = array();
    foreach ($anArray as $row) {
      $result[] = end($row);
    } 
    return $result;    
  } 
  
  //!---------------------------------------------------------
  // @function  DatabaseConnection::readPossibleValues
  // @desc		Returns the values possible for a given parameter
  // @param		parameter	Parameter (object)
  // @return	Array of possible values
  //!---------------------------------------------------------  
  function readPossibleValues($parameter) {
    $name = $parameter->name();
    $query = "select value from possible_values where parameter = '" .$name . "'";
    $answer = $this->query($query);
    $result = $this->flatten($answer);
    return $result;
  }
  
  //!---------------------------------------------------------
  // @function  DatabaseConnection::readTranslatedPossibleValues
  // @desc		Returns the translated possible values for a given parameter
  // @param		parameter	Parameter (object)
  // @return	Array of translated possible values
  //!---------------------------------------------------------  
  function readTranslatedPossibleValues($parameter) {
    $name = $parameter->name();
    $query = "select translation from possible_values where parameter = '" .$name . "'";
    $answer = $this->query($query);
    $result = $this->flatten($answer);
    return $result;
  }

  //!---------------------------------------------------------
  // @function  DatabaseConnection::translationFor
  // @desc		Returns the translation of current value for a given parameter
  // @param		parameter	Parameter (object)
  // @return	Translated value
  //!---------------------------------------------------------  
  function translationFor($parameter) {
    $name = $parameter->name();
    $value = $parameter->value();    
    $query = "select translation from possible_values where parameter = '" .$name . "' and value = '" . $value . "'";
    $result = $this->queryLastValue($query);
    return $result;
  } 

  //!---------------------------------------------------------
  // @function  DatabaseConnection::fileExtensions
  // @desc		Returns an array of file extensions associated to a given file format
  // @param		imageFormat	File format
  // @return	Array of file extensions
  //!---------------------------------------------------------  
  function fileExtensions($imageFormat) {
    $query = "select distinct extension from file_extension where file_format = '" . $imageFormat . "'";
    $answer = $this->query($query);
    $result = $this->flatten($answer);
    return $result;    
  } 

  //!---------------------------------------------------------
  // @function  DatabaseConnection::readNumericalValueRestrictions
  // @desc		Returns all restrictions for a given numerical parameter
  // @param		parameter	Parameter (object)
  // @return	Array of restrictions
  //!---------------------------------------------------------  
  function readNumericalValueRestrictions($parameter) { 
    $name = $parameter->name();
    $query = "select min, max, min_included, max_included, standard from boundary_values where parameter = '" .$name . "'";
    $result = $this->queryLastRow($query);
    return $result;
  } 

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::fileFormatsWith
  // @desc        Answer the file formats that fit the conditions
  //              expressed by the parameters.
  // @param       isSingleChannel   Boolean   set whether the file format must be single channel (True), 
  //                                          multi channel (False) or if it doesn't matter (NULL).  
  // @param       isVariableChannel Boolean   set whether the number of channels must be variable (True), 
  //                                          fixed (False) or if it doesn't matter (NULL).  
  // @param       isFixedGeometry   Boolean   set whether the geometry (xyzt) must be fixed (True), 
  //                                          variable (False) or if it doesn't matter (NULL).
  // @return      array
  //!---------------------------------------------------------------
  function fileFormatsWith($isSingleChannel, $isVariableChannel, $isFixedGeometry) {  
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

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::geometriesWith
  // @desc        Returns the geometries (XY, XY-time, XYZ, XYZ-time) that are
  //              (or are not) 3D and time series
  // @param       $isThreeDimensional   Boolean   true if 3D
  // @param       $isTimeSeries 		Boolean   true if time-series
  // @return      array of geometries
  //!---------------------------------------------------------------
  function geometriesWith($isThreeDimensional, $isTimeSeries) { 
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
  
  //!---------------------------------------------------------------
  // @function    DatabaseConnection::retrieveColumnFromTableWhere
  // @desc        Answer all values from the column column from the
  //              table table where the condition condition 
  //              evaluates to true
  // @param       column   string   the column from which the values are taken
  // @param       table    string   the table from which the values are taken
  // @param       conditions array  the conditions that the result values must fullfil.
  //                                this is an array with column names as indices and
  //                                boolean values as content.
  // @return      array
  //!---------------------------------------------------------------  
  function retrieveColumnFromTableWhere($column, $table, $conditions) {
    $query = "select distinct $column from $table where ";
    foreach ($conditions as $eachName => $eachValue) {
      $query = $query . $eachName . " = '" . $eachValue . "' and ";
    } 
    $query = $query . "1 = 1";
    $answer = $this->query($query);
    $result = array();
    foreach ($answer as $row) {
      $result[] = end($row);
    } 
    return $result;
  }

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::defaultValue
  // @desc        Returns the dafault value for a given parameter
  // @param       $parameterName   String	Name of the parameter
  // @return      Default value
  //!---------------------------------------------------------------
  function defaultValue($parameterName) {
    $query = "select value from possible_values where parameter='" .$parameterName . "' and isDefault='t'";
    $result = $this->queryLastValue($query);
    if (!$result) {
      return NULL;
    } 
    return $result;
  }

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::getNextIdFromQueue
  // @desc        Returns the id for next job from the queue, sorted by priority
  // @return      Job id
  //!---------------------------------------------------------------
  function getNextIdFromQueue() {
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

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::getQueueJobs
  // @desc        Returns all jobs from the queue, both compound and simple,
  //              ordered by priority
  // @return      All jobs
  //!---------------------------------------------------------------
  function getQueueJobs() {
    // Get jobs as they are in the queue, compound or not, without splitting
    // them.
    $query = "SELECT id, username, queued, start, server, process_info, status
    FROM job_queue
    ORDER BY job_queue.priority asc, job_queue.queued asc, job_queue.status asc";
    $result = $this->query($query);
    return $result;
  }

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::getQueueContents
  // @desc        Returns all jobs from the queue, both compund and simple,
  //              and the associated file names, ordered by priority
  // @return      All jobs
  //!---------------------------------------------------------------
  function getQueueContents() {
    // For the query we join job_queue and job_files, since we want to sort also by file name
    $query = "SELECT id, username, queued, start, stop, server, process_info, status, file
    FROM job_queue, job_files
    WHERE job_queue.id = job_files.job AND job_queue.username = job_files.owner
    ORDER BY job_queue.priority asc, job_queue.queued asc, job_queue.status asc, job_files.file asc";
    $result = $this->query($query);
    return $result;
  }
  
  //!---------------------------------------------------------------
  // @function    DatabaseConnection::getQueueContentsForId
  // @desc        Returns all jobs from the queue for a given id (that must be
  //              univocal!)
  // @param		  id	String	id of the job
  // @return      All jobs for the id
  //!---------------------------------------------------------------
  function getQueueContentsForId($id) {   
    $query = "select id, username, queued, start, server, process_info, status from job_queue where id='" . $id . "'";
    $result = $this->queryLastRow($query);  // it is supposed that just one job exists with a given id
    return $result;
  }

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::getJobFilesFor
  // @desc        Returns all file names associated to a job with given id
  // @return      Array of file names
  //!---------------------------------------------------------------
  function getJobFilesFor($id) {
    $query = "select file from job_files where job = '" . $id . "'";
    $result = $this->query($query);
    $result = $this->flatten($result);
    return $result;
  } 

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::getNumberOfQueuedJobsForUser
  // @desc        Returns the number of jobs currently in the queue for a
  //              given username
  // @param	      username	String	Name of the user
  // @return      Integer number of jobs in queue
  //!---------------------------------------------------------------
  function getNumberOfQueuedJobsForUser($username) {
	$query = "SELECT COUNT(id) FROM job_queue WHERE username = '" . $_SESSION['user']->name( ) . "';";
    $row = $this->Execute( $query )->FetchRow( );
    return $row[ 0 ];
  } 

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::userWhoCreatedJob
  // @desc        Returns the name of the user who created the job with given id
  // @param	      id	String	id of the job
  // @return      Integer number of jobs in queue
  //!---------------------------------------------------------------
  function userWhoCreatedJob($id) {
    $query = "select username from job_queue where id = '" . $id . "'";
    $result = $this->queryLastValue($query);
    if (!$result) {
      return NULL;
    } 
    return $result;    
  } 

  //!---------------------------------------------------------------
  // @function    DatabaseConnection::deleteFromTablesWhereColumnEquals
  // @desc        Deletes entries from specified tables and columns for given id
  // @param	      tables	Array of strings	Names of the tables
  // @param	      columns	Array of strings	Names of the columns
  // @param	      id		String				id of the job
  // @return      Boolen True if success
  //!---------------------------------------------------------------
  function deleteFromTablesWhereColumnEquals($tables, $columns, $id) {
    $result = True;
    $index = 0;
    foreach ($tables as $table) {
      $query = "delete from $table where $columns[$index] = '" . $id . "'";
      $result = $result && $this->execute($query);
      $index++;
    } 
    return $result;
  }
  
  //!---------------------------------------------------------------
  // @function    DatabaseConnection::huscriptPathOn
  // @desc        Returns the path to hucore on given host
  // @param	      host	String	Host name
  // @return      Full path to hucore
  //!---------------------------------------------------------------
  // TODO better management of multiple hosts
  function huscriptPathOn($host) {
    $query = "SELECT huscript_path FROM server where name = '" . $host . "'";
    $result = $this->queryLastValue($query);
    if (!$result) {
      return NULL;
    } 
    return $result; 
  }

  //!---------------------------------------------------------
  // @function    Database::freeServer
  // @desc        Get the name of a free server
  // @return      String
  //!---------------------------------------------------------
  function freeServer() { 
    $query = "select name from server where status='free'";
    $result = $this->queryLastValue($query);
    return $result;
  } 

  //!---------------------------------------------------------
  // @function    Database::statusOfServer
  // @desc        Get the status (i.e. free, busy, paused) of server $name
  // @param       name     String    Name of the server
  // @return      String
  //!---------------------------------------------------------
  function statusOfServer($name) {  
    $query = "select status from server where name='$name'";
    $result = $this->queryLastValue($query);  
    return $result;
  } 

  //!---------------------------------------------------------
  // @function    Database::isServerBusy
  // @desc        Checks whether server $name is busy
  // @param       name     String    Name of the server
  // @return      Boolean
  //!---------------------------------------------------------
  function isServerBusy($name) {  
    $status = $this->statusOfServer($name);
    $result = ($status == 'busy');
    return $result;
  } 

  //!---------------------------------------------------------
  // @function    Database::isSwitchOn
  // @desc        Checks whether the switch in the queue manager is 'on'
  // @return      Boolean
  //!---------------------------------------------------------
  function isSwitchOn() {
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

  //!---------------------------------------------------------
  // @function    Database::getSwitchStatus
  // @desc        Gets the status of the queue manager's switch
  // @return      'on' or 'off'
  //!---------------------------------------------------------
  function getSwitchStatus() { 
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

  //!---------------------------------------------------------
  // @function    Database::setSwitchStatus
  // @desc        Sets the status of the queue manager's switch
  // @param		  status	String	Either 'on' or 'off'
  // @return      Query result
  //!---------------------------------------------------------
  function setSwitchStatus( $status ) {
  	$result = $this->execute("UPDATE queuemanager SET value = '". $status ."' WHERE field = 'switch'");
  	return $result;
  }

  //!---------------------------------------------------------
  // @function    Database::reserveServer
  // @desc        Sets the state of the server to 'busy' and the pid for a running job
  // @param		  name	String	Server name
  // @param		  pid	String	Process identifier associated with a running job
  // @return      Query result
  //!---------------------------------------------------------
  function reserveServer($name, $pid) { 
    $query = "update server set status='busy', job='$pid' where name='$name'";
    $result = $this->execute($query);
    return $result;
  } 

  //!---------------------------------------------------------
  // @function    Database::resetServer
  // @desc        Sets the state of the server to 'free' and deletes the the pid
  // @param		  name	String	Server name
  // @param		  pid	String	Process identifier (UNUSED!)
  // @param		  pid	String	Process identifier associated with a running job
  // @return      Query result
  //!---------------------------------------------------------
  function resetServer($name, $pid) { 
    $query = "update server set status='free', job=NULL where name='$name'";
    $result = $this->execute($query);
    return $result;
  } 

  //!---------------------------------------------------------
  // @function    Database::startJob
  // @desc        Starts a job
  // @param		  job	Job object
  // @return      Query result
  //!---------------------------------------------------------
  function startJob($job) { 
    $desc = $job->description();
    $id = $desc->id();
    $server = $job->server();
    $process_info = $job->pid();
    $query = "update job_queue set start=NOW(), server='$server', process_info='$process_info', status='started' where id='" .$id . "'";  
    $result = $this->execute($query);
    return $result;
  } 

  //!---------------------------------------------------------
  // @function    Database::getRunningJobs
  // @desc        Get an array of running job (objects)
  // @return      Array of job objects
  //!---------------------------------------------------------
  function getRunningJobs() { 
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

  //!---------------------------------------------------------
  // @function    Database::availableServer
  // @desc        Get all server names (independent of their status)
  // @return      Array of strings
  //!---------------------------------------------------------
  function availableServer() {
    $query = "select name from server";
    $result = $this->query($query);
    $result = $this->flatten($result);
    return $result;
  }

  //!---------------------------------------------------------
  // @function    Database::startTimeOf
  // @desc        Get the starting time of given job object
  // @param		  job	Job object
  // @return      Time (String)
  //!---------------------------------------------------------
  function startTimeOf($job) {
    $desc = $job->description();
    $id = $desc->id();
    $query = "select start from job_queue where id = '" .$id . "'";
    $result = $this->queryLastValue($query);
    return $result;
  }

  //!---------------------------------------------------------
  // @function    Database::fromUnixTime
  // @desc        Returns a formatted time from a unix timestamp
  // @param		  timestamp	String	Unix time stamp
  // @return      Formatted time string: YYYY-MM-DD hh:mm:ss
  //!---------------------------------------------------------
  function fromUnixTime($timestamp) {
    $query = "select FROM_UNIXTIME($timestamp)";
    $result = $this->queryLastValue($query);
    return $result;
  } 
  
  //!---------------------------------------------------------
  // @function    Database::pauseJob
  // @desc        Pauses a job of given id
  // @param		  id	String	Job id
  // @return      Query result
  //!---------------------------------------------------------
  function pauseJob($id) {   
    $query = "update job_queue set status='paused' where id='" . $id . "'";
    $result = $this->execute($query);
    return $result;
  } 

  //!---------------------------------------------------------
  // @function    Database::setJobEndTime
  // @desc        Sets the end time of a job
  // @param		  id	String	Job id
  // @param		  date	String	Formatted date: YYYY-MM-DD hh:mm:ss
  // @return      Query result
  //!---------------------------------------------------------
  function setJobEndTime($id, $date) {   
    $query = "update job_queue set stop='".$date."' where id='" . $id . "'";
    $result = $this->execute($query);
    return $result;
  } 

  //!---------------------------------------------------------
  // @function    Database::restartPausedJobs
  // @desc        Restart all paused jobs
  // @return      Query result
  //!---------------------------------------------------------
  function restartPausedJobs() {  
    $query = "update job_queue set status='queued' where status='paused'";
    $result = $this->execute($query);
    return $result;    
  }

  //!---------------------------------------------------------
  // @function    Database::markJobAsRemoved
  // @desc        Mark a job with given id as 'broken' (i;e; to be removed)
  // @param		  id	String	Job id
  // @return      Query result
  //!---------------------------------------------------------
  function markJobAsRemoved($id) {  
    $query = "update job_queue set status='broken' where (status='queued' or status='paused') and id='" . $id . "'";
    // $query = "update job_queue set status='broken' where id='" . $id . "'"; 
    $result = $this->execute($query);
    $query = "update job_queue set status='kill' where status='started' and id='" . $id . "'";
    $result = $this->execute($query);
    return $result;    
  }
  
  //!---------------------------------------------------------
  // @function    Database::markServerAsFree
  // @desc        Set the server status to free
  // @param		  server	String	Server name
  // @return      Query result
  //!---------------------------------------------------------
  function markServerAsFree($server) {  
    $query = "update server set status='free' where name='" . $server . "'";
    $result = $this->execute($query);
    return $result;
  }

  //!---------------------------------------------------------
  // @function    Database::getMarkedJobIds
  // @desc        Get all jobs with status 'broken'
  // @return      Array of ids
  //!---------------------------------------------------------
  function getMarkedJobIds() { 
    $conditions['status'] = 'broken';
    $ids = $this->retrieveColumnFromTableWhere('id', 'job_queue', $conditions);
    return $ids;
  }

  //!---------------------------------------------------------
  // @function    Database::getJobIdsToKill
  // @desc        Get all jobs with status 'kill' to be killed by the Queue Manager
  // @return      Array of ids
  //!---------------------------------------------------------
  function getJobIdsToKill() { 
    $conditions['status'] = 'kill';
    $ids = $this->retrieveColumnFromTableWhere('id', 'job_queue', $conditions);
    return $ids;
  }
 
  //!---------------------------------------------------------
  // @function    Database::checkUser
  // @desc        Check whether a user exists
  // @param		  name	String	Username
  // @return      Boolean True if the user exists
  //!---------------------------------------------------------
  function checkUser($name) {
    $query = "select status from username where name = '" . $name . "'";
    $result = $this->queryLastValue($query);
    if ($result) $result = true;
    return $result;
  }
  
  //!---------------------------------------------------------
  // @function    Database::getUserStatus
  // @desc        Get the status of a user
  // @param		  name	String	Username
  // @return      Status (string)
  //!---------------------------------------------------------
  function getUserStatus($name) {
  	$query = "select status from username where name = '" . $name . "'";
  	$result = $this->queryLastValue($query);
  	return $result;
  }

  //!---------------------------------------------------------
  // @function    Database::getJobOwner
  // @desc        Get the name of the user who owns a job with given id
  // @param		  id	String	Job id
  // @return      Name of the user who owns the job
  //!---------------------------------------------------------
  function getJobOwner($id) {
  	$query = "select username from job_queue where id = '" . $id . "'";
  	$result = $this->queryLastValue($query);
  	return $result;
  }
 
  //!---------------------------------------------------------
  // @function    Database::now
  // @desc        Returns current date and time
  // @return      Formatted date (YYYY-MM-DD hh:mm:ss)
  //!---------------------------------------------------------
  function now() {
    $query = "select now()";
    $result = $this->queryLastValue($query);
    return $result;	
  }
  
  //!---------------------------------------------------------
  // @function    Database::getGroup
  // @desc        Returns the group to which the user belongs
  // @param		  username	String	Name of the user
  // @return      Group (string)
  //!---------------------------------------------------------
  function getGroup($userName) {
  	$query = "SELECT research_group FROM username WHERE name= '" . $userName . "'";
  	$result = $this->queryLastValue($query);
  	return $result;
  }
  
  //!---------------------------------------------------------
  // @function    Database::updateMail
  // @desc        Updates the e-mail address of a user
  // @param		  username	String	Name of the user
  // @param		  email		String	E-mail address
  // @return      Query result
  //!---------------------------------------------------------
  function updateMail($userName, $email) {
  	$cmd = "UPDATE username SET email = '". $email ."' WHERE name = '".$userName."'";
  	$result = $this->execute($cmd);
  	return $result;
  }
  
  //!---------------------------------------------------------
  // @function    Database::updateLastAccessDate
  // @desc        Updates the last access date of a user
  // @param		  username	String	Name of the user
  // @return      Query result
  //!---------------------------------------------------------
  function updateLastAccessDate($userName) {
  	$query = "UPDATE username SET last_access_date = CURRENT_TIMESTAMP WHERE name = '". $userName . "'";
  	$result = $this->execute($query);
  	return $result;
  }
 
  //!---------------------------------------------------------
  // @function    Database::doGlobalVariablesExist
  // @desc        Ugly hack to check for old table structure
  // @return      True if global variables exist
  //!---------------------------------------------------------
  function doGlobalVariablesExist() {
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
