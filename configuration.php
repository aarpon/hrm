<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use \hrm\Settings;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

// Create/get the (singleton) Setting class. It is used to read and persist settings in the database.
$instanceSettings = Settings::getInstance();

$message = "";

// Try storing the posted settings
$storageWasSuccessful = true;
if (isset($_POST) && count($_POST) > 0) {
    // The setAll() method will perform validation
    if ($instanceSettings->setAll($_POST)) {
        // If not error, was returned, we can persist the Settings to the database
        $instanceSettings->save();
        $storageWasSuccessful = true;
    } else {
        $storageWasSuccessful = false;
    }
}

include("header_cf.inc.php");

?>

<div id="fullpage">

    <h1>HRM configuration</h1>

    <?php
    if ($storageWasSuccessful == false) {
        ?>
        <h2 style="color:red">There was an issue storing your settings. Please check your inputs!</h2>
        <?php
    }
    ?>

    <div class="bs-callout bs-callout-warning hidden">
        <p>Please correct the invalid fields!</p>
    </div>

    <div class="bs-callout bs-callout-info hidden">
        <p>All fields validate correctly!</p>
    </div>

    <form id="configuration_form" method="post">

        <!-- --------------------------------------------------------------------------
            Web server (HRM web application) parameters
        --------------------------------------------------------------------------- -->

        <p class="section_title">Web server settings</p>

        <p class="section_explanation">These options apply the HRM web application
            (web server).</p>

        <div class="section">

            <!-- Full URL of the HRM web application. -->
            <label for="hrm_url">HRM web application URL</label>
            <input type="text"
                   class="form-control"
                   name="hrm_url"
                   placeholder="Example: http://localhost/hrm"
                   value="<?php echo $instanceSettings->get("hrm_url"); ?>"
                   required>
            <p class="param_explanation">Full URL of the HRM web application as seen from
                the users' browsers.</p>

            <!-- Web application installation directory -->
            <label for="hrm_path">HRM installation dir</label>
            <input type="text"
                   class="form-control"
                   name="hrm_path"
                   data-parsley-ispath=""
                   placeholder="Example: /var/www/html/hrm"
                   value="<?php echo $instanceSettings->get("hrm_path"); ?>"
                   required>
            <p class="param_explanation">Installation directory of the HRM web application.</p>

            <!-- Local (freeware) hucore executable -->
            <label for="local_huygens_core">Full path to local Huygens Core executable</label>
            <input type="text"
                   class="form-control"
                   name="local_huygens_core"
                   data-parsley-ispath=""
                   placeholder="Example: /usr/local/svi/hucore"
                   value="<?php echo $instanceSettings->get("local_huygens_core"); ?>"
                   required>
            <p class="param_explanation">Path to a local Huygens Core executable that is
                used by the web server to handle image information and thumbnails. This
                installation of Huygens Core mostly uses freeware functions that do not
                require a full license. However, support for certain proprietary file
                formats requires the <i>Additional file readers</i> license.</p>
        </div>

        <!-- --------------------------------------------------------------------------
        Logging
        --------------------------------------------------------------------------- -->

        <p class="section_title">Logging</p>

        <p class="section_explanation">These options apply to logging for both the HRM
            web application (web server user) and the Queue Manager user.</p>

        <div class="section">

            <!-- HRM log directory -->
            <label for="log_dir">HRM log directory</label>
            <input type="text"
                   class="form-control"
                   name="log_dir"
                   data-parsley-ispath=""
                   placeholder="Example: /var/log/hrm"
                   value="<?php echo $instanceSettings->get("log_dir"); ?>"
                   required>
            <p class="param_explanation">HRM log directory: it must be writable by
                both the web server and the Queue Manager users.</p>

            <!-- HRM log file name -->
            <label for="log_file">HRM log file name</label>
            <input type="text"
                   class="form-control"
                   name="log_file"
                   placeholder="Example: hrm.log"
                   value="<?php echo $instanceSettings->get("log_file"); ?>"
                   required>
            <p class="param_explanation">HRM log file name.</p>

            <!-- Log verbosity -->
            <label for="log_verbosity">Logging verbosity</label>
            <select id="log_verbosity"
                    name="log_verbosity" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $selected_2 = "";
                $value = $instanceSettings->get("log_verbosity");
                if ($value == "0") {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == "1") {
                    $selected_1 = "selected=\"selected\"";
                }
                if ($value == "2") {
                    $selected_2 = "selected=\"selected\"";
                }
                ?>
                <option value="0" <?php echo($selected_0); ?>>Quiet</option>
                <option value="1" <?php echo($selected_1); ?>>Normal</option>
                <option value="2" <?php echo($selected_2); ?>>Debug</option>
            </select>
            <p class="param_explanation">Explanation</p>

        </div>

        <!-- --------------------------------------------------------------------------
                Processing server settings
        --------------------------------------------------------------------------- -->

        <p class="section_title">Processing server settings</p>

        <p class="section_explanation">These options apply to the machine running
            Huygens Core.</p>

        <div class="section">

            <!-- Processing hucore executable -->
<!--
            <label for="hucore_path">Full path to processing Huygens Core executable</label>
            <input type="text"
                   class="form-control"
                   name="hucore_path"
                   data-parsley-ispath=""
                   placeholder="Example: /usr/local/svi/hucore"
                   value="THIS IS NOT IN THE CONFIGURATION FILE!"
                   required>
            <p class="param_param_explanation">Explanation</p>
-->
            <!-- Queue Manager and Huygens Core run on the same machine -->
            <label for="image_processing_is_on_queue_manager">Queue Manager and Huygens Core run on the same
                machine</label>
            <select id="image_processing_is_on_queue_manager"
                    name="image_processing_is_on_queue_manager" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $value = $instanceSettings->get("image_processing_is_on_queue_manager");
                if ($value == false) {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == true) {
                    $selected_1 = "selected=\"selected\"";
                }
                ?>
                <option value="0" <?php echo($selected_0); ?>>No</option>
                <option value="1" <?php echo($selected_1); ?>>Yes</option>
            </select>
            <p class="param_explanation">Explanation</p>

            <!-- File server image base folder as seen from the server machines -->
            <label for="huygens_server_image_folder">File server image base folder as seen from the processing
                machines</label>
            <input type="text"
                   class="form-control"
                   name="huygens_server_image_folder"
                   data-parsley-ispath=""
                   placeholder="Example: /scratch/hrm_data"
                   value="<?php echo $instanceSettings->get("huygens_server_image_folder"); ?>"
                   required>
            <p class="param_explanation">Explanation.</p>

            <!-- Images must be copied to the Huygens Core machine -->
            <label for="copy_images_to_huygens_server">Images must be copied to the Huygens Core machine</label>
            <select id="copy_images_to_huygens_server"
                    name="copy_images_to_huygens_server" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $value = $instanceSettings->get("copy_images_to_huygens_server");
                if ($value == false) {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == true) {
                    $selected_1 = "selected=\"selected\"";
                }
                ?>
                <option value="0" <?php echo($selected_0); ?>>No</option>
                <option value="1" <?php echo($selected_1); ?>>Yes</option>
            </select>
            <p class="param_explanation">Set to Yes if the images must be copied to the machine running
                Huygens Core over SSH.</p>

            <!-- huygens user -->
            <label for="huygens_user">Huygens server user</label>
            <input type="text"
                   class="form-control"
                   name="huygens_user"
                   placeholder="Example: hrm"
                   value="<?php echo $instanceSettings->get("huygens_user"); ?>"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- huygens group-->
            <label for="huygens_group">Huygens server group</label>
            <input type="text"
                   class="form-control"
                   name="huygens_group"
                   placeholder="Example: hrm"
                   value="<?php echo $instanceSettings->get("huygens_group"); ?>"
                   required>
            <p class="param_explanation">Explanation</p>
        </div>

        <!-- --------------------------------------------------------------------------
                File server settings
        --------------------------------------------------------------------------- -->

        <p class="section_title">File server settings</p>

        <p class="section_explanation">Explanation.</p>

        <div class="section">

            <!-- Image host -->
            <label for="image_host">File server host name</label>
            <input type="text"
                   class="form-control"
                   name="image_host"
                   placeholder="Example: localhost"
                   value="<?php echo $instanceSettings->get("image_host"); ?>"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- File server user -->
            <label for="image_user">File server user</label>
            <input type="text"
                   class="form-control"
                   name="image_user"
                   placeholder="Example: hrm"
                   value="<?php echo $instanceSettings->get("image_user"); ?>"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- File server group -->
            <label for="image_group">File server group</label>
            <input type="text"
                   class="form-control"
                   name="image_group"
                   placeholder="Example: hrm"
                   value="<?php echo $instanceSettings->get("image_group"); ?>"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- File server image base folder -->
            <label for="image_folder">File server image base folder</label>
            <input type="text"
                   class="form-control"
                   name="image_folder"
                   data-parsley-ispath=""
                   placeholder="Example: /scratch/hrm_data"
                   value="<?php echo $instanceSettings->get("image_folder"); ?>"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- File server image source folder -->
            <label for="image_source">File server image source folder</label>
            <input type="text"
                   class="form-control"
                   name="image_source"
                   placeholder="Example: src"
                   value="<?php echo $instanceSettings->get("image_source"); ?>"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- File server image destination folder -->
            <label for="image_destination">File server image destination folder</label>
            <input type="text"
                   class="form-control"
                   name="image_destination"
                   placeholder="Example: dst"
                   value="<?php echo $instanceSettings->get("image_destination"); ?>"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- Allow HTTP transfer (download) of the restored images -->
            <label for="allow_http_download">Allow HTTP transfer (download) of the restored images</label>
            <select id="allow_http_download"
                    name="allow_http_download" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $value = $instanceSettings->get("allow_http_download");
                if ($value == false) {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == true) {
                    $selected_1 = "selected=\"selected\"";
                }
                ?>
                <option value="0" <?php echo($selected_0); ?>>No</option>
                <option value="1" <?php echo($selected_1); ?>>Yes</option>
            </select>
            <p class="param_explanation">Explanation</p>

            <!-- Allow HTTP transfer (upload) of the original images to be restored -->
            <label for="allow_http_upload">Allow HTTP transfer (upload) of the original images to be restored</label>
            <select id="allow_http_upload"
                    name="allow_http_upload" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $value = $instanceSettings->get("allow_http_upload");
                if ($value == false) {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == true) {
                    $selected_1 = "selected=\"selected\"";
                }
                ?>
                <option value="0" <?php echo($selected_0); ?>>No</option>
                <option value="1" <?php echo($selected_1); ?>>Yes</option>
            </select>
            <p class="param_explanation">Limitations in upload size must be configured in the parameters
                defined below.</p>

            <!-- Maximum file size for uploads in MB -->
            <label for="max_upload_limit">Maximum file size for uploads in MB</label>
            <input type="number"
                   class="form-control"
                   name="max_upload_limit"
                   placeholder="Example: 0"
                   value="<?php echo $instanceSettings->get("max_upload_limit"); ?>"
                   required>
            <p class="param_explanation">Limits the maximum file size that can be uploaded. Set
                to zero to have it read from PHP.ini (upload_max_filesize).</p>

            <!-- Maximum post size for uploads in MB -->
            <label for="max_post_limit">Maximum post size for uploads in MB</label>
            <input type="number"
                   class="form-control"
                   name="max_post_limit"
                   placeholder="Example: 0"
                   value="<?php echo $instanceSettings->get("max_post_limit"); ?>"
                   required>
            <p class="param_explanation">Limits the maximum post size from the client. Set
                to zero to have it read from PHP.ini (post_max_size).</p>

        </div>

        <!-- --------------------------------------------------------------------------
        E-mail settings
        --------------------------------------------------------------------------- -->

        <p class="section_title">E-mail settings</p>

        <p class="section_explanation">Explanation.</p>

        <div class="section">

            <!-- Send e-mails -->
            <label for="send_mail">Send e-mails</label>
            <select id="send_mail"
                    name="send_mail" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $value = $instanceSettings->get("send_mail");
                if ($value == false) {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == true) {
                    $selected_1 = "selected=\"selected\"";
                }
                ?>
                <option value="0" <?php echo($selected_0); ?>>No</option>
                <option value="1" <?php echo($selected_1); ?>>Yes</option>
            </select>
            <p class="param_explanation">Send e-mails to the users.</p>

            <!-- E-mail address of the sender of HRM e-mails -->
            <label for="email_sender">E-mail address of the sender of HRM e-mails</label>
            <input type="text"
                   class="form-control"
                   name="email_sender"
                   placeholder="Example: hrm@localhost"
                   value="<?php echo $instanceSettings->get("email_sender"); ?>"
                   required>
            <p class="param_explanation">E-mails sent by HRM will be associated with this e-mail address.</p>

            <!-- Name of the sender of e-mails -->
            <label for="email_admin">Name of the sender of e-mails</label>
            <input type="text"
                   class="form-control"
                   name="email_admin"
                   placeholder="Example: HRM administrator"
                   value="<?php echo $instanceSettings->get("email_admin"); ?>"
                   required>
            <p class="param_explanation">E-mails sent by HRM will be associated to this name.</p>

            <!-- E-mail list separator -->
            <label for="email_list_separator">E-mail list separator</label>
            <select id="email_list_separator"
                    name="email_list_separator" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $value = $instanceSettings->get("email_list_separator");
                if ($value == ',') {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == ';') {
                    $selected_1 = "selected=\"selected\"";
                }
                ?>
                <option value="," <?php echo($selected_0); ?>>Comma (,)</option>
                <option value=";" <?php echo($selected_1); ?>>Semicolon (;)</option>
            </select>
            <p class="param_explanation">Comma (',') is the standard separator for lists of email addresses
                (http://tools.ietf.org/html/rfc2822#section-3.6.3). Unfortunately, Microsoft Outlook
                uses semicolon (';') by default and ',' only optionally (http://support.microsoft.com/kb/820868).
                If your users are mostly using Outlook, you can tell configure HRM to use ';' instead of the
                standard ',' for distribution lists.</p>

        </div>

        <!-- --------------------------------------------------------------------------
        Authentication
        --------------------------------------------------------------------------- -->

        <p class="section_title">Authentication</p>

        <p class="section_explanation">Supported authentication types. "Integrated" is the
            internal HRM authentication; "Active Directory" is Microsoft's directory
            service; "Generic LDAP" is an LDAP service that does not follow Microsoft's
            structure.</p>

        <div class="section">

            <label for="default_authentication">Select the default authentication mechanism</label>
            <select id="default_authentication"
                    name="default_authentication" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $selected_2 = "";
                $value = $instanceSettings->get("default_authentication");
                if ($value == "integrated") {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == "active_dir") {
                    $selected_1 = "selected=\"selected\"";
                }
                if ($value == "ldap") {
                    $selected_2 = "selected=\"selected\"";
                }
                ?>
                <option value="integrated" <?php echo($selected_0); ?>>Integrated</option>
                <option value="active_dir" <?php echo($selected_1); ?>>Active Directory</option>
                <option value="ldap" <?php echo($selected_2); ?>>Generic LDAP</option>
            </select>
            <p class="param_explanation">This is the default authentication for new users.</p>

            <label for="alt_authentication_1">Select an additional authentication mechanism (optional)</label>
            <select id="alt_authentication_1"
                    name="alt_authentication_1" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $selected_2 = "";
                $selected_3 = "";
                $value = $instanceSettings->get("alt_authentication_1");
                if ($value == "none") {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == "integrated") {
                    $selected_1 = "selected=\"selected\"";
                }
                if ($value == "active_dir") {
                    $selected_2 = "selected=\"selected\"";
                }
                if ($value == "ldap") {
                    $selected_3 = "selected=\"selected\"";
                }
                ?>
                <option value="none" <?php echo($selected_1); ?>>None</option>
                <option value="integrated" <?php echo($selected_1); ?>>Integrated</option>
                <option value="active_dir" <?php echo($selected_2); ?>>Active Directory</option>
                <option value="ldap" <?php echo($selected_3); ?>>Generic LDAP</option>
            </select>
            <p class="param_explanation">This is an additional (optional) authentication mechanism.</p>

            <label for="alt_authentication_2">Select an additional authentication mechanism (optional)</label>
            <select id="alt_authentication_2"
                    name="alt_authentication_2" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $selected_2 = "";
                $selected_3 = "";
                $value = $instanceSettings->get("alt_authentication_2");
                if ($value == "none") {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == "integrated") {
                    $selected_1 = "selected=\"selected\"";
                }
                if ($value == "active_dir") {
                    $selected_2 = "selected=\"selected\"";
                }
                if ($value == "ldap") {
                    $selected_3 = "selected=\"selected\"";
                }
                ?>
                <option value="none" <?php echo($selected_1); ?>>None</option>
                <option value="integrated" <?php echo($selected_1); ?>>Integrated</option>
                <option value="active_dir" <?php echo($selected_2); ?>>Active Directory</option>
                <option value="ldap" <?php echo($selected_3); ?>>Generic LDAP</option>
            </select>
            <p class="param_explanation">This is an additional (optional) authentication mechanism.</p>

        </div>

        <!-- --------------------------------------------------------------------------
        Previews
        --------------------------------------------------------------------------- -->

        <p class="section_title">Previews</p>

        <p class="section_explanation">Explanation.</p>

        <div class="section">

            <!-- Create thumbnails of source and result files (during deconvolution) -->
            <label for="use_thumbnails">Create thumbnails of source and result files (during deconvolution)</label>
            <select id="use_thumbnails"
                    name="use_thumbnails" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $value = $instanceSettings->get("use_thumbnails");
                if ($value == false) {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == true) {
                    $selected_1 = "selected=\"selected\"";
                }
                ?>
                <option value="0" <?php echo($selected_0); ?>>No</option>
                <option value="1" <?php echo($selected_1); ?>>Yes</option>
            </select>
            <p class="param_explanation">Generate thumbnails and previews for both the source
                and the results images during the deconvolution on the computation servers.
                These are maximum intensity projections (MIP) of the stacks along the main
                axes, so they provide xy, xz and yz views.</p>

            <!-- Create thumbnails of source files (web server) -->
            <label for="gen_thumbnails">Create thumbnails of source files on demand in HRM</label>
            <select id="gen_thumbnails"
                    name="gen_thumbnails" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $value = $instanceSettings->get("gen_thumbnails");
                if ($value == false) {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == true) {
                    $selected_1 = "selected=\"selected\"";
                }
                ?>
                <option value="0" <?php echo($selected_0); ?>>No</option>
                <option value="1" <?php echo($selected_1); ?>>Yes</option>
            </select>
            <p class="param_explanation">On-demand generation of a preview of the original
                images by the web server prior to the deconvolution. For this to work the
                local Huygens Core executable (see above) must be set correctly. Notice that
                not all file formats are readable in a freeware Huygens Core and for full
                functionality you may need a license string. Otherwise just let the original
                thumbnails be generated during the deconvolution itself with the previous option.
                This also enables the SNR estimator tools.</p>

            <!-- Lateral size (in pixels) of a movie to browse date through Z and time -->
            <label for="movie_max_size">Lateral size (in pixels) of a movie to browse date through Z and time</label>
            <input type="number"
                   class="form-control"
                   name="movie_max_size"
                   placeholder="Example: 300"
                   value="<?php echo $instanceSettings->get("movie_max_size"); ?>"
                   required>
            <p class="param_explanation">Set the size to 0 to disable the generation of an
                AVI movie that allows browsing along Z for 3D stacks, and along MIPs in time.
                The movie will have this maximum number of pixels in the largest dimension.</p>

            <!-- Lateral size (in pixels) of movie for side-by-side comparison (raw vs. deconvolved) -->
            <label for="max_comparison_size">Lateral size (in pixels) of movie for side-by-side comparison</label>
            <input type="number"
                   class="form-control"
                   name="max_comparison_size"
                   placeholder="Example: 300"
                   value="<?php echo $instanceSettings->get("max_comparison_size"); ?>"
                   required>
            <p class="param_explanation">If non-zero, save stack and time series previews of
                the original and restored data side by side, to allow comparisons. This is the
                max size in pixels for each of the images' slicer.</p>

            <!-- Save Simulated Fluorescence Process top-view rendering -->
            <label for="save_sfp_previews">Save Simulated Fluorescence Process top-view rendering</label>
            <select id="save_sfp_previews"
                    name="save_sfp_previews" required>
                <?php
                $selected_0 = "";
                $selected_1 = "";
                $value = $instanceSettings->get("save_sfp_previews");
                if ($value == false) {
                    $selected_0 = "selected=\"selected\"";
                }
                if ($value == true) {
                    $selected_1 = "selected=\"selected\"";
                }
                ?>
                <option value="0" <?php echo($selected_0); ?>>No</option>
                <option value="1" <?php echo($selected_1); ?>>Yes</option>
            </select>
            <p class="param_explanation">Generate a Simulated Fluorescence Process top-view rendering
                of the original and the restored images (http://support.svi.nl/wiki/SFP).</p>

        </div>

        <!-- Validate -->
        <div id="controls">
            <input type="submit" id="conf_submit" class="icon save" value="validate">
        </div>

    </form>

</div> <!-- content -->

<?php

include("footer.inc.php");

?>

<script type="text/javascript">
    $(function () {
        $('#configuration_form').parsley()
            .on('field:validated', function () {
                var ok = $('.parsley-error').length === 0;
                $('.bs-callout-info').toggleClass('hidden', !ok);
                $('.bs-callout-warning').toggleClass('hidden', ok);
                if (ok === true) {
                    $('#configuration_form').submit();
                }
            })
            .on('form:submit', function () {
                return true;
            })
    });
    $(function () {
        window.Parsley.addValidator('ispath', {
            validateString: function (value) {
                // @TODO: Add proper validation!
                return true;
            },
            requirementType: 'string',
            messages: {
                en: 'Please enter a valid path.'
            }
        })
    });
    $(function () {
        window.Parsley.addValidator('isbool', {
            validateString: function (value) {
                return (
                    value === "true" ||
                    value === "false" ||
                    value === "1" ||
                    value === "0"
                );
            },
            requirementType: 'boolean',
            messages: {
                en: 'Please enter true or false.'
            }
        })
    });
</script>