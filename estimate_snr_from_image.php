<?php

// php page: estimate_snr_from_image.php

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS)

// contributors :
// 	     Pierre Travo	(concept)
// 	     Volker Baecker	(concept, implementation)
// >         Jose Viña          (SNR estimation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr
// >    jose@svi.nl

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and 
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL 
// license as circulated by CEA, CNRS and INRIA at the following URL 
// "http://www.cecill.info".

// As a counterpart to the access to the source code and  rights to copy, 
// modify and redistribute granted by the license, users are provided only 
// with a limited warranty and the software's author, the holder of the 
// economic rights, and the successive licensors  have only limited 
// liability.

// In this respect, the user's attention is drawn to the risks associated 
// with loading, using, modifying and/or developing or reproducing the 
// software by the user in light of its specific status of free software, 
// that may mean that it is complicated to manipulate, and that also 
// therefore means that it is reserved for developers and experienced 
// professionals having in-depth IT knowledge. Users are therefore encouraged 
// to load and test the software's suitability as regards their requirements 
// in conditions enabling the security of their systems and/or data to be 
// ensured and, more generally, to use and operate it in the same conditions 
// as regards security.

// The fact that you are presently reading this means that you have had 
// knowledge of the CeCILL license and that you accept its terms.

require_once("./inc/User.inc");
require_once("./inc/Fileserver.inc");

function showFileBrowser($type = "") {

    //$browse_folder can be 'src' or 'dest'.
    $browse_folder = "src";
    $page_title = "Estimate SNR from a raw image";
    $form_title = "Available images";
    $top_navigation = "
            <li>".$_SESSION['user']->name()."</li>
            <li><a href=\"javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpSnrEstimator')\"><img src=\"images/help.png\" alt=\"help\" />&nbsp;Help</a></li>
            ";
    $multiple_files = false;
    // Number of displayed files.
    $size = 15;
    // Show files of the same type as in the current task:
    $type = "";
    if ( $_SESSION['user']->name() == "admin") {
        # The administrator can edit templates without adding a task...
        $restrictFileType = false;
    } else {
        $restrictFileType = true;
        $fileFormat = $_SESSION['setting']->parameter("ImageFileFormat");
        $type = $fileFormat->value();
    }
    // To (re)generate the thumbnails, use data from the current template for
    // colors (wavelengths).
    $useTemplateData = 1;
    $file_buttons = array();
    $file_buttons[] = "update";

    $control_buttons = "
        <input type=\"button\" value=\"\" class=\"icon up\"
        onmouseover=\"Tip('Go back without estimating the SNR.')\"
        onmouseout=\"UnTip()\"
        onclick=\"document.location.href='task_parameter.php'\" />";

    $control_buttons .= "
        <input type=\"submit\" value=\"\" class=\"icon calc\"
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

    include("./inc/FileBrowser.inc");
}


function estimateSnrFromFile($file) {


    include("header.inc.php");

    $top_navigation = "
            <li>".$_SESSION['user']->name()."</li>
            <li><a href=\"javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpSnrEstimator')\"><img src=\"images/help.png\" alt=\"help\" />&nbsp;Help</a></li>
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

    if ($_SESSION['user']->name() != "admin") {

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
        "-returnImages \"0.5 0.71 1 1.71 \" -series $series $extra";



    // Navigations buttons are shown after the image is processed. No
    // line-breaks in this declarations, as this is going to be escaped for
    // JavaScript.

    $buttons = "<input type=\"button\" value=\"\" class=\"icon previous\" onmouseover=\"Tip('Select another image.' )\" onmouseout=\"UnTip()\" onclick=\"document.location.href='estimate_snr_from_image.php'\" />";

    $buttons .= "<input type=\"button\" value=\"\" class=\"icon next\" onmouseover=\"Tip('Proceed to the restoration parameters.' )\" onmouseout=\"UnTip()\" onclick=\"document.location.href='task_parameter.php'\" /></div>";


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
      <div id="controls" class="" onmouseover="smoothChangeDivCond('general','thumb','<?php echo escapeJavaScript($defaultView);?>', 200);">
      </div>
      </div> <!-- content -->

      <div id="rightpanel" onmouseover="smoothChangeDivCond('general','thumb','<?php echo escapeJavaScript($defaultView);?>',200;">
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

    $estimation = askHuCore("estimateSnrFromImage", $opt);
    // No line-breaks in the output, it is going to be escaped for JavaScript.
    $output = 
        "<h3>SNR estimation</h3>".
        "<fieldset>".
        "<table>";

    // debug: dump returned array.
    // $output .= "<pre>". str_replace("\n", "<br />", var_export($estimation, true)). "</pre>";

    $chanCnt = $estimation['channelCnt'];


    $msgSNR = "<p>Suggested SNR values per channel:</p><p>";
    $msgBG = "<p>Estimated background per channel:</p><p>";
    $msgClip = "";

    for ($ch = 0; $ch < $chanCnt; $ch++ ) {
        $output .= "<tr><td>".
            "<table>".
            "<tr><td colspan=\"1\">".
            "<b><hr>Channel $ch</b></td></tr><tr>";

        $chKey = "Ch_$ch,";
        $estSNR = $estimation[$chKey.'estSNR'];
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
                          "onmouseover=\"Tip('$tag'); changeDiv('thumb','$zoomImg', 300); window.divCondition = 'zoom';\"  onmouseout=\"UnTip()\"/>";
                $output .= "<br /><small>Original</small>";
            } else {
                $output .=  "<img src=\"file_management.php?getThumbnail=".
                          $tmbFile."&amp;dir=src\" alt=\"SNR $snr\" ".
                          "onmouseover=\"Tip('Simulation for $tag'); changeDiv('thumb','$zoomImg', 300); window.divCondition = 'zoom';\" onmouseout=\"UnTip()\"/>";
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

    $message = "<h3>Estimation results</h3>".
               "<p>Please <b>write down</b> these values to use them ".
               "in the settings editor.</p>".
               $msgClip.$msgSNR;
    if ( isset($estimation['error']) ) {
        foreach ($estimation['error'] as $line) {
            $message .= $line;
        }
    }
    $message .= "<div id=\"thumb\">".$defaultView."</div>";
    $output .= "</table></fieldset>";
    $preload .= "} ";


    ?>
    </div>
    <script type="text/javascript">
    <!--
         window.divCondition = 'general';
         <?php // preloading code doesn't seem to help (at least if it doesn't go in the head of the document; echo $preload; ?>
         smoothChangeDiv('info','<?php echo escapeJavaScript($message); ?>',1300);
         smoothChangeDiv('output','<?php echo escapeJavaScript($output); ?>',1000);
         smoothChangeDiv('controls','<?php echo escapeJavaScript($buttons); ?>',1500);
         changeDiv('tmp','');
    -->
    </script>

<?php
}


session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

$task = "select";

if (count($_POST) == 1 && isset($_POST['userfiles'] )) {
   $task = "calculate";
   $file = $_POST['userfiles'][0];
}


if ( $task == "select") {
  showFileBrowser($type);
} else {
   estimateSnrFromFile($file);
}

include("footer.inc.php");

?>

