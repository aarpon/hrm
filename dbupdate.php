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




// =============================================================================
// Description
// =============================================================================
// This script has the objective of updating the database linked to HRM.
// Moreover the number of the last revision for the database is contained in the
// script (this is the only place where this information can be found).
//
// When you want to change something in the database, thta is, to create a new
// database release, it is necessary to insert the modifications in the last part
// of the script and to update the variable $LAST_REVISION at the very beginning
// of the script.
//
// When running the script, three situations are possible:
// 1) a new user of HRM run the script from command line, the database does not
//    exist yet. In this case the database $db_name is created. Then all the tables
//    are created and the tables with fixed content (ex: boundary_values) are filled.
//    The admin user is created in the table username.
//    The database is updated to the last revision.
// 2) a user has his database version, but he never run this script: the table
//    global_variables does not exist and the revision number of the database is
//    unknown. In this case the table global_variable is created and the field
//    dbrevision is set to 0; the structure of all the tables is checked and
//    eventually corrected; the content of the fixed tables is checked and
//    eventually corrected; the number of # in the field value, in the tables
//    parameter and task_parameter, is checked and eventually corrected (it should
//    be 5). The content of the tables parameter, parameter_setting, task_parameter
//    and task_setting is preserved.
//    The database is updated to the last revision.
// 3) the user has a database identified by a revision number (field dbrevision
//    in the table global_variables). In this case the database is not checked and
//    it is simply updated to the last revision.


include "inc/hrm_config.inc";
include "inc/reservation_config.inc";
include $adodb;


// Database last revision
$LAST_REVISION = 3;


// For test purposes
//$db_name = "prova";


// =============================================================================
// Utility functions
// =============================================================================

// Returns a timestamp
function timestamp() {
    return date('l jS \of F Y h:i:s A');
}

// Write a message into the log file
function write_to_log($msg) {
    global $fh;
    fwrite($fh, $msg . "\n"); 
}

// Write a message into the error log file
function write_to_error($msg) {
    global $efh;
    fwrite($efh, $msg . "\n"); 
}

// Write a message to the standard output
function write_message($msg) {
    global $interface;
    global $message;
    if (isset($interface)) {
        $message .= $msg . "\n";
    }
    else echo $msg . "\n";
}

// Return an error message
function error_message($table) {
    return "An error occured while updating table " . $table . ".";
}




// =============================================================================
// Query functions
// =============================================================================

// Create a table with the specified name and fields        
function create_table($name, $fields) {
    global $datadict;
    $sqlarray = $datadict->CreateTableSQL($name, $fields);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
       $msg = error_message($name);
       write_message($msg);
       write_to_error($msg);
       return False;
    }
    $msg = $name . ": has been created\n";
    write_to_log($msg);
    return True;
}


// Drop the table with the specified name      
function drop_table($tabname) {
   global $datadict, $db;
   
   $sqlarray = $datadict->DropTableSQL($tabname);
   $rs = $datadict->ExecuteSQLArray($sqlarray);
   if($rs != 2) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return False;
   }
   $msg = $tabname . ": has been dropped\n";
   write_to_log($msg);
   return True;
}


// Insert a set of records ($records is a multidimensional associative array) into the table $tabname 
function insert_records($records,$tabname) {
    global $db;
    
    $keys = array_keys($records);
    for($i=0; $i<count($records[$keys[0]]); $i++) {
        $record = array();
        foreach($keys as $key)
            $record[$key] = $records[$key][$i];
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);
            return False;
        }
    }
    $msg = $tabname . ": records have been inserted.\n";
    write_to_log($msg);
    return True;
}


// Check the existence and the structure of a table.
// If the table does not exist, it is created;
// if a field is not correct, it is altered;
// if a field does not exist, it is added and the default value for that field is put in the record. 
function check_table_existence_and_structure($tabname,$flds) {
    global $datadict;
    
    $sqlarray = $datadict->ChangeTableSQL($tabname, $flds);
    $rs = $datadict->ExecuteSQLArray($sqlarray);    // return 0 if failed, 1 if executed all but with errors, 2 if executed successfully 
    if($rs != 2) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return False;
    }
    $msg = $tabname . ": existence and the structure have been checked.\n";
    write_to_log($msg);    
    return True;
}


// Update field dbrevision in table global_variables to $n)        
function update_dbrevision($n) {
    global $db, $current_revision;
    $tabname = "global_variables";
    $record = array();
    $record["value"] = $n;
    if (!$db->AutoExecute($tabname, $record, 'UPDATE', "name like 'dbrevision'")) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return false;
    }
    $msg = $tabname . ": dbrevision has been updated to " . $n . ".\n";
    return True;
}


// Verify the number of # in the field value, when name = $value.
// This function has been thought to check the content of tables parameter and task_parameter.
function check_number_gates($tabname, $value, $fields_set, $primary_key) {
    global $db;
    
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE name = '" . $value . "'");
    if($rs) {           
        while ($row = $rs->FetchRow()) {
            $test = substr_count($row[3], '#');
            if($test < 5) {
                $msg = $tabname . ": value '" . $row[3];
                if(strlen($row[3]) != $test) { // in this case there are characters in the field different from #
                    if(strpos($row[3],'#') != 0) {  // the # is not the first character in the field (it should be)
                        $row[3] = str_pad($row[3],strlen($row[3])+1,'#',STR_PAD_LEFT);
                        $row[3] = str_pad($row[3],strlen($row[3])+5-$test-1,'#',STR_PAD_RIGHT);
                    }
                    else {
                        $row[3] = str_pad($row[3],strlen($row[3])+5-$test,'#',STR_PAD_RIGHT);
                    }
                }
                else {  // in the field there are only #, but less then 5
                    $row[3] = str_pad($row[3],5,'#',STR_PAD_RIGHT);
                }
                for($i = 0; $i < count($fields_set); $i++) {
                    $temp[$fields_set[$i]] = $row[$i];
                }
                if(!$ret = $db->Replace($tabname,$temp,$primary_key,$autoquote=true)) {
                    $msg = error_message($tabname);
                    write_message($msg);
                    write_to_error($msg);    
                    return False;
                }
            $msg .= "' has be changed in '" . $row[3] . "'\n";
            write_to_log($msg);
            } 
        }
    }
    return True;
}


// Manage ENUM problem derived from ADODataDictionary
// (temporary function; next step: change hrm code concerning ENUM variables /
// waiting for ADODataDictionary correction)
function manage_enum($tabname, $field, $values_string, $default) {
    global $db;

    if (strcmp($default, 'NULL') != 0)
        $SQLquery = "ALTER TABLE " . $tabname ." CHANGE " . $field . " " . $field . " ENUM(" . $values_string . ") DEFAULT '" . $default . "'";
    else
        $SQLquery = "ALTER TABLE " . $tabname ." CHANGE " . $field . " " . $field . " ENUM(" . $values_string . ")";

    if(!$db->Execute($SQLquery)) {
        $msg = "An error occured while updating the table " . $tabname . ".";
        write_message($msg);
        write_to_error($msg);
        return False;
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



// =============================================================================
// Script
// =============================================================================

// -----------------------------------------------------------------------------
// Initialization
// -----------------------------------------------------------------------------

// Open log file
$log_file = "run/dbupdate.log";
if (!($fh = @fopen($log_file, 'a'))) {
    $msg = "Cannot open the dbupdate log file.";
    write_message($msg);
    write_to_error($msg);
    return;
}
chmod($log_file, 0666);
write_to_log(timestamp());

// Open error log file
$error_file = "run/dbupdate_error.log";
if (!($efh = @fopen($error_file, 'a'))) { // If the file does not exist, it is created
    $msg = "Cannot open the dbupdate error file."; // If the file does not exist and cannot be created, an error message is displayed 
    write_message($msg);
    write_to_error($msg);
    return;
}
chmod($log_file, 0666);
write_to_error(timestamp());

//  Check if the database exists; if it does not exist, create it
$dsn = $db_type."://".$db_user.":".$db_password."@".$db_host;
$db = ADONewConnection($dsn);
if(!$db) {
    $msg = "Cannot connect to database host.";
    write_message($msg);
    write_to_error($msg);
    return;
}
$datadict = NewDataDictionary($db, $db_type);   // Build a data dictionary
$databases = $db->MetaDatabases();
if (!in_array($db_name, $databases)) {
    $createDb = $datadict->CreateDatabase($db_name);
    if(!$datadict->ExecuteSQLArray($createDb)) {
        $msg = "An error occured in the creation of the HRM database.";
        write_message($msg);
        write_to_error($msg);
        return;
    }
    $msg = "The database has been created.\n";
    write_message($msg);
    write_to_log($msg);
}

// Connect to the database
$dsn = $db_type."://".$db_user.":".$db_password."@".$db_host."/".$db_name;
$db = ADONewConnection($dsn);
if(!$db) {
    $msg = "Cannot connect to the database.";
    write_message($msg);
    write_to_error($msg);
    return;
}

// Build a data dictionary to automate the creation of tables
$datadict = NewDataDictionary($db, $db_type);

// Extract the list of existing tables
$tables = $db->MetaTables("TABLES");



// -----------------------------------------------------------------------------
// Read the current database revision
// -----------------------------------------------------------------------------

// Check if the table global_variables exists
if (!in_array("global_variables", $tables)) {
    // If the table does not exist, create it
    $flds = "
        name C(30) KEY,
        value C(30) NOTNULL
    ";
    if (!create_table("global_variables", $flds)) {
        return;
    }
}

// Check if the variable dbrevision exists
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
    $current_revision = 0;
    $msg = "The database revision has been set to 0.\n";
    write_message($msg);
    write_to_log($msg);
}
else {
    $o = $rs->FetchObj();
    $current_revision = $o->value;
}


// -----------------------------------------------------------------------------
// If the current database revision is 0 (new user or user whose database is not 
// identified by a recision number), create or check all the tables
// -----------------------------------------------------------------------------

if ($current_revision == 0) { 

    // Drop and create fixed tables (structure and content)
    // -------------------------------------------------------------------------
    // -------------------------------------------------------------------------
    
    // NOTE: ENUM is not available as a portable type code, which forces us to
    //       hardcode the type string in the following descriptions, which in turn
    //       forces us to use uppercase 'T' and 'F' enum values (because of some
    //       stupid rule in adodb data dictionary class).
    
    // boundary_values
    // -------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "boundary_values";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname))   
            return;
    }
    // Create table
    $flds = ("
        parameter C(255) DEFAULT 0 PRIMARY,
        min C(30),
        max C(30),
        min_included \"enum ('t', 'f')\" DEFAULT t,
        max_included \"enum ('t', 'f')\" DEFAULT t,
        standard C(30)
    ");
    if (!create_table($tabname, $flds))
        return;
    
    // Manage enum problem
    $values_string = "'t', 'f'";
    if (!manage_enum($tabname, 'min_included', $values_string,'t'))
        return;
    if (!manage_enum($tabname, "max_included", $values_string,'t'))
        return;
    
    // Insert records in table
    $records = array("parameter"=>array("PinholeSize","RemoveBackgroundPercent","BackgroundOffsetPercent","ExcitationWavelength",
                        "EmissionWavelength","CMount","TubeFactor","CCDCaptorSizeX","CCDCaptorSizeY","ZStepSize","TimeInterval",
                        "SignalNoiseRatio","NumberOfIterations","QualityChangeStoppingCriterion"),
                     "min"=>array("0","0","0","0","0","0.4","1","1","1","50","0.001","0","1","0"),
                     "max"=>array(NULL,"100","",NULL,NULL,"1","2","25000","25000","600000",NULL,"100","100",NULL),
                     "min_included"=>array("f","f","t","f","f","t","t","t","t","t","f","f","t","t"),
                     "max_included"=>array("t","t","f","t","t","t","t","t","t","t","t","t","t","t"),
                     "standard"=>array(NULL,NULL,NULL,NULL,NULL,"1","1",NULL,NULL,NULL,NULL,NULL,NULL,NULL));
    if(!insert_records($records,$tabname))     
        return;

    
    // possible_values
    // -------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "possible_values";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname))    
            return;
    }
    // Create table
    $flds = "
        parameter C(30) NOTNULL DEFAULT 0 PRIMARY,
        value C(255) NOTNULL DEFAULT 0 PRIMARY,
        translation C(50) DEFAULT NULL,
        isDefault \"enum ('t', 'f')\" DEFAULT 'f'
    ";
    if (!create_table($tabname, $flds)) 
        return;
    
    // Manage enum problem
    $values_string = "'t', 'f'";
    if (!manage_enum($tabname, 'isDefault', $values_string, 'f'))
        return;
    
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
                                "Delta Vision (*.dv)","Metamorph (*.stk)","Numbered series","single XY plane","Imaris Classic (*.ims)","Zeiss (*.lsm)","Zeiss (*.lsm) single XY plane","Biorad (*.pic)",
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
                                "OME-XML (*.ome)","Olympus FluoView","Leica (*.lif)","Leica series","Image Cytometry Standard (*.ics/*.ids)",
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
    if(!insert_records($records,$tabname))     
        return;
    
    
    // geometry
    // -------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "geometry";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname))     
            return;
    }
    // Create table
    $flds = "
        name C(30) KEY DEFAULT 0 PRIMARY,
        isThreeDimensional \"enum ('T', 'F')\" DEFAULT NULL,
        isTimeSeries \"enum ('T', 'F')\" DEFAULT NULL
    ";
    if (!create_table($tabname, $flds))     
        return;

    // Manage enum problem
    $values_string = "'t', 'f'";
    if (!manage_enum($tabname, "isThreeDimensional", $values_string, "NULL")) 
        return;
    if (!manage_enum($tabname, "isTimeSeries", $values_string, "NULL")) 
        return;
    
    // Insert records in table
    $records = array("name"=>array("XYZ","XYZ - time","XY - time"), 
                    "isThreeDimensional"=>array("t","t","f"),
                    "isTimeSeries"=>array("f","t","t"));
    if(!insert_records($records,$tabname))    
        return;
    
    
    // file_format
    // -------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "file_format";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname))    
            return;
    }
    // Create table
    $flds = "
        name C(30) NOTNULL DEFAULT 0 PRIMARY,
        isFixedGeometry \"enum ('T', 'F')\" NOTNULL DEFAULT T PRIMARY,
        isSingleChannel \"enum ('T', 'F')\" NOTNULL DEFAULT T PRIMARY,
        isVariableChannel \"enum ('T', 'F')\" NOTNULL DEFAULT T PRIMARY
    ";
    if (!create_table($tabname, $flds))     
        return;
    
    // Manage enum problem
    $values_string = "'t', 'f'";
    if (!manage_enum($tabname, 'isFixedGeometry', $values_string, 't'))
        return;
    if (!manage_enum($tabname, 'isSingleChannel', $values_string, 't'))
        return;
     if (!manage_enum($tabname, 'isVariableChannel', $values_string, 't'))
        return;
    
    // Insert records in table
    $records = array("name"=>array("dv","ics","ics2","ims","lif","lsm","lsm-single","ome-xml","pic","stk","tiff","tiff-leica","tiff-series","tiff-single"),
                    "isFixedGeometry"=>array("f","f","f","f","f","f","t","f","f","f","f","f","f","t"),
                    "isSingleChannel"=>array("f","f","f","f","f","f","f","f","f","f","f","f","f","f"),
                    "isVariableChannel"=>array("t","t","t","t","t","t","t","t","t","t","t","t","t","t"));
    if(!insert_records($records,$tabname))     
        return;
    
    
    // file_extension
    // -------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "file_extension";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname))    
            return;
    }
    // Create table
    $flds = "
        file_format C(30) NOTNULL DEFAULT 0 PRIMARY,
        extension C(4) NOTNULL PRIMARY
    ";
    if (!create_table($tabname, $flds))   
        return;
    
    // Insert records in table
    $records = array("file_format"=>array("dv","ics","ics2","ims","lif","lsm","lsm-single","ome-xml","pic","stk","tiff","tiff-leica","tiff-series","tiff-single",
                                            "tiff","tiff-leica","tiff-series","tiff-single"),
                    "extension"=>array("dv","ics","ics2","ims","lif","lsm","lsm","ome","pic","stk","tif","tif","tif","tif",
                                            "tiff","tiff","tiff","tiff"));
    if(!insert_records($records,$tabname)) 
        return;
    
    
    // queuemanager
    // -------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "queuemanager";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname))    
            return;
    }
    // Create table
    $flds = "
        field C(30) NOTNULL DEFAULT 0 PRIMARY,
        value  \"enum ('ON', 'OFF')\" NOTNULL DEFAULT ON
    ";
    if (!create_table($tabname, $flds))   
        return;
    
    // Manage enum problem
    $values_string = "'on', 'off'";
    if (!manage_enum($tabname, 'value', $values_string, 'on'))
        return;

    // Insert records in table
    $records = array("field"=>array("switch"),
                    "value"=>array("on"));
    if(!insert_records($records,$tabname)) 
        return;
    
    
    // Drop and create fixed tables (create structure only)
    // The content is deleted
    // -------------------------------------------------------------------------
    // -------------------------------------------------------------------------
    
    // job_queue
    // -------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "job_queue";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname))     
            return;
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
    if (!create_table($tabname, $flds))    
        return;
    
    // Manage enum problem
    $values_string = "'queued', 'started', 'finished', 'broken', 'paused'";
    if (!manage_enum($tabname, 'status', $values_string, 'queued'))
        return;
    
    
    // job_files
    // -------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "job_files";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname))     
            return;
    }
    // Create table
    $flds = "
        job C(30) DEFAULT 0 PRIMARY,
        owner C(30) DEFAULT 0,
        file C(255) DEFAULT 0
    ";
    if (!create_table($tabname, $flds))     
        return;
        
        
    // job_parameter
    // -------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "job_parameter";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname))    
            return;
    }
    // Create table
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        setting C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL DEFAULT 0 PRIMARY,
        value C(255) DEFAULT NULL
    ";
    if (!create_table($tabname, $flds))    
        return;
    
    
    // job_parameter_setting
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "job_parameter_setting";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname))     
            return; 
    }
    // Create table
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL PRIMARY,
        standard \"enum ('t','f')\" DEFAULT t
    ";
    if (!create_table($tabname, $flds))    
        return;
    
    // Manage enum problem
    $values_string = "'t', 'f'";
    if (!manage_enum($tabname, 'standard', $values_string, 'f'))
        return;
    
                     
    // job_task_parameter
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "job_task_parameter";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) 
            return;
    }
    // Create table
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        setting C(30) NOTNULL PRIMARY,
        name C(30) NOTNULL PRIMARY,
        value C(255) DEFAULT NULL
    ";
    if (!create_table($tabname, $flds))    
        return;
        
        
    // job_task_setting
    // -----------------------------------------------------------------------------
    // Drop table if it exists
    $tabname = "job_task_setting";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname))    
            return;
    }
    // Create table
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL PRIMARY,
        standard \"enum('t','f')\" DEFAULT f
    ";
    if (!create_table($tabname, $flds))     
        return;
    
    // Manage enum problem
    $values_string = "'t', 'f'";
    if (!manage_enum($tabname, 'standard', $values_string, 'f'))
        return;
    
    
    // Check the existence and the structure of the tables with variable contents
    // Keep the content
    // -------------------------------------------------------------------------
    // -------------------------------------------------------------------------
    
    // parameter_setting
    // -------------------------------------------------------------------------
    $tabname = "parameter_setting";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL PRIMARY,
        standard \"enum('t','f')\" DEFAULT 'f'
    ";
    if(!check_table_existence_and_structure($tabname,$flds))     
        return;
    
    // Manage enum problem
    $values_string = "'t', 'f'";
    if (!manage_enum($tabname, 'standard', $values_string, 'f'))
        return;
    
    
    // task_setting
    // -------------------------------------------------------------------------
    $tabname = "task_setting";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL PRIMARY,
        standard \"enum('t','f')\" DEFAULT 'f'
    ";
    if(!check_table_existence_and_structure($tabname,$flds))     
        return;
    
    // Manage enum problem
    $values_string = "'t', 'f'";
    if (!manage_enum($tabname, 'standard', $values_string, 'f'))
        return;
    
    
    // server
    // -------------------------------------------------------------------------
    $tabname = "server";
    $flds = "
        name C(60) NOTNULL DEFAULT 0 PRIMARY,
        huscript_path C(60) NOTNULL,
        status \"enum ('busy', 'disconnected', 'free')\" NOTNULL DEFAULT 'free',
        job C(30) DEFAULT NULL
    ";
    if(!check_table_existence_and_structure($tabname,$flds))     
        return;
    
    // Manage enum problem
    $values_string = "'busy', 'disconnected', 'free'";
    if (!manage_enum($tabname, 'status', $values_string, 'free'))
        return;
    
    
        // username
    // -------------------------------------------------------------------------
    $tabname = "username";
    $flds = "
        name C(30) NOTNULL PRIMARY,
        password C(255) NOTNULL,
        email C(80) NOTNULL,
        research_group C(30) NOTNULL,
        creation_date T NOTNULL DEFAULT 'CURRENT_TIMESTAMP',
        last_access_date T NOTNULL DEFAULT '0000-00-00 00:00:00',
        status C(10) NOTNULL
    ";
    if (in_array($tabname, $tables)) {
        if(!check_table_existence_and_structure($tabname,$flds))     
            return;
    }
    else {
        if (!create_table($tabname, $flds))   
            return;
    }
    $rs = $db->Execute("SELECT * FROM username WHERE name = 'admin'");
    if($rs->EOF) {
        $records = array("name"=>array("admin"),
                    "password"=>array("e903fece385fd2167780216958310b0d"),
                    "email"=>array(" "),
                    "research_group"=>array(" "),
                    "creation_date"=>array(" "),
                    "last_access"=>array(" "),
                    "status"=>array("a")
                    );
        if(!insert_records($records,$tabname)) 
            return;
    }
    

    // Check the existence and the structure of the tables with variable contents;
    // check the format of the records content (number of #).
    // Keep the content
    // -------------------------------------------------------------------------
    // -------------------------------------------------------------------------
    
    // task_parameter
    // -------------------------------------------------------------------------
    $tabname = "task_parameter";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        setting C(30) NOTNULL PRIMARY,
        name C(30) NOTNULL PRIMARY,
        value C(255) DEFAULT NULL
    ";
    if(!check_table_existence_and_structure($tabname,$flds))    
        return;
    $fields_set = array('owner','setting','name','value');
    $primary_key = array('owner','setting','name');
    // Verify fields (number of #) where value = 'NumberOfIterationsRange'
    if(!check_number_gates($tabname,'NumberOfIterationsRange',$fields_set,$primary_key)) 
        return;
    // Verify fields (number of #) where value = 'RemoveBackgroundPercent'
    if(!check_number_gates($tabname,'RemoveBackgroundPercent',$fields_set,$primary_key)) 
        return;
    // Verify fields (number of #) where value = 'SignalNoiseRatio'
    if(!check_number_gates($tabname,'SignalNoiseRatio',$fields_set,$primary_key)) 
        return;
    // Verify fields (number of #) where value = 'SignalNoiseRatioRange'
    if(!check_number_gates($tabname,'SignalNoiseRatioRange',$fields_set,$primary_key)) 
        return;
    // Verify fields (number of #) where value = 'BackgroundOffsetPercent'
    if(!check_number_gates($tabname,'BackgroundOffsetPercent',$fields_set,$primary_key)) 
        return;
    // Verify fields (number of #) where value = 'BackgroundOffsetRange'
    if(!check_number_gates($tabname,'BackgroundOffsetRange',$fields_set,$primary_key)) 
        return;

    
    // parameter
    // -------------------------------------------------------------------------
    $tabname = "parameter";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        setting C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL DEFAULT 0 PRIMARY,
        value C(255) DEFAULT NULL
    ";
    if(!check_table_existence_and_structure($tabname,$flds))   
        return;
    $fields_set = array('owner','setting','name','value');
    $primary_key = array('owner','setting','name');
    // Verify fields (number of #) where value = 'PSF'
    if(!check_number_gates($tabname,'PSF',$fields_set,$primary_key)) 
        return;
    // Verify fields (number of #) where value = 'PinholeSize'
    if(!check_number_gates($tabname,'PinholeSize',$fields_set,$primary_key)) 
        return;
    // Verify fields (number of #) where value = 'EmissionWavelength'
    if(!check_number_gates($tabname,'EmissionWavelength',$fields_set,$primary_key)) 
        return;
    // Verify fields (number of #) where value = 'ExcitationWavelength'
    if(!check_number_gates($tabname,'ExcitationWavelength',$fields_set,$primary_key)) 
        return;
}



// -----------------------------------------------------------------------------
// Update the database to the last revision
// -----------------------------------------------------------------------------
$msg = "The last available revision for the HRM database is the number " . $LAST_REVISION . ".\n";
$msg .= "The revision of your HRM database before this update was " . $current_revision . ".\n";
write_message($msg);
write_to_log($msg);


      
// -----------------------------------------------------------------------------
// Update to revision 1
// Description: add qmle algorithm as option
// -----------------------------------------------------------------------------   
$n = 1;
if ($current_revision < $n) {
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "DeconvolutionAlgorithm";
    $record["value"] = "cmle";
    $record["translation"] = "Classic Maximum Likelihood Estimation";
    $record["isDefault"] = "t";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = "An error occured while updateing the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }
    
    $record["value"] = "qmle";
    $record["translation"] = "Quick Maximum Likelihood Estimation";
    $record["isDefault"] = "f";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = "An error occured while updateing the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }
    
    if(!update_dbrevision($n)) 
        return;
    
    $current_revision = $n;
    $msg = "Your HRM database has been updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);
}
   


// -----------------------------------------------------------------------------
// Update to revision 2
// Description: add ICS2 as possible output file format
// -----------------------------------------------------------------------------
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
        $msg = "An error occured while updateing the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }
    
    if(!update_dbrevision($n)) 
        return;
    
    $current_revision = $n;
    $msg = "Your HRM database has been updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);
}



// -----------------------------------------------------------------------------
// Update to revision 3
// Description: remove psf generation in script (check sample orientation)
// -----------------------------------------------------------------------------
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
        $msg = "An error occured while updateing the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }
    
    $record["value"] = "top";
    $record["translation"] = "Plane 0 is farthest from the coverslip";
    $record["isDefault"] = "F";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = "An error occured while updateing the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }
    
    $record["value"] = "ignore";
    $record["translation"] = "Do not perform depth-dependent correction";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = "An error occured while updateing the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }
    
    if(!update_dbrevision($n)) 
        return;
    
    $current_revision = $n;
    $msg = "Your HRM database has been updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);
}

$msg = "\nThe current revision of your HRM database is " . $current_revision . ".";
write_message($msg);
write_to_log($msg);

fclose($fh);

return;

?>