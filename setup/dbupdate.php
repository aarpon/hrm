<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt


// =============================================================================
// Description
// =============================================================================
// This script has the objective of updating the database linked to HRM.
// Moreover the number of the last revision for the database is contained in the
// script (this is the only place where this information can be found).
//
// When you want to change something in the database, that is, to create a new
// database release, it is necessary to insert the modifications in the last part
// of the script and to update the constant DB_LAST_REVISION in System.inc.php.
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

// Include hrm_config.inc.php
require_once( dirname( __FILE__ ) . "/../inc/hrm_config.inc.php" );
require_once( dirname( __FILE__ ) . "/../inc/System.inc.php" );

// Database last revision
$LAST_REVISION = System::getDBLastRevision( );

// For test purposes
//$db_name = "hrm-test";


// =============================================================================
// Utility functions
// =============================================================================

// Returns a timestamp
function timestamp() {
    return date('l jS \of F Y h:i:s A');
}


// Return a timestamp for a T field in a database
function timestampADODB() {
    return date('Y-m-d H:i:s');
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
    return "An error occurred while updating table " . $table . ".";
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


// Insert a single record in the table $tabname.
// $array id the record to be insert, $colnames contains the names of the columns of the table.
// $array and $colnames are simple 1D arrays.
function insert_record($tabname, $array, $colnames) {
    global $db;

    for($i=0; $i<count($colnames); $i++)
        $record[$colnames[$i]] = $array[$i];

    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return False;
    }
    return True;
}


// Insert a column into the table $tabname
function insert_column($tabname,$fields) {
    global $datadict;

    // NOTE: ADOdb AddColumnSQL, not guaranteed to work under all situations.
    // Please document here those situations (unknown as of February 2014).
    $sqlarray = $datadict->AddColumnSQL($tabname, $fields);

    // return 0 if failed, 1 if executed all but with errors,
    // 2 if executed successfully
    $rs = $datadict->ExecuteSQLArray($sqlarray);    
    if($rs != 2) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_log($msg);
        write_to_error($msg);
        return False;
    }
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
        $msg = "An error occurred while updating the table " . $tabname . ".";
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
if (isset($logdir)==false) {
    echo "<strong>Error: the log directory was not set in the configuration!
       Please do it and try again!</strong>";
    return;
}
if ( file_exists($logdir)==false) {
    echo "<strong>Error: the log directory specified in the configuration does
       not exist! Please create it and make sure that the web server uses
       has read/write access to it!</strong>";
    return;
}

// Define the log and error_log file names
$log_file   = $logdir . "/dbupdate.log";
$error_file = $logdir . "/dbupdate_error.log";

// If the log files do not exist, we create them and set the correct file mode
foreach ( array( $log_file, $error_file ) as $currentFile ) {
    if ( file_exists($currentFile)==false) {
        if (!($fh = @fopen($currentFile, 'a'))) {
            echo "<strong>Cannot create file " . $currentFile . ".</strong>";
            return;
        }
        //Close the file
        fclose($fh);
        // Set the mode to 0666
        chmod($currentFile, 0666);
    }
}

// Now open the files for use
if (!($fh = @fopen($log_file, 'a'))) {
    echo "<strong>Cannot open the dbupdate log file!</strong>";
    return;
}
write_to_log(timestamp());

// Open error log file
if (!($efh = @fopen($error_file, 'a'))) { // If the file does not exist, it is created
    echo "<strong>Cannot open the dbupdate error file.</strong>";
    return;
}
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
$datadict = NewDataDictionary($db);   // Build a data dictionary
$databases = $db->MetaDatabases();
if (!in_array($db_name, $databases)) {
    $createDb = $datadict->CreateDatabase($db_name);
    $ret = $datadict->ExecuteSQLArray($createDb);
    if(!$ret) {
        $msg = "An error occurred in the creation of the HRM database.";
        write_message($msg);
        write_to_error($msg);
        return;
    }
    $msg = "Executed database creation query.\n";
    write_message($msg);
    write_to_log($msg);
}

// Connect to the database
$dsn = $db_type."://".$db_user.":".$db_password."@".$db_host."/".$db_name;
$db = ADONewConnection($dsn);
if(!$db) {
    $msg = "Cannot connect to the database, probably creation failed.\n".
    "Please check that database user '$db_user' exists\n".
    "and has privileges to administrate databases.";
    write_message($msg);
    write_to_error($msg);
    return;
}

// Build a data dictionary to automate the creation of tables
$datadict = NewDataDictionary($db);

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
        $msg = "Could not create the table \"global_variables\".";
        write_message($msg);
        write_to_error($msg);
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
        $msg = "An error occurred while updating the table \"global_variables\".";
        write_message($msg);
        write_to_error($msg);
        return;
    }
    $current_revision = 0;
    $msg = "Initialized database revision to 0.\n";
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
        min_included C(1) DEFAULT t,
        max_included C(1) DEFAULT t,
        standard C(30)
    ");
    if (!create_table($tabname, $flds))
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
        isDefault C(1) DEFAULT f
    ";
    if (!create_table($tabname, $flds))
        return;

    // Insert records in table
    $records = array(
             "parameter"=>array(
                        "IsMultiChannel",
                        "IsMultiChannel",
                        
                        "ImageFileFormat",
                        "ImageFileFormat",
                        "ImageFileFormat",
                        "ImageFileFormat",
                        "ImageFileFormat",
                        "ImageFileFormat",
                        "ImageFileFormat",
                        "ImageFileFormat",
                        
                        "NumberOfChannels",
                        "NumberOfChannels",
                        "NumberOfChannels",
                        "NumberOfChannels",
                        
                        "ImageGeometry",
                        "ImageGeometry",
                        "ImageGeometry",
                        
                        "MicroscopeType",
                        "MicroscopeType",
                        "MicroscopeType",
                        "MicroscopeType",
                        
                        "ObjectiveMagnification",
                        "ObjectiveMagnification",
                        "ObjectiveMagnification",
                        "ObjectiveMagnification",
                        
                        "ObjectiveType",
                        "ObjectiveType",
                        "ObjectiveType",
                        
                        "SampleMedium",
                        "SampleMedium",
                        
                        "Binning",
                        "Binning",
                        "Binning",
                        "Binning",
                        "Binning",
                        
                        "MicroscopeName",
                        "MicroscopeName",
                        "MicroscopeName",
                        "MicroscopeName",
                        "MicroscopeName",
                        "MicroscopeName",
                        "MicroscopeName",
                        "MicroscopeName",
                        
                        "Resolution",
                        "Resolution",
                        "Resolution",
                        "Resolution",
                        "Resolution",
                        
                        "RemoveNoiseEffectiveness",
                        "RemoveNoiseEffectiveness",
                        "RemoveNoiseEffectiveness",
                        
                        "OutputFileFormat",
                        "OutputFileFormat",
                        "OutputFileFormat",
                        "OutputFileFormat",
                        "OutputFileFormat",
                        
                        "ObjectiveMagnification",
                        "ObjectiveMagnification",
                        
                        "PointSpreadFunction",
                        "PointSpreadFunction",
                        
                        "HasAdaptedValues",
                        "HasAdaptedValues",
                        
                        "ImageFileFormat",
                        "ImageFileFormat",
                        "ImageFileFormat",
                        "ImageFileFormat",
                        "ImageFileFormat",
                        
                        "ObjectiveType"),
             "value"=>array(
                    "True",      /* IsMultiChannel */ 
                    "False",
                    
                    "dv",        /* ImageFileFormat */
                    "stk",
                    "tiff-series",
                    "tiff-single",
                    "ims",
                    "lsm",
                    "lsm-single",
                    "pic",
                    
                    "1",         /* NumberOfChannels */  
                    "2",
                    "3",
                    "4",
                    
                    "XYZ",       /* ImageGeometry */
                    "XY - time",
                    "XYZ - time",
                    
                    "widefield", /* MicroscopeType */
                    "multipoint confocal (spinning disk)",
                    "single point confocal",
                    "two photon",
                    
                    "10",        /* ObjectiveMagnification */ 
                    "20",
                    "25",
                    "40",
                    
                    "oil",       /* ObjectiveType */
                    "water",
                    "air",
                    
                    "water / buffer",  /* SampleMedium */
                    "liquid vectashield / 90-10 (v:v) glycerol - PBS ph 7.4",
                    
                    "1",         /* Binning */
                    "2",
                    "3",
                    "4",
                    "5",
                    
                    "Zeiss 510", /* MicroscopeName */
                    "Zeiss 410",
                    "Zeiss Two Photon 1",
                    "Zeiss Two Photon 2",
                    "Leica DMRA",
                    "Leica DMRB",
                    "Leica Two Photon 1",
                    "Leica Two Photon 2",
                    
                    "128",       /* Resolution */
                    "256",
                    "512",
                    "1024",
                    "2048",
                    
                    "1",         /* RemoveNoiseEffectiveness */
                    "2",
                    "3",
                    
                    "TIFF 8-bit", /* OutputFileFormat */
                    "TIFF 16-bit",
                    "IMS (Imaris Classic)",
                    "ICS (Image Cytometry Standard)",
                    "OME-XML",
                    
                    "63",          /* ObjectiveMagnification */
                    "100",
                    
                    "theoretical", /* PointSpreadFunction */
                    "measured",
                    
                    "True",        /* HasAdaptedValues */
                    "False",
                    
                    "ome-xml",     /* ImageFileFormat */
                    "tiff",
                    "lif",
                    "tiff-leica",
                    "ics",
                    
                    "glycerol"     /* ObjectiveType */
                    ),
             "translation"=>array(
                          "",                       /* IsMultiChannel */ 
                          "",
                          
                          "Delta Vision (*.dv)",    /* ImageFileFormat */
                          "Metamorph (*.stk)",
                          "Numbered series",
                          "single XY plane",
                          "Imaris Classic (*.ims)",
                          "Zeiss (*.lsm)",
                          "Zeiss (*.lsm) single XY plane",
                          "Biorad (*.pic)",
                          
                          "",                       /* NumberOfChannels */ 
                          "",
                          "",
                          "",
                          
                          "",                       /* ImageGeometry */
                          "",
                          "",
                          
                          "widefield",              /* MicroscopeType */
                          "nipkow",
                          "confocal",
                          "widefield",
                          
                          "",                       /* ObjectiveMagnification */ 
                          "",
                          "",
                          "",
                          
                          "1.515",                  /* ObjectiveType */
                          "1.3381",
                          "1.0",
                          
                          "1.339",                  /* SampleMedium */
                          "1.47",
                          
                          "",                       /* Binning */
                          "",
                          "",
                          "",
                          "",
                          
                          "",                       /* MicroscopeName */ 
                          "",
                          "",
                          "",
                          "",
                          "",
                          "",
                          "",
                          
                          "",                       /* Resolution */
                          "",
                          "",
                          "",
                          "",
                          
                          "",                    /* RemoveNoiseEffectiveness */
                          "",
                          "",
                          
                          "tiff",                /* OutputFileFormat */
                          "tiff16",
                          "imaris",
                          "ics",
                          "ome",                
                          
                          "",                    /* ObjectiveMagnification */
                          "",
                          
                          "",                    /* PointSpreadFunction */
                          "",
                          
                          "",                    /* HasAdaptedValues */
                          "",
                          
                          "OME-XML (*.ome)",      /* ImageFileFormat */
                          "Olympus FluoView",
                          "Leica (*.lif)",
                          "Leica series",
                          "Image Cytometry Standard (*.ics/*.ids)",
                          
                          "1.4729"                /* ObjectiveType */
                          ),
             "isDefault"=>array(
                        "f",                   /* IsMultiChannel */ 
                        "f",
                        
                        "f",                   /* ImageFileFormat */
                        "f",
                        "f",
                        "f",
                        "f",
                        "f",
                        "f",
                        "f",
                        
                        "f",                    /* NumberOfChannels */
                        "f",
                        "f",
                        "f",
                        
                        "f",                    /* ImageGeometry */
                        "f",
                        "f",
                        
                        "f",                    /* MicroscopeType */
                        "f",
                        "f",
                        "f",
                        
                        "f",                     /* ObjectiveMagnification */
                        "f",
                        "f",
                        "f",
                        
                        "f",                     /* ObjectiveType */
                        "f",
                        "f",
                        
                        "f",                     /* SampleMedium */
                        "f",
                        
                        "f",                     /* Binning */
                        "f",
                        "f",
                        "f",
                        "f",
                        
                        "f",                     /* MicroscopeName */
                        "f",
                        "f",
                        "f",
                        "f",
                        "f",
                        "f",
                        "f",
                        
                        "f",                     /* Resolution */
                        "f",
                        "f",
                        "f",
                        "f",
                        
                        "f",                /* RemoveNoiseEffectiveness */
                        "f",
                        "f",
                        
                        "f",                     /* OutputFileFormat */
                        "f",
                        "t",
                        "f",
                        "f",
                        
                        "f",                   /* ObjectiveMagnification */
                        "f",
                        
                        "f",                    /* PointSpreadFunction */
                        "f",
                        
                        "f",                    /* HasAdaptedValues */
                        "f",
                        
                        "f",                    /* ImageFileFormat */
                        "f",
                        "f",
                        "f",
                        "f",
                        
                        "f"                     /* ObjectiveType */
                        ),
             "parameter_key"=>array(
                    "IsMultiChannel1",          /* IsMultiChannel */ 
                    "IsMultiChannel2",
                    
                    "ImageFileFormat1",          /* ImageFileFormat */
                    "ImageFileFormat2",
                    "ImageFileFormat3",
                    "ImageFileFormat4",
                    "ImageFileFormat5",
                    "ImageFileFormat6",
                    "ImageFileFormat7",
                    "ImageFileFormat8",
                    
                    "NumberOfChannels1",         /* NumberOfChannels */
                    "NumberOfChannels2",
                    "NumberOfChannels3",
                    "NumberOfChannels4",
                    
                    "ImageGeometry1",            /* ImageGeometry */
                    "ImageGeometry2",
                    "ImageGeometry3",
                    
                    "MicroscopeType1",           /* MicroscopeType */
                    "MicroscopeType2",
                    "MicroscopeType3",
                    "MicroscopeType4",
                    
                    "ObjectiveMagnification1",   /* ObjectiveMagnification */
                    "ObjectiveMagnification2",
                    "ObjectiveMagnification3",
                    "ObjectiveMagnification4",
                    
                    "ObjectiveType1",                /* ObjectiveType */
                    "ObjectiveType2",
                    "ObjectiveType3",
                    
                    "SampleMedium1",                 /* SampleMedium */
                    "SampleMedium2",
                    
                    "Binning1",                      /* Binning */
                    "Binning2",
                    "Binning3",
                    "Binning4",
                    "Binning5",
                    
                    "MicroscopeName1",               /* MicroscopeName */ 
                    "MicroscopeName2",
                    "MicroscopeName3",
                    "MicroscopeName4",
                    "MicroscopeName5",
                    "MicroscopeName6",
                    "MicroscopeName7",
                    "MicroscopeName8",
                    
                    "Resolution1",                   /* Resolution */
                    "Resolution2",
                    "Resolution3",
                    "Resolution4",
                    "Resolution5",
                    
                    "RemoveNoiseEffectiveness1",  /* RemoveNoiseEffectiveness */
                    "RemoveNoiseEffectiveness2",
                    "RemoveNoiseEffectiveness3",
                    
                    "OutputFileFormat1",            /* OutputFileFormat */
                    "OutputFileFormat2",
                    "OutputFileFormat3",
                    "OutputFileFormat4",
                    "OutputFileFormat5",
                    
                    "ObjectiveMagnification1",      /* ObjectiveMagnification */
                    "ObjectiveMagnification2",
                    
                    "PointSpreadFunction1",       /* PointSpreadFunction */
                    "PointSpreadFunction2",
                    
                    "HasAdaptedValues1",          /* HasAdaptedValues */ 
                    "HasAdaptedValues2",
                    
                    "ImageFileFormat1",           /* ImageFileFormat */
                    "ImageFileFormat2",
                    "ImageFileFormat3",
                    "ImageFileFormat4",
                    "ImageFileFormat5",
                    
                    "ObjectiveType"               /* Objective Type */
                    )
                 );
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
        isThreeDimensional C(1) DEFAULT NULL,
        isTimeSeries C(1) DEFAULT NULL
    ";
    if (!create_table($tabname, $flds))
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
        isFixedGeometry C(1) NOTNULL DEFAULT t PRIMARY,
        isSingleChannel C(1) NOTNULL DEFAULT t PRIMARY,
        isVariableChannel C(1) NOTNULL DEFAULT t PRIMARY
    ";
    if (!create_table($tabname, $flds))
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
        value  C(3) NOTNULL DEFAULT on
    ";
    if (!create_table($tabname, $flds))
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
        queued T DEFAULT NULL,
        start T DEFAULT NULL,
        stop T DEFAULT NULL,
        server C(30) DEFAULT NULL,
        process_info C(30) DEFAULT NULL,
        status C(8) NOTNULL DEFAULT queued
    ";
    if (!create_table($tabname, $flds))
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
        file C(255) DEFAULT 0 PRIMARY
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
        standard C(1) DEFAULT t
    ";
    if (!create_table($tabname, $flds))
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
        standard C(1) DEFAULT f
    ";
    if (!create_table($tabname, $flds))
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
        standard C(1) DEFAULT f
    ";
    if (!in_array($tabname, $tables)) {
        if (!create_table($tabname, $flds))
            return;
    }


    // task_setting
    // -------------------------------------------------------------------------
    $tabname = "task_setting";
    //$flds = "
    //    owner C(30) NOTNULL DEFAULT 0 PRIMARY,
    //    name C(30) NOTNULL PRIMARY,
    //    standard \"enum('t','f')\" DEFAULT 'f'
    //";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL PRIMARY,
        standard C(1) DEFAULT f
    ";
    if (!in_array($tabname, $tables)) {
        if (!create_table($tabname, $flds))
            return;
    }
    //if(!check_table_existence_and_structure($tabname,$flds))
    //    return;

    // Manage enum problem
    //$values_string = "'t', 'f'";
    //if (!manage_enum($tabname, 'standard', $values_string, 'f'))
    //    return;


    // server
    // -------------------------------------------------------------------------
    $tabname = "server";
    $flds = "
        name C(60) NOTNULL DEFAULT 0 PRIMARY,
        huscript_path C(60) NOTNULL,
        status C(12) NOTNULL DEFAULT free,
        job C(30) DEFAULT NULL
    ";
    if (!in_array($tabname, $tables)) {
        if (!create_table($tabname, $flds))
            return;
        // Insert records in table
        $records = array("name"=>array("localhost"),
                    "huscript_path"=>array("/usr/local/bin/hucore"),
                    "status"=>array("free"),
                    "job"=>array(""));
        if(!insert_records($records,$tabname))
            return;
    }


    // username
    // -------------------------------------------------------------------------
    $tabname = "username";
    $defaultTimestamp = timestampADODB();
    $flds = "
        name C(30) NOTNULL PRIMARY,
        password C(255) NOTNULL,
        email C(80) NOTNULL,
        research_group C(30) NOTNULL,
        creation_date T NOTNULL DEFAULT '" . $defaultTimestamp ."',
        last_access_date T NOTNULL DEFAULT '" . $defaultTimestamp . "',
        status C(10) NOTNULL
    ";
    if (!in_array($tabname, $tables)) {
        if (!create_table($tabname, $flds))
            return;
    }

    $rs = $db->Execute("SELECT * FROM username WHERE name = 'admin'");
    if($rs->EOF) {
        $records = array("name"=>array("admin"),
                    "password"=>array("e903fece385fd2167780216958310b0d"),
                    "email"=>array(" "),
                    "research_group"=>array(" "),
                    "creation_date"=>array($defaultTimestamp),
                    "last_access"=>array($defaultTimestamp),
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
    if (!in_array($tabname, $tables)) {
        if (!create_table($tabname, $flds))
            return;
    }
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
    if (!in_array($tabname, $tables)) {
        if (!create_table($tabname, $flds))
            return;
    }
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
$msg = "Needed database revision for HRM v" .System::getHRMVersionAsString( ) .
    " is number " . $LAST_REVISION . ".\n";
$msg .= "Current database revision is number " . $current_revision . ".\n";
if( $LAST_REVISION == $current_revision ) {
    $msg .= "Nothing to do.\n";
    write_message($msg);
    write_to_log($msg);
    fclose($fh);
    fclose($efh);
    return;
} else {
    $msg .= "Updating...\n";
}
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
        $msg = "An error occurred while updating the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }

    $record["value"] = "qmle";
    $record["translation"] = "Quick Maximum Likelihood Estimation";
    $record["isDefault"] = "f";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = "An error occurred while updating the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }

    if(!update_dbrevision($n))
        return;

    $current_revision = $n;
    $msg = "Database successfully updated to revision " . $current_revision . ".";
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
        $msg = "An error occurred while updating the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }

    if(!update_dbrevision($n))
        return;

    $current_revision = $n;
    $msg = "Database successfully updated to revision " . $current_revision . ".";
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
    $rs = $db->Execute("DELETE FROM " . $tabname . " WHERE parameter = 'CoverslipRelativePosition'");
    if(!$rs) {
        $msg = "An error occurred while updating the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }

    $record = array();
    $record["parameter"] = "CoverslipRelativePosition";
    $record["value"] = "closest";
    $record["translation"] = "Plane 0 is closest to the coverslip";
    $record["isDefault"] = "T";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = "An error occurred while updating the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }

    $record["value"] = "farthest";
    $record["translation"] = "Plane 0 is farthest from the coverslip";
    $record["isDefault"] = "F";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = "An error occurred while updating the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }

    $record["value"] = "ignore";
    $record["translation"] = "Do not perform depth-dependent correction";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = "An error occurred while updating the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }

    // Check if the value are correct in parameter (correction respect to the previous version top/bottom/ignore)
    $rs = $db->Execute("SELECT * FROM parameter WHERE name = 'CoverslipRelativePosition'");
    if($rs) {
        while ($row = $rs->FetchRow()) {
            if(strcmp($row[2],'CoverslipRelativePosition') == 0) {
                if(strcmp($row[3],'top') == 0)
                    $row[3] = 'closest';
                elseif(strcmp($row[3],'bottom') == 0)
                    $row[3] = 'farthest';

                $fields_set = array('owner','setting','name','value');
                for($i = 0; $i < count($fields_set); $i++) {
                    $temp[$fields_set[$i]] = $row[$i];
                }
                $primary_key = array('owner', 'setting', 'name');
                if(!$ret = $db->Replace('parameter',$temp,$primary_key,$autoquote=true)) {
                    $msg = error_message('parameter');
                    write_message($msg);
                    write_to_error($msg);
                    return False;
                }
            }
        }
    }

    if(!update_dbrevision($n))
        return;

    $current_revision = $n;
    $msg = "Database successfully updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);
}


// -----------------------------------------------------------------------------
// Update to revision 4
// Description: support for zvi file format in HRM
// -----------------------------------------------------------------------------
$n = 4;
if ($current_revision < $n) {
    $tabname = "file_extension";
    $record = array();
    $record["file_format"] = "zvi";
    $record["extension"] = "zvi";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = "An error occurred while updating the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }

    $tabname = "file_format";
    $record = array();
    $record["name"] = "zvi";
    $record["isFixedGeometry"] = "f";
    $record["isSingleChannel"] = "f";
    $record["isVariableChannel"] = "t";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = "An error occurred while updating the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ImageFileFormat";
    $record["value"] = "zvi";
    $record["translation"] = "Zeiss Vision ZVI (*.zvi)";
    $record["isDefault"] = "f";
    $insertSQL = $db->GetInsertSQL($tabname, $record);
    if(!$db->Execute($insertSQL)) {
        $msg = "An error occurred while updating the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }

    if(!update_dbrevision($n))
        return;

    $current_revision = $n;
    $msg = "Database successfully updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);
}


// -----------------------------------------------------------------------------
// Update to revision 5
// Description: modification for Spherical Aberration correction
// -----------------------------------------------------------------------------
$n = 5;
if ($current_revision < $n) {
    $tabname = "possible_values";

    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter = 'CoverslipRelativePosition' AND value = 'ignore'");
    if (!($rs->EOF)) {
        $rss = $db->Execute("DELETE FROM " . $tabname . " WHERE parameter = 'CoverslipRelativePosition' AND value = 'ignore'");
        if(!$rss) {
            $msg = "An error occurred while updating the database to revision " . $n . ".\n";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter = 'PerformAberrationCorrection' AND translation = 'Do not perform depth-dependent correction'");
    if (!($rs->EOF)) {
        $rss = $db->Execute("DELETE FROM " . $tabname . " WHERE parameter = 'PerformAberrationCorrection' AND translation = 'Do not perform depth-dependent correction'");
        if(!$rss) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $record = array();
    $record["parameter"] = "AberrationCorrectionNecessary";
    $record["value"] = "0";
    $record["translation"] = "no";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "' AND translation='" . $record["translation"] . "' AND isDefault='" . $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $record["value"] = "1";
    $record["translation"] = "yes";
    $record["isDefault"] = "F";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "' AND translation='" . $record["translation"] . "' AND isDefault='" . $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $record["parameter"] = "PerformAberrationCorrection";
    $record["value"] = "1";
    $record["translation"] = "Yes, perform depth-dependent correction";
    $record["isDefault"] = "f";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "' AND translation='" . $record["translation"] . "' AND isDefault='" . $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $record["parameter"] = "AberrationCorrectionMode";
    $record["value"] = "automatic";
    $record["translation"] = "Perform automatic correction";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "' AND translation='" . $record["translation"] . "' AND isDefault='" . $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $record["value"] = "advanced";
    $record["translation"] = "Perform advanced correction";
    $record["isDefault"] = "F";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "' AND translation='" . $record["translation"] . "' AND isDefault='" . $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $record["parameter"] = "PSFGenerationDepth";
    $record["value"] = "0";
    $record["translation"] = "0";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "' AND translation='" . $record["translation"] . "' AND isDefault='" . $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "boundary_values";

    $record = array();
    $record["parameter"] = "PSFGenerationDepth";
    $record["min"] = "0";
    $record["max"] = "NULL";
    $record["min_included"] = "T";
    $record["max_included"] = "T";
    $record["standard"] = "0";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND min='" . $record["min"] . "' AND max='" . $record["max"] . "' AND min_included='" . $record["min_included"] . "' AND max_included='" . $record["max_included"] . "' AND standard='" . $record["standard"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    if(!update_dbrevision($n))
        return;

    $current_revision = $n;
    $msg = "Database successfully updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);
}



// -----------------------------------------------------------------------------
// Update to revision 6
// Description: change lenght of text fields (settings name and translation).
//              Correct field in possible_values
// -----------------------------------------------------------------------------
$n = 6;
if ($current_revision < $n) {

    $tabname = "parameter";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        setting C(255) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL DEFAULT 0 PRIMARY,
        value C(255) DEFAULT NULL
    ";
    $colnames = array("owner","setting","name","value");
    $multiarray = $db->GetArray("SELECT * from " . $tabname);
    if(!drop_table($tabname))
        return;
    if(!create_table($tabname, $flds))
        return;
    foreach($multiarray as $array) {
        if(!insert_record($tabname, $array, $colnames))
            return;
    }

    $tabname = "parameter_setting";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(255) NOTNULL PRIMARY,
        standard C(1) DEFAULT f
    ";
    $colnames = array("owner","name","standard");
    $multiarray = $db->GetArray("SELECT * from " . $tabname);
    if(!drop_table($tabname))
        return;
    if(!create_table($tabname, $flds))
        return;
    foreach($multiarray as $array) {
        if(!insert_record($tabname, $array, $colnames))
            return;
    }

    $tabname = "task_parameter";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        setting C(255) NOTNULL PRIMARY,
        name C(30) NOTNULL PRIMARY,
        value C(255) DEFAULT NULL
    ";
    $colnames = array("owner","setting","name","value");
    $multiarray = $db->GetArray("SELECT * from " . $tabname);
    if(!drop_table($tabname))
        return;
    if(!create_table($tabname, $flds))
        return;
    foreach($multiarray as $array) {
        if(!insert_record($tabname, $array, $colnames))
            return;
    }

    $tabname = "task_setting";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(255) NOTNULL PRIMARY,
        standard C(1) DEFAULT f
    ";
    $colnames = array("owner","name","standard");
    $multiarray = $db->GetArray("SELECT * from " . $tabname);
    if(!drop_table($tabname))
        return;
    if(!create_table($tabname, $flds))
        return;
    foreach($multiarray as $array) {
        if(!insert_record($tabname, $array, $colnames))
            return;
    }

    $tabname = "possible_values";
    $flds = "
        parameter C(30) NOTNULL DEFAULT 0 PRIMARY,
        value C(255) NOTNULL DEFAULT 0 PRIMARY,
        translation C(255) DEFAULT NULL,
        isDefault C(1) DEFAULT f
    ";
    $colnames = array("parameter","value","translation","isDefault");
    $multiarray = $db->GetArray("SELECT * from " . $tabname);
    if(!drop_table($tabname))
        return;
    if(!create_table($tabname, $flds))
        return;
    foreach($multiarray as $array) {
        if(!insert_record($tabname, $array, $colnames))
            return;
    }

    $record[$colnames[0]] = "PerformAberrationCorrection";
    $record[$colnames[1]] = "0";
    $record[$colnames[2]] = "No, do not perform depth-dependent correction";
    $record[$colnames[3]] = "T";
    $array = array("PerformAberrationCorrection","0","No, do not perform depth-dependent correction","t");
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if (!($rs->EOF)) {
        if(!$db->Execute("DELETE FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'")) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_log($msg);
            write_to_error($msg);
            return;
        }
    }
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if ($rs->EOF) {
        if(!insert_record($tabname, $array, $colnames))
            return;
    }

    $record[$colnames[0]] = "AdvancedCorrectionOptions";
    $record[$colnames[1]] = "user";
    $record[$colnames[2]] = "Deconvolution with PSF generated at user-defined depth";
    $record[$colnames[3]] = "T";
    $array = array("AdvancedCorrectionOptions","user","Deconvolution with PSF generated at user-defined depth","T");
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record[$colnames[0]] . "' AND value='" . $record[$colnames[1]] . "'");
    if (!($rs->EOF)) {
        if(!$db->Execute("DELETE FROM " . $tabname . " WHERE parameter='" . $record[$colnames[0]] . "' AND value='" . $record[$colnames[1]] . "'")) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_log($msg);
            write_to_error($msg);
            return;
        }
    }
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if ($rs->EOF) {
        if(!insert_record($tabname, $array, $colnames))
            return;
    }

    $record[$colnames[1]] = "slice";
    $record[$colnames[2]] = "Depth-dependent correction performed slice by slice";
    $record[$colnames[3]] = "F";
    $array = array("AdvancedCorrectionOptions","slice","Depth-dependent correction performed slice by slice","F");
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if (!($rs->EOF)) {
        if(!$db->Execute("DELETE FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'")) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_log($msg);
            write_to_error($msg);
            return;
        }
    }
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if ($rs->EOF) {
        if(!insert_record($tabname, $array, $colnames))
            return;
    }

    $record[$colnames[1]] = "few";
    $record[$colnames[2]] = "Depth-dependent correction performed on few bricks";
    $record[$colnames[3]] = "F";
    $array = array("AdvancedCorrectionOptions","few","Depth-dependent correction performed on few bricks","F");
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if (!($rs->EOF)) {
        if(!$db->Execute("DELETE FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'")) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_log($msg);
            write_to_error($msg);
            return;
        }
    }
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if ($rs->EOF) {
        if(!insert_record($tabname, $array, $colnames))
            return;
    }

    if(!update_dbrevision($n))
        return;

    $current_revision = $n;
    $msg = "Database successfully updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);
}


// -----------------------------------------------------------------------------
// Update to revision 7
// Description: insert statistics table;
//              add 'priority' column in table 'job_queue';
//              correct ics2 extension;
//              add support for ics2 file format in intput;
//              add support for HDF5 file format (in input and output)
// -----------------------------------------------------------------------------
$n = 7;
if ($current_revision < $n) {
    // Update tables array
    $tables = $db->MetaTables("TABLES");

    if (!in_array("statistics", $tables)) {
        // If the table does not exist, create it
        $flds = "
            id C(30) NOTNULL DEFAULT 0 PRIMARY,
            owner C(30) DEFAULT 0,
            research_group C(30) DEFAULT 0,
            start T DEFAULT NULL,
            stop T DEFAULT NULL,
            ImageFileFormat C(255) DEFAULT 0,
            OutputFileFormat C(255) DEFAULT 0,
            PointSpreadFunction C(255) DEFAULT 0,
            ImageGeometry C(255) DEFAULT 0,
            MicroscopeType C(255) DEFAULT 0
        ";
        if (!create_table("statistics", $flds)) {
            $msg = "An error occurred while updating the database to revision " . $n . ", statistics table creation.";
            write_message($msg);
            write_to_log($msg);
            write_to_error($msg);
            return;
        }
    }

    // Add 'priority' column in table 'job_queue'
    $tabname = "job_queue";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname)) {
            $msg = "An error occurred while updating the database to revision " . $n . ", job_queue 1.";
            write_message($msg);
            write_to_log($msg);
            write_to_error($msg);
            return;
        }
    }
    // Create table
    $flds = "
        id C(30) NOTNULL DEFAULT 0 PRIMARY,
        username C(30) NOTNULL,
        queued T DEFAULT NULL,
        start T DEFAULT NULL,
        stop T DEFAULT NULL,
        server C(30) DEFAULT NULL,
        process_info C(30) DEFAULT NULL,
        status C(8) NOTNULL DEFAULT queued,
        priority I NOTNULL DEFAULT 0
    ";
    if (!create_table($tabname, $flds)) {
        $msg = "An error occurred while updating the database to revision " . $n . ", job_queue 2.";
        write_message($msg);
        write_to_log($msg);
        write_to_error($msg);
        return;
    }

    // Change ics2 extension in ics
    $tabname = "file_extension";
    $record = array();
    $record["file_format"] = "ics2";
    $record["extension"] = "ics";
    if (!$db->AutoExecute($tabname, $record, 'UPDATE', "file_format like 'ics2'")) {
        $msg = "An error occurred while updating the database to revision " . $n . ", update ics2 format information.";
        write_message($msg);
        write_to_error($msg);
        return false;
    }

    // Add support for HDF5 file format
    $record = array();
    $record["file_format"] = "hdf5";
    $record["extension"] = "h5";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE file_format='" . $record["file_format"] . "' AND extension='" . $record["extension"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "file_format";
    $record = array();
    $record["name"] = "hdf5";
    $record["isFixedGeometry"] = "f";
    $record["isSingleChannel"] = "f";
    $record["isVariableChannel"] = "t";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE name='" . $record["name"] . "' AND isFixedGeometry='" . $record["isFixedGeometry"] . "' AND isSingleChannel='" . $record["isSingleChannel"] . "'AND isVariableChannel='" . $record["isVariableChannel"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ImageFileFormat";
    $record["value"] = "ics2";
    $record["translation"] = "Image Cytometry Standard 2 (*.ics/*.ids)";
    $record["isDefault"] = "f";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $record = array();
    $record["parameter"] = "ImageFileFormat";
    $record["value"] = "hdf5";
    $record["translation"] = "SVI HDF5 (.*h5)";
    $record["isDefault"] = "f";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $record = array();
    $record["parameter"] = "OutputFileFormat";
    $record["value"] = "hdf5";
    $record["translation"] = "SVI HDF5";
    $record["isDefault"] = "f";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if (!($rs->EOF)) {
        if(!$db->Execute("DELETE FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'")) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_log($msg);
            write_to_error($msg);
            return;
        }
    }

    $record = array();
    $record["parameter"] = "OutputFileFormat";
    $record["value"] = "SVI HDF5";
    $record["translation"] = "hdf5";
    $record["isDefault"] = "f";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    // Check the existence of the server table and the presence of one entry at least
    // If there is no entry, insert default values (localhost, /usr/local/bin/hucore, free, NULL)
    $tabname = "server";
    if (in_array($tabname, $tables)) {
        $rs = $db->Execute("SELECT * FROM " . $tabname);
        $temp = $rs->RecordCount();
        //write_message("Record count = " . $temp);
        if($temp == 0) {
            $records = array("name"=>array("localhost"),
                    "huscript_path"=>array("/usr/local/bin/hucore"),
                    "status"=>array("free"),
                    "job"=>array(""));
            if(!insert_records($records,$tabname)) {
                $msg = "An error occurred while updating the database to revision " . $n . ".";
                write_message($msg);
                write_to_error($msg);
                return;
            }
        }
    }

    if(!update_dbrevision($n))
        return;
    $current_revision = $n;
    $msg = "Database successfully updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);
}

// -----------------------------------------------------------------------------
// Update to revision 8
// Description: Add NumberOfChannels = 5 into possible_values
//              Update (and fix) PSFGenerationDepth in boundary_values
//              Create the confidence_levels table
//              Add a translation for the file formats to match hucore's
// -----------------------------------------------------------------------------
$n = 8;
if ($current_revision < $n) {

    // Add NumberOfChannels = 5 into possible_values
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "NumberOfChannels";
    $record["value"] = "5";
    $record["translation"] = "";
    $record["isDefault"] = "f";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    // Update (and fix) PSFGenerationDepth in boundary_values
    $tabname = "boundary_values";
    $record = array();
    $record["parameter"] = "PSFGenerationDepth";
    $record["min"] = "0";
    $record["max"] = NULL;
    $record["min_included"] = "t";
    $record["max_included"] = "t";
    $record["standard"] = "0";
    if ( !$db->AutoExecute($tabname,$record, 'UPDATE', "parameter like '" . $record["parameter"] ."'") ) {
        $msg = "An error occurred while updating the database to revision " . $n . ", update PSFGenerationDepth boundary values.";
        write_message($msg);
        write_to_error($msg);
        return false;
    }

    // Create the confidence_levels table
    // Update tables array
    $tables = $db->MetaTables("TABLES");

	// Does the confidence_levels table exist?
	if ( !in_array( "confidence_levels", $tables ) ) {

	  // Define the fields for the confidence_levels table
      $fields = "
        fileFormat C(16) NOTNULL UNIQUE PRIMARY,
        sampleSizesX C(16) NOTNULL,
		sampleSizesY C(16) NOTNULL,
		sampleSizesZ C(16) NOTNULL,
		sampleSizesT C(16) NOTNULL,
		iFacePrim C(16) NOTNULL,
		iFaceScnd C(16) NOTNULL,
		pinhole C(16) NOTNULL,
		chanCnt C(16) NOTNULL,
		imagingDir C(16) NOTNULL,
		pinholeSpacing C(16) NOTNULL,
		objQuality C(16) NOTNULL,
		lambdaEx C(16) NOTNULL,
		lambdaEm C(16) NOTNULL,
		mType C(16) NOTNULL,
		NA C(16) NOTNULL,
		RIMedia C(16) NOTNULL,
		RILens C(16) NOTNULL,
		photonCnt C(16) NOTNULL,
		exBeamFill C(16) NOTNULL
      ";

      if ( !create_table( "confidence_levels", $fields ) ) {
        $msg = "An error occurred while updating the database to revision " . $n . ", confidence_levels table creation.";
        write_message($msg);
        write_to_log($msg);
        write_to_error($msg);
        return;
      }

    }

    // Add a translation for the file formats to match them to the file formats
    // returned by hucore

	// Does the column exist already?
    $columns = $db->MetaColumnNames( 'file_format' );
    if ( !array_key_exists( strtoupper( "hucoreName"), $columns ) ) {
        $fields = "hucoreName C(30)";
        if ( !insert_column( "file_format", $fields ) ) {
          $msg = "An error occurred while updating the database to revision " . $n . ", file_format table update.";
          write_message($msg);
          write_to_log($msg);
          write_to_error($msg);
          return;
        }
    }
    $ok  = $db->AutoExecute( "file_format", array( "hucoreName" => "r3d"),   "UPDATE", "name = 'dv'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "ics"),   "UPDATE", "name = 'ics'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "ics"),   "UPDATE", "name = 'ics2'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "ims"),   "UPDATE", "name = 'ims'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "lif"),   "UPDATE", "name = 'lif'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "lsm"),   "UPDATE", "name = 'lsm'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "lsm"),   "UPDATE", "name = 'lsm-single'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "ome"),   "UPDATE", "name = 'ome-xml'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "pic"),   "UPDATE", "name = 'pic'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "stk"),   "UPDATE", "name = 'stk'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "tiff"),  "UPDATE", "name = 'tiff'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "tiff"),  "UPDATE", "name = 'tiff-series'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "tiff"),  "UPDATE", "name = 'tiff-single'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "leica"), "UPDATE", "name = 'tiff-leica'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "zvi"),   "UPDATE", "name = 'zvi'" );
    $ok &= $db->AutoExecute( "file_format", array( "hucoreName" => "hdf5"),  "UPDATE", "name = 'hdf5'" );

    if ( !$ok ) {
          $msg = "An error occurred while updating the database to revision " . $n . ", file_format table update.";
          write_message($msg);
          write_to_log($msg);
          write_to_error($msg);
          return;
        }

    // Add new parameter OverrideConfidence possible values
    $tabname = "possible_values";
    $parameter = "OverrideConfidence";
    $value = "1";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $parameter . "' AND value='" . $value . "'");
    if ($rs->EOF) {
        $records = array(
            "parameter"=>array( "OverrideConfidence", "OverrideConfidence", "OverrideConfidence", "OverrideConfidence", "OverrideConfidence" ),
            "value"=>array( "1", "2", "3", "4", "5" ),
            "translation"=>array(   "Do not override any of my parameters",
                                    "Override parameters with confidence default and better",
                                    "Override parameters with confidence estimated and better",
                                    "Override parameters with confidence reported and better",
                                    "Override parameters with confidence verified" ),
            "isDefault"=>array( "t","f", "f", "f", "f" ),
            "parameter_key"=>array("OverrideConfidence1","OverrideConfidence2","OverrideConfidence3", "OverrideConfidence4", "OverrideConfidence5" ) );
        if(!insert_records($records,"possible_values")) {
            return;
        }
    }

    // Add support for Delta Vision r3d format in table possible_values
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ImageFileFormat";
    $record["value"] = "r3d";
    $record["translation"] = "Delta Vision (*.r3d)";
    $record["isDefault"] = "f";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" . $record["parameter"] . "' AND value='" . $record["value"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    // Add support for Delta Vision r3d format in table file_extension
    $tabname = "file_extension";
    $record = array();
    $record["file_format"] = "r3d";
    $record["extension"] = "r3d";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE file_format='" . $record["file_format"] . "' AND extension='" . $record["extension"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    // Add support for Delta Vision r3d format in table file_format
    $tabname = "file_format";
    $record = array();
    $record["name"] = "r3d";
    $record["isFixedGeometry"] = "f";
    $record["isSingleChannel"] = "f";
    $record["isVariableChannel"] = "t";
    $record["hucoreName"] = "r3d";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE name='" . $record["name"] . "' AND hucoreName='" . $record["hucoreName"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    // Update revision
    if(!update_dbrevision($n))
        return;
    $current_revision = $n;
    $msg = "Database successfully updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);

}

// -----------------------------------------------------------------------------
// Update to revision 9
// Description: Add 'ismultifile' column into file_format
//              Correct description of ics2 file format
//              Add Olympus OIF as input format
//              Add Delta Vision (3rd) as output
// -----------------------------------------------------------------------------
$n = 9;
if ($current_revision < $n) {
    // Does the column exist already?
    $columns = $db->MetaColumnNames( 'file_format' );
    if ( !array_key_exists( strtoupper( "ismultifile"), $columns ) ) {

        $fields = "ismultifile C(1) NOTNULL DEFAULT 'f'";
        if ( !insert_column( "file_format", $fields ) ) {
          $msg = "An error occurred while updating the database to revision " . $n . ", file_format table update.";
          write_message($msg);
          write_to_log($msg);
          write_to_error($msg);
          return;
        }
        // Set lif to multi file
        $tabname = "global_variables";
        $record = array();
        $record["ismultifile"] = 't';
        if (!$db->AutoExecute('file_format', $record, 'UPDATE', "name like 'lif'")) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);
            return false;
        }

    }

    // Correct ics2 description
    $tabname = 'possible_values';
    $record = array();
    $record["parameter"]   = 'ImageFileFormat';
    $record["translation"] = 'Image Cytometry Standard 2 (*.ics)';
    $record["isDefault"]   = 'f';
    if (!$db->AutoExecute($tabname, $record, 'UPDATE', "value like 'ics2'")) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return false;
    }

    // Make ics the default output file format
    $tabname = 'possible_values';
    $record = array();
    $record["parameter"]   = 'OutputFileFormat';
    $record["translation"] = 'ics';
    $record["isDefault"]   = 't';
    if (!$db->AutoExecute($tabname, $record, 'UPDATE', "value like 'ICS (Image Cytometry Standard)'")) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return false;
    }

    // Add Olympus OIF file to possible_values
    $tabname = 'possible_values';
    $record = array();
    $record["parameter"]   = 'ImageFileFormat';
    $record["value"]   = 'oif';
    $record["translation"] = 'Olympus OIF file (*.oif)';
    $record["isDefault"] = 'f';
    // This is just a hack for developers; it the row is already there, skip
    $query = "SELECT * FROM " . $tabname . " WHERE parameter='" . 
        $record['parameter'] . "' AND value='" . $record['value'] . "' " .
        " AND translation='" . $record["translation"] . "' AND isDefault='" .
        $record["isDefault"] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {
        if (!$db->AutoExecute($tabname, $record, 'INSERT')) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);
            return false;
        }
    }
    // Add Olympus OIF file to file_format
    $tabname = 'file_format';
    $record = array();
    $record["name"]                = 'oif';
    $record["isFixedGeometry"]     = 'f';
    $record["isSingleChannel"]     = 'f';
    $record["isVariableChannel"]   = 't';
    $record["hucoreName"]          = 'oif';
    $record["ismultifile"]         = 'f';
    // This is just a hack for developers; it the row is already there, skip
    $query = "SELECT * FROM " . $tabname . " WHERE name='" . 
        $record['name'] . "' AND isFixedGeometry='" . 
        $record['isFixedGeometry'] . "' AND isSingleChannel='" . 
        $record["isSingleChannel"] . "' AND isVariableChannel='" .
        $record["isVariableChannel"] . "' AND hucoreName='" .
        $record["hucoreName"] . "' AND ismultifile='" .
        $record["ismultifile"] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {
        if (!$db->AutoExecute($tabname, $record, 'INSERT')) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);
            return false;
        }
    }

    // Add Olympus OIF file to file_extension
    $tabname = 'file_extension';
    $record = array();
    $record["file_format"] = 'oif';
    $record["extension"]   = 'oif';
    // This is just a hack for developers; it the row is already there, skip
    $query = "SELECT * FROM " . $tabname . " WHERE file_format='" .
        $record['file_format'] . "' AND extension='" .
        $record['extension'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {
        if (!$db->AutoExecute($tabname, $record, 'INSERT')) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);
            return false;
        }
    }

    // Add Delta Vision (*dv) as output to possible_values
    $tabname = 'possible_values';
    $record = array();
    $record["parameter"]   = 'OutputFileFormat';
    $record["value"]       = 'Delta Vision (*.r3d)';
    $record["translation"] = 'r3d';
    $record["isDefault"]   = 'f';
    // This is just a hack for developers; it the row is already there, skip
    $query = "SELECT * FROM " . $tabname . " WHERE parameter='" .
        $record['parameter'] . "' AND value='" .
        $record['value'] . "' AND translation='" .
        $record['translation'] . "' AND isDefault='" .
        $record['isDefault'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {
        if (!$db->AutoExecute($tabname, $record, 'INSERT')) {
            $msg = error_message($tabname);
            write_message($msg);
            write_to_error($msg);
            return false;
        }
    }

    // Delete the OverrideConfidence entries from possible_values
    $tabname = 'possible_values';
    $record = array();
    $record["parameter"]   = 'OverrideConfidence';
    for ( $i = 0; $i < 6; $i++ ) {
        // This is just a hack for developers; it the row was already deleted,
        // skip
        $record["value"]       = $i;
        $baseQuery = " FROM " . $tabname . " WHERE parameter='" .
            $record["parameter"] . "' AND value='" .
            $record["value"] ."'";

        $query = "SELECT *" . $baseQuery;
        if ( $db->Execute( $query )->RecordCount( ) == 1 ) {
            $query = "DELETE" . $baseQuery;
            if ( !$db->Execute( $query ) ) {
                $msg = error_message($tabname);
                write_message($msg);
                write_to_error($msg);
                return false;
            }
        }
    }

        // Update revision
    if(!update_dbrevision($n))
        return;
    $current_revision = $n;
    $msg = "Database successfully updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);
}


// -----------------------------------------------------------------------------
// Update to revision 10
// Description: Add 'Analysis' as setting and job tables.
// -----------------------------------------------------------------------------
$n = 10;
if ($current_revision < $n) {
    
// ------------  Add tables for the 'hucore_license'  ---------------------
// hucore_license
    
    $tabname = "hucore_license";
    
        // Create the hucore_license table
        // Update tables array
    $tables = $db->MetaTables("TABLES");

	// Does the hucore_license table exist?
	if ( !in_array( $tabname, $tables ) ) {
            
            $flds = "feature C(30) NOTNULL DEFAULT 0 PRIMARY";
            
            if (!create_table($tabname, $flds)) {
                $msg = "An error occurred while updating the database to ".
                       "revision " . $n . ", hucore_license table creation.";
                write_message($msg);
                write_to_log($msg);
                write_to_error($msg);

                return;
            }
        }

// ------------  Add tables for the 'analysis' templates ---------------------
// analysis_parameter
    $tabname = "analysis_parameter";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        setting C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL DEFAULT 0 PRIMARY,
        value C(255) DEFAULT NULL
    ";
    if (!in_array($tabname, $tables)) {
        if (!create_table($tabname, $flds))
            return;
    }

// analysis_setting
    $tabname = "analysis_setting";
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL PRIMARY,
        standard C(1) DEFAULT f
    ";
    if (!in_array($tabname, $tables)) {
        if (!create_table($tabname, $flds))
            return;
    }

// job_analysis_parameter
    $tabname = "job_analysis_parameter";
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

// job_analysis_setting
    $tabname = "job_analysis_setting";
    if (in_array($tabname, $tables)) {
        if (!drop_table($tabname))
            return;
    }
    // Create table
    $flds = "
        owner C(30) NOTNULL DEFAULT 0 PRIMARY,
        name C(30) NOTNULL PRIMARY,
        standard C(1) DEFAULT t
    ";
    if (!create_table($tabname, $flds))
        return;

// ------------------ Add entries to 'possible_values' -------------------------
    
    // Values for parameter 'ColocAnalysis'.
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocAnalysis";
    $record["value"] = "0";
    $record["translation"] = "No, it is not necessary.";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocAnalysis";
    $record["value"] = "1";
    $record["translation"] = "Yes, perform colocalization analysis.";
    $record["isDefault"] = "F";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocCoefficient";
    $record["value"] = "Pearson";
    $record["translation"] = "Pearson";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocCoefficient";
    $record["value"] = "ObjectPearson";
    $record["translation"] = "Object Pearson";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocCoefficient";
    $record["value"] = "Spearman";
    $record["translation"] = "Spearman";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocCoefficient";
    $record["value"] = "ObjectSpearman";
    $record["translation"] = "Object Spearman";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocCoefficient";
    $record["value"] = "overlap";
    $record["translation"] = "Overlap";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocCoefficient";
    $record["value"] = "k12";
    $record["translation"] = "k1,2";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }
    
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocCoefficient";
    $record["value"] = "i12";
    $record["translation"] = "i1,2";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocCoefficient";
    $record["value"] = "Manders";
    $record["translation"] = "M1,2";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }
    
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocCoefficient";
    $record["value"] = "inters";
    $record["translation"] = "Inters";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocMap";
    $record["value"] = "Pearson";
    $record["translation"] = "Pearson";
    $record["isDefault"] = "T";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocMap";
    $record["value"] = "ObjectPearson";
    $record["translation"] = "Object Pearson";
    $record["isDefault"] = "F";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocMap";
    $record["value"] = "Manders";
    $record["translation"] = "M1,2";
    $record["isDefault"] = "F";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ColocMap";
    $record["value"] = "overlap";
    $record["translation"] = "Overlap";
    $record["isDefault"] = "F";
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
                       $record["parameter"] . "' AND value='" .
                       $record["value"] . "' AND translation='" .
                       $record["translation"] . "' AND isDefault='" .
                       $record["isDefault"] . "'");
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision ".
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

// ------------------ Add entries to 'statistics' -------------------------
    $tabname   = "statistics";
    $newcolumn = "ColocAnalysis";
    $type = "VARCHAR(1)";
    
    // Does the column exist already?
    $columns = $db->MetaColumnNames( $tabname );
    if ( !array_key_exists( strtoupper( $newcolumn ), $columns ) ) {
        $SQLquery  = "ALTER TABLE " . $tabname . " ADD COLUMN " . $newcolumn .
            " " . $type;
        if(!$db->Execute($SQLquery)) {
            $msg = "An error occurred while updating the database to revision " .
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

// ------------------ Add entries to 'job_files' -------------------------
    $tabname   = "job_files";
    $newcolumn = "autoseries";
    $yype = "VARCHAR(1)";
    
    // Does the column exist already?
    $columns = $db->MetaColumnNames( $tabname );
    if ( !array_key_exists( strtoupper( $newcolumn ), $columns ) ) {
        $SQLquery  = "ALTER TABLE " . $tabname . " ADD COLUMN " . $newcolumn .
            " " . $type . " DEFAULT 'f'";

        if(!$db->Execute($SQLquery)) {
            $msg = "An error occurred while updating the database to revision " .
                $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

// ------------------ Add RGB TIFF 8 bit as output format ----------------------
    
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "OutputFileFormat";
    $record["value"] = "RGB TIFF 8-bit";
    $record["translation"] = "tiffrgb";
    $record["isDefault"] = "f";
    // Check if it already exists
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
            $record["parameter"] . "' AND value='" .
            $record["value"] . "' AND translation='" .
            $record["translation"] . "' AND isDefault='" .
            $record["isDefault"] . "'");
    
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    // ---------------------- Add Generic TIFF file format ---------------------
    
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ImageFileFormat";
    $record["value"] = "tiff-generic";
    $record["translation"] = "Generic TIFF (*.tiff, *.tif)";
    $record["isDefault"] = "f";
    // Check if it already exists
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
            $record["parameter"] . "' AND value='" .
            $record["value"] . "' AND translation='" .
            $record["translation"] . "' AND isDefault='" .
            $record["isDefault"] . "'");
    
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "file_format";
    $record = array();
    $record["name"] = "tiff-generic";
    $record["hucorename"] = "tiff";
    
    // Check if it already exists
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE name='" .
            $record["name"] . "' AND hucorename='" .
            $record["hucorename"] . "'");
    
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    // ---------------------- Add OME-TIFF file format -------------------------
    
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ImageFileFormat";
    $record["value"] = "ome-tiff";
    $record["translation"] = "OME-TIFF (*.ome.tiff, *.ome.tif)";
    $record["isDefault"] = "f";
    // Check if it already exists
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE parameter='" .
            $record["parameter"] . "' AND value='" .
            $record["value"] . "' AND translation='" .
            $record["translation"] . "' AND isDefault='" .
            $record["isDefault"] . "'");
    
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "file_format";
    $record = array();
    $record["name"] = "ome-tiff";
    $record["hucorename"] = "ome";
    
    // Check if it already exists
    $rs = $db->Execute("SELECT * FROM " . $tabname . " WHERE name='" .
            $record["name"] . "' AND hucorename='" .
            $record["hucorename"] . "'");
    
    if ($rs->EOF) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

// ------- Correct the translations of Olympus FluoView and Leica series -------

    // Correct Olympus FluoView description
    $tabname = 'possible_values';
    $record = array();
    $record["parameter"]   = 'ImageFileFormat';
    $record["translation"] = 'Olympus FluoView (*.tiff, *.tif)';
    $record["isDefault"]   = 'f';
    if (!$db->AutoExecute($tabname, $record, 'UPDATE', "value = 'tiff'")) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return false;
    }

    // Correct Leica series description
    $tabname = 'possible_values';
    $record = array();
    $record["parameter"]   = 'ImageFileFormat';
    $record["translation"] = 'Leica series (*.tiff, *.tif)';
    $record["isDefault"]   = 'f';
    if (!$db->AutoExecute($tabname, $record, 'UPDATE', "value = 'tiff-leica'")) {
        $msg = error_message($tabname);
        write_message($msg);
        write_to_error($msg);
        return false;
    }    
    
// ---------------------- Remove lsm-single file format ------------------------

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ImageFileFormat";
    $record["value"] = "lsm-single";
    $record["translation"] = "Zeiss (*.lsm) single XY plane";
    $record["isDefault"] = "f";
    
    // Check if it exists and delete it
    $whereClause = " WHERE parameter='" .
            $record["parameter"] . "' AND value='" .
            $record["value"] . "' AND translation='" .
            $record["translation"] . "' AND isDefault='" .
            $record["isDefault"] . "'";
    $rs = $db->Execute("SELECT * FROM " . $tabname . $whereClause );
    if (!$rs->EOF) {
        if(!$db->Execute("DELETE FROM " . $tabname . $whereClause )) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }
    
    $tabname = "file_format";
    $record = array();
    $record["name"] = "lsm-single";
    $record["hucorename"] = "lsm";
    
    // Check if it exists and delete it
    $whereClause = " WHERE name='" .
            $record["name"] . "' AND hucorename='" .
            $record["hucorename"] . "'";
    $rs = $db->Execute("SELECT * FROM " . $tabname . $whereClause );
    if (!$rs->EOF) {
        if(!$db->Execute("DELETE FROM " . $tabname . $whereClause )) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }    

// ---------------------- Remove tiff-single file format ------------------------

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ImageFileFormat";
    $record["value"] = "tiff-single";
    $record["translation"] = "single XY plane";
    $record["isDefault"] = "f";
    
    // Check if it exists and delete it
    $whereClause = " WHERE parameter='" .
            $record["parameter"] . "' AND value='" .
            $record["value"] . "' AND translation='" .
            $record["translation"] . "' AND isDefault='" .
            $record["isDefault"] . "'";
    $rs = $db->Execute("SELECT * FROM " . $tabname . $whereClause );
    if (!$rs->EOF) {
        if(!$db->Execute("DELETE FROM " . $tabname . $whereClause )) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "file_format";
    $record = array();
    $record["name"] = "tiff-single";
    $record["hucorename"] = "tiff";
    
    // Check if it exists and delete it
    $whereClause = " WHERE name='" .
            $record["name"] . "' AND hucorename='" .
            $record["hucorename"] . "'";
    $rs = $db->Execute("SELECT * FROM " . $tabname . $whereClause );
    if (!$rs->EOF) {
        if(!$db->Execute("DELETE FROM " . $tabname . $whereClause )) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }    
   
// ---------------------- Remove tiff-series file format -----------------------

    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ImageFileFormat";
    $record["value"] = "tiff-series";
    $record["translation"] = "Numbered series";
    $record["isDefault"] = "f";
    
    // Check if it exists and delete it
    $whereClause = " WHERE parameter='" .
            $record["parameter"] . "' AND value='" .
            $record["value"] . "' AND translation='" .
            $record["translation"] . "' AND isDefault='" .
            $record["isDefault"] . "'";
    $rs = $db->Execute("SELECT * FROM " . $tabname . $whereClause );
    if (!$rs->EOF) {
        if(!$db->Execute("DELETE FROM " . $tabname . $whereClause )) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $tabname = "file_format";
    $record = array();
    $record["name"] = "tiff-series";
    $record["hucorename"] = "tiff";
    
    // Check if it exists and delete it
    $whereClause = " WHERE name='" .
            $record["name"] . "' AND hucorename='" .
            $record["hucorename"] . "'";
    $rs = $db->Execute("SELECT * FROM " . $tabname . $whereClause );
    if (!$rs->EOF) {
        if(!$db->Execute("DELETE FROM " . $tabname . $whereClause )) {
            $msg = "An error occurred while updating the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }    

// -------------------- Recreate the file extension table ----------------------
    
    // Table name
    $tabname = "file_extension";

    // Drop the table if it exists (it should, obviously)
    $query = "DROP TABLE IF EXISTS " . $tabname . ";";
    if (!$db->Execute($query)) {
        $msg = "An error occurred while updating the database to revision " . $n . ".";
        write_message($msg);
        write_to_error($msg);
        return;
    }
    
    // Now recreate it
 
    // Table structure
    $flds = ("
        id I(11) NOTNULL AUTOINCREMENT PRIMARY,
        file_format C(30),
        extension C(10)
    ");
    if (!create_table($tabname, $flds))
        return;
    
    // Create the records to be added
    $records = array(
        "file_format"=>array(
            "dv", "ics", "ims", "lif", "lsm", "ome-xml", "pic", "stk", 
            "tiff-leica", "tiff-leica", "zvi", "ics2", "hdf5", "r3d",
            "oif", "tiff", "tiff", "tiff-generic", "tiff-generic", 
            "ome-tiff", "ome-tiff"),
        "extension"=>array(
            "dv", "ics", "ims", "lif", "lsm", "ome", "pic", "stk",
             "tif", "tiff", "zvi", "ics", "h5", "r3d", "oif", "tif", 
             "tiff", "tif", "tiff", "ome.tif", "ome.tiff "));

    // Insert the records
    if(!insert_records($records,$tabname))
        return;

        // Update revision
    if(!update_dbrevision($n))
        return;
    $current_revision = $n;
    $msg = "Database successfully updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);
}


// -----------------------------------------------------------------------------
// Update to revision 11
// Description: support for czi file format in HRM
// -----------------------------------------------------------------------------
$n = 11;
if ($current_revision < $n) {
    $tabname = "file_extension";
    $record = array();
    $record["file_format"] = "czi";
    $record["extension"] = "czi";

    // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .
             " WHERE file_format='" . $record['file_format'] .
             "' AND extension='" . $record['extension'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {
        $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating " .
                   "the database to revision " . $n . ".";   
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }    

    
    $tabname = "file_format";
    $record = array();
    $record["name"] = "czi";
    $record["isFixedGeometry"] = "f";
    $record["isSingleChannel"] = "f";
    $record["isVariableChannel"] = "t";
    
    // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE name='" . $record['name'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {
       $insertSQL = $db->GetInsertSQL($tabname, $record);
       if(!$db->Execute($insertSQL)) {
           $msg = "An error occurred while updating " .
                  "the database to revision " . $n . ".";
           write_message($msg);
           write_to_error($msg);
           return;
       }
    }

    
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ImageFileFormat";
    $record["value"] = "czi";
    $record["translation"] = "Carl Zeiss Image (*.czi)";
    $record["isDefault"] = "f";
    
    // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE value='" . $record['value'] .
             "' AND parameter='" . $record['parameter'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {    
       $insertSQL = $db->GetInsertSQL($tabname, $record);
       if(!$db->Execute($insertSQL)) {
           $msg = "An error occurred while updating " .
                  "the database to revision " . $n . ".";
           write_message($msg);
           write_to_error($msg);
           return;
       }
    }

    // Correct a blank too many. 
    $tabname = "file_extension";
    $query = "UPDATE file_extension SET extension = \"ome.tiff\" " .
             "WHERE extension = \"ome.tiff \"";
    $rs = $db->Execute($query);

    
    // Update revision
    if(!update_dbrevision($n))
        return;
    
    $current_revision = $n;
    $msg = "Database successfully updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);
}


// -----------------------------------------------------------------------------
// Update to revision 12
// Description: support for nd2 file format and STED microscopy in HRM
// -----------------------------------------------------------------------------
$n = 12;
if ($current_revision < $n) {

    // Fix a blank left in a table by previous revision.
    $ok = $db->AutoExecute( "file_format",
                            array( "hucoreName" => "czi"),
                            "UPDATE", "name = 'czi'" );
    if ( !$ok ) {
       $msg = "An error occurred while updating " .
              "the database to revision " . $n . ", file_format table update.";
       write_message($msg);
       write_to_log($msg);
       write_to_error($msg);
       return;
    }

    
    $tabname = "file_extension";
    $record = array();
    $record["file_format"] = "nd2";
    $record["extension"] = "nd2";

    // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE file_format='" . $record['file_format'] .
             "' AND extension='" . $record['extension'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {    
       $insertSQL = $db->GetInsertSQL($tabname, $record);
       if(!$db->Execute($insertSQL)) {
           $msg = "An error occurred while updating " .
                  "the database to revision " . $n . ".";
           write_message($msg);
           write_to_error($msg);
           return;
       }
    }
    

    $tabname = "file_format";
    $record = array();
    $record["name"] = "nd2";
    $record["isFixedGeometry"] = "f";
    $record["isSingleChannel"] = "f";
    $record["isVariableChannel"] = "t";
    $record["hucoreName"] = "nd2";

    // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE name='" . $record['name'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {    
       $insertSQL = $db->GetInsertSQL($tabname, $record);
       if(!$db->Execute($insertSQL)) {
           $msg = "An error occurred while updating " .
                  "the database to revision " . $n . ".";
           write_message($msg);
           write_to_error($msg);
           return;
       }
    }

    
    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ImageFileFormat";
    $record["value"] = "nd2";
    $record["translation"] = "Nikon NIS-Elements (*.nd2)";
    $record["isDefault"] = "f";

    // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE parameter='" . $record['parameter'] .
             "' AND value='" . $record['value'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {    
       $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating " .
                   "the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }


    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ZStabilization";
    $record["value"] = "1";
    $record["translation"] = "Yes, stabilize the dataset in the Z direction";
    $record["isDefault"] = "t";

    // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE parameter='" . $record['parameter'] .
             "' AND value='" . $record['value'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {    
       $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating " .
                   "the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }


    $tabname = "possible_values";
    $record = array();
    $record["parameter"] = "ZStabilization";
    $record["value"] = "0";
    $record["translation"] = "No, stabilization is not necessary.";
    $record["isDefault"] = "f";

    // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE parameter='" . $record['parameter'] .
             "' AND value='" . $record['value'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {    
       $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating " .
                   "the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }

    $record = array();
    $record["parameter"] = "MicroscopeType";
    $record["value"] = "STED";
    $record["translation"] = "sted";
    $record["isDefault"] = "f";
    $record["parameter_key"] = "MicroscopeType5";     

        // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE parameter='" . $record['parameter'] .
             "' AND value='" . $record['value'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {    
       $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating " .
                   "the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }


    $record = array();
    $record["parameter"] = "MicroscopeType";
    $record["value"] = "STED 3D";
    $record["translation"] = "sted3d";
    $record["isDefault"] = "f";
    $record["parameter_key"] = "MicroscopeType6";           

        // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE parameter='" . $record['parameter'] .
             "' AND value='" . $record['value'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {    
       $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating " .
                   "the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }


    $record = array();
    $record["parameter"] = "StedDeplMode";
    $record["value"] = "CW gated detection";
    $record["translation"] = "CWGated";
    $record["isDefault"] = "f";
    $record["parameter_key"] = "StedDeplMode1";           

        // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE parameter='" . $record['parameter'] .
             "' AND value='" . $record['value'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {    
       $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating " .
                   "the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }


    $record = array();
    $record["parameter"] = "StedDeplMode";
    $record["value"] = "CW Non gated detection";
    $record["translation"] = "CWNonGated";
    $record["isDefault"] = "f";
    $record["parameter_key"] = "StedDeplMode2";           

        // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE parameter='" . $record['parameter'] .
             "' AND value='" . $record['value'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {    
       $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating " .
                   "the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }


    $record = array();
    $record["parameter"] = "StedDeplMode";
    $record["value"] = "Pulsed";
    $record["translation"] = "pulsed";
    $record["isDefault"] = "t";
    $record["parameter_key"] = "StedDeplMode3";           

        // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE parameter='" . $record['parameter'] .
             "' AND value='" . $record['value'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {    
       $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating " .
                   "the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }


    $record = array();
    $record["parameter"] = "StedDeplMode";
    $record["value"] = "Off/Confocal";
    $record["translation"] = "off-confocal";
    $record["isDefault"] = "f";
    $record["parameter_key"] = "StedDeplMode4";           

        // Skip it if the row is already there.
    $query = "SELECT * FROM " . $tabname .      
             " WHERE parameter='" . $record['parameter'] .
             "' AND value='" . $record['value'] . "'";
    if ( $db->Execute( $query )->RecordCount( ) == 0 ) {    
       $insertSQL = $db->GetInsertSQL($tabname, $record);
        if(!$db->Execute($insertSQL)) {
            $msg = "An error occurred while updating " .
                   "the database to revision " . $n . ".";
            write_message($msg);
            write_to_error($msg);
            return;
        }
    }


// ------------------ Add columns to 'confidence_levels' ----------------------
    $tabname   = "confidence_levels";
    $newcolumns = array("stedMode",
                        "stedLambda",
                        "stedSatFact",
                        "stedImmunity",
                        "sted3D");
    $type = "C(16)";

    $allcolumns = $db->MetaColumnNames( 'confidence_levels' );    
    foreach ($newcolumns as $newcolumn) {
        if (array_key_exists( strtoupper($newcolumn), $allcolumns) ) {
            continue;
        }
        if ( !insert_column($tabname, $newcolumn . " " . $type) ) {
            $msg = "An error occurred while updating " .
                "the database to revision " . $n . ".";
            write_message($msg);
            write_to_log($msg);
            write_to_error($msg);
            return;
        }
    }

// ----------------------------------------------------------------------------
    
    //Update revision
    if(!update_dbrevision($n))
        return;
    
    $current_revision = $n;
    $msg = "Database successfully updated to revision " . $current_revision . ".";
    write_message($msg);
    write_to_log($msg);
}


fclose($fh);

return;

?>
