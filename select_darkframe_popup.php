<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt


use hrm\Fileserver;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

// fileserver related code
if (!isset($_SESSION['fileserver'])) {
    $name = $_SESSION['user']->name();
    $_SESSION['fileserver'] = new Fileserver($name);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Huygens Remote Manager</title>

    <!-- Include jQuery -->
    <script type="text/javascript" src="scripts/jquery-1.8.3.min.js"></script>

    <script type="text/javascript">
        <!--
        function lock(l) {
            var darkframe = l.options[l.options.selectedIndex].value;
            window.opener.document.forms["select"].elements["darkframe"].value = darkframe;
            window.opener.document.forms["select"].elements["darkframe"].style.color = "black";
        }
        //-->
    </script>

    <!-- Theming support -->
    <script type="text/javascript" src="scripts/theming.js"></script>

    <!-- Main stylesheets -->
    <link rel="stylesheet" type="text/css" href="css/fonts.css?v=3.7">
    <link rel="stylesheet" type="text/css" href="css/default.css?v=3.7">

    <!-- Themes -->
    <link rel="stylesheet" type="text/css" href="css/themes/dark.css?v=3.7" title="dark">
    <link rel="alternate stylesheet" type="text/css" href="css/themes/light.css?v=3.7" title="light">

</head>

<body>

<div>

    <form method="get" action="">

            <fieldset>

                <legend>Available files</legend>
                <?php
                $files = $_SESSION['fileserver']->listFiles(true);

                ?>

                <div id="userfiles">
                    <select name="userfiles[]"
                            title="Available files"
                            class="selection"
                            size="10"
                            onchange="lock(this)">
                        <?php

                        foreach ($files as $file) {
                            $style = "";
                            print "            <option value=\"$file\" $style>$file </option>\n";
                        }

                        ?>
                    </select>
                </div>

            </fieldset>

            <div>
                <input name="update"
                       type="submit"
                       value=""
                       class="icon update"/>
            </div>

            <div>
                <input type="button" value="close" onclick="window.close()"/>
            </div>
    </form>

</div>

</body>

</html>
