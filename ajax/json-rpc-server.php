<?php

/**
 * json-rpc-server
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 *
 * Server implementing the JSON-RPC (version 2.0) protocol.
 *
 * This is an example Javascript code to interface with json-rpc-server.php:
 *
 * ```
 * 01:    <script type="text/javascript">
 * 02:        $(document).ready($('#button').click(function() {
 * 03:            JSONRPCRequest({
 * 04:                method : 'jsonGetParameter',
 * 05:                params : { parameterName : 'ExcitationWavelength'}
 * 06:            }, function(response) {
 * 07:                $('#report').html("<b>" + response['message'] + "</b>");
 * 08:            });
 * 09:        }));
 * 10:    </script>
 * ```
 *
 * Passing parameters to the Ajax method is very flexible (line 5). The recommended method is:
 *
 * ```
 * params : {parameter : 'value'}
 * ```
 *
 *
 * for one parameter, and:
 *
 * ```
 * params : {parameterOne : 'valueOne', parameterTwo : 'valueTwo'}
 * ```
 *
 * for more parameters. If a parameter is an array, use:
 *
 * ```
 * params : {parameter : ['valueOne', 'valueTwo', 'valueThree']}
 * ```
 *
 * In PHP, the parameters can then be retrieved with:
 *
 * ```
 * $params = $_POST['params'];
 * ```
 *
 * ===
 *
 * For illustration, the following is also possible:
 *
 * For a single value:
 *
 * Javascript:   params : 'ExcitationWavelength'
 * PHP:          $params := "ExcitationWavelength"
 *
 * For a vector:
 *
 * Javascript:   params : ['ExcitationWavelength', 'EmissionWavelength']
 * PHP:          $params[0] := "ExcitationWavelength"
 * $params[1] := "EmissionWavelength"
 */

use hrm\param\base\Parameter;
use hrm\setting\AnalysisSetting;
use hrm\DatabaseConnection;
use hrm\Mail;
use hrm\setting\ParameterSetting;
use hrm\System;
use hrm\setting\TaskSetting;
use hrm\user\UserManager;
use hrm\user\UserV2;

require_once dirname(__FILE__) . '/../inc/bootstrap.php';

/*

Server implementing the JSON-RPC (version 2.0) protocol.

This is an example Javascript code to interface with json-rpc-server.php:

01:    <script type="text/javascript">
02:        $(document).ready($('#button').click(function() {
03:            JSONRPCRequest({
04:                method : 'jsonGetParameter',
05:                params : { parameterName : 'ExcitationWavelength'}
06:            }, function(response) {
07:                $('#report').html("<b>" + response['message'] + "</b>");
08:            });
09:        }));
10:    </script>

Passing parameters to the Ajax method is very flexible (line 5). The recommended method is:

                  params : {parameter : 'value'}

for one parameter, and:

                  params : {parameterOne : 'valueOne',
                            parameterTwo : 'valueTwo'}

for more parameters. If a parameter is an array, use:


                  params : {parameter : ['valueOne', 'valueTwo', 'valueThree']}

In PHP, the parameters can then be retrieved with:

                  $params = $_POST['params'];

===

For illustration, the following is also possible:

For a single value:

Javascript:   params : 'ExcitationWavelength'
PHP:          $params := "ExcitationWavelength"

For a vector:

Javascript:   params : ['ExcitationWavelength', 'EmissionWavelength']
PHP:          $params[0] := "ExcitationWavelength"
              $params[1] := "EmissionWavelength"

*/

// This is not strictly necessary for the Ajax communication, but will be
// necessary for accessing session data to create the response.
session_start();

// If the user is not logged on, we return without doing anything
if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    return;
}

// ============================================================================
//
// PROCESS THE POSTED ARGUMENTS
//
// ============================================================================

// Check that we have a valid request
if (!isset($_POST)) {
    // No request received.
    $json = "No request received.";
    header("Content-Type: application/json", true, 405);
    echo $json;
    return true;
}

// Do we jave a JSON-RPC 2.0 request? We do NOT test for the value of id.
if (!(isset($_POST['id']) && isset($_POST['jsonrpc']) && $_POST['jsonrpc'] == "2.0")) {
    // Invalid JSON-RPC 2.0 call
    $json = "Invalid JSON-RPC 2.0 call.";
    header("Content-Type: application/json", true, 405);
    echo $json;
    return true;
}

// Do we have a method with params?
if (!isset($_POST['method']) && !isset($_POST['params'])) {
    // Expected 'method' and 'params'
    $json = "Expected 'method' and 'params'.";
    header("Content-Type: application/json", true, 405);
    echo $json;
    return true;
}

// Get the method
$method = $_POST['method'];

// Method parameters
$params = null;
if (isset($_POST['params'])) {
    $params = $_POST['params'];
}

// Call the requested method and collect the JSON-encoded response
switch ($method) {
    case 'jsonGetUserAndTotalNumberOfJobsInQueue':
        $json = jsonGetUserAndTotalNumberOfJobsInQueue();
        break;

    case 'jsonCheckForUpdates':
        $json = jsonCheckForUpdates();
        break;

    case 'jsonSendTestEmail':
        $json = jsonSendTestEmail();
        break;

    case 'jsonGetParameter':
        // Get the Parameter name
        $paramName = null;
        if (isset($params['parameterName'])) {
            $paramName = $params['parameterName'];
        }
        $json = jsonGetParameter($paramName);
        break;

    case 'jsonGetImageParameterFromSession':
        // Get the Parameter name
        $paramName = null;
        if (isset($params['parameterName'])) {
            $paramName = $params['parameterName'];
        }
        $json = jsonGetImageParameterFromSession($paramName);
        break;

    case 'jsonGetAllImageParameters':
        $json = jsonGetAllImageParameters();
        break;

    case 'jsonGetAllImageParametersFromSession':
        $json = jsonGetAllImageParametersFromSession();
        break;

    case 'jsonGetUserList':
        $username = $params[0];
        $json = jsonGetUserList($username);
        break;

    case 'jsonGetSharedTemplateList':
        $username = $params[0];
        $type = $params[1];
        $json = jsonGetSharedTemplateList($username, $type);
        break;

    case 'jsonAcceptSharedTemplate':
        $template = $params[0];
        $type = $params[1];
        $json = jsonAcceptSharedTemplate($template, $type);
        break;

    case 'jsonDeleteSharedTemplate':
        $template = $params[0];
        $type = $params[1];
        $json = jsonDeleteSharedTemplate($template, $type);
        break;

    case 'jsonPreviewSharedTemplate':
        $template = $params[0];
        $type = $params[1];
        $json = jsonPreviewSharedTemplate($template, $type);
        break;

    case 'jsonScanSourceFiles':
        $format = $params[0];
        $autoseries = $params[1];
        $json = jsonScanSourceFiles($format, $autoseries);
        break;

    case 'jsonGetFileFormats':
        $json = jsonGetFileFormats();
        break;

    case 'jsonResetSourceFiles':
        $format = $params[0];
        $json = jsonResetSourceFiles($format);
        break;

    case 'jsonAddFilesToSelection':
        $fileList = $params[0];
        $format = $params[1];
        $json = jsonAddFilesToSelection($fileList, $format);
        break;

    case 'jsonRemoveFilesFromSelection':
        $fileList = $params[0];
        $format = $params[1];
        $json = jsonRemoveFilesFromSelection($fileList, $format);
        break;

    default:
        // Unknown method
        $json = "Unknown method.";
        header("Content-Type: application/json", true, 405);
        echo $json;
        return true;
}

// Return the JSON object
header("Content-Type: application/json", true, 200);
echo $json;

return true;

// ============================================================================
//
// METHOD IMPLEMENTATIONS
//
// ============================================================================

/**
 * Create default (PHP) array with "success" and "message" properties. Methods
 * should initialize their JSON output array with this function, to make sure
 * that there are a "success" and a "message" properties in the returned object
 * (defaulting to "true" and "", respectivey) and then expand it as needed.
 *
 * Before the method functions return, they must call json_encode() on it!
 *
 * The two valid values for the property "success" are the strings (and not
 * booleans!) "true" and "false".
 *
 * @return array (PHP) with "success" = "true" and "message" = "" properties.
 */
function initJSONArray()
{
    // Initialize the JSON array with success
    return (array("success" => "true", "message" => ""));
}

/**
 * Get the total number and the number of jobs owned by the specified user
 * currently in the queue.
 *
 * @return string JSON-encoded array with keys 'numAllJobsInQueue' and
 * 'numUserJobsInQueue'
 */
function jsonGetUserAndTotalNumberOfJobsInQueue()
{
    // Prepare the output array
    $json = initJSONArray();

    // Get the total number of jobs
    $json["numAllJobsInQueue"] = UserManager::getTotalNumberOfQueuedJobs();

    // Get the number of jobs for current user
    /** @var $user UserV2 */
    $user = $_SESSION['user'];
    if ($user->isAdmin()) {
        $numUserJobsInQueue = 0;
    } else {
        $numUserJobsInQueue = UserManager::numberOfJobsInQueue($user->name());
    }
    $json["numUserJobsInQueue"] = $numUserJobsInQueue;

    // Also add time of update
    $json["lastUpdateTime"] = date('H:i:s');

    // Return as a JSON string
    return (json_encode($json));
}


/**
 * Check whether there is an update for HRM.
 * @return string JSON-encoded array with key 'newerVersionExist' and 'newVersion'
 */
function jsonCheckForUpdates()
{
    // Prepare the output array
    $json = initJSONArray();

    try {
        // Check if there is a newer version
        $isNew = System::isThereNewHRMRelease();

        if ($isNew) {
            $json["newerVersionExist"] = "true";
            $json["newVersion"] = System::getLatestHRMVersionFromRemoteAsString();
        } else {
            $json["newerVersionExist"] = "false";
            $json["newVersion"] = "";
        }
    } catch (Exception $e) {
        $json["success"] = "false";
        $json["message"] = $e->getMessage();
        $json["newerVersionExist"] = "false";
        $json["newVersion"] = "";
    }

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Send a test email to the administrator to check that the email system is set
 * up properly.
 * @return string JSON-encoded array with key 'success' and 'message'
 */
function jsonSendTestEmail()
{
    global $email_sender;
    global $email_admin;

    // Include configuration file
    include(dirname(__FILE__) . "/../config/hrm_client_config.inc");

    // Prepare the output array
    $json = initJSONArray();

    // Configure the email
    $mail = new Mail($email_sender);
    $mail->setReceiver($email_admin);
    $mail->setSubject('HRM test e-mail');
    $mail->setMessage('Congratulations! You have successfully ' .
        'configured your e-mail server!');

    // Send it
    if ($mail->send()) {
        $json['success'] = "true";
        $json['message'] = "Sent!";
    } else {
        $json['success'] = "false";
        $json['message'] = "Failed!";
    }

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Return a JSON-encoded version of the requested PHP parameter. The Parameter can
 * be of the image, restoration or processing Parameter classes.
 *
 * The Parameter is constructed and immediately returned. This method is to
 * be used to run checks on the user-defined values at the client side before
 * they get posted and stored in the session. Its value is not set.
 *
 * To retrieve a specific Parameter from current session use the
 * jsonGet*ParameterFromSession() methods instead.
 *
 * @param string $parameterName Class name of the Parameter to be serialized
 *
 * @return string JSON-encoded Parameter string.
 */
function jsonGetParameter($parameterName)
{
    // Prepare the output array
    $json = initJSONArray();

    try {
        // Try to instantiate the requested parameter
        $param = new $parameterName();

        // Get the JSON data
        /** @var $param Parameter */
        $json = $param->getJsonData();
    } catch (Exception $e) {
        // Return failure
        $json['success'] = "false";
        $json['message'] = "Failed retrieving parameter " . $parameterName . "!";
    }

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Return a JSON-encoded version of the requested image Parameter from current
 * session.
 *
 * The Parameter is retrieved from the session, and therefore contains the
 * value(s) set by the user in the browser.
 *
 * @param string $parameterName Class name of the Parameter to be serialized
 *
 * @return string JSON-encoded Parameter.
 *
 * @todo Implement corresponding method for restoration and processing Parameters.
 */
function jsonGetImageParameterFromSession($parameterName)
{
    // Prepare the output array
    $json = initJSONArray();

    // Get all image parameters
    $setting = new ParameterSetting();
    $names = $setting->parameterNames();

    // Check that we are asking for an image Parameter
    if (!in_array($parameterName, $names)) {
        // Return failure
        $json['success'] = "false";
        $json['message'] = "Parameter " . $parameterName . " is not " .
            "an image Parameter!";

        // Return as a JSON string
        return (json_encode($json));
    }

    // Is the session active?
    if (isset($_SESSION['setting'])) {
        // Get the Parameter from the session
        $param = $_SESSION['setting']->parameter($parameterName);

        // Get the JSON data
        /** @var $param Parameter */
        $json = $param->getJsonData();
    } else {
        // Return failure
        $json['success'] = "false";
        $json['message'] = "Failed retrieving parameter " . $parameterName . "!";
    }

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Return a JSON-encoded version of all image PHP parameter.
 *
 * The Parameters are constructed and immediately returned. This method is to
 * be used to run checks on the user-defined values at the client side before
 * they get posted and stored in the session. Their values are not set.
 *
 * To retrieve all Parameters from current session use the
 * jsonGet*ParameterFromSession() methods instead.
 *
 * @return String JSON-encoded array of Parameters.
 *
 * @todo Implement corresponding method for restoration and processing Parameters.
 */
function jsonGetAllImageParameters()
{
    // Prepare the output array
    $json = initJSONArray();

    try {
        // Get all image parameters
        $setting = new ParameterSetting();
        $names = $setting->parameterNames();

        // Initialize parameter array
        $json["parameters"] = array();

        // Now serialize the Parameters
        foreach ($names as $name) {
            // Get and encode the JSON data
            $json["parameters"][$name] =
                $setting->parameter($name)->getJsonData();
        }
    } catch (Exception $e) {
        $json['success'] = "false";
        $json['message'] = "Failed retrieving parameters!";
    }

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Return a JSON-encoded version of all image PHP parameter from current session.
 *
 * The Parameters are retrieved from the session, and therefore contain the current
 * values set by the user in the browser.
 *
 * @return String JSON-encoded array of Parameters.
 */
function jsonGetAllImageParametersFromSession()
{
    // Prepare the output array
    $json = initJSONArray();

    // Is the session active?
    if (isset($_SESSION["setting"])) {
        // Retrieve the settings from the session
        $setting = $_SESSION["setting"];

        // Get all names
        $names = $setting->parameterNames();

        // Initialize parameter array
        $json["parameters"] = array();

        // Now serialize the Parameters
        foreach ($names as $name) {
            // Get and encode the JSON data
            $json["parameters"][$name] =
                $setting->parameter($name)->getJsonData();
        }
    } else {
        // Return failure
        $json['success'] = "false";
        $json['message'] = "Failed retrieving parameters!";
    }

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Return the list of known users.
 * @param  String User name to filter out from the list (optional).
 * @return String JSON-encoded array of user names.
 */
function jsonGetUserList($username)
{
    // Prepare the output array
    $json = initJSONArray();

    // Retrieve user list from database
    $db = DatabaseConnection::get();

    // Get the list of users
    $users = $db->getUserList($username);
    if ($users == null) {
        // Return failure
        $json['success'] = "false";
        $json['message'] = "Failed retrieving user list!";
    }
    $json["users"] = $users;

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Return the list of shared templates with the given user.
 * @param  string $username Name of the user for which to query for shared templates.
 * @param string $type Template type: one of 'parameter', 'task', analysis'.
 * @return string $type JSON-encoded array of shared templates.
 */
function jsonGetSharedTemplateList($username, $type)
{
    // Prepare the output array
    $json = initJSONArray();

    // Retrieve list of shared templates
    $sharedTemplatesWith = "";
    $sharedTemplatesBy = "";
    $success = true;
    switch ($type) {
        case "parameter":
            $sharedTemplatesWith = ParameterSetting::getTemplatesSharedWith($username);
            $sharedTemplatesBy   = ParameterSetting::getTemplatesSharedBy($username);
            break;

        case "task":
            $sharedTemplatesWith = TaskSetting::getTemplatesSharedWith($username);
            $sharedTemplatesBy = TaskSetting::getTemplatesSharedBy($username);
            break;

        case "analysis":
            $sharedTemplatesWith = AnalysisSetting::getTemplatesSharedWith($username);
            $sharedTemplatesBy = AnalysisSetting::getTemplatesSharedBy($username);
            break;

        default:
            // Return failure
            $success = false;
    }

    if (! $success) {
        // Return failure
        $json['success'] = "false";
        $json['message'] = "Could not accept selected template.";
        $json["sharedTemplatesWith"] = "";
        $json["sharedTemplatesBy"] = "";
    } else {
        $json["sharedTemplatesWith"] = $sharedTemplatesWith;
        $json["sharedTemplatesBy"] = $sharedTemplatesBy;
    }
    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Accept and copy a template to the target user.
 * @param  string $template Name of the user for which to query for shared templates.
 * @param  string $type Type of the template: 'parameter', 'task', 'analysis'.
 * @return string JSON-encoded array with 'success' and 'message' fields.
 */
function jsonAcceptSharedTemplate($template, $type)
{
    // Prepare the output array
    $json = initJSONArray();

    // Get a database connection
    $db = DatabaseConnection::get();

    // Copy the setting
    switch ($type) {
        case "parameter":
            // Copy the template
            $success = $db->copySharedTemplate(
                $template["id"],
                ParameterSetting::sharedTable(),
                ParameterSetting::sharedParameterTable(),
                ParameterSetting::table(),
                ParameterSetting::parameterTable()
            );
            break;

        case "task":
            // Copy the template
            $success = $db->copySharedTemplate(
                $template["id"],
                TaskSetting::sharedTable(),
                TaskSetting::sharedParameterTable(),
                TaskSetting::table(),
                TaskSetting::parameterTable()
            );
            break;

        case "analysis":
            // Copy the template
            $success = $db->copySharedTemplate(
                $template["id"],
                AnalysisSetting::sharedTable(),
                AnalysisSetting::sharedParameterTable(),
                AnalysisSetting::table(),
                AnalysisSetting::parameterTable()
            );
            break;

        default:
            // Return failure
            $success = false;
    }
    if (! $success) {
        // Return failure
        $json['success'] = "false";
        $json['message'] = "Could not accept selected template.";
    }

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Delete a shared template without copying it.
 * @param  string $template Name of the user for which to query for shared templates.
 * @param  string $type Type of the template: 'parameter', 'task', 'analysis'.
 * @return string JSON-encoded array with 'success' and 'message' fields.
 */
function jsonDeleteSharedTemplate($template, $type)
{
    // Prepare the output array
    $json = initJSONArray();

    // Get a database connection
    $db = DatabaseConnection::get();

    // Copy the setting
    $success = true;
    switch ($type) {
        case "parameter":
            // Delete the template
            $success = $db->deleteSharedTemplate(
                $template["id"],
                ParameterSetting::sharedTable(),
                ParameterSetting::sharedParameterTable()
            );

            break;

        case "task":
            // Delete the template
            $success = $db->deleteSharedTemplate(
                $template["id"],
                TaskSetting::sharedTable(),
                TaskSetting::sharedParameterTable()
            );
            break;

        case "analysis":
            // Delete the template
            $success = $db->deleteSharedTemplate(
                $template["id"],
                AnalysisSetting::sharedTable(),
                AnalysisSetting::sharedParameterTable()
            );
            break;

        default:
            // Return failure
            $json['success'] = "false";
            $json['message'] = "Unknown template type!";
    }
    if (! $success) {
        // Return failure
        $json['success'] = "false";
        $json['message'] = "Could not delete selected template.";
    }

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Preview the shared template.
 * @param string $template Id of the template.
 * @param string $type Type of the template: 'parameter', 'task', 'analysis'.
 * @return string JSON-encoded array.
 * @throws Exception
 */
function jsonPreviewSharedTemplate($template, $type)
{
    // Prepare the output array
    $json = initJSONArray();

    // Prepare the 'preview' field
    $json["preview"] = "";

    // Get a database connection
    $db = DatabaseConnection::get();

    // Read the settings from the shared table and prepare the preview
    /** @var hrm\setting\ParameterSetting $settings */
    $settings = $db->loadSharedParameterSettings($template["id"], $type);
    if (! $settings) {
        // Return failure
        $json['success'] = "false";
        $json['message'] = "Could not preview selected template.";
        $json['preview'] = "";
    } else {
        // Get the parameters into a string
        $paramStr = $settings->displayString();

        // Prepare the string for display
        $paramStr = "<small><b>" . str_replace("\n", "\n<b>", $paramStr);
        $paramStr = str_replace(": ", ":</b> ", $paramStr) . "</small>";
        $paramStr = "<h3>Shared template preview</h3>" . nl2br($paramStr);

        // Add the preview to the json array
        $json['preview'] = $paramStr;
    }

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Scan the source folder and filter/condense as requested.
 * @param $format string File format to use for filtering.
 * @param $autoseries boolean True if series should be condensed, false otherwise.
 * @return string JSON-encoded response.
 */
function jsonScanSourceFiles($format, $autoseries)
{
    // Make sure to store autoseries in the session
    if (strtolower($autoseries) === "true" || $autoseries === true) {
        $_SESSION["autoseries"] = "TRUE";
        $autoseries = true;
    } else {
        $_SESSION["autoseries"] = "FALSE";
        $autoseries = false;
    }

    // Prepare the output array
    $json = initJSONArray();

    // Is the session active?
    if (!isset($_SESSION) || !isset($_SESSION['fileserver']) || !isset($_SESSION['parametersetting'])) {
        $json['success'] = "false";
        $json['message'] = "The session is not properly initialized!";
        $json['files'] = array();
        return (json_encode($json));
    }

    // Store the new file format
    $parameterFileFormat = $_SESSION['parametersetting']->parameter("ImageFileFormat");
    $parameterFileFormat->setValue($format);
    $_SESSION['parametersetting']->set($parameterFileFormat);

    // Get the Fileserver object
    $fileServer = $_SESSION['fileserver'];

    // Should we condense the files in the source folder?
    if ($autoseries === true) {
        $allFiles = $fileServer->condenseSeries();
    } else {
        if ($fileServer->hasFiles()) {
            $allFiles = $fileServer->getCurrentFileList();
        } else {
            $allFiles = $fileServer->scanAndStoreFiles(true);
        }
    }

    $selectedFiles = $fileServer->selectedFiles();

    // Prepare the lists to be returned to the client
    $preparedLists = processFilesAndSelectedFilesLists($allFiles, $selectedFiles, $fileServer, $format);

    // Add the file list to the result json object
    $json["files"] = $preparedLists["files"];
    $json["selected_files"] = $preparedLists["selected_files"];

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Returns the list of file formats
 * @return string JSON-encoded response.
 */
function jsonGetFileFormats()
{
    // Prepare the output array
    $json = initJSONArray();

    // If the ParameterSetting object already exists, avoid recreating it
    if (isset($_SESSION) && isset($_SESSION['parametersetting'])) {
        $parameterSetting = $_SESSION['parametersetting'];
    } else {
        $parameterSetting = new ParameterSetting();
    }

    $fileFormat = $parameterSetting->parameter("ImageFileFormat");
    $formats = $fileFormat->possibleValues();

    // We want to sort the fields alphabetically on their translations.
    $translations = array();
    foreach ($formats as $key => $format) {
        $translations[$key] = $fileFormat->translatedValueFor($format);
    }
    sort($translations);

    $keyArr = array();
    foreach ($translations as $key => $translation) {
        $format = $formats[$key];        

        if ($format == "all") {
            $translation .= " Please choose a file format ...";
        }

        $keyArr[$format] = $translation;
    }

    // Add the file list to the result json object
    $json["formats"] = $keyArr;

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Reset the list of source files and rescan on request.
 * @param $format String File format.
 * @return string JSON-encoded response.
 */
function jsonResetSourceFiles($format)
{
    // Prepare the output array
    $json = initJSONArray();

    // Is the session active?
    if (!isset($_SESSION) || !isset($_SESSION['fileserver'])) {
        $json['success'] = "false";
        $json['message'] = "Could not access Fileserver object in session!";
        $json['files'] = array();
        return (json_encode($json));
    }

    // Get the Fileserver object
    $fileServer = $_SESSION['fileserver'];

    // Reset the list of files
    $fileServer->resetFiles();

    // Reset the selection
    $fileServer->removeAllFilesFromSelection();

    // Prepare the lists to be returned to the client
    $preparedLists = processFilesAndSelectedFilesLists(
        $fileServer->getCurrentFileList(),
        $fileServer->selectedFiles(),
        $fileServer,
        $format
    );

    // Add the file list to the result json object
    $json["files"] = $preparedLists["files"];
    $json["selected_files"] = $preparedLists["selected_files"];

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Add list of files to current selection.
 * @param $fileList array List of file names.
 * @return string JSON-encoded response.
 */
function jsonAddFilesToSelection($fileList, $format)
{
    // Prepare the output array
    $json = initJSONArray();

    // Initialize output
    $json["files"] = [];
    $json["selected_files"] = [];

    // Are there files to add?
    if (count($fileList) == 0) {
        // We still consider this a success
        return json_encode($json);
    }

    // Is the session active?
    if (!isset($_SESSION) || !isset($_SESSION['fileserver'])) {
        $json['success'] = "false";
        $json['message'] = "Could not access Fileserver object in session!";
        $json['files'] = array();
        return (json_encode($json));
    }

    // Get the Fileserver object
    $fileServer = $_SESSION['fileserver'];

    // // Sanitize file names
    // @TODO Disabled for now.
    // for ($i = 0; $i < count($fileList); $i++) {
    //     $fileList[$i] = str_replace('&nbsp;', ' ', $fileList[$i]);
    // }

    // Add the new files to the selection
    $fileServer ->addFilesToSelection($fileList);

    // Get updated selection list
    $selectedFiles = $fileServer->selectedFiles();

    // Prepare the lists to be returned to the client
    $preparedLists = processFilesAndSelectedFilesLists(
        $fileServer->getCurrentFileList(),
        $selectedFiles,
        $fileServer,
        $format
    );

    // Add the file list to the result json object
    $json["files"] = $preparedLists["files"];
    $json["selected_files"] = $preparedLists["selected_files"];

    // Return as a JSON string
    return (json_encode($json));
}

/**
 * Remove list of files from current selection.
 * @param $fileList array List of file names.
 * @return string JSON-encoded response.
 */
function jsonRemoveFilesFromSelection($fileList, $format)
{
    // Prepare the output array
    $json = initJSONArray();

    // Initialize output
    $json["files"] = [];
    $json["selected_files"] = [];

    // Are there files to remove?
    if (count($fileList) == 0) {
        // We still consider this a success
        return json_encode($json);
    }

    // // Sanitize file names
    // @TODO Disabled for now.
    // for ($i = 0; $i < count($fileList); $i++) {
    //     $fileList[$i] = str_replace('&nbsp;', ' ', $fileList[$i]);
    // }

    // Is the session active?
    if (!isset($_SESSION) || !isset($_SESSION['fileserver'])) {
        $json['success'] = "false";
        $json['message'] = "Could not access Fileserver object in session!";
        $json['files'] = array();
        return (json_encode($json));
    }

    // Get the Fileserver object
    $fileServer = $_SESSION['fileserver'];

    // Add the new files to the selection
    $fileServer ->removeFilesFromSelection($fileList);

    // Get updated selection list
    $selectedFiles = $fileServer->selectedFiles();

    // Prepare the lists to be returned to the client
    $preparedLists = processFilesAndSelectedFilesLists(
        $fileServer->getCurrentFileList(),
        $selectedFiles,
        $fileServer,
        $format
    );

    // Add the file list to the result json object
    $json["files"] = $preparedLists["files"];
    $json["selected_files"] = $preparedLists["selected_files"];

    // Return as a JSON string
    return (json_encode($json));
}

/*
 *
 * SUPPORT FUNCTIONS
 *
 */

function processFilesAndSelectedFilesLists($allFiles, $selectedFiles, $fileServer, $format)
{
    // Are the selected files compatible with the current format?
    if (count($selectedFiles) > 0 && !$fileServer->checkAgainstFormat($selectedFiles[0], $format)) {
        $fileServer->removeAllFilesFromSelection();
        $selectedFiles = [];
    }

    // Remove the selected files from the complete list
    $allFiles = array_diff($allFiles, $selectedFiles);

    // Sanitize and filter the file names.
    $files = array();
    if ($allFiles != null) {
        // Filter by file type
        foreach ($allFiles as $key => $file) {
            if ($fileServer->checkAgainstFormat($file, $format)) {
                // Consecutive spaces are collapsed into one space in HTML.
                // Hence '&nbsp;' to correct this when the file has more spaces.
                // @TODO Disabled for now.
                // $filteredFile = str_replace(' ', '&nbsp;', $file);
                // $files[] = $filteredFile;
                $files[] = $file;
            }
        }
    }


    // // Sanitize and filter the file names.
    // @TODO Disabled for now.
    // for ($i = 0; $i < count($selectedFiles); $i++) {
    //     $selectedFiles[$i] = str_replace(' ', '&nbsp;', $selectedFiles[$i]);
    // }

    // Returned the two lists
    $preparedLists["files"] = $files;
    $preparedLists["selected_files"] = $selectedFiles;

    return $preparedLists;
}
