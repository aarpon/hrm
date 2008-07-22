<?php
// Module dbupdate.php

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

include "../inc/hrm_config.inc";
include "../inc/reservation_config.inc";
include $adodb;


// Temporary part
$db_name_test = "hrm-test";


// Last database version
$last_version = 2;

// Log file
$log_file = "dbupdate.log";
$fh = fopen($log_file, 'a') or die("Can't open the dbupdate log file.\n"); // If the file does not exist, it is created
 
// Connect to the database   
$connection = ADONewConnection($db_type);
$result = $connection->Connect($db_host, $db_user, $db_password, $db_name_test); 
if(!$result)
    exit("Database connection failed.\n");   // OK cosi , poi ridirezione??


// Read the database current version
$query = "SELECT value FROM global_variables WHERE variable='dbversion'";
$result = $connection->Execute($query);
if($result) {
    $rows = $result->GetRows();
    $current_version = $rows[0][0];
}
else {
    exit('global_variables table not found in the database.\n');
}



// Check existing database
// -----------------------

// Check 'boundary_values'
// -----------------------
// Check the existence of the table
$query = "SELECT * FROM boundary_values";   
$result = $connection->Execute($query);
$table = "`boundary_values`";
$fields_structure = "`parameter` VARCHAR( 255 ) NOT NULL DEFAULT '0',
                    `min` VARCHAR( 30 ) NULL DEFAULT NULL ,
                    `max` VARCHAR( 30 ) NULL DEFAULT NULL ,
                    `min_included` ENUM( 't', 'f' ) NULL DEFAULT 't',
                    `max_included` ENUM( 't', 'f' ) NULL DEFAULT 't',
                    `standard` VARCHAR( 30 ) NULL DEFAULT NULL";
if(!$result) {  // the table does not exist
    $query = "CREATE TABLE " . $table . " (" . $fields_structure .")";
    $test = $connection->Execute($query);
    if(!$test) {
        exit(error_message($table));
    }
}

// is this necessary?????????? 
//else {  // Check the structure of the table
//    echo "I was here (table update)\n";
//    $query = "ALTER TABLE " . $table . " CHANGE `parameter` `parameter` VARCHAR( 255 ) NOT NULL DEFAULT '0'";
//    echo "query = " . $query . "\n";
//    $test = $connection->Execute($query);
//    if(!$test) {
//        exit(error_message($table));
//    }
//}

// Check the entries of the table
$parameter = "PinholeSize";
$min = "0"; // I want to change this with an associative array
$max = "NULL";
$min_included = "f";
$max_included = "t";
$standard = "NULL";
check_boundary_values_fileds($parameter);

$parameter = "RemoveBackgroundPercent";
$max = 100;
check_boundary_values_fileds($parameter);

// BackgroundOffsetPercent  	0  	   	t  	f  	NULL
	//Edit 	Delete 	ExcitationWavelength 	0 	NULL 	f 	t 	NULL
	//Edit 	Delete 	EmissionWavelength 	0 	NULL 	f 	t 	NULL
	//Edit 	Delete 	CMount 	0.4 	1 	t 	t 	1
	//Edit 	Delete 	TubeFactor 	1 	2 	t 	t 	1
	//Edit 	Delete 	CCDCaptorSizeX 	1 	25000 	t 	t 	NULL
	//Edit 	Delete 	CCDCaptorSizeY 	1 	25000 	t 	t 	NULL
	//Edit 	Delete 	ZStepSize 	50 	600000 	t 	t 	NULL
	//Edit 	Delete 	TimeInterval 	0.001 	NULL 	f 	t 	NULL
	//Edit 	Delete 	SignalNoiseRatio 	0 	100 	f 	t 	NULL
	//Edit 	Delete 	NumberOfIterations 	1 	100 	t 	t 	NULL
	//Edit 	Delete 	QualityChangeStoppingCriterion 	0 	NULL 	t 	t 	NULL


















fclose($fh);

//
echo "current version = " . $current_version . "\n";
echo "last version = " . $last_version . "\n";
//












// Methods
// -------

// Return the database current version
function current_version() {
    global $current_version;
    return $current_version;
}

// Return the database last available version
function last_version() {
    global $last_version;
    return $last_version;
}

// Return an error message
function error_message($table) {
    $string = "An error occured in the update of the table " . $table . ".\n";
    return($string);
}

// Write a message in the log file
function write_to_log($message) {
    global $fh;
    fwrite($fh, $message); 
    return;
}

// -----------------------------------------------------------------------------

function check_boundary_values_fileds($parameter) {
    global $min, $max, $min_included, $max_included, $standard, $table;
    global $connection, $table;
    
    $query = "SELECT *  FROM " . $table . " WHERE parameter =  '" . $parameter . "'";   
    $result = $connection->Execute($query);
    $rows = $result->GetRows();
    
    if(count($rows) == 0) {
        echo "I was here\n";
        $query = "INSERT INTO `boundary_values` (`parameter`, `min`, `max`, `min_included`, `max_included`, `standard`)
                  VALUES ('".$parameter."', '".$min."', '".$max."', '".$min_included."', '".$max_included."', '".$standard."')";
        $message = "The row '".$parameter."' has been inserted in the table ".$table.".\n";
        write_to_log($message);
    }
    else {
        $query = "UPDATE " . $table . " SET `parameter` = '".$parameter."',
                                            `min` = '".$min."',
                                            `max` = '".$max."' ,
                                            `min_included` = '".$min_included."',
                                            `max_included` = '".$max_included."',
                                            `standard` = '".$standard."'
                                        WHERE `parameter` = '".$parameter."'";
    }
    $test = $connection->Execute($query);
    if(!$test) {
       exit(error_message($table)); 
    }
    
    return; 
}












?>