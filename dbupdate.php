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

include "inc/hrm_config.inc";
include "inc/reservation_config.inc";
include $adodb;

// From update.php
global $interface;
global $message;

// For test purposes
$db_name = "hrm-test";

// Current database revision
$current_revision = 2;


// -----------------------------------------------------------------------------
// Utility functions
// -----------------------------------------------------------------------------

// Returns a timestamp
function timestamp() {
    return date('l jS \of F Y h:i:s A');
}

// Return an error message
function error_message($table) {
    return "An error occured while updating table " . $table . ".";
}

// Write a message in the log file
function write_to_log($msg) {
    global $fh;
    fwrite($fh, timestamp() . " " . $msg . "\n"); 
}

// Write a message in the error log file
function write_to_error($msg) {
    global $efh;
    fwrite($efh, timestamp() . " " . $msg . "\n"); 
}

// Write a message to the standard output
function write_message($msg) {
    global $interface;
    global $message;
    if (isset($interface)) {
        $message = "            <p class=\"warning\">" . $msg . "</p>\n";
    }
    else echo $msg."\n";
}


// -----------------------------------------------------------------------------
// Query functions
// -----------------------------------------------------------------------------

// Search a value into a multidimensional array. Return true if the value has been found, false otherwise
function in_array_multi($needle,$haystack) {
    $found = false;
    foreach($haystack as $value) {
        if((is_array($value) && in_array_multi($needle,$value)) || $value == $needle) {
            $found = true;
        }
    }
    return $found;
}

// From the associative array that describe the table structure, create a string for the query
function create_string_for_update($key,$table_structure) {
    $string = "`" . $key . "`";
    for($i=0; $i<count($table_structure[$key]); $i++) {
        $string .= " " . $table_structure[$key][$i];
    }
    return $string;
}

// Check if the table $table exists; if not, create the table
function check_table_existence($var,$table_existance) {
echo "Enter into check_table_existence\n";
    global $table, $connection;

    $query = "SELECT * FROM ". $table;   
    $result = $connection->Execute($query);
    
    if(!$result) {  // the table does not exist
        $table_existance = false;
        
        $keys = array_keys($var);
        $n_fields = count($keys);
        $n_attributes = count($var[$keys[0]]);
        
        $query = "CREATE TABLE `" . $table . "` (";
        
        for($i=0; $i<$n_fields; $i++) {
            $query .= " " . create_string_for_update($keys[$i],$var);
            if($i!=($n_fields-1)) {
                $query .= ",";
            }
            
        }
        
        $query .= ")";
 
echo "query for check table existance = " . $query . "\n";

        $test = $connection->Execute($query);
        
echo "test = " . $test . "\n";
        if(!$test) {
            $msg = error_message($table);
            write_to_error($msg);
            return false;
        }
        $msg = "\nThe table '" . $table . "' has been created in the database\n";
    }
    else {
        $msg = "\nThe table '" . $table . "' exists\n";
    }
    write_to_log($msg);
    return true;
}

// Check the existence and the structure of the single fields
function check_table_fields($var) {
echo "Enter into check_table_fields\n";
    global $table, $connection;
    
    $keys = array_keys($var);
    
    $query = "DESCRIBE " . $table;
    $result = $connection->Execute($query);
    $description = $result->GetRows();
    
    for($i=0; $i<count($keys); $i++) {  // Loop through all the fileds
        
        // Check the existence of the single field
        if(!in_array_multi($keys[$i],$description)){    // if the field does not exist, it is created
            $query = "ALTER TABLE `" . $table . "` ADD `" . $keys[$i] .
                     "` " . $var[$keys[$i]][0] . " " . $var[$keys[$i]][1] . " " . $var[$keys[$i]][2];
            $result = $connection->Execute($query);
            if(!$result) {
                $msg = error_message($table);
                write_to_error($msg);
                return false;
            }
            else{
                write_to_log("\tThe field '" . $keys[$i] . "' has been inserted into the table '" . $table . "'\n");
            }
        }
        
        else {  // if the field exists, its attributes are checked
            
            // Find the position of $keys[i]in the $description 2d array
            for($j=0; $j<count($description); $j++) {
                if($description[$j][0] == $keys[$i]) {
                    $index = $j;
                    break;
                }
            }

            // Extract an array from $description, suited to the comparison
            $test1 = array($description[$index][1],"","");
            if($description[$index][2] == "YES") 
                $test1[1] = "NULL";
            else
                $test1[1] = "NOT NULL";
            if($description[$index][4] == "") 
                $test1[2] = "DEFAULT NULL";
            else
                $test1[2] = "DEFAULT '" . $description[$index][4] . "'";
                
            $test2 = array($var[$keys[$i]]);
            
            if(!in_array($test1,$test2)) {
                $string = create_string_for_update($keys[$i],$var);
                $query = "ALTER TABLE `" . $table . "` CHANGE `" . $description[$index][0] . "` " . $string;
                $result = $connection->Execute($query);
                if(!$result) {
                $msg = error_message($table);
                write_to_error($msg);
                return false;
                }
                write_to_log("\tThe field '" . $keys[$i] . "' in the table '" . $table . "' has been rebuild\n");
            }
            else{
                write_to_log("\tThe field '" . $keys[$i] . "' in the table '" . $table . "' has been checked\n");
            }
        }
    }
    return true;
}

// Check table content: for a table of fix content, it empty the table and refill it
function check_table_content($var) {
echo "Enter into check_table_content\n";
    global $connection, $table;
    
    // Empty the table
    $query =  "TRUNCATE TABLE " . $table;
    $result = $connection->Execute($query);
echo "truncate table, result = " . $result . "\n";
    if(!$result) {
        $msg = error_message($table);
        write_to_error($msg);
        return false;
    }
    
    $keys = array_keys($var);
    $n_columns = count($keys);
    $n_rows = count($var[$keys[0]]);
    
echo "n columns = " . $n_columns . "\n";
echo "n rows = " . $n_rows . "\n";
    
    for($i = 0; $i < $n_rows; $i++) {    // rebuild all the records (rows) of the table
        $query = "INSERT INTO " . $table . " (" . $keys[0];
        
        for($j = 1; $j < $n_columns; $j++) {
            $query .= ", " . $keys[$j]; 
        }
        
        $query .= ") VALUES (" . $var[$keys[0]][$i];
        
        for($j = 1; $j < $n_columns; $j++) {
            $query .= ", " . $var[$keys[$j]][$i];
        }
        
        $query .= ")";
        
echo "query = " . $query . "\n";
        
        $result = $connection->Execute($query);

echo "result for insert field = " . $result . "\n";        

        if(!$result) {
            $msg = error_message($table);
            write_to_error($msg);
            return false;
        }
        
        write_to_log("\tThe record ". $var[$keys[0]][$i] ." in the table '" . $table . "' hes been checked\n");
    }
    
    return true;
}


// -----------------------------------------------------------------------------
// Script
// -----------------------------------------------------------------------------

//TODO: change $message to $msg and die() to return
//TODO: add conditions for the comparison table_structure - result of DESCRIBE (problems when an attribute is not define)
//NOTE: this rutine is not robust if the name of a field (column) of a table has been modified

// Open log file
$log_file = "run/dbupdate.log";
if (!($fh = @fopen($log_file, 'a'))) {
    write_message("Can't open the dbupdate log file.");
    return;
}
write_to_log("");

// Open error log file
$error_file = "run/dbupdate_error.log";
if (!($efh = @fopen($error_file, 'a'))) { // If the file does not exist, it is created
    write_message("Can't open the dbupdate error log file."); // If the file does not exist and cannot be created, an error message is displayed
    return;
}
write_to_error("");
 
// Connect to the database
$dsn = $db_type."://".$db_user.":".$db_password."@".$db_host."/".$db_name;
$connection = ADONewConnection($dsn); 
if(!$connection) {
    write_message("Can't connect to the database.");
    write_to_error("An error occured while connecting to the database.");
    return;
}

// Read the current database revision
$query = "SELECT * FROM global_variables";  // Check if the table global_variables exists
$result = $connection->Execute($query);
if(!$result) {  // If the table does not exist, create it
    $query = "CREATE TABLE `global_variables` (`variable` varchar(30) NOT NULL,
                                                `value` varchar(30) NOT NULL DEFAULT 0)";
    $test = $connection->Execute($query);
    if(!$test) {
       $msg = error_message("global_variables");
       write_to_error($msg);
       write_message($msg);
       return;
    }
    write_to_log("The table global_variables has been created\n");
}
$query = "SELECT value FROM global_variables WHERE variable = 'dbversion'"; // Check if the record dbversion does exist
$result = $connection->Execute($query);
$rows = $result->GetRows();
if(count($rows) == 0) { // If the record dbversion does not exist, create it and set the value to 0
    $query = "INSERT INTO global_variables (variable, value) VALUES ('dbversion','0')";
    $result = $connection->Execute($query);
    if(!$result) {
        $message = error_message("global_variables");
        write_to_error($message);
        write_message($msg);
        return;
    }
    $current_version = 0;
    write_to_log("The db version has been set to 0\n");
}
else {
    $current_version = $rows[0][0];
}


// Check database - check existence, structure and content
// -------------------------------------------------------

// Check 'boundary_values'
// -----------------------
$table = "boundary_values";
$table_structure = array("parameter"=>array("varchar(255)","NOT NULL","DEFAULT '0'"),
                        "min"=>array("varchar(30)","NULL","DEFAULT NULL"),
                        "max"=>array("varchar(30)","NULL","DEFAULT NULL"),
                        "min_included"=>array("enum('t','f')","NULL","DEFAULT 't'"),
                        "max_included"=>array("enum('t','f')","NULL","DEFAULT 't'"),
                        "standard"=>array("varchar(30)","NULL","DEFAULT NULL"));
$table_content = array("parameter"=>array("'PinholeSize'","'RemoveBackgroundPercent'","'BackgroundOffsetPercent'","'ExcitationWavelength'",
                                          "'EmissionWavelength'","'CMount'","'TubeFactor'","'CCDCaptorSizeX'",
                                          "'CCDCaptorSizeY'","'ZStepSize'","'TimeInterval'","'SignalNoiseRatio'",
                                          "'NumberOfIterations'","'QualityChangeStoppingCriterion'"),
                       "min"=>array("'0'","'0'","'0'","'0'","'0'","'0.4'","'1'","'1'","'1'","'50'","'0.001'","'0'","'1'","'0'"),
                       "max"=>array("'NULL'","'100'","''","'NULL'","'NULL'","'1'","'2'","'25000'","'25000'","'600000'","'NULL'","'100'","'100'","'NULL'"),
                       "min_included"=>array("'f'","'f'","'t'","'f'","'f'","'t'","'t'","'t'","'t'","'t'","'f'","'f'","'t'","'t'"),
                       "max_included"=>array("'t'","'t'","'f'","'t'","'t'","'t'","'t'","'t'","'t'","'t'","'t'","'t'","'t'","'t'"),
                       "standard"=>array("'NULL'","'NULL'","'NULL'","'NULL'","'NULL'","'1'","'1'","'NULL'","'NULL'","'NULL'","'NULL'","'NULL'","'NULL'","'NULL'"));
$table_existance = true;
if(!check_table_existence($table_structure,$table_existance))
    return;
if($table_existance) {  // if the table has been created by the script, the fileds are not checked
    if(!check_table_fields($table_structure))
        return;
}
if(!check_table_content($table_content))
    return;


// Check 'file_extension'
// ----------------------
$table = "file_extension";
$table_structure = array("file_format"=>array("varchar(30)","NOT NULL","DEFAULT '0'"),
                        "extension"=>array("varchar(4)","NOT NULL",""),     // is it ok here????????
                        "file_format_key"=>array("varchar(30)","NOT NULL","DEFAULT '0'"));
$table_content = array("file_format"=>array("'dv'","'ics'","'ics2'","'ims'","'lif'","'lsm'","'lsm-single'","'ome-xml'",
                                              "'pic'","'stk'","'tiff'","'tiff-leica'","'tiff-series'","'tiff-single'",
                                              "'tiff'","'tiff-leica'","'tiff-series'","'tiff-single'"),
                       "extension"=>array("'dv'","'ics'","'ics2'","'ims'","'lif'","'lsm'","'lsm'","'ome'",
                                              "'pic'","'stk'","'tif'","'tif'","'tif'","'tif'",
                                              "'tiff'","'tiff'","'tiff'","'tiff'"),
                       "file_format_key"=>array("'dv'","'ics'","'ics2'","'ims'","'lif'","'lsm'","'lsm-single'","'ome-xml'",
                                              "'pic'","'stk'","'tiff'","'tiff-leica'","'tiff-series'","'tiff-single'",
                                              "'tiff2'","'tiff-leica2'","'tiff-series2'","'tiff-single2'"));
$table_existance = true;
if(!check_table_existence($table_structure,$table_existance))
    return;
if($table_existance) {  // if the table has been created by the script, the fileds are not checked
    if(!check_table_fields($table_structure))
        return;
}
if(!check_table_content($table_content))
    return;


// Check 'file_format'
// -------------------
$table = "file_format";
$table_structure = array("name"=>array("varchar(30)","NOT NULL","DEFAULT '0'"),
                        "isFixedGeometry"=>array("enum('t','f')","NOT NULL","DEFAULT 't'"),
                        "isSingleChannel"=>array("enum('t','f')","NOT NULL","DEFAULT 't'"),
                        "isVariableChannel"=>array("enum('t','f')","NOT NULL","DEFAULT 't'"));
$table_content = array("name"=>array("'dv'","'ics'","'ics2'","'ims'","'lif'","'lsm'","'lsm-single'","'ome-xml'",
                                    "'pic'","'stk'","'tiff'","'tiff-leica'","'tiff-series'","'tiff-single'"),
                       "isFixedGeometry"=>array("'f'","'f'","'f'","'f'","'f'","'f'","'t'","'f'","'f'","'f'","'f'","'f'","'f'","'t'"),
                       "isSingleChannel"=>array("'f'","'f'","'f'","'f'","'f'","'f'","'f'","'f'","'f'","'f'","'f'","'f'","'f'","'f'"),
                       "isVariableChannel"=>array("'t'","'t'","'t'","'t'","'t'","'t'","'t'","'t'","'t'","'t'","'t'","'t'","'t'","'t'"));
$table_existance = true;
if(!check_table_existence($table_structure,$table_existance))
    return;
if($table_existance) {  // if the table has been created by the script, the fileds are not checked
    if(!check_table_fields($table_structure))
        return;
}
if(!check_table_content($table_content))
    return;


// Check 'geometry' 
// ----------------
$table = "geometry";
$table_structure = array("name"=>array("varchar(30)","NOT NULL","DEFAULT '0'"),
                             "isThreeDimensional"=>array("enum('t','f')","NULL","DEFAULT NULL"),
                             "isTimeSeries"=>array("enum('t','f')","NULL","DEFAULT NULL"));
$table_content = array("name"=>array("'XYZ'","'XYZ - time'","'XY - time'"), 
                       "isThreeDimensional"=>array("'t'","'t'","'f'"),
                       "isTimeSeries"=>array("'f'","'t'","'t'"));
$table_existance = true;
if(!check_table_existence($table_structure,$table_existance))
    return;
if($table_existance) {  // if the table has been created by the script, the fileds are not checked
    if(!check_table_fields($table_structure))
        return;
}
if(!check_table_content($table_content))
    return;


// Check 'global_variables'
// ------------------------
$table = "global_variables";
$table_structure = array("variable"=>array("varchar(30)","NOT NUL",""),
                        "value"=>array("varchar(30)","NOT NULL","DEFAULT '0'"));
$table_content = array("variable"=>array("'dbversion'"),
                       "value"=>array("'" . $current_version . "'"));
$table_existance = true;
if(!check_table_existence($table_structure,$table_existance))
    return;
if($table_existance) {  // if the table has been created by the script, the fileds are not checked
    if(!check_table_fields($table_structure))
        return;
}
if(!check_table_content($table_content))
    return;



// Check 'possible_values'
// -----------------------
$table = "possible_values";
$table_structure = array("parameter"=>array("varchar(30)","NOT NULL","DEFAULT '0'"),
                        "value"=>array("varchar(255)","NULL","DEFAULT NULL"),
                        "translation"=>array("varchar(50)","NULL","DEFAULT NULL"),
                        "isDefault"=>array("enum('t','f')","NULL","DEFAULT 'f'"),
                        "parameter_key"=>array("varchar(30)","NOT NULL","DEFAULT '0'"));
$table_content = array("parameter"=>array("'IsMultiChannel'","'IsMultiChannel'",
                                          "'ImageFileFormat'","'ImageFileFormat'","'ImageFileFormat'","'ImageFileFormat'","'ImageFileFormat'","'ImageFileFormat'","'ImageFileFormat'","'ImageFileFormat'",
                                          "'NumberOfChannels'","'NumberOfChannels'","'NumberOfChannels'","'NumberOfChannels'",
                                          "'ImageGeometry'","'ImageGeometry'","'ImageGeometry'",
                                          "'MicroscopeType'","'MicroscopeType'","'MicroscopeType'","'MicroscopeType'",
                                          "'ObjectiveMagnification'","'ObjectiveMagnification'","'ObjectiveMagnification'","'ObjectiveMagnification'",
                                          "'ObjectiveType'","'ObjectiveType'","'ObjectiveType'",
                                          "'SampleMedium'","'SampleMedium'",
                                          "'Binning'","'Binning'","'Binning'","'Binning'","'Binning'",
                                          "'MicroscopeName'","'MicroscopeName'","'MicroscopeName'","'MicroscopeName'","'MicroscopeName'","'MicroscopeName'","'MicroscopeName'","'MicroscopeName'",
                                          "'Resolution'","'Resolution'","'Resolution'","'Resolution'","'Resolution'",
                                          "'RemoveNoiseEffectiveness'","'RemoveNoiseEffectiveness'","'RemoveNoiseEffectiveness'",
                                          "'OutputFileFormat'","'OutputFileFormat'","'OutputFileFormat'","'OutputFileFormat'","'OutputFileFormat'",
                                          "'ObjectiveMagnification'","'ObjectiveMagnification'",
                                          "'PointSpreadFunction'","'PointSpreadFunction'",
                                          "'HasAdaptedValues'","'HasAdaptedValues'",
                                          "'ImageFileFormat'","'ImageFileFormat'","'ImageFileFormat'","'ImageFileFormat'","'ImageFileFormat'",
                                          "'ObjectiveType'"),
                       "value"=>array("'True'","'False'",
                                      "'dv'","'stk'","'tiff-series'","'tiff-single'","'ims'","'lsm'","'lsm-single'","'pic'",
                                      "'1'","'2'","'3'","'4'",
                                      "'XYZ'","'XY - time'","'XYZ - time'",
                                      "'widefield'","'multipoint confocal (spinning disk)'","'single point confocal'","'two photon '",
                                      "'10'","'20'","'25'","'40'",
                                      "'oil'","'water'","'air'",
                                      "'water / buffer'","'liquid vectashield / 90-10 (v:v) glycerol - PBS ph 7.4'",
                                      "'1'","'2'","'3'","'4'","'5'",
                                      "'Zeiss 510'","'Zeiss 410'","'Zeiss Two Photon 1'","'Zeiss Two Photon 2'","'Leica DMRA'","'Leica DMRB'","'Leica Two Photon 1'","'Leica Two Photon 2'",
                                      "'128'","'256'","'512'","'1024'","'2048'",
                                      "'1'","'2'","'3'",
                                      "'TIFF 8-bit'","'TIFF 16-bit'","'IMS (Imaris Classic)'","'ICS (Image Cytometry Standard)'","'OME-XML'",
                                      "'63'","'100'",
                                      "'theoretical'","'measured'",
                                      "'True'","'False'",
                                      "'ome-xml'","'tiff'","'lif'","'tiff-leica'","'ics'",
                                      "'glycerol'"),
                       "translation"=>array("''","''",
                                            "'Delta Vision (*.dv)'","'Metamorph (*.stk)'","'Numbered TIFF series (*.tif, *.tiff)'","'TIFF (*.tif, *.tiff) single XY plane'","'Imaris Classic (*.ims)'","'Zeiss (*.lsm)'","'Zeiss (*.lsm) single XY plane'","'Biorad (*.pic)'",
                                            "''","''","''","''",
                                            "''","''","''",
                                            "'widefield'","'nipkow'","'confocal'","'widefield'",
                                            "''","''","''","''",
                                            "'1.515'","'1.3381'","'1.0'",
                                            "'1.339 '","'1.47'",
                                            "''","''","''","''","''",
                                            "''","''","''","''","''","''","''","''",
                                            "''","''","''","''","''",
                                            "''","''","''",
                                            "'tiff'","'tiff16'","'imaris'","'ics'","'ome'",
                                            "''","''",
                                            "''","''",
                                            "''","''",
                                            "'OME-XML (*.ome)'","'Olympus TIFF (*.tif, *.tiff)'","'Leica (*.lif)'","'Leica TIFF series (*.tif, *.tiff)'","'Image Cytometry Standard (*.ics/*.ids)'",
                                            "'1.4729'"),
                       "isDefault"=>array("'f'","'f'",
                                          "'f'","'f'","'f'","'f'","'f'","'f'","'f'","'f'",
                                          "'f'","'f'","'f'","'f'",
                                          "'f'","'f'","'f'",
                                          "'f'","'f'","'f'","'f'",
                                          "'f'","'f'","'f'","'f'",
                                          "'f'","'f'","'f'",
                                          "'f'","'f'", 
                                          "'f'","'f'","'f'","'f'","'f'",
                                          "'f'","'f'","'f'","'f'","'f'","'f'","'f'","'f'",
                                          "'f'","'f'","'f'","'f'","'f'",
                                          "'f'","'f'","'f'",
                                          "'f'","'f'","'t'","'f'","'f'",
                                          "'f'","'f'",
                                          "'f'","'f'",
                                          "'f'","'f'",
                                          "'f'","'f'","'f'","'f'","'f'",
                                          "'f'"),
                       "parameter_key"=>array("'IsMultiChannel1'","'IsMultiChannel2'",
                                              "'ImageFileFormat1'","'ImageFileFormat2'","'ImageFileFormat3'","'ImageFileFormat4'","'ImageFileFormat5'","'ImageFileFormat6'","'ImageFileFormat7'","'ImageFileFormat8'",
                                              "'NumberOfChannels1'","'NumberOfChannels2'","'NumberOfChannels3'","'NumberOfChannels4'",
                                              "'ImageGeometry1'","'ImageGeometry2'","'ImageGeometry3'",
                                              "'MicroscopeType1'","'MicroscopeType2'","'MicroscopeType3'","'MicroscopeType4'",
                                              "'ObjectiveMagnification1'","'ObjectiveMagnification2'","'ObjectiveMagnification3'","'ObjectiveMagnification4'",
                                              "'ObjectiveType1'","'ObjectiveType2'","'ObjectiveType3'",
                                              "'SampleMedium1'","'SampleMedium2'",
                                              "'Binning1'","'Binning2'","'Binning3'","'Binning4'","'Binning5'",
                                              "'MicroscopeName1'","'MicroscopeName2'","'MicroscopeName3'","'MicroscopeName4'","'MicroscopeName5'","'MicroscopeName6'","'MicroscopeName7'","'MicroscopeName8'",
                                              "'Resolution1'","'Resolution2'","'Resolution3'","'Resolution4'","'Resolution5'",
                                              "'RemoveNoiseEffectiveness1'","'RemoveNoiseEffectiveness2'","'RemoveNoiseEffectiveness3'",
                                              "'OutputFileFormat1'","'OutputFileFormat2'","'OutputFileFormat3'","'OutputFileFormat4'","'OutputFileFormat5'",
                                              "'ObjectiveMagnification1'","'ObjectiveMagnification2'",
                                              "'PointSpreadFunction1'","'PointSpreadFunction2'",
                                              "'HasAdaptedValues1'","'HasAdaptedValues2'",
                                              "'ImageFileFormat1'","'ImageFileFormat2'","'ImageFileFormat3'","'ImageFileFormat4'","'ImageFileFormat5'",
                                              "'ObjectiveType'"));
$table_existance = true;
if(!check_table_existence($table_structure,$table_existance))
    return;
if($table_existance) {  // if the table has been created by the script, the fileds are not checked
    if(!check_table_fields($table_structure))
        return;
}
if(!check_table_content($table_content))
    return;



// Check existing database - check table existance and structure only
// ------------------------------------------------------------------

// Check 'job_files'
// -----------------
$table = "job_files";
$table_structure = array("job"=>array("varchar(30)","NULL","DEFAULT '0'"),
                        "owner"=>array("varchar(30)","NULL","DEFAULT '0'"),
                        "file"=>array("varchar(255)","NULL","DEFAULT '0'"));
$table_existance = true;
if(!check_table_existence($table_structure,$table_existance))
    return;
if($table_existance) {  // if the table has been created by the script, the fileds are not checked
    if(!check_table_fields($table_structure))
        return;
}


// TODO:
# job_files
# job_parameter
# job_parameter_setting
# job_queue
# job_task_parameter
# job_task_setting
# parameter_setting
# server
# task_setting
# username

// TODO: check also table content structure
# parameter
# task_parameter

// TODO: check queuemanager 



fclose($fh);

//
echo "current version = " . $current_version . "\n";
echo "last version = " . $last_version . "\n";
//



//Update possivle_values!!!! (dbversion 2)
//
//DeconvolutionAlgorithm 	qmle 	Quick Maximum Likelihood Estimation 	f
//DeconvolutionAlgorithm 	cmle 	Classic Maximum Likelihood Estimation 	f
//OutputFileFormat 	ICS2 (Image Cytometry Standard 2) 	ics2 	f

?>