<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt


// This is for the 'Omero Data' button.
if ($omero_transfers && !$_SESSION['user']->isAdmin()) {

    if ( $browse_folder == "src" ) {
        $file_buttons[] = "omeroImport";
    } else {
        $file_buttons[] = "omeroExport";
    }
}

// If the 'Omero Data' button gets pressed we'll instantiate the class.
if (isset($_POST['omeroCheckCredentials'])) {

    if (!isset($_SESSION['omeroConnection'])) {

        if (isset($_POST['omeroUser']) ) {
            $omeroUser = $_POST['omeroUser'];
        } else {
            $omeroUser = '';
        }

        if (isset($_POST['omeroPass']) ) {
            $omeroPass = $_POST['omeroPass'];
        } else {
            $omeroPass = '';
        }

        $omeroConnection = new OmeroConnection( $omeroUser, $omeroPass );

        if ($omeroConnection->loggedIn) {
            $_SESSION['omeroConnection'] = $omeroConnection;
        } else {
            $message = "Impossible to log in. Please try again.";
        }
    }
}

// If an instance of the Omero class exists and 'Import' gets pressed.
if (isset($_POST['importFromOmero'])) {
    $message = $_SESSION['fileserver']->importFromOmero();
} else if (isset($_POST['update'])) {

    if ( $browse_folder == "src" ) {
        $_SESSION['fileserver']->resetFiles();
    } else {
        $_SESSION['fileserver']->resetDestFiles();
    }
}

// If an instance of the Omero class exists and 'Export' gets pressed.
if (isset($_POST['exportToOmero'])) {
    $message = $_SESSION['fileserver']->exportToOmero();
} else if (isset($_POST['update'])) {

    if ( $browse_folder == "src" ) {
        $_SESSION['fileserver']->resetFiles();
    } else {
        $_SESSION['fileserver']->resetDestFiles();
    }
}

?>
