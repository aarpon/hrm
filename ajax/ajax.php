<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once '../inc/SettingEditor.inc.php';
require_once '../inc/User.inc.php';
require_once '../inc/JobQueue.inc.php';

//
// Functions

/**
 * Get the summary for current parameter set
 * @param SettingsEditor $editor  SettingsEditor
 * @param String $setName Parameter name
 * @param int $numChannels Number of channels
 * @return String parameter dump
 */
function getParameters($editor, $setName, $numChannels, $micrType) {
  if ($setName == '') {
      // In Chrome, the onclick event is fired even if one clicks on an empty
      // area of an input field (passing a value of ''). In Firefox, the event
      // is fired only if one clicks on one of the existing values.
      return;
  }
  $setting = $editor->setting($setName);
  $data = $setting->displayString($numChannels, $micrType);

  return $data;
}

/**
 * Get number of jobs currently in the queue for current user
 * @param User $user User object
 * @return String
 */
function getNumberOfUserJobsInQueue(User $user) {
    if ($user->isAdmin()) {
      return '';
    }
    $jobsInQueue = $user->numberOfJobsInQueue();
    if ($jobsInQueue == 0) {
        $data = "no jobs";
    } elseif ($jobsInQueue == 1) {
        $data = "1 job";
    } else {
        $data = "$jobsInQueue jobs";
    }
    return $data;
}

/**
 * Get total number of jobs in the queue (for all users)
 * @return String
 */
function getTotalNumberOfJobsInQueue() {
    $db = new DatabaseConnection();
    $allJobsInQueue = $db->getTotalNumberOfQueuedJobs();
    $data = 'There ';
    if ( $allJobsInQueue == 0 ) {
      $data .= "are no jobs";
    } elseif ( $allJobsInQueue == 1 ) {
      $data .= "is <strong>1 job</strong>";
    } else {
      $data .= 'are <strong>' .$allJobsInQueue . ' jobs</strong>';
    }
    $data .= " in the queue.";
    return $data;
}

/**
 * Get complete job queue table for rendering
 * @return String
 */
function getJobQueuetable() {

  // Get queue information
  $queue = new JobQueue();
  $rows = $queue->getContents();
  $allJobsInQueue = count($rows);

  // Disable displaying estimated end time for the time being
  $showStopTime = false;

  // Initialize $data
  $data = '';

  $data .= '
      <table>
        <tr>
          <td class="del"></td>
          <td class="nr">nr</td>
          <td class="owner">owner</td>
          <td class="files">file(s)</td>
          <td class="created">created</td>
          <td class="status">status</td>
          <td class="started">started</td>';

  if ($showStopTime == true) {
    $data .= '<td class="stop">estimated end</td>';
  }

  $data .= '
          <td class="pid">pid</td>
          <td class="server">server</td>
        </tr>';

  if (count($rows) > 0) {

    $data .= '
        <tr style="background: #eeeeee">
        <td colspan="9">';

    if (!$_SESSION['user']->isAdmin()) {
      $data .= 'You can delete jobs owned by yourself.';
    } else {
      $data .= 'You can delete any jobs.';
    }

    $data .= '
         </td>
        </tr>
        ';
  }

  if (count($rows) == 0) {

    $data .= '
      <tr style="background: #ffffcc">
        <td colspan="9">The job queue is empty.</td>
      </tr>';
  } else {
    $index = 1;
    foreach ($rows as $row) {
      if ($row['status'] == "started") {
        $color = '#99ffcc';
      } else if ($row['status'] == "broken" || $row['status'] == "kill") {
        $color = '#ff9999';
      } else if ($index % 2 == 0) {
        $color = '#ffccff';
      } else {
        $color = '#ccccff';
      }

      $data .= "<tr style=\"background: $color\">";

      if ($row['username'] == $_SESSION['user']->name() ||
              $_SESSION['user']->isAdmin()) {

        if ($row['status'] != "broken") {

          $data .= '
          <td>
            <input name="jobs_to_kill[]" type="checkbox"
              value="' . $row['id'] . '" />
          </td>';
        } else {

          $data .= '<td></td>';
        }
      } else {
        $data .= '<td></td>';
      }

      // Fill job row
      $username = $row['username'];
      $jobFiles = implode(';', $queue->getJobFilesFor($row['id']));
      $queued = $row['queued'];
      $status = $row['status'];
      $start = $row['start'];
      $data .= "
      <td>$index</td>
      <td>$username</td>
      <td>$jobFiles</td>
      <td>$queued</td>
      <td>$status</td>
      <td>$start</td>";

      if ($showStopTime) {
        $stop = $row['stop'];
        $data .= "<td>$stop</td>";
      }

      $process_info = $row['process_info'];
      $server = $row['server'];
      $data .= "
      <td>$process_info</td>
      <td>$server</td>
    ";

      $data .= "</tr>";

      $index++;
    }
  }

  $data .= "</table>";

  if (count($rows) != 0) {

    $data .= '
  <label style="padding-left: 3px">
    <img src="images/arrow.png" alt="arrow" />
    <a href="javascript:mark()">Check All</a> /
    <a href="javascript:unmark()">Uncheck All</a>
  </label>
  &nbsp;
  <label style="font-style: italic">
    With selected:
    <input name="delete" type="submit" value=""
      class="icon delete"  id="controls_delete" />
  </label>';
  }
  return $data;
}

/* ==========================================================================
 * ========================================================================== */

/**
 * Set the selected image format in the $_SESSION
 * @param format Selected image file format
 */
function setFileFormat($format) {

    // Check that parameter settings and fileserver exist
    if (!isset($_SESSION['parametersetting']) ||
        (!isset($_SESSION['fileserver']))) {
        return "";
    }

    // Get current file format
    $parameterFileFormat =
        $_SESSION['parametersetting']->parameter("ImageFileFormat");

    // There has been an event of the type "Image file format" selection,
    // "Automatically load file series" or similar. Thus, let's update the
    // current selection.
    $_SESSION['fileserver']->removeAllFilesFromSelection();
    $parameterFileFormat->setValue($format);
    $_SESSION['parametersetting']->set($parameterFileFormat);

    return "";
}

/* ==========================================================================
 * ========================================================================== */

/**
 * Calls the requested action and collects the output
 * @param String $action Action to be performed
 * @param Reference $data String to be returned
 * @return true if the call was successful, false otherwise
 */
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
          } elseif ($setType == "task_setting") {
            if ( isset( $_SESSION['admin_taskeditor'] ) ) {
              $editor = $_SESSION['admin_taskeditor'];
            } else {
              return false;
            }
          } elseif ($setType == "analysis_setting") {
            if ( isset( $_SESSION['admin_analysiseditor'] ) ) {
              $editor = $_SESSION['admin_analysiseditor'];
            } else {
              return false;
            }
          } else {
              return false;
          }
        } else {
          $publicSet = 0;
          if ( $setType == "setting" ) {
            if ( isset( $_SESSION['editor'] ) ) {
              $editor = $_SESSION['editor'];
            } else {
              return false;
            }
          } elseif ( $setType == "task_setting" ) {
            if ( isset( $_SESSION['taskeditor'] ) ) {
              $editor = $_SESSION['taskeditor'];
            } else {
              return false;
            }
          } elseif ( $setType == "analysis_setting" ) {
            if ( isset( $_SESSION['analysiseditor'] ) ) {
              $editor = $_SESSION['analysiseditor'];
            } else {
              return false;
            }
          } else {
              return false;
          }
        }

        if ( isset( $_SESSION['setting'] ) ) {
          $numChannels = $_SESSION['setting']->numberOfChannels();
        } else {
          $numChannels = null;
        }
        if ( isset($_SESSION['setting']) ) {
            $micrType = $_SESSION['setting']->microscopeType();
        } else {
            $micrType = null;
        }
        $data = getParameters( $editor, $setName, $numChannels, $micrType);

        /* Make a distinction between the parameter name and its value. */
        $data = "<small><b>" . str_replace("\n","\n<b>",$data);
        $data = str_replace(": ",":</b> ",$data) . "</small>";
        $data = "<h3>Preview</h3>" . nl2br($data);

        return true;
        break;

        // ---------------------------------------------------------------------

        case 'getNumberOfUserJobsInQueue':
            $user = $_SESSION['user'];
            $data = getNumberOfUserJobsInQueue($user);
            return true;
            break;

        // ---------------------------------------------------------------------

        case 'getTotalNumberOfJobsInQueue':
            $data = getTotalNumberOfJobsInQueue();
            return true;
            break;

        // ---------------------------------------------------------------------

        case 'getJobQueueTable':
            $data = getJobQueueTable();
            return true;
            break;

        // ---------------------------------------------------------------------

        case 'setFileFormat':
            if (isset( $_POST['format'])) {
              $data = setFileFormat($_POST['format']);
            }
            return true;
            break;

        // ---------------------------------------------------------------------

        default:
          return false;
  }
}

// -----------------------------------------------------------------------------
// -----------------------------------------------------------------------------
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
