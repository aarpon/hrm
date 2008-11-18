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


// Last database revision
$LAST_REVISION = 3;


// For test purposes
$db_name = "hrm-test";




// =============================================================================
// Utility functions
// =============================================================================

// Returns a timestamp
function timestamp() {
    return date('l jS \of F Y h:i:s A');
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
    //global $interface;
    //global $message;
    if (isset($interface)) {
        $message = "            <p class=\"warning\">" . $msg . "</p>\n";
    }
    else echo $msg."\n";
}

// Return an error message
function error_message($table) {
    return "An error occured while updating table " . $table . ".";
}




// =============================================================================
// Query functions
// =============================================================================

// Create a table with the specified name and fields        OK
function create_table($name, $fields) {
    global $datadict;
    $sqlarray = $datadict->CreateTableSQL($name, $fields);
    foreach ($sqlarray as $str) {
        echo $str."\n";
    }
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
echo "An error occured in creating table " . $name . "\n";
       $msg = error_message($name);
       write_message($msg);
       write_to_error($msg);
       return False;
    }
echo "Table " . $name . " has been created\n";
    write_to_log("Table " . $name . " has been created.");
    return True;
}

// Drop the table with the specified name       OK
function drop_table($tabname) {
   global $datadict, $db;
   
   $sqlarray = $datadict->DropTableSQL($tabname);
   $rs = $datadict->ExecuteSQLArray($sqlarray);
   if($rs != 2) {
echo "An error occured in dropping table " . $tabname . "\n";
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return False;
   }
echo "Table " . $tabname . " has been dropped\n";
   write_to_log("Table " . $tabname . " has been dropped.");
   return True;
}

// Insert a set of records ($records in a multidimensional associative array) into the table $tabname       OK
function insert_records($records,$tabname) {
    global $db;
    
    $keys = array_keys($records);
    for($i=0; $i<count($records[$keys[0]]); $i++) {
        $record = array();
        foreach($keys as $key)
            $record[$key] = $records[$key][$i];
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        $db->Execute($insertSQL);
    }
    return True;
}

// Check the existence and the structure of a table     OK
// If the table does not exist, it is created;
// if a field is not correct, it is altered;
// if a field does not exist, it is added and the default value for that field is put in the records 
function check_table_existence_and_structure($tabname,$flds) {
    global $datadict;
    
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2)
        return False;
    return True;
}

// Update dbrevision table global_variables (to revidision $n)        OK
function update_dbrevision($n) {
    global $db, $current_revision;
    $tabname = "global_variables";
    $record = array();
    $record["value"] = $n;
    if (!$db->AutoExecute($tabname, $record, 'UPDATE', "name like 'dbrevision'")) {
        write_to_error("An error occured while updateing the database to revision " . $n . ".");
        return false;
    }
    if($current_revision < $n) {
        $msg = "The database has been updated to revision " . $n . ".";
        write_to_log($msg);
        write_message($msg);
    }
    return True;
}

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
function check_table_existence($var,$table_existence) {
echo "Enter into check_table_existence\n";
    global $table, $connection;

    $query = "SELECT * FROM ". $table;   
    $result = $connection->Execute($query);
    
    if(!$result) {  // the table does not exist
        $table_existence = false;
        
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
 
echo "query for check table existence = " . $query . "\n";

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




// =============================================================================
// Script
// =============================================================================


//TODO: add conditions for the comparison table_structure - result of DESCRIBE (problems when an attribute is not define)
//NOTE: this rutine is not robust if the name of a field (column) of a table has been modified


// -----------------------------------------------------------------------------
// Initialization
// -----------------------------------------------------------------------------

// Open log file
$log_file = "run/dbupdate.log";
if (!($fh = @fopen($log_file, 'a'))) {
    write_message("Can't open the dbupdate log file.");
    return;
}
write_to_log(timestamp());

// Open error log file
$error_file = "run/dbupdate_error.log";
if (!($efh = @fopen($error_file, 'a'))) { // If the file does not exist, it is created
    write_message("Can't open the dbupdate error log file."); // If the file does not exist and cannot be created, an error message is displayed
    return;
}
write_to_error(timestamp());

// Connect to the database
$dsn = $db_type."://".$db_user.":".$db_password."@".$db_host."/".$db_name;
$db = ADONewConnection($dsn); 
if(!$db) {
    write_message("Can't connect to the database.");
    write_to_error("An error occured while connecting to the database.");
    return;
}

// Construct a data dictionary to automate the creation of tables
$datadict = NewDataDictionary($db, $db_type);

// Extract the list of available tables
$tables = $db->MetaTables("TABLES");


// -----------------------------------------------------------------------------
// Read the current database revision
// -----------------------------------------------------------------------------


// Check if table global_variables exists
if (!in_array("global_variables", $tables)) {
    // If the table does not exist, create it
    $flds = "
        name C(30) KEY,
        value C(30) NOTNULL
    ";
    if (!create_table("global_variables", $flds)) {
        $msg = "An error occured while creating the table \"global_variables\".";
        write_message($msg);
        write_to_error($msg);
        return;
    }
}

// Check if variable dbrevision exists
$rs = $db->Execute("SELECT * FROM global_variables WHERE name = 'dbrevision'");
if ($rs->EOF) { // If the variable dbrevision does not exist, create it and set its value to 0
    $record = array();
    $record["name"] = "dbrevision";
    $record["value"] = "0";
    $insertSQL = $db->GetInsertSQL($rs, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = "An error occured while updating the table \"global_variables\".";
        write_message($msg);
        write_to_error($msg);
        return;
    }
    $msg = "The database revision has been set to 0.";
    write_message($msg);
    write_to_log($msg);
}
else {
    $o = $rs->FetchObj();
    $current_revision = $o->value;
}


// If the current revision is 0, it is necessary to (re)fill the database and eventually check its content
if ($current_revision == 0) { // DA CHIUDERE!!!!!!!!!!!!!! 

    // -----------------------------------------------------------------------------
    // Drop and Create fixed tables (structure and content)
    // -----------------------------------------------------------------------------
    
    // NOTE: ENUM is not available as a portable type code, which forces us to
    //       hardcode the type string in the following descriptions, which in turn
    //       forces us to use uppercase 'T' and 'F' enum values (because of some
    //       stupid rule in adodb data dictionary class).
    
    
    // boundary_values
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "boundary_values";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);    
        return;
        }
    }
    // Create table
    $flds = "
        parameter C(255) KEY,
        min C(30),
        max C(30),
        min_included \"enum ('T', 'F')\" DEFAULT T,
        max_included \"enum ('T', 'F')\" DEFAULT T,
        standard C(30)
    ";
    if (!create_table($tabname, $flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
    echo "An error occured in creating table " . $tabname . "\n";
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return;
    }
    else
        echo "Table " . $tabname . " has been created\n";
    // Insert records in table
    $records = array("parameter"=>array("PinholeSize","RemoveBackgroundPercent","BackgroundOffsetPercent","ExcitationWavelength",
                        "EmissionWavelength","CMount","TubeFactor","CCDCaptorSizeX","CCDCaptorSizeY","ZStepSize","TimeInterval",
                        "SignalNoiseRatio","NumberOfIterations","QualityChangeStoppingCriterion"),
                     "min"=>array("0","0","0","0","0","0.4","1","1","1","50","0.001","0","1","0"),
                     "max"=>array("NULL","100","","NULL","NULL","1","2","25000","25000","600000","NULL","100","100","NULL"),
                     "min_included"=>array("F","F","T","F","F","T","T","T","T","T","F","F","T","T"),
                     "max_included"=>array("T","T","F","T","T","T","T","T","T","T","T","T","T","T"),
                     "standard"=>array("NULL","NULL","NULL","NULL","NULL","1","1","NULL","NULL","NULL","NULL","NULL","NULL","NULL"));
    if(!insert_records($records,$tabname)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    
    
    // possible_values
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "possible_values";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);    
        return;
        }
    }
    // Create table
    $flds = "
        parameter C(30) NOTNULL DEFAULT 0,
        value C(255) DEFAULT NULL,
        translation C(50) DEFAULT NULL,
        isDefault \"enum ('T', 'F')\" DEFAULT F
    ";
    // PRIMARY KEY (parameter value)
    if (!create_table($tabname, $flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
    echo "An error occured in creating table " . $tabname . "\n";
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return;
    }
    else
        echo "Table " . $tabname . " has been created\n";
    // Insert records in table
    $records = array("parameter"=>array("IsMultiChannel","IsMultiChannel",
                                "ImageFileFormat","ImageFileFormat","ImageFileFormat","ImageFileFormat","ImageFileFormat","ImageFileFormat","ImageFileFormat","ImageFileFormat",
                                "NumberOfChannels","NumberOfChannels","NumberOfChannels","NumberOfChannels",
                                "ImageGeometry","ImageGeometry","ImageGeometry",
                                "MicroscopeType","MicroscopeType","MicroscopeType","MicroscopeType",
                                "ObjectiveMagnification","ObjectiveMagnification","ObjectiveMagnification","ObjectiveMagnification",
                                "ObjectiveType","ObjectiveType","ObjectiveType",
                                "SampleMedium","SampleMedium",
                                "Binning","Binning","Binning","Binning","Binning",
                                "MicroscopeName","MicroscopeName","MicroscopeName","MicroscopeName","MicroscopeName","MicroscopeName","MicroscopeName","MicroscopeName",
                                "Resolution","Resolution","Resolution","Resolution","Resolution",
                                "RemoveNoiseEffectiveness","RemoveNoiseEffectiveness","RemoveNoiseEffectiveness",
                                "OutputFileFormat","OutputFileFormat","OutputFileFormat","OutputFileFormat","OutputFileFormat",
                                "ObjectiveMagnification","ObjectiveMagnification",
                                "PointSpreadFunction","PointSpreadFunction",
                                "HasAdaptedValues","HasAdaptedValues",
                                "ImageFileFormat","ImageFileFormat","ImageFileFormat","ImageFileFormat","ImageFileFormat",
                                "ObjectiveType"),
                           "value"=>array("True","False",
                                "dv","stk","tiff-series","tiff-single","ims","lsm","lsm-single","pic",
                                "1","2","3","4",
                                "XYZ","XY - time","XYZ - time",
                                "widefield","multipoint confocal (spinning disk)","single point confocal","two photon",
                                "10","20","25","40",
                                "oil","water","air",
                                "water / buffer","liquid vectashield / 90-10 (v:v) glycerol - PBS ph 7.4",
                                "1","2","3","4","5",
                                "Zeiss 510","Zeiss 410","Zeiss Two Photon 1","Zeiss Two Photon 2","Leica DMRA","Leica DMRB","Leica Two Photon 1","Leica Two Photon 2",
                                "128","256","512","1024","2048",
                                "1","2","3",
                                "TIFF 8-bit","TIFF 16-bit","IMS (Imaris Classic)","ICS (Image Cytometry Standard)","OME-XML",
                                "63","100",
                                "theoretical","measured",
                                "True","False",
                                "ome-xml","tiff","lif","tiff-leica","ics",
                                "glycerol"),
                           "translation"=>array("","",
                                "Delta Vision (*.dv)","Metamorph (*.stk)","Numbered TIFF series (*.tif, *.tiff)","TIFF (*.tif, *.tiff) single XY plane","Imaris Classic (*.ims)","Zeiss (*.lsm)","Zeiss (*.lsm) single XY plane","Biorad (*.pic)",
                                "","","","",
                                "","","",
                                "widefield","nipkow","confocal","widefield",
                                "","","","",
                                "1.515","1.3381","1.0",
                                "1.339","1.47",
                                "","","","","",
                                "","","","","","","","",
                                "","","","","",
                                "","","",
                                "tiff","tiff16","imaris","ics","ome",
                                "","",
                                "","",
                                "","",
                                "OME-XML (*.ome)","Olympus TIFF (*.tif, *.tiff)","Leica (*.lif)","Leica TIFF series (*.tif, *.tiff)","Image Cytometry Standard (*.ics/*.ids)",
                                "1.4729"),
                           "isDefault"=>array("f","f",
                                "f","f","f","f","f","f","f","f",
                                "f","f","f","f",
                                "f","f","f",
                                "f","f","f","f",
                                "f","f","f","f",
                                "f","f","f",
                                "f","f",
                                "f","f","f","f","f",
                                "f","f","f","f","f","f","f","f",
                                "f","f","f","f","f",
                                "f","f","f",
                                "f","f","t","f","f",
                                "f","f",
                                "f","f",
                                "f","f",
                                "f","f","f","f","f",
                                "f"),
                           "parameter_key"=>array("IsMultiChannel1","IsMultiChannel2",
                                "ImageFileFormat1","ImageFileFormat2","ImageFileFormat3","ImageFileFormat4","ImageFileFormat5","ImageFileFormat6","ImageFileFormat7","ImageFileFormat8",
                                "NumberOfChannels1","NumberOfChannels2","NumberOfChannels3","NumberOfChannels4",
                                "ImageGeometry1","ImageGeometry2","ImageGeometry3",
                                "MicroscopeType1","MicroscopeType2","MicroscopeType3","MicroscopeType4",
                                "ObjectiveMagnification1","ObjectiveMagnification2","ObjectiveMagnification3","ObjectiveMagnification4",
                                "ObjectiveType1","ObjectiveType2","ObjectiveType3",
                                "SampleMedium1","SampleMedium2",
                                "Binning1","Binning2","Binning3","Binning4","Binning5",
                                "MicroscopeName1","MicroscopeName2","MicroscopeName3","MicroscopeName4","MicroscopeName5","MicroscopeName6","MicroscopeName7","MicroscopeName8",
                                "Resolution1","Resolution2","Resolution3","Resolution4","Resolution5",
                                "RemoveNoiseEffectiveness1","RemoveNoiseEffectiveness2","RemoveNoiseEffectiveness3",
                                "OutputFileFormat1","OutputFileFormat2","OutputFileFormat3","OutputFileFormat4","OutputFileFormat5",
                                "ObjectiveMagnification1","ObjectiveMagnification2",
                                "PointSpreadFunction1","PointSpreadFunction2",
                                "HasAdaptedValues1","HasAdaptedValues2",
                                "ImageFileFormat1","ImageFileFormat2","ImageFileFormat3","ImageFileFormat4","ImageFileFormat5",
                                "ObjectiveType"));
    if(!insert_records($records,$tabname)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    
    
    // geometry
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "geometry";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);    
        return;
        }
    }
    // Create table
    $flds = "
        name C(30) KEY DEFAULT 0,
        isThreeDimensional \"enum ('T', 'F')\" DEFAULT NULL,
        isTimeSeries \"enum ('T', 'F')\" DEFAULT NULL
    ";
    if (!create_table($tabname, $flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
    echo "An error occured in creating table " . $tabname . "\n";
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return;
    }
    else
        echo "Table " . $tabname . " has been created\n";
    // Insert records in table
    $records = array("name"=>array("XYZ","XYZ - time","XY - time"), 
                    "isThreeDimensional"=>array("t","t","f"),
                    "isTimeSeries"=>array("f","t","t"));
    if(!insert_records($records,$tabname)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    
    
    // file_format
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "file_format";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);    
        return;
        }
    }
    // Create table
    $flds = "
        name C(30) NOTNULL DEFAULT 0,
        isFixedGeometry \"enum ('T', 'F')\" NOTNULL DEFAULT T,
        isSingleChannel \"enum ('T', 'F')\" NOTNULL DEFAULT T,
        isVariableChannel \"enum ('T', 'F')\" NOTNULL DEFAULT T
    ";
    if (!create_table($tabname, $flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
    echo "An error occured in creating table " . $tabname . "\n";
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return;
    }
    else
        echo "Table " . $tabname . " has been created\n";
    // Insert records in table
    $records = array("name"=>array("dv","ics","ics2","ims","lif","lsm","lsm-single","ome-xml","pic","stk","tiff","tiff-leica","tiff-series","tiff-single"),
                    "isFixedGeometry"=>array("F","F","F","F","F","F","T","F","F","F","F","F","F","T"),
                    "isSingleChannel"=>array("F","F","F","F","F","F","F","F","F","F","F","F","F","F"),
                    "isVariableChannel"=>array("T","T","T","T","T","T","T","T","T","T","T","T","T","T"));
    if(!insert_records($records,$tabname)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    
    
    // file_extension
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "file_extension";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);    
        return;
        }
    }
    // Create table
    $flds = "
        file_format C(30) NOTNULL DEFAULT 0,
        extension C(4) NOTNULL
    ";
    if (!create_table($tabname, $flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
    echo "An error occured in creating table " . $tabname . "\n";
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return;
    }
    else
        echo "Table " . $tabname . " has been created\n";
    // Insert records in table
    $records = array("file_format"=>array("dv","ics","ics2","ims","lif","lsm","lsm-single","ome-xml","pic","stk","tiff","tiff-leica","tiff-series","tiff-single",
                                            "tiff","tiff-leica","tiff-series","tiff-single"),
                    "extension"=>array("dv","ics","ics2","ims","lif","lsm","lsm","ome","pic","stk","tif","tif","tif","tif",
                                            "tiff","tiff","tiff","tiff"));
    if(!insert_records($records,$tabname)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    
    
    
    // -----------------------------------------------------------------------------
    // Drop and Create fixed tables (create structure only)
    // -----------------------------------------------------------------------------
    
    // job_queue
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "job_queue";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);    
        return;
        }
    }
    // Create table
    $flds = "
        id C(30) NOTNULL DEFAULT 0 PRIMARY,
        username C(30) NOTNULL,
        queued T NOTNULL DEFAULT '0000-00-00 00:00:00',
        start T DEFAULT NULL,
        stop T DEFAULT NULL,
        server C(30) DEFAULT NULL,
        process_info C(30) DEFAULT NULL,
        status \"enum ('queued', 'started', 'finished', 'broken', 'paused')\" NOTNULL DEFAULT 'queued'  
    ";
    if (!create_table($tabname, $flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
    echo "An error occured in creating table " . $tabname . "\n";
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return;
    }
    else
        echo "Table " . $tabname . " has been created\n";
    
    
    // job_files
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "job_files";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);    
        return;
        }
    }
    // Create table
    $flds = "
        job C(30) DEFAULT 0 PRIMARY,
        owner C(30) DEFAULT 0,
        file C(30) DEFAULT 0
    ";
    if (!create_table($tabname, $flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
    echo "An error occured in creating table " . $tabname . "\n";
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return;
    }
    else
        echo "Table " . $tabname . " has been created\n";
        
        
    // job_parameter
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "job_parameter";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);    
        return;
        }
    }
    // Create table
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        setting C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL DEFAULT 0 PRIMARY,
        value C(255) DEFAULT NULL
    ";
    if (!create_table($tabname, $flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
    echo "An error occured in creating table " . $tabname . "\n";
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return;
    }
    else
        echo "Table " . $tabname . " has been created\n";
    
    
    // job_parameter_setting
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "job_parameter_setting";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);    
        return;
        }
    }
    // Create table
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL DEFAULT 0 PRIMARY,
        standard \"enum ('t','f')\" DEFAULT NULL
    ";
    if (!create_table($tabname, $flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
    echo "An error occured in creating table " . $tabname . "\n";
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return;
    }
    else
        echo "Table " . $tabname . " has been created\n";
                  
    
    // job_task_parameter
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "job_task_parameter";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);    
        return;
        }
    }
    // Create table
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        setting C(30) NOTNULL PRIMARY,
        name C(30) NOTNULL PRIMARY,
        value C(255) DEFAULT NULL
    ";
    if (!create_table($tabname, $flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
    echo "An error occured in creating table " . $tabname . "\n";
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return;
    }
    else
        echo "Table " . $tabname . " has been created\n";
        
        
    // job_task_setting
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "job_task_setting";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);    
        return;
        }
    }
    // Create table
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL PRIMARY,
        standard \"enum('t','f')\" DEFAULT 'f'
    ";
    if (!create_table($tabname, $flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
    echo "An error occured in creating table " . $tabname . "\n";
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return;
    }
    else
        echo "Table " . $tabname . " has been created\n";
        
    
    
    // -----------------------------------------------------------------------------
    // Check the existence and the structure of the tables with variable contents
    // -----------------------------------------------------------------------------
    
    // parameter_setting
    // -----------------------------------------------------------------------------
    $tabname = "parameter_setting";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL PRIMARY,
        standard \"enum('t','f')\" DEFAULT 'f'
    ";
    if(!check_table_existence_and_structure($tabname,$flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    
    
    // task_setting
    // -----------------------------------------------------------------------------
    $tabname = "task_setting";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL PRIMARY,
        standard \"enum('t','f')\" DEFAULT 'f'
    ";
    if(!check_table_existence_and_structure($tabname,$flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    

    
    // -----------------------------------------------------------------------------
    // Check the existence and the structure of the tables with variable contents
    // Check the format of the records content too
    // -----------------------------------------------------------------------------
    
    // task_parameter
    // -----------------------------------------------------------------------------
    $tabname = "task_parameter";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        setting C(30) NOTNULL PRIMARY,
        name C(30) NOTNULL PRIMARY,
        value C(255) DEFAULT NULL
    ";
    if(!check_table_existence_and_structure($tabname,$flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }

    if (in_array($tabname, $tables)) {
        $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE name = 'NumberOfIterationsRange'");
        if($rs) {           
            while ($row = $rs->FetchRow()) {
echo "here: " . $row[3] . "\n";
                $test = substr_count($row[3], '#');
echo "number of #: " . $test . "\n";
                if($test < 5) {
echo "I know that there are less then 5 #\n";
                    if(strpos($row[3], '#') != 0) {
                        // concatenare un diesis all'inizio e un diesis alla fine
                        
                    }
                }
            }
        }
    }
    
    
    // parameter
    // -----------------------------------------------------------------------------
    $tabname = "parameter";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        setting C(30) NOTNULL PRIMARY,
        name C(30) NOTNULL PRIMARY,
        value C(255) DEFAULT NULL
    ";
    if(!check_table_existence_and_structure($tabname,$flds)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);    
        return;
    }
    if (in_array($tabname, $tables)) {
        $rs = $db->Execute("SELECT value FROM " . $tabname . " WHERE name = 'NumberOfIterationsRange'");
        if($rs) {
            while ($value = $rs->FetchRow()) {
                //TODO: control the format of value
            }
        }
    }
    

    

}


// -----------------------------------------------------------------------------
// Update the database to the last revision
// -----------------------------------------------------------------------------
$msg = "\nThe last available revision for the HRM database is the number " . $LAST_REVISION . ".\n";
$msg .= "The current revision of your HRM database is " . $current_revision . ".";
write_message($msg);
write_to_log($msg);

      
// -------------------------------------------------------------------------
// Update to revision 1
// Description: add qmle algorithm as option
// -------------------------------------------------------------------------   
$n = 1;
if ($current_revision < $n) {
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "DeocnvolutionAlgorithm";
    $record["value"] = "cmle";
    $record["translation"] = "Classic Maximum Likelihood Estimation";
    $record["isDefault"] = "T";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        write_to_error("An error occured while updateing the database to revision " . $n . ".");
        return;
    }
    
    $record["value"] = "qmle";
    $record["translation"] = "Quick Maximum Likelihood Estimation";
    $record["isDefault"] = "T";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        write_to_error("An error occured while updateing the database to revision " . $n . ".");
        return;
    }
    
    if(!update_dbrevision($n)) {
        return;
    }
    
    $current_revision = $n;
}
        


// -------------------------------------------------------------------------
// Update to revision 2
// Description: add ICS2 as possible output file format
// -------------------------------------------------------------------------
$n = 2;
if ($current_revision < $n) {
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "OutputFileFormat";
    $record["value"] = "ICS2 (Image Cytometry Standard 2)";
    $record["translation"] = "ics2";
    $record["isDefault"] = "F";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        write_to_error("An error occured while updateing the database to revision " . $n . ".");
        return;
    }
    
    if(!update_dbrevision($n)) {
        return;
    }
    
    $current_revision = $n;
}



// -------------------------------------------------------------------------
// Update to revision 3
// Description: remove psf generation in script (check sample orientation)
// -------------------------------------------------------------------------
$n = 3;
if ($current_revision < $n) {
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "CoverslipRelativePosition";
    $record["value"] = "bottom";
    $record["translation"] = "Plane 0 is closest to the coverslip";
    $record["isDefault"] = "T";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        write_to_error("An error occured while updateing the database to revision 2.");
        return;
    }
    
    $record["value"] = "top";
    $record["translation"] = "Plane 0 is farthest from the coverslip";
    $record["isDefault"] = "F";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        write_to_error("An error occured while updateing the database to revision 2.");
        return;
    }
    
    $record["value"] = "ignore";
    $record["translation"] = "Do not perform depth-dependent correction";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        write_to_error("An error occured while updateing the database to revision 2.");
        return;
    }
    
    if(!update_dbrevision($n)) {
        return;
    }
    
    $current_revision = $n;
}




fclose($fh);

return;

?>