<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Util;
use hrm\System;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
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

    <link rel="stylesheet" href="scripts/jqTree/jqtree.css">
    <link rel="stylesheet" href="css/jqtree-custom.css">
    <link rel="stylesheet" href="scripts/jquery-ui/jquery-ui-1.9.1.custom.css">

    <!-- Include jQuery -->
    <script type="text/javascript" src="scripts/jquery-1.8.3.min.js"></script>

    <script type="text/javascript" src="scripts/common.js"></script>

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

    <style type="text/css">
        @import "css/default.css";
    </style>
    <!--[if lt IE 9]>
    <h3>This browser is OBSOLETE and is known to have important issues with HRM.
        Please upgrade to a later version of Internet Explorer or to a new
        browser altogether.</h3>
    <link rel="stylesheet" href="css/default.css">
    <![endif]-->

<?php
    $custom_css = "css/custom.css";
    if (file_exists($custom_css)) {
        echo '    <link rel="stylesheet" href="' . $custom_css . '">' . "\n";
    }
?>

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

