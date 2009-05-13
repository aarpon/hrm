<?php

/*
  
  CLASS VERSIONS
  
  This class centralizes all information about versions relevant to the HRM
  and the tools to get and compare them.

  Versions are:
  (1) The version of the HRM itself
  (2) The database revision supported by current HRM version
  (3) Current database revision (obtained by querying the DB itself)
   
*/

require_once("Database.inc");

class Versions {
    
    // These fields have to be updated by the developers!
    const HRM_VERSION      = "1.1.4";
    const DB_LAST_REVISION = 6;
    
    // Return HRM version
    public static function getHRMVersion( ) {
        return self::HRM_VERSION;
    }

    // Print HRM version
    public static function printHRMVersion( ) {
        print self::HRM_VERSION;
    }
    
    // Return DB revision expected by this version of the HRM
    public static function getDBLastRevision( ) {
        return self::DB_LAST_REVISION;
    }
  
    // Return DB revision from the database
    public static function getDBCurrentRevision( ) {
        $db   = new DatabaseConnection();
        $rows = $db->query(
            "SELECT * FROM `global_variables` WHERE `name` LIKE 'dbrevision';");
        if ( !$rows ) {
            return 0;
        } else {
            return $rows[0]['value'];
        }
    }

    // Return true if the database is up-to-date
    public static function isDBUpToDate( ) {
        return ( self::getDBLastRevision( ) == self::getDBCurrentRevision( ) );
    }

};

?>
