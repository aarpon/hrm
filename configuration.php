<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Log;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

global $email_admin;
global $authenticateAgainst;

session_start();

if (isset($_GET['exited'])) {
    if (session_id() && isset($_SESSION['user'])) {
        Log::info("User " . $_SESSION['user']->name() . " logged off.");
        $_SESSION['user']->logout();
        $_SESSION = array();
        session_unset();
        session_destroy();
    }
    header("Location: " . "login.php");
    exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

$message = "";

include("header_cf.inc.php");

?>

<div id="fullpage">

    <h1>HRM configuration</h1>

    <div class="bs-callout bs-callout-warning hidden">
        <p>Please correct the invalid fields!</p>
    </div>

    <div class="bs-callout bs-callout-info hidden">
        <p>All fields validate correctly!</p>
    </div>

    <form id="configuration_form">

        <!-- --------------------------------------------------------------------------
                Huygens settings
        --------------------------------------------------------------------------- -->

        <p class="section_title">Huygens settings</p>

        <p class="section_explanation">Explanation.</p>

        <div class="section">

            <!-- Processing hucore executable -->
            <label for="hucore_path">Full path to processing Huygens Core executable</label>
            <input type="text"
                   class="form-control"
                   name="hucore_path"
                   data-parsley-ispath=""
                   placeholder="/usr/local/svi/hucore"
                   required>
            <p class="param_param_explanation">Explanation</p>

            <!-- Local (freeware) hucore executable -->
            <label for="local_hucore_path">Full path to local Huygens Core executable</label>
            <input type="text"
                   class="form-control"
                   name="local_hucore_path"
                   data-parsley-ispath=""
                   placeholder="/usr/local/svi/hucore"
                   required>
            <p class="param_explanation">Path to a local Huygens Core executable, to handle image
                information and thumbnails. This installation of hucore doesn't require a
                full license unless this is also the computation server. For image information
                and thumbnails mostly freeware functions will be used, but support for certain
                proprietary file formats requires a B flag in the license string.
                (See http://support.svi.nl/wiki/FileFormats).</p>

            <!-- Queue Manager and Huygens Core run on the same machine -->
            <label for="image_processing_is_on_queuemanager">Queue Manager and Huygens Core run on the same
                machine</label>
            <select id="image_processing_is_on_queuemanager" required>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
            <p class="param_explanation">Explanation</p>

            <!-- Images must be copied to the Huygens Core machine -->
            <label for="copy_images_to_huygens_server">Images must be copied to the Huygens Core machine</label>
            <select id="copy_images_to_huygens_server" required>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
            <p class="param_explanation">Set to Yes if the images must be copied to the machine running
                Huygens Core over SSH.</p>

            <!-- huygens user -->
            <label for="huygens_user">Huygens server user</label>
            <input type="text"
                   class="form-control"
                   name="huygens_user"
                   placeholder="huygens"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- huygens group-->
            <label for="huygens_group">Huygens server group</label>
            <input type="text"
                   class="form-control"
                   name="huygens_group"
                   placeholder="huygens"
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
                   placeholder="localhost"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- File server user -->
            <label for="image_user">File server user</label>
            <input type="text"
                   class="form-control"
                   name="image_user"
                   placeholder="hrm"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- File server group -->
            <label for="image_group">File server group</label>
            <input type="text"
                   class="form-control"
                   name="image_group"
                   placeholder="hrm"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- File server image base folder -->
            <label for="image_folder">File server image base folder</label>
            <input type="text"
                   class="form-control"
                   name="image_folder"
                   data-parsley-ispath=""
                   placeholder="/scratch/hrm_data"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- File server image source folder -->
            <label for="image_source">File server image source folder</label>
            <input type="text"
                   class="form-control"
                   name="image_source"
                   placeholder="src"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- File server image destination folder -->
            <label for="image_destination">File server image destination folder</label>
            <input type="text"
                   class="form-control"
                   name="image_destination"
                   placeholder="dst"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- File server image base folder as seen from the server machines -->
            <label for="huygens_server_image_folder">File server image base folder as seen from the processing
                machines</label>
            <input type="text"
                   class="form-control"
                   name="huygens_server_image_folder"
                   data-parsley-ispath=""
                   placeholder="/scratch/hrm_data"
                   required>
            <p class="param_explanation">Explanation</p>

            <!-- Allow HTTP transfer (download) of the restored images -->
            <label for="allow_http_transfer">Allow HTTP transfer (download) of the restored images</label>
            <select id="allow_http_transfer" required>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
            <p class="param_explanation">Explanation</p>

            <!-- Allow HTTP transfer (upload) of the original images to be restored -->
            <label for="allow_http_upload">Allow HTTP transfer (upload) of the original images to be restored</label>
            <select id="allow_http_upload" required>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
            <p class="param_explanation">Limitations in upload size must be configured in the parameters
                defined below.</p>

            <!-- Maximum file size for uploads in MB -->
            <label for="max_upload_limit">Maximum file size for uploads in MB</label>
            <input type="number"
                   class="form-control"
                   name="max_upload_limit"
                   placeholder="0"
                   required>
            <p class="param_explanation">Limits the maximum file size that can be uploaded. Set
                to zero to have it read from PHP.ini (upload_max_filesize).</p>

            <!-- Maximum post size for uploads in MB -->
            <label for="max_upload_limit">Maximum post size for uploads in MB</label>
            <input type="number"
                   class="form-control"
                   name="max_upload_limit"
                   placeholder="0"
                   required>
            <p class="param_explanation">Limits the maximum post size from the client. Set
                to zero to have it read from PHP.ini (post_max_size).</p>

            <!-- Archiver for downloading the results via the web browser -->
            <label for="compress_ext">Archiver for downloading the results via the web browser</label>
            <select id="compress_ext" required>
                <option value="zip">.zip</option>
                <option value="tgz">.tgz|.tar.gz</option>
                <option value="tar">.tar</option>
            </select>
            <p class="param_explanation">Type of the archive that will be served when downloading
                data from HRM.</p>

        </div>

        <!-- --------------------------------------------------------------------------
            HRM configuration parameters
        --------------------------------------------------------------------------- -->

        <p class="section_title">Web application parameters</p>

        <p class="section_explanation">Explanation.</p>

        <div class="section">

            <!-- Full URL of the HRM web application. -->
            <label for="hrm_url">HRM web application URL</label>
            <input type="text"
                   class="form-control"
                   name="hrm_url"
                   placeholder="http://localhost/hrm"
                   required>
            <p class="param_explanation">Full URL of the HRM web application.</p>

            <!-- Web application installation directory -->
            <label for="hrm_path">HRM installation dir</label>
            <input type="text"
                   class="form-control"
                   name="image_folder"
                   data-parsley-ispath=""
                   placeholder="/var/www/html/hrm"
                   required>
            <p class="param_explanation">Installation directory of the HRM web application.</p>

            <!-- HRM log directory -->
            <label for="log_dir">HRM log directory</label>
            <input type="text"
                   class="form-control"
                   name="log_dir"
                   data-parsley-ispath=""
                   placeholder="/var/log/hrm"
                   required>
            <p class="param_explanation">HRM log directory.</p>

            <!-- HRM log file name -->
            <label for="log_file">HRM log file name</label>
            <input type="text"
                   class="form-control"
                   name="log_file"
                   placeholder="hrm.log"
                   required>
            <p class="param_explanation">HRM log file name.</p>

            <!-- Log verbosity -->
            <label for="log_verbosity">Logging verbosity</label>
            <select id="log_verbosity" required>
                <option value="0">Quiet</option>
                <option value="1">Normal</option>
                <option value="2">Debug</option>
            </select>
            <p class="param_explanation">Explanation</p>

        </div>

        <!-- --------------------------------------------------------------------------
        E-mail settings
        --------------------------------------------------------------------------- -->

        <p class="section_title">E-mail settings</p>

        <p class="section_explanation">Explanation.</p>

        <div class="section">

            <!-- Send e-mails -->
            <label for="send_mail">Send e-mails</label>
            <select id="send_mail" required>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
            <p class="param_explanation">Send e-mails to the users.</p>

            <!-- E-mail address of the sender of HRM e-mails -->
            <label for="email_sender">E-mail address of the sender of HRM e-mails</label>
            <input type="text"
                   class="form-control"
                   name="email_sender"
                   placeholder="hrm@localhost"
                   required>
            <p class="param_explanation">E-mails sent by HRM will be associated with this e-mail address.</p>

            <!-- Name of the sender of e-mails -->
            <label for="log_file">Name of the sender of e-mails</label>
            <input type="text"
                   class="form-control"
                   name="email_admin"
                   placeholder="HRM administrator"
                   required>
            <p class="param_explanation">E-mails sent by HRM will be associated to this name.</p>

            <!-- E-mail list separator -->
            <label for="email_list_separator">E-mail list separator</label>
            <select id="email_list_separator" required>
                <option value=",">Comma (,)</option>
                <option value=";">Semicolon (;)</option>
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
            <select id="default_authentication" required>
                <option value="integrated">Integrated</option>
                <option value="active_dir">Active Directory</option>
                <option value="ldap">Generic LDAP</option>
            </select>
            <p class="param_explanation">This is the default authentication for new users.</p>

            <label for="add_authentication_1">Select an additional authentication mechanism (optional)</label>
            <select id="add_authentication_1" required>
                <option value="none">None</option>
                <option value="integrated">Integrated</option>
                <option value="active_dir">Active Directory</option>
                <option value="ldap">Generic LDAP</option>
            </select>
            <p class="param_explanation">This is an additional (optional) authentication mechanism.</p>

            <label for="add_authentication_1">Select an additional authentication mechanism (optional)</label>
            <select id="add_authentication_1" required>
                <option value="none">None</option>
                <option value="integrated">Integrated</option>
                <option value="active_dir">Active Directory</option>
                <option value="ldap">Generic LDAP</option>
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
            <select id="use_thumbnails" required>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
            <p class="param_explanation">Generate thumbnails and previews for both the source
                and the results images during the deconvolution on the computation servers.
                These are maximum intensity projections (MIP) of the stacks along the main
                axes, so they provide xy, xz and yz views.</p>

            <!-- Create thumbnails of source files (web server) -->
            <label for="gen_thumbnails">Create thumbnails of source files on demand in HRM</label>
            <select id="gen_thumbnails" required>
                <option value="1">Yes</option>
                <option value="0">No</option>
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
                   placeholder="300"
                   required>
            <p class="param_explanation">Set the size to 0 to disable the generation of an
                AVI movie that allows browsing along Z for 3D stacks, and along MIPs in time.
                The movie will have this maximum number of pixels in the largest dimension.</p>

            <!-- Lateral size (in pixels) of movie for side-by-side comparison (raw vs. deconvolved) -->
            <label for="max_comparison_size">Lateral size (in pixels) of movie for side-by-side comparison</label>
            <input type="number"
                   class="form-control"
                   name="max_comparison_size"
                   placeholder="300"
                   required>
            <p class="param_explanation">If non-zero, save stack and time series previews of
                the original and restored data side by side, to allow comparisons. This is the
                max size in pixels for each of the images' slicer.</p>

            <!-- Save Simulated Fluorescence Process top-view rendering -->
            <label for="save_sfp_previews">Save Simulated Fluorescence Process top-view rendering</label>
            <select id="save_sfp_previews" required>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
            <p class="param_explanation">Generate a Simulated Fluorescence Process top-view rendering
                of the original and the restored images (http://support.svi.nl/wiki/SFP).</p>

        </div>

        <!-- Validate -->
        <input type="submit" class="btn btn-default" value="validate">

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
            })
            .on('form:submit', function () {
                return false; // Don't submit form for this demo
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