<?php
// Module Database.inc

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS) 

// contributors : 
// 	     Pierre Travo	(concept)	     
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL
// license as circulated by CEA, CNRS and INRIA at the following URL
// "http://www.cecill.info". 

// As a counterpart to the access to the source code and  rights to copy,
// modify and redistribute granted by the license, users are provided only
// with a limited warranty and the software's author, the holder of the
// economic rights, and the successive licensors  have only limited
// liability. 

// In this respect, the user's attention is drawn to the risks associated
// with loading, using, modifying and/or developing or reproducing the
// software by the user in light of its specific status of free software,
// that may mean that it is complicated to manipulate, and that also
// therefore means that it is reserved for developers and experienced
// professionals having in-depth IT knowledge. Users are therefore encouraged
// to load and test the software's suitability as regards their requirements
// in conditions enabling the security of their systems and/or data to be
// ensured and, more generally, to use and operate it in the same conditions
// as regards security. 

// The fact that you are presently reading this means that you have had
// knowledge of the CeCILL license and that you accept its terms.

include "hrm_config.inc";
include "reservation_config.inc";
include $adodb;

//!---------------------------------------------------------
// @class    DatabaseConnection
// @desc     Manages the database connection parameter
//!---------------------------------------------------------
 
Class DatabaseConnection {

  var $connection; // @var connection db_id  The database connection  
  
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
  // @function    DatabaseConnection::passwordQueryString
  // @desc        Answer the sql query string that yields the
  //              the password of the user name
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

  function saveJobFiles($id, $owner, $files) {
    $result = True;
    $username = $owner->name();
    foreach ($files as $file) {
      $query = "insert into job_files values ('" . $id ."', '" . $username ."', '" . $file . "')";
      $result = $result && $this->execute($query);
    } 
    return $result;
  } 
  
  function queueJob($id, $username) {   
    $query = "insert into job_queue (id, username, queued, status) values ('" .$id . "', '" . $username . "', NOW(), 'queued')";
    return $this->execute($query);
  } 
  
  function flatten($anArray) {
    $result = array();
    foreach ($anArray as $row) {
      $result[] = end($row);
    } 
    return $result;    
  } 
  
  function readPossibleValues($parameter) {
    $name = $parameter->name();
    $query = "select value from possible_values where parameter = '" .$name . "'";
    $answer = $this->query($query);
    $result = $this->flatten($answer);
    return $result;
  }
  
  function readTranslatedPossibleValues($parameter) {
    $name = $parameter->name();
    $query = "select translation from possible_values where parameter = '" .$name . "'";
    $answer = $this->query($query);
    $result = $this->flatten($answer);
    return $result;
  }

  function translationFor($parameter) {
    $name = $parameter->name();
    $value = $parameter->value();    
    $query = "select translation from possible_values where parameter = '" .$name . "' and value = '" . $value . "'";
    $result = $this->queryLastValue($query);
    return $result;
  } 

  function fileExtensions($imageFormat) {
    $query = "select distinct extension from file_extension where file_format = '" . $imageFormat . "'";
    $answer = $this->query($query);
    $result = $this->flatten($answer);
    return $result;    
  } 

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

  function defaultValue($parameterName) {
    $query = "select value from possible_values where parameter='" .$parameterName . "' and isDefault='t'";
    
    $result = $this->queryLastValue($query);
    if (!$result) {
      return NULL;
    } 
    return $result;
  }

  function getNextIdFromQueue() { 
    $query = "SELECT id FROM job_queue WHERE status='queued' order by queued desc";
    $result = $this->queryLastValue($query);
    if (!$result) {
      return NULL;
    } 
    return $result;
  } 

  function getQueueContents() {
    $query = "select id, username, queued, start, server, process_info, status from job_queue order by queued, status desc";
    $result = $this->query($query);
    return $result;
  }
  
  function getQueueContentsForId($id) {   
    $query = "select id, username, queued, start, server, process_info, status from job_queue where id='" . $id . "'";
    $result = $this->queryLastRow($query);  // it is supposed that just one job exists with a given id
    return $result;
  }

  function getJobFilesFor($id) {
    $query = "select file from job_files where job = '" . $id . "'";
    $result = $this->query($query);
    $result = $this->flatten($result);
    return $result;
  } 

  function userWhoCreatedJob($id) {
    $query = "select username from job_queue where id = '" . $id . "'";
    $result = $this->queryLastValue($query);
    if (!$result) {
      return NULL;
    } 
    return $result;    
  } 

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
  
  // TODO better management of multiple hosts
  function huscriptPathOn($host) {
    $query = "SELECT huscript_path FROM server where name = '" . $host . "'";
    $result = $this->queryLastValue($query);
    if (!$result) {
      return NULL;
    } 
    return $result; 
  }

  function freeServer() { 
    $query = "select name from server where status='free'";
    $result = $this->queryLastValue($query);
    return $result;
  } 

  function statusOfServer($name) {  
    $query = "select status from server where name='$name'";
    $result = $this->queryLastValue($query);  
    return $result;
  } 

  function isServerBusy($name) {  
    $status = $this->statusOfServer($name);
    $result = ($status == 'busy');
    return $result;
  } 

  function isSwitchOn() { 
    if ($this->isDatabaseUpdated()) {
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
  
  function reserveServer($name, $pid) { 
    $query = "update server set status='busy', job='$pid' where name='$name'";
    $result = $this->execute($query);
    return $result;
  } 

  function resetServer($name, $pid) { 
    $query = "update server set status='free', job=NULL where name='$name'";
    $result = $this->execute($query);
    return $result;
  } 

  function startJob($job) { 
    $desc = $job->description();
    $id = $desc->id();
    $server = $job->server();
    $process_info = $job->pid();
    $query = "update job_queue set start=NOW(), server='$server', process_info='$process_info', status='started' where id='" .$id . "'";  
    $result = $this->execute($query);
    return $result;
  } 

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

  function availableServer() {
    $query = "select name from server";
    $result = $this->query($query);
    $result = $this->flatten($result);
    return $result;
  }

  function startTimeOf($job) {
    $desc = $job->description();
    $id = $desc->id();
    $query = "select start from job_queue where id = '" .$id . "'";
    $result = $this->queryLastValue($query);
    return $result;
  }

  function fromUnixTime($timestamp) {
    $query = "select FROM_UNIXTIME($timestamp)";
    $result = $this->queryLastValue($query);
    return $result;
  } 
  
  function pauseJob($id) {   
    $query = "update job_queue set status='paused' where id='" . $id . "'";
    $result = $this->execute($query);
    return $result;
  } 

  function restartPausedJobs() {  
    $query = "update job_queue set status='queued' where status='paused'";
    $result = $this->execute($query);
    return $result;    
  }

  function markJobAsRemoved($id) {  
    //$query = "update job_queue set status='broken' where status='queued' or status='paused' and id='" . $id . "'";
    $query = "update job_queue set status='broken' where id='" . $id . "'"; 
    $result = $this->execute($query);
    return $result;    
  }
  
  function markServerAsFree($server) {  
    $query = "update server set status='free' where name='" . $server . "'";
    $result = $this->execute($query);
    return $result;
  }

  function getMarkedJobIds() { 
    $conditions['status'] = 'broken';
    $ids = $this->retrieveColumnFromTableWhere('id', 'job_queue', $conditions);
    return $ids;
  }
  
  function checkUser($name) {
    $query = "select status from username where name = '" . $name . "'";
    $result = $this->queryLastValue($query);
    if ($result) $result = true;
    return $result;
  }
  
  function getUserStatus($name) {
  	$query = "select status from username where name = '" . $name . "'";
  	$result = $this->queryLastValue($query);
  	return $result;
  }
  
  function now() {
    $query = "select now()";
    $result = $this->queryLastValue($query);
    return $result;	
  }
  
  function getGroup($userName) {
  	$query = "SELECT research_group FROM username WHERE name= '" . $userName . "'";
  	$result = $this->queryLastValue($query);
  	return $result;
  }
  
  function updateMail($userName, $email) {
  	$result = $this->execute("UPDATE username SET email = '". $email ."' WHERE name = $userName");
  	return $result;
  }
  
  function updateLastAccessDate($userName) {
  	$query = "UPDATE username SET last_access_date = CURRENT_TIMESTAMP WHERE name = '". $userName . "'";
  	$result = $this->execute($query);
  	return $result;
  }
  
   function saveAccountingData($id, $credit, $group) {
    $query = "insert into job_accounting_data values ('$id', '$credit', '$group')";
    $result = $this->execute($query);
    return $result;
  }

  function loadAccountingData($jobDescription) {
    $id = $jobDescription->id();
    $query = "select * from job_accounting_data where id='$id'";
    $row = $this->query($query);
    if ($row) { 
      return $row[0];
    }
    return false;
  }
  
  function isDatabaseUpdated() {
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
