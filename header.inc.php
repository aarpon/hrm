<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once( "./inc/System.inc.php" );
require_once( "./inc/Util.inc.php" );

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";

?>

<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>
<?php

if (isset($meta)) {
  echo "    ".$meta;
}

?>

  <title>Huygens Remote Manager</title>
    <link rel="SHORTCUT ICON" href="images/hrm.ico"/>

    <!-- Include jQuery --> 
    <script type="text/javascript" src="scripts/jquery-1.8.2.min.js"></script>
    
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

    <script type="text/javascript">
    <!--
    <?php echo $generatedScript ?>
    -->
    </script>
<?php

}



?>
    <style type="text/css">

<?php
    if (using_IE () ) {
        echo '@import url("css/default_ie.css");';
    } else {
        echo '@import url("css/default.css");';
    }
?>
      
    </style>
    
</head>

<body>

      <!--
        // Use the great Tooltip JavaScript Library by Walter Zorn
      -->
      <script type="text/javascript"
        src="./scripts/wz_tooltip/wz_tooltip.js">
      </script>

<div id="basket">

<?php if (!isset($excludeTitle)) { ?>
	  <div id="title">
	  <h1>
          Huygens Remote Manager
            <span id="about">v<?php echo System::getHRMVersion( ); ?></span>
      </h1>
  	  <div id="logo"></div>
	  </div>
<?php } ?>

