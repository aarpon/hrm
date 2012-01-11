<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/Util.inc.php");
require_once("./inc/User.inc.php");
require_once("./inc/Fileserver.inc.php");


// Two private functions, for the two tasks of this script:

// This configures and shows the file browser module inc/FileBrowser.inc.php.
// The Signal-to-noise estimator works only on raw images, so the listed
// directory is the source ('src') one.
function showFileBrowser() {

    //$browse_folder can be 'src' or 'dest'.
    $browse_folder = "src";
    $page_title = "Estimate SNR from a raw image (beta)";
    $explanation_text = "<span class=\"message_large_SNR_beta\">
        <img src=\"images/newSNR.png\" alt=\"\" />&nbsp;&nbsp;
        We appreciate
        <a href=\"javascript:openWindow('http://www.svi.nl/BetaSNRFeedback')\">
        your feedback</a> to improve the new SNR estimator!</span><br /><br />
        Please choose an image and click on the calculator button to
        estimate the SNR.";
    $form_title = "Available images";
    $top_navigation = "
            <li><img src=\"images/user.png\" alt=\"user\" />&nbsp;".
                $_SESSION['user']->name()."</li>
            <li><a href=\"javascript:openWindow('".
                "http://support.svi.nl/wiki/style=hrm&amp;".
                "help=HuygensRemoteManagerHelpSnrEstimator".
              "')\">".
            "<img src=\"images/help.png\" alt=\"help\" />&nbsp;Help</a></li>
            ";
    $multiple_files = false;
    // Number of displayed files.
    $size = 15;
    $type = "";
    $useTemplateData = 0;
    if ($_SESSION['user']->isAdmin()) {
        // The administrator can edit templates without adding a task, so no
        // image type is predefined in this case...
        $restrictFileType = false;
        $expandSubImages = true;
    } else {
        // Users can only estimate the SNR inside the workflow of a task
        // creation, so a selected file type is known at this point. Show files
        // of the same type as in the current task:
        $restrictFileType = true;
        $fileFormat = $_SESSION['setting']->parameter("ImageFileFormat");
        $type = $fileFormat->value();
        // To (re)generate the thumbnails, use data from the current template
        // for colors (wavelengths).
        $useTemplateData = 1;
    }
    $file_buttons = array();
    $file_buttons[] = "update";

    $control_buttons = "
        <input type=\"button\" value=\"\" class=\"icon up\"
        onmouseover=\"Tip('Go back without estimating the SNR.')\"
        onmouseout=\"UnTip()\"
        onclick=\"document.location.href='task_parameter.php'\" />";

    $control_buttons .= "
        <input name=\"estimate\" type=\"submit\" value=\"\" class=\"icon calc\"
        onmouseover=\"Tip('Estimate SNR from a selected image.' )\"
        onmouseout=\"UnTip()\"
        onclick=\"process()\" />";

    $info = '
            <h3>Quick help</h3>

            <p>Select a raw image to estimate the Signal-to-Noise Ratio (SNR).
               You can then use the estimated SNR values in the Restoration
               Settings to deconvolve similar images, acquired under similar
               conditions.</p>';

    if ($type != "") {
        $info .= "<p>Only images of type <b>$type</b>, as set in the image
        parameters, are shown.</p>";
    }

    $info .= '<p>Please notice that undersampled or clipped images will provide
            wrong estimations, as well as wrong deconvolution results!</p>
            <p>A SNR value must be set per image channel: please select an
               image that is representative of the datasets you will deconvolve,
               as each channel may have a different noise level.</p>
            <p>Please remind that, in this context, the SNR
               is not a property of the images but a parameter
               for the deconvolution that you can tune, to adapt the result to
               the experimental needs.
               The estimated value is just a reasonable initial parameter.</p>
            <p>Click <b>Help</b> on the top menu for more details.</p>
               ';

    include("./inc/FileBrowser.inc.php");
}




// This function does the main job.
// When a file name is posted, this function processes it by sending it to
// Huygens in the background and showing the result images with the different
// SNR estimations. The best value is shown, but other more pessimistic and
// optimistic values are also included for the user to visually verify the
// validity of the estimate.
function estimateSnrFromFile($file) {

    // If using IE make sure to enforce IE7 Document Mode
    if ( using_IE( ) ) {
        $meta = "<meta http-equiv=\"X-UA-Compatible\" content=\"IE=EmulateIE7\" >";
    }
    include("header.inc.php");

    $top_navigation = "
            <li><img src=\"images/user.png\" alt=\"user\" />&nbsp;".
                $_SESSION['user']->name()."</li>
            <li><a href=\"javascript:openWindow('".
                 "http://support.svi.nl/wiki/style=hrm&amp;".
                 "help=HuygensRemoteManagerHelpSnrEstimator".
              "')\">".
              "<img src=\"images/help.png\" alt=\"help\" />&nbsp;Help</a></li>
            ";

    echo "<div id=\"nav\">
        <ul>$top_navigation</ul>
    </div>";

    // Noise estimations can be done only in raw images.

    $psrc =  $_SESSION['fileserver']->sourceFolder();
    $basename = basename($psrc."/".$file);
    $psrc = dirname($psrc."/".$file);
    $subDir = dirname($file);
    if ( $subDir != "." ) {
        $subDir .= "/";
    } else {
        $subDir = "";
    }
    $pdest = $psrc."/hrm_previews";

    $series = "auto";
    $extra = "";

    if ( !$_SESSION['user']->isAdmin() ) {

        // This is only for when template parameters are available, in a
        // 'add task' workflow. Admin can't see specific colors, just whatever
        // comes from the image.

        $nchan = $_SESSION['setting']->NumberOfChannels();
        $lmbV = "\"";
        $lambda = $_SESSION['setting']->parameter("EmissionWavelength");
        $l = $lambda->value();
        for ( $i = 0; $i < $nchan; $i++ ) {
            $lmbV .= " ".$l[$i];
        }
        $lmbV .= "\"";

        $xy = $_SESSION['setting']->parameter("CCDCaptorSizeX");
        $z = $_SESSION['setting']->parameter("ZStepSize");
        $xy_s = $xy->value() / 1000.0;
        $z_s = $z->value() / 1000.0;
        $extra = " -emission $lmbV -sampling \"$xy_s $xy_s $z_s\"";

        // Enable the -series off option depending on the file type.
        if (stristr($file, ".stk")) {
            $geom = $_SESSION['setting']->parameter("ImageGeometry");
            $geometry = $geom->value();
            if ( !stristr($geometry, "time") ) {
                $series = "off";
            }
        }
        $formatParam = $_SESSION['setting']->parameter('ImageFileFormat');
        $format = $formatParam->value();
        if ($format == "tiff" || $format == "tiff-single") {
            // Olympus FluoView, or single XY plane: always
            $series = "off";
        }
    }


    // Change returnImages to \"0.6 1 1.66 \" in order to show SNR
    // estimates calculated af given factors of the best match. This
    // requires Huygens 3.5.1p2.
    // For 3.5.1p1, use '-returnImages sample' instead.

    $opt = "-basename \"$basename\" -src \"$psrc\" -dest \"$pdest\" ".
        "-returnImages \"0.5 0.71 1 1.71 \" -snrVersion \"new\" ".
        "-series $series $extra";

    // When no particular SNR estimation image is shown (in a small portion of
    // the image), the image preview goes back to the whole image.
    $defaultView = $_SESSION['fileserver']->imgPreview($file, "src",
            "preview_xy", false);


    ?>

    <div id="nav">
    </div>


    <div id="content">
      <div id="output" >
        <h3>Estimating SNR</h3>

        <fieldset>
        <center>
        Processing file<br /><?php echo $file; ?>...<br />
        <b>Please wait</b>
        <img src="images/spin.gif">
        </center>
        </fieldset>
      </div>
      <div id="controls"
           class=""
           onmouseover="smoothChangeDivCond('general', 'thumb',
            '<?php echo escapeJavaScript($defaultView);?>', 200);">
      </div>
      </div> <!-- content -->

      <div id="rightpanel"
           onmouseover="smoothChangeDivCond('general','thumb',
           '<?php echo escapeJavaScript($defaultView);?>', 200);">
      <div id="info">
      <?php // echo $defaultView;  ?>
      </div>

      <div id="message">
      </div> <!-- message -->

    </div> <!-- rightpanel -->

    <!-- Post-processing output: -->
    <div id="tmp">
    <?php

    ob_flush();
    flush();

    // Launch Huygens Core in the background to do the calculations. It will
    // write the JPEG images in a predefined location for this script to
    // display them.

    $estimation = askHuCore("estimateSnrFromImage", $opt);
    // No line-breaks in the output, it is going to be escaped for JavaScript.
    $output =
        "<h3>SNR estimation</h3>".
        "<fieldset>".
        "<table>";

    $chanCnt = $estimation['channelCnt'];


    $msgSNR = "<p>Suggested SNR values per channel:</p><p>";
    $msgBG = "<p>Estimated background per channel:</p><p>";
    $msgClip = "";

    // Keep the results in an easy place to access them later
    $calculatedSNRValues = array();

    for ($ch = 0; $ch < $chanCnt; $ch++ ) {
        $output .= "<tr><td>".
            "<table>".
            "<tr><td colspan=\"1\">".
            "<b><hr>Channel $ch</b></td></tr><tr>";

        $chKey = "Ch_$ch,";
        $estSNR = $estimation[$chKey.'estSNR'];
        $calculatedSNRValues[$ch] = $estSNR;
        $msgSNR .= "Ch $ch: <strong>$estSNR</strong><br />";
        $estBG = $estimation[$chKey.'estBG'];
        $msgBG .= "Ch $ch: $estBG<br />";
        $estClipFactor = $estimation[$chKey.'estClipFactor'];

        if ($estClipFactor > 0.0005) {
            if ($chanCnt > 1) {
                if ($msgClip == "") {
                    $msgClip = "<p>The following channels contain <b>clipped".
                    " voxels</b>. This affects the SNR estimations and the ".
                    "quality of the deconvolution.</p><p>";
                }
                $msgClip .= "Ch $ch: ~". ($estClipFactor * 100) .
                    "% clipped voxels<br>";
            } else {
                    $msgClip = "<p>The image contains about ".
                    ($estClipFactor * 100). "% of <b>clipped".
                    " voxels</b>. This affects the SNR estimation and the ".
                    "quality of the deconvolution.";
            }
        }

        $simVals = explode(" ", $estimation[$chKey.'simulationList']);
        $simImg = explode(" ", $estimation[$chKey.'simulationImages']);
        $simZoom = explode(" ", $estimation[$chKey.'simulationZoom']);
        $bestColumn = array_search($estSNR, $simVals);
        $preload = "if (document.images) {\n";

        $i = 0;
        foreach ($simVals as $snr) {
            $output .= "<td>";

            $tmbFile = urlencode($subDir.$simImg[$i]);
            $zoomFile = urlencode($subDir.$simZoom[$i]);
            if ($snr == 0) {
                $tag = "Original reference data";
            } else {
                $tag = "SNR $snr";
                if ($snr == $estSNR) {
                    $tag = "<b>$tag</b> (Suggested value)";
                } else if ($snr < $estSNR) {
                    $tag = "$tag (Pessimistic estimate)";
                } else {
                    $tag = "$tag (Optimistic estimate)";
                }
            }
            $zoomImg =  escapeJavaScript(
                    "<p><b>Portion of channel $ch:</b></p>".
                    "<p>$tag</p><img src=\"file_management.php?getThumbnail=".
                    $zoomFile."&amp;dir=src\" alt=\"SNR $snr\" id=\"ithumb\"".
                    " />");

            $preload .= ' pic'.$i.'= new Image(200,200); '."\n".
                'pic'.$i.'.src="file_management.php?getThumbnail='.
                $tmbFile.'&dir=src"; '."\n";

            if ($snr == 0) {
                for ($j = 1; $j < $bestColumn; $j++) {
                    // Align the original with the best SNR result
                    $output .= "</td><td>";
                }
                $output .=  "<img src=\"file_management.php?getThumbnail=".
                          $tmbFile."&amp;dir=src\" alt=\"SNR $snr\" ".
                          "onmouseover=\"Tip('$tag'); ".
                          "changeDiv('thumb','$zoomImg', 300); ".
                          "window.divCondition = 'zoom';\"  ".
                          "onmouseout=\"UnTip()\"/>";
                $output .= "<br /><small>Original</small>";
            } else {
                $output .=  "<img src=\"file_management.php?getThumbnail=".
                          $tmbFile."&amp;dir=src\" alt=\"SNR $snr\" ".
                          "onmouseover=\"Tip('Simulation for $tag'); ".
                          "changeDiv('thumb','$zoomImg', 300); ".
                          "window.divCondition = 'zoom';\" ".
                          "onmouseout=\"UnTip()\"/>";
                if ( $snr == $estSNR ) {
                    $output .= "<br /><small><b>SNR ~ $snr</b></small>";
                } else {
                    $output .= "<br /><small>$snr</small>";
                }
            }

            if ($snr == 0) {
                $output .= "</td></tr><tr>";
            } else {
                $output .= "</td>";
            }
            $i++;
        }
        $output .= "</tr></table></td></tr>";
    }

    $msgSNR .= "</p>";
    $msgBG .= "</p>";
    $msgClip .= "</p>";

    # Do not report the $msgBG, not to confuse the users.

    $message = "<h3>Estimation results</h3>" . $msgClip.$msgSNR;

    if ( isset($estimation['error']) ) {
        foreach ($estimation['error'] as $line) {
            $message .= $line;
        }
    }
    $message .= "<div id=\"thumb\">".$defaultView."</div>";
    $output .= "</table></fieldset>";
    $preload .= "} ";


    ?>

    <?php
        // Now create the buttons

        // Navigations buttons are shown after the image is processed. No
        // line-breaks in this declarations, as this is going to be escaped for
        // JavaScript.

        // We put the buttons in a form since we want to submit
        $buttons = "<form method=\"post\" action=\"\" id=\"store\">";

        // We put the 'controls' div around the buttons
        $buttons .= "<div>";

        // We store the calculated values in hidden controls
        $buttons .= "<input type=\"hidden\" name=\"store\" value=\"store\" />";
        for ( $i = 0; $i < count( $calculatedSNRValues ); $i++ ) {
            $buttons .= "<input type=\"hidden\" ".
            "name=\"Channel$i\" value=\"$calculatedSNRValues[$i]\" />";
        }

        $buttons .= "<input type=\"button\" value=\"\" class=\"icon previous\" ".
                    "onmouseover=\"Tip('Try again on another image.' )\" ".
                    "onmouseout=\"UnTip()\" ".
                    "onclick=\"document.location.href='estimate_snr_from_image_beta.php'\" />";

        $buttons .= "<input type=\"button\" value=\"\" class=\"icon up\" ".
                    "onmouseover=\"Tip('Discard the calculated values and " .
                    "return to the restoration parameters page.' )\" ".
                    "onmouseout=\"UnTip()\" ".
                    "onclick=\"document.location.href='task_parameter.php'\" />";

        $buttons .= "<input type=\"submit\" name=\"store\" class=\"icon next\" ".
                    "onmouseover=\"Tip('Accept the calculated values and " .
                    "return to the restoration parameters page.' )\" ".
                    "onmouseout=\"UnTip()\" value=\"\" /></div>";

        $buttons .= "</div>";

        $buttons .= "</form>";

    ?>

    </div>
    <script type="text/javascript">
    <!--
         window.divCondition = 'general';
         <?php
         // Preloading code doesn't seem to help (at least if it doesn't go in
         // the head of the document;
         // echo $preload;
         ?>

         // Show the results with a nice JavaScript smooth transition.
         smoothChangeDiv('info',
             '<?php echo escapeJavaScript($message); ?>',1300);
         smoothChangeDiv('output',
             '<?php echo escapeJavaScript($output); ?>',1000);
         smoothChangeDiv('controls',
             '<?php echo escapeJavaScript($buttons); ?>',1500);
         changeDiv('tmp','');
    -->
    </script>

<?php
}


// This is the starting point of this script.


session_start();

// Ask the user to login if necessary.
if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

// If the $_SESSION['SNR_Calculated'] flag is set we unset it, to make sure
// that it is there only when 'storing' and going back to
// 'parameter_'task_parameter.php'
if ( isset( $_SESSION['SNR_Calculated'] ) ) {
    unset( $_SESSION['SNR_Calculated'] );
}

// Depending on the user actions (or lack thereof) we either display or refresh
// the file browser, or we estimate the SNR from the selected image
if ( isset($_POST['estimate'] ) && isset($_POST['userfiles'] ) ) {

  // Estimate the SNR from the selected file
  $task = "calculate";
  $file = $_POST['userfiles'][0];
  estimateSnrFromFile($file);

} elseif ( isset( $_POST['store'] ) )  {

    // Collect the calculated SNR values
    $found = true;
    $ch = 0;
    $estSNR = array();
    if ( isset( $_POST['Channel0'] ) ) {
        $estSNR[ 0 ] = $_POST['Channel0'];
        while ( 1 ) {
            $ch++;
            $chName = "Channel$ch";
            if ( isset( $_POST[$chName]) ) {
                $estSNR[ $ch ] = $_POST[$chName];
            } else {
                break;
            }
        }
    }

    // Now store the calculated values in the Parameter and back into the session
    $snrParam = $_SESSION['task_setting']->parameter('SignalNoiseRatio');
    $snrParam->setValue( $estSNR );
    $_SESSION['task_setting']->set( $snrParam );

    // Inform task_parameter.php that we do not want the values to be
    // recovered from SessionStorage
    $_SESSION['SNR_Calculated'] = 'true';

    // And now go back to task_parameter.php
    header("Location: " . "task_parameter.php"); exit();

} else {

  // Just show (or refresh) the file browser with the file list
  showFileBrowser();

}

include("footer.inc.php");

?>

