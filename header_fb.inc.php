<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Util;
use hrm\UtilV2;
use hrm\System;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

?>

<!DOCTYPE html>

<head>
    <meta charset="utf-8">

    <!-- Include jQuery -->
    <script type="text/javascript" src="scripts/jquery-1.8.3.min.js"></script>

    <?php

if (Util::using_IE()) {
    echo '<meta http-equiv="X-UA-Compatible" content="IE=Edge"/>';
}

?>

  <title>Huygens Remote Manager</title>
<?php
    $ico = 'images/hrm_custom.ico';
    if (!file_exists($ico)) {
        $ico = 'images/hrm.ico';
    }
    echo '    <link rel="SHORTCUT ICON" href="' . $ico . '"/>';
?>
    <link rel="stylesheet" type="text/css" href="scripts/jqTree/jqtree.css">
    <link rel="stylesheet" type="text/css" href="css/jqtree-custom.css">
    <link rel="stylesheet" type="text/css" href="scripts/jquery-ui/jquery-ui-1.9.1.custom.css">

    <!-- Fine uploader's css -->
    <link rel="stylesheet" type="text/css" href="scripts/fineuploader/fine-uploader-new.css">
    <link rel="stylesheet" type="text/css" href="css/custom_fineuploader.css?v=3.7">
    <link rel="stylesheet" type="text/css" href="css/themes/custom_fineuploader_dark.css?v=3.7" title="dark">
    <link rel="alternate stylesheet" type="text/css" href="css/themes/custom_fineuploader_light.css?v=3.7" title="light">

    <!-- Theming support -->
    <script type="text/javascript" src="scripts/theming.js"></script>

    <script type="text/javascript" src="scripts/common.js"></script>

    <!-- Include Fine Uploader -->
    <!--<script src="scripts/fineuploader/jquery.fine-uploader.js"></script> -->

    <!-- Fine Uploader Thumbnails template w/ customization
    ====================================================================== -->
    <script type="text/template" id="qq-template-manual-trigger">
        <div class="qq-uploader-selector qq-uploader" qq-drop-area-text="Drop files here">
            <div class="qq-total-progress-bar-container-selector qq-total-progress-bar-container">
                <div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" class="qq-total-progress-bar-selector qq-progress-bar qq-total-progress-bar"></div>
            </div>
            <div class="qq-upload-drop-area-selector qq-upload-drop-area" qq-hide-dropzone>
                <span class="qq-upload-drop-area-text-selector"></span>
            </div>
            <div class="buttons">
                <div class="qq-upload-button-selector qq-upload-button">
                    <div>Select files</div>
                </div>
                <button type="button" id="trigger-upload" class="btn btn-primary">
                    <i class="icon-upload icon-white"></i>Upload
                </button>
            </div>
            <span class="qq-drop-processing-selector qq-drop-processing">
                <span>Processing dropped files...</span>
                <span class="qq-drop-processing-spinner-selector qq-drop-processing-spinner"></span>
            </span>
            <ul class="qq-upload-list-selector qq-upload-list" aria-live="polite" aria-relevant="additions removals">
                <li>
                    <div class="qq-progress-bar-container-selector">
                        <div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" class="qq-progress-bar-selector qq-progress-bar"></div>
                    </div>
                    <span class="qq-upload-spinner-selector qq-upload-spinner"></span>
                    <img class="qq-thumbnail-selector" qq-max-size="100" qq-server-scale>
                    <span class="qq-upload-file-selector qq-upload-file"></span>
                    <span class="qq-edit-filename-icon-selector qq-edit-filename-icon" aria-label="Edit filename"></span>
                    <input class="qq-edit-filename-selector qq-edit-filename" tabindex="0" type="text">
                    <span class="qq-upload-size-selector qq-upload-size"></span>
                    <button type="button" class="qq-btn qq-upload-cancel-selector qq-upload-cancel">Cancel</button>
                    <button type="button" class="qq-btn qq-upload-retry-selector qq-upload-retry">Retry</button>
                    <button type="button" class="qq-btn qq-upload-delete-selector qq-upload-delete">Delete</button>
                    <span role="status" class="qq-upload-status-text-selector qq-upload-status-text"></span>
                </li>
            </ul>

            <dialog class="qq-alert-dialog-selector">
                <div class="qq-dialog-message-selector"></div>
                <div class="qq-dialog-buttons">
                    <button type="button" class="qq-cancel-button-selector">Close</button>
                </div>
            </dialog>

            <dialog class="qq-confirm-dialog-selector">
                <div class="qq-dialog-message-selector"></div>
                <div class="qq-dialog-buttons">
                    <button type="button" class="qq-cancel-button-selector">No</button>
                    <button type="button" class="qq-ok-button-selector">Yes</button>
                </div>
            </dialog>

            <dialog class="qq-prompt-dialog-selector">
                <div class="qq-dialog-message-selector"></div>
                <input type="text">
                <div class="qq-dialog-buttons">
                    <button type="button" class="qq-cancel-button-selector">Cancel</button>
                    <button type="button" class="qq-ok-button-selector">Ok</button>
                </div>
            </dialog>
        </div>
    </script>

<?php

if (isset($script)) {
	if ( is_array( $script ) ) {
		foreach ( $script as $current ) {

			// Workaround for the lack of canvas in IE
			if ( $current == "highcharts/excanvas.compiled.js" ) {
				?>
				<!--[if IE]>
				<script type="text/javascript"
                    src="scripts/<?php echo $current ?>"></script>
				<![endif]-->
				<?php
			} else {
				?>
				<script type="text/javascript"
                    src="scripts/<?php echo $current ?>"></script>
				<?php
			}
		}
	} else {
			// Workaround for the lack of canvas in IE
			if ( $script == "highcharts/excanvas.compiled.js" ) {
				?>
				<!--[if IE]>
				<script type="text/javascript"
                    src="scripts/<?php echo $script ?>"></script>
				<![endif]-->
				<?php
			} else {
				?>
				<script type="text/javascript"
                    src="scripts/<?php echo $script ?>"></script>
				<?php
			}
	}
}

if (isset($generatedScript)) {

?>

    <script type="text/javascript"><?php echo $generatedScript ?></script>

<?php
}
?>

    <!-- Main stylesheets -->
    <link rel="stylesheet" type="text/css" href="css/fonts.css?v=3.7">
    <link rel="stylesheet" type="text/css" href="css/default.css?v=3.7">

    <!-- Themes -->
    <link rel="stylesheet" type="text/css" href="css/themes/dark.css?v=3.7" title="dark">
    <link rel="alternate stylesheet" type="text/css" href="css/themes/light.css?v=3.7" title="light">

    <!--[if lt IE 9]>
    <h3>This browser is OBSOLETE and is known to have important issues with HRM.
        Please upgrade to a later version of Internet Explorer or to a new
        browser altogether.</h3>
    <![endif]-->

<?php
    $custom_css = "css/custom.css";
    if (file_exists($custom_css)) {
        echo '    <link rel="stylesheet" href="' . $custom_css . '">' . "\n";
    }
?>

    <script>
        <!-- Apply the theme -->
        apply_stored_or_default_theme();
    </script>

</head>

<body>

      <!--
        // Use the great Tooltip JavaScript Library by Walter Zorn
      -->
      <script type="text/javascript" src="./scripts/wz_tooltip/wz_tooltip.js"></script>

<div id="basket">

<?php if (!isset($excludeTitle)) { ?>    
    <div id="title">
        <h1>
            Huygens Remote Manager
        </h1>
    </div>
<?php } ?>
