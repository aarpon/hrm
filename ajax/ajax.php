<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once '../inc/SettingEditor.inc.php';
require_once '../inc/User.inc.php';

// Functions

function getParameters($editor, $setName, $numChannels) {
  $setting = $editor->setting($setName);
  $data = $setting->displayString($numChannels, true);
  return $data;
}

// Get number of jobs currently in the queue
function getNumberOfJobsInQueue($user) {
    $data = "<p />See all jobs.<br />You have <strong>";
    $jobsInQueue = $_SESSION['user']->numberOfJobsInQueue();
    if ($jobsInQueue == 0) {
        $data .= "no jobs";
    } elseif ($jobsInQueue == 1) {
        $data .= "1 job";
    } else {
        $data .= "$jobsInQueue jobs";
    }
    $data .= "</strong> in the queue.";
    return $data;
}

function act( $action, &$data ) {
  
  switch ( $action ) {
      
      case 'getParameterListForSet':
        
        if ( isset( $_POST['setType'] ) ) {
          $setType = $_POST['setType'];
        } else {
          return false;
        }

        if ( isset( $_POST['setName'] ) ) {
          $setName = $_POST['setName'];
        } else {
          return false;
        }
        
        if ( isset( $_POST['publicSet'] ) ) {
          $publicSet = $_POST['publicSet'];
        } else {
          return false;
        }
        
        if ( $publicSet == "true" ) {
          $publicSet = 1;
          if ( $setType == "setting" ) {
            if ( isset( $_SESSION['admin_editor'] ) ) {
              $editor = $_SESSION['admin_editor'];
            } else {
              return false;
            }
          } else {
            if ( isset( $_SESSION['admin_taskeditor'] ) ) {
              $editor = $_SESSION['admin_taskeditor'];
            } else {
              return false;
            }
            
          }
        } else {
          $publicSet = 0;
          if ( $setType == "setting" ) {
            if ( isset( $_SESSION['editor'] ) ) {
              $editor = $_SESSION['editor'];
            } else {
              return false;
            }
          } else {
            if ( isset( $_SESSION['taskeditor'] ) ) {
              $editor = $_SESSION['taskeditor'];
            } else {
              return false;
            }            
          }
        }
        
        if ( isset( $_SESSION['setting'] ) ) {
          $numChannels = $_SESSION['setting']->numberOfChannels();
        } else {
          $numChannels = null;
        }
        $data = getParameters( $editor, $setName, $numChannels);
        $data = "<h3>Preview</h3>" . nl2br($data);
        return true;
        break;
        
        // ---------------------------------------------------------------------

        case 'getNumberOfJobsInQueue':
            $user = $_SESSION['user'];
            $data = getNumberOfJobsInQueue($user);
            return true;
            break;
            
        // ---------------------------------------------------------------------

        default:
          return false;
  }
}

// -----------------------------------------------------------------------------

session_start();

// If the user is not logged on, we return without doing anything
if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  return;
}

// Initialize needed variables
$data = "";
$action = "";

// Make sure we have an action
if ( isset( $_POST ) ) {
  if ( isset( $_POST['action'] ) ) {
    $action = $_POST['action'];
  }
}
if ( $action == "" ) {
  return;
}

// Execute the requested action
if ( act( $action, $data ) == true ) {
  // And write data for the post
  echo $data;
}

?>
