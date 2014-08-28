<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once ("./inc/User.inc.php");
require_once("./inc/wiki_help.inc.php");

session_start();

if (!isset ($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit ();
}


$params = array("d", "na", "wl", "mo", "msys");
$reportparams = array("micro", "d", "na", "wl", "mo", "msys", "c");



// What follows is a mini-database of microscope models with the necessary known
// parameters.
// Developers adding a new microscope model should see the usage of these
// parameters at
// http://support.svi.nl/wiki/index.php?edit=ReportOtherMicroscope
// It is not easy because originally this was not to be included in the HRM but
// online on the SVI server. Popular demand convinced us to include it embedded
// in the HRM but without much time to make this more programmer-friendly.
// Still it is easier than it looks, so please just read the linked
// instructions.
// Or even better, just report the new model to SVI and we will include online
// and in the HRM, for the benefit of all users:
// http://support.svi.nl/wiki/ReportOtherMicroscope

$microscopes = array (
    "Biorad MRC 500, 600 and 1024" =>
    array ("micro=Biorad_MRC_500_600_1024&param=Pinhole+diameter+(mm)&a=1&b=0&na=0&wl=0&msys=53.2&c=0.5&u=-3&cmsys=1&extra1=1.25&txt1=Fluorescence+attachment&extra2=1.25&txt2=DIC+attachment", "http://support.svi.nl/wiki/BioradMRC_500_600_1024"),
    "Biorad Radiance" =>
    array ("micro=Biorad_Radiance&param=Pinhole+diameter+(mm)&a=1&b=0&na=0&wl=0&msys=73.2&c=0.5&u=-3&cmsys=1&extra1=1.25&txt1=Fluorescence+attachment&extra2=1.25&txt2=DIC+attachment", "http://support.svi.nl/wiki/Biorad_Radiance"),
    "Leica confocal TCS 4d (parameter P_8)" =>
    array
    ("micro=Leica_TCS4d_P8&param=Reported+parameter+(P_8)&a=2.39216&b=20&na=0&wl=0&msys=4.5&c=0.56419&u=-6",
    "http://support.svi.nl/wiki/LeicaConfocal_TCS4d_SP1_NT"),
    "Leica confocals TCS 4d, SP1 and NT (Airy disk units)" =>
    array
    ("micro=Leica_TCS4d_SP1_NT_Airy_units&param=Number+of+Airy+disks&msys=0&mo=0&c=0.56419&a=0&b=0&u=0&wl=580",
    "http://support.svi.nl/wiki/LeicaConfocal_TCS4d_SP1_NT"),
    "Leica confocal SP2" =>
    array ("micro=Leica_TCS_SP2_Airy_units&param=Number+of+Airy+disks&msys=0&mo=0&c=0.56419&a=0&b=0&u=0&wl=580", "http://support.svi.nl/wiki/LeicaConfocal_TCS_SP2"),
    "Leica confocal SP5" =>
    array ("micro=Leica_TCS_SP5_Airy_units&param=Number+of+Airy+disks&msys=0&mo=0&c=0.56419&a=0&b=0&u=0&wl=580", "http://support.svi.nl/wiki/LeicaConfocal_TCS_SP5"),
    "Leica confocal SP8" =>
    array ("micro=Leica_TCS_SP8_Airy_units&param=Number+of+Airy+disks&msys=0&mo=0&c=0.56419&a=0&b=0&u=0&wl=580", "http://support.svi.nl/wiki/LeicaConfocal_TCS_SP8"),
    "Nikon TE2000-E with the C1 scanning head" =>
    array ("micro=Nikon_TE2000E_C1&param=Pinhole+diameter+(microns)&a=1&b=0&na=0&wl=0&msys=1&c=0.5&u=-6&extra1=1.5&txt1=Optional+1.5x+magnification",
    "http://support.svi.nl/wiki/Nikon_TE2000E_C1"),
    "Nikon TiE A1R" =>
    array ("micro=Nikon_TiE_A1R&param=Pinhole+diameter+(microns)&a=1&b=0&na=0&wl=0&msys=1&c=0.456&u=-6","http://support.svi.nl/wiki/Nikon_TiE_A1R"),
    "Olympus FV300 and FVX" =>
    array( "micro=Olympus_FV300&param=Reported+pinhole+parameter&table=1&a=1&b=0&na=0&wl=0&msys=3.426&c=0.5&u=-6", "http://support.svi.nl/wiki/Olympus_FV300"),
    "Olympus FV500" =>
    array( "micro=Olympus_FV500&param=Pinhole+side+(microns)&a=1&b=0&na=0&wl=0&msys=3.8&c=0.5641896&u=-6", "http://support.svi.nl/wiki/Olympus_FV500"),
    "Olympus FV1000" =>
    array ("micro=Olympus_FV1000&param=Pinhole+side+(microns)&a=1&b=0&na=0&wl=0&msys=3.82&c=0.5641896&u=-6", "http://support.svi.nl/wiki/Olympus_FV1000"),
    "Yokogawa spinning disk (pinhole radius)" =>
    array ("micro=Yokogawa_spinning_disk&d=50&a=1&b=0&na=0&wl=0&msys=1&c=0.5&u=-6",
    "http://support.svi.nl/wiki/YokogawaDisk"),
    "Yokogawa spinning disk (pinhole distance)" =>
    array ("micro=Yokogawa_disk_(pinhole_distance)&d=253&a=1&b=0&na=0&wl=0&msys=1&c=1&u=-6&ru=-6&rtag=pinhole+distance",
    "http://support.svi.nl/wiki/BackProjectedPinholeDistance"),
    "Zeiss LSM410 inverted" =>
    array ("micro=Zeiss_LSM410_inverted_P8&param=Reported+parameter+(P_8)&a=3.92157&b=0&na=0&wl=0&msys=2.23&c=0.56419&u=-6",
    "http://support.svi.nl/wiki/Zeiss_LSM410_inverted"),
    "Zeiss LSM510" =>
    array("micro=Zeiss_LSM510&param=Pinhole+diameter+(microns)&a=1&b=0&na=0&wl=0&msys=3.33&c=0.5&u=-6", "http://support.svi.nl/wiki/Zeiss_LSM510"),
    "Zeiss LSM700" =>
    array("micro=Zeiss_LSM700&param=Pinhole+diameter+(microns)&a=1&b=0&na=0&wl=0&msys=1.53&c=0.564&u=-6", "http://support.svi.nl/wiki/Zeiss_LSM700"),
    "Zeiss LSM710" =>
    array("micro=Zeiss_LSM710&param=Pinhole+diameter+(microns)&a=1&b=0&na=0&wl=0&msys=1.9048&c=0.564&u=-6", "http://support.svi.nl/wiki/Zeiss_LSM710"),
    "Zeiss LSM780" =>
    array("micro=Zeiss_780&param=Pinhole+diameter+(microns)&a=1&b=0&na=0&wl=0&msys=1.9048&c=0.564&u=-6", "http://support.svi.nl/wiki/Zeiss_LSM780"),
    "Not listed" => 
    array ("micro=Not_listed_microscope&param=Pinhole+physical+diameter+(microns)&a=1&b=0&na=0&wl=0&u=-6", "http://support.svi.nl/wiki/ReportOtherMicroscope")
);



function globalize_vars ($var_string, $type) {
   global ${$type};
   if ($var_string && $type) {
      $var_name = trim(strtok ($var_string, ","));
      global ${$var_name};
      if (!isset(${$var_name}) && isset(${$type}["$var_name"]))
         ${$var_name} = ${$type}["$var_name"];
         #echo $var_name, $$var_name, " ";
      while ($var_name) {
         $var_name = trim(strtok (","));
         global ${$var_name};
         if (!isset(${$var_name}) && isset(${$type}["$var_name"]) )
            ${$var_name} = ${$type}["$var_name"];
      }
   }
}


// Parameters passed by _GET or _POST that are dumped in global variables:
$post_string = "param, micro, d, a, b, c, u, wl, na, mo, msys, task, ref, ".
               "cmsys, extra1, extra2, table, txt1, txt2, checked1, checked2, ".
               "help, ru, rtag";

globalize_vars ($post_string, "_POST");
globalize_vars ($post_string, "_GET");

if (!isset($ref) || $ref =="" )
       $ref = "capturing_parameter.php";

if (!isset($ru) || $ru == "" ) $ru="-9";

// Units of the input parameter
switch($u) {
    case 0:
       $units = "(m)";
       break;
    case -3:
       $units = "(mm)";
       break;
    case -6:
       $units = "(&micro;m)";
       break;
    case -9:
       $units = "(nm)";
       break;
    default:
       $units = "";
       break;
}

// Units of the reported parameter
switch($ru) {
    case 0:
       $runits = "(m)";
       break;
    case -3:
       $runits = "(mm)";
       break;
    case -6:
       $runits = "(&micro;m)";
       break;
    case -9:
       $runits = "(nm)";
       break;
    default:
       $runits = "";
       break;
}


if (!isset($rtag) || $rtag == "" ) {
    $rtag="pinhole radius";
}

if ($param == "") $param = "Reported parameter $units";

$Label = array("micro" => "Microscope model",
                "d"     => $param,
                "wl"    => "Wavelength (nm)",
                "na"    => "Lens numerical aperture",
                "mo"    => "Objective magnification",
                "msys"  => "Internal system magnification",
                "c"     => "Shape factor"
                );

$Default = array("micro" => "Not specified",
                "d"     => "",
                "wl"    => "590",
                "na"    => "1.3",
                "mo"    => "100",
                "msys"  => "1",
                "c"     => "0.5"
                );




    function field($p)
    {

       global $Label, $WikiURL, $WikiArticle;

       #$string = " <a target=\"SviWiki\" href=\"".$WikiURL.$WikiArticle[$param]."\">";
       $string = $Label[$p];
       #$string .= "</a> ";
       return $string;
    }

    function fieldEntry($p)
    {

      global $Details, $Default;
      global $$p;

      if (isset($$p)) $val = $$p;
      else $val =  $Default[$p];

        $string = field($p);
        $string .= "<input type=\"text\" ".
              "name=\"$p\" size=\"5\" value=\"".
               $val."\">";
        #$string .= "<td>".$Details[$param]."</td>";
        return $string;
    }



# --

function start() {
    global $microscopes, $na;

    echo "<h4>Please choose a microscope from the following list:</h4>";

    echo "\n<ul>";

    $script = $_SERVER['PHP_SELF'];


    foreach ($microscopes as $m => $data) {
        $extra = "";
        // If na is not defined in the model parameters take the one reported
        // from the HRM.
        if (!strstr($data[0], "na=" ) && isset($na) && $na != "") {
            $extra = "&na=$na";
        }
        echo "\n<li><a href=\"$script?$data[0]&help=$data[1]&task=form$extra\">$m</a></li>";
    }
    echo "\n</ul>";

    echo "<div id=\"controls\">

            <input type=\"button\" value=\"\" class=\"icon up\"
                  onmouseover=\"Tip('Go back without calculating the ' +
                  'backprojected pinhole radius for your microscope.')\"
                  onmouseout=\"UnTip()\"
                  onclick=\"document.location.href='capturing_parameter.php'\" /></div>";

    echo "\n</div> <!-- content -->\n";
    ?>
         <div id="rightpanel">

        <div id="info">

            <h3>Quick help</h3>

            <?php
            echo "<p>The following forms will assist you in calculating the ".
            "(circular-equivalent) backprojected pinhole radius expressed in ".
            "nanometers, that you can enter directly in the HRM settings.</p>";
            echo "<p>Please mind that there is one special entry to calculate ";
            echo "pinhole distances in spinning disks.</p>";
    echo "<p>Click <b>Help</b> on the top menu for more details.</p>";

    echo "<p>Start by selecting your microscope model from the list on the
        left, or click on cancel at the bottom to go back.</p>";
    echo "</div>";
    echo "</div>";



}

function form($error=false,$imglink="") {

global $params, $reportparams, $ref, $help, $rtag, $ru;
global $a, $b, $c, $d, $u, $param, $wl, $na, $mo, $msys, $micro, $units ;
global $cmsys, $table, $extra1, $txt1, $extra2, $txt2, $checked1, $checked2;

        // Initialize message
        $message = "";

        if ($error) print $error . "<br><br>";
        print "\n<h2>".str_replace("_"," ",$micro)."</h2>";
        print "\n<form action=\"calculate_bp_pinhole.php\" method=\"post\"".
            " id=\"select\">";
        print "\n<fieldset class=\"setting\">";
        print "\nEnter the parameters as reported by your microscope:";

        print "\n<INPUT TYPE=\"hidden\" name=\"task\" value=\"calc\">";
        print "\n<INPUT TYPE=\"hidden\" name=\"ref\" value=\""
                                                       .$ref."\">";
        print "\n<INPUT TYPE=\"hidden\" name=\"param\" value=\""
                                                   .$param."\">";
        #print "\n<INPUT TYPE=\"hidden\" name=\"micro\" value=\""
                                                   #.$micro."\">";
        print "\n<INPUT TYPE=\"hidden\" name=\"a\" value=\""
                                                   .$a."\">";
        print "\n<INPUT TYPE=\"hidden\" name=\"b\" value=\""
                                                   .$b."\">";
     #   print "\n<INPUT TYPE=\"hidden\" name=\"c\" value=\""
                                                   #.$c."\">";
        print "\n<INPUT TYPE=\"hidden\" name=\"u\" value=\""
                                                   .$u."\">";
        print "\n<INPUT TYPE=\"hidden\" name=\"ru\" value=\""
                                                   .$ru."\">";
        print "\n<INPUT TYPE=\"hidden\" name=\"rtag\" value=\""
                                                   .$rtag."\">";
        print "\n<INPUT TYPE=\"hidden\" name=\"table\" value=\""
                                                   .$table."\">";
        print "\n<br><br>";
        $afterTable = "";

     foreach ($reportparams as $entry) {

        if (!isset($$entry) && $$entry !== "" && $$entry !== 0
            || ($entry == "msys" && $cmsys==1) ) {
            print fieldEntry($entry)."<br>\n";
        }
        else
            $afterTable .= "\n<INPUT TYPE=\"hidden\" name=\"".$entry.
                                "\" value=\"".$$entry."\">";
     }

     if (isset($extra1) && isset($txt1)) {
         print (urldecode($txt1));
         print ("\n<input type=\"checkbox\" name=\"extra1\" value=\"$extra1\"".
                " $checked1><br>"); #checked
     }
     if (isset($extra2) && isset($txt2)) {
         print (urldecode($txt2));
         print ("\n<input type=\"checkbox\" name=\"extra2\" value=\"$extra2\"".
                " $checked2><br>"); #checked
     }
     print "<div><input name=\"OK\" type=\"hidden\" /></div>";



     print "\n$afterTable";

?>

          </fieldset>

          <div id="controls">
            <input type="button" value="" class="icon previous"
                  onmouseover="Tip('Go back to the microscope list.' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='<?php echo $_SERVER['PHP_SELF']; ?>'" />
            <input type="submit" value="" class="icon calc"
                  onmouseover="Tip('Calculate the backprojected pinhole ' +
                    'radius or distance from the entered parameters.' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
          </div>

        </form>
      </div> <!-- content -->

      <div id="rightpanel">

        <div id="info">

        <h3>Quick help</h3>

            <p>
               Enter or confirm the requested values and press the calculator
               button to calculate the
               <a href="javascript:openWindow('
                  http://support.svi.nl/wiki/BackProjected')">
                  back projected
               </a>
               pinhole radius.
               Press the back button to go back to the list of microscopes.
            </p>
            <p>
                Read more about the
               <a href="javascript:openWindow('<?php echo $help; ?>')">
                   <?php echo $micro; ?></a> model.
            </p>

       </div>

        <div id="message">

<?php

            echo "<p>$message</p>";
 ?>

       </div>

    </div> <!-- rightpanel -->


<?php
} # END form

# --

function serve() {

global $a, $b, $c, $d, $u, $param, $wl, $na, $mo, $msys, $micro;
global $extra1, $extra2, $table, $ru, $runits, $rtag;
global $ref, $params, $Label, $Default, $reportparams;

$warning = NULL; $out = ""; $error = "";

foreach ( $params as $entry) {
#    $out .= $entry. " ". $$entry."<br>\n";
    if (!isset($$entry) || $$entry == ""   || $$entry < 0) {
                    $$entry = $Default[$entry];
                    $warning .= "\n<br>Using default value ".$$entry.
                        " for ".$Label[$entry]." ".$entry;
    }
}


if ( ($mo == 0 || $msys == 0) && ($na == 0 && $wl ==0) ) {
    $error .= "Wrong magnification value.<br>\n";
} else {
    $M = $mo * $msys;
    if ($extra1) $M *= $extra1;
    if ($extra2) $M *= $extra2;
}

if ($c == 0)
    $error .= "Wrong shape factor value.<br>\n";

if ($d == 0 || !isset($d))
    $error .= "Wrong pinhole value. Please enter a value as reported " .
        "by your microscope.<br>\n";

if ($table == 1) {

    if ($micro == "Olympus_FV300") {

        switch($d) {
            case 1:
              $deff = 60;
              break;
            case 2:
              $deff = 100;
              break;
            case 3:
              $deff = 150;
              break;
            case 4:
              $deff = 200;
              break;
            case 5:
              $deff = 300;
              break;
            default:
              $error .= "Unknown size parameter";
              break;
        }
    }
    else $error .= "Undefined table for model $micro<br>\n";

} else {
    $deff = $d;
}
    #$out .= "d $deff";

if ($na != 0 && $wl != 0) {  # Reported in Airy disks

    $phr = $c * 1.22 * $wl * $deff / $na;
    $M = 1;  #Magnification doesn't apply in this case, don't divide at all.


} elseif ($a != 0) {

  if (!is_numeric($ru)) {
      $error .= "Wrong report units value $ru.<br>\n";
  }
  if (is_numeric($u) ) {
      $unitsf = pow(10,$u);
      $phr = pow(10,-1*$ru) * $unitsf * $c * ($a * $deff + $b);
      #$out .= "rb = ".pow(10,9) * $unitsf." * $c * ($a * $deff plus $b)<br>";
  }
  else
      $error .= "Wrong units value $u.<br>\n";


} else {
    $error .= "Wrong equation: not enough parameters.<br>\n";
}

if ($error) {
    $out .="<h2>Error!</h2>\n".$error;
}
else
{
    $result = round($phr / $M,2);
    $out .= "<h4>";
    $out .=  "Backprojected $rtag $runits: <b>$result</b>";
    $out .= "</h4>";

    $out .= "<p>&nbsp;</p>";

    $out .= "<p>This is the parameter list used in this calculation:</p>";
    $out .= $warning;

    $out .= "<p>";
    $out .= "".field("micro").": ".$micro." ";
    foreach ( $reportparams as $entry ) {
        if ($$entry !=0 && isset($$entry)) {
            $out .= "\n<br>".field($entry).": ".$$entry." ";
            if ($entry == "msys") {
                if ($extra1) $out .= "&times;$extra1 ";
                if ($extra2) $out .= "&times;$extra2 ";
            }
        }
    }
    $out .= "</p>\n";
}

$out .= "<div id=\"controls\">";

$toScript = $_SERVER['HTTP_REFERER'];

$out .= "<input type=\"button\" value=\"\" class=\"icon previous\"
    onmouseover=\"Tip('Try again with other parameters.' )\"
    onmouseout=\"UnTip()\"
    onclick=\"document.location.href='" . $toScript ."'\" />";

$out .= "<input type=\"button\" value=\"\" class=\"icon next\"
    onmouseover=\"Tip('Proceed to the optical parameters back.' )\"
    onmouseout=\"UnTip()\"
    onclick=\"document.location.href='" . $ref ."'\" /></div>";

$out .= "</div> <!-- content -->";

$out .= "<div id=\"rightpanel\">
        <div id=\"info\">
        <h3>Quick help</h3>";

if ($error) {
    $out .= "<p>Please go back and correct the wrong or missing parameter(s) or
                proceed to the optical parameter pages.</p>";
} else {
    $out .= "<p>On the left you can see the result of the calculation and the
                parameters used for it. <u>Please annotate the calculated value
                and enter it in the image parameter pages. The value <strong>
                will not</strong> be transferred automatically</u>.</p>
             <p>You can repeat the calculation with different input values
                (e.g. for other channels) or proceed to the image parameter
                pages.</p>";
}

$out .= "</div>
    </div> <!-- rightpanel -->";

return $out;

} # END calc




# --

############ Start page
$script = "settings.js";

include("header.inc.php");
?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
                wiki_link('BackprojectedPinholeCalculator');
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
                include("./inc/nav/user.inc.php");
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<?php
echo "<div id=\"content\"><h3><img alt=\"CalcPinhole\" src=\"./images/pinhole.png\"
         width=\"40\"/>&nbsp;Backprojected pinhole calculator</h3>";

switch($task) {
    case 'calc':
        $html =  serve();
        echo $html;
        break;
    case 'form':
        form();
        break;
    default:
        start();
# this case doesn't print html headers, it is intended to be called from
# a wiki article by inserting %%Nyquistcalculator%% there.
}
include ("footer.inc.php");

?>
