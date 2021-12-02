<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// session_start();

/**
 * Creates a span with a mouseover effect that displays a default tip on
 * the developer name.
 * @param $name String Developer name to be added to the mouseover tip.
 * @return String <span> Element to be echoed to the page.
 */
function addTip($name)
{
    return ("<span onmouseover=\"Tip('Login to see contact information.' )\" " .
        "onmouseout=\"UnTip()\">$name</span>");
}

/**
 * Creates a string with names and (optionally) emails for a list of developers
 * to be echoed on the page. If the email for a specific developer is "", then
 * code to generate a default tooltip is added instead
 *
 * @see addTip()
 *
 * @param array $name_list Array of developer names
 * @param array $email_list Array of developer email addresses (can contain "")
 * @return string To be echoed on the page
 */
function addDevelopers(array $name_list, array $email_list)
{

    // Total number of developers to add
    $numDev = count($name_list);

    $str = "";
    for ($i = 0; $i < $numDev; $i++) {

        // Get name and email
        $name = $name_list[$i];
        $email = $email_list[$i];  // Can be empty

        // Append
        if ($email != "") {
            //$str .= '<a href=\"mailto:' . $email. '\">'  . $name . '</a>';
            $str .= "<a href=\"mailto:$email\">$name</a>";
        } else {
            $str .= addTip($name);
        }

        // Add the appropriate separator (if needed)
        if ($numDev == 1) {
            return $str;
        }

        if ($i == ($numDev - 1)) {
            // Nothing to add. The string is finished
        } elseif ($i == ($numDev - 2)) {
            $str .= " & ";
        } else {
            $str .= ", ";
        }
    }

    // Return the string
    return $str;
}

?>

<?php

use hrm\System;
use hrm\Nav;

// Check whether a user is currently logged in
$loggedIn = (isset($_SESSION['user']) && $_SESSION['user']->isLoggedIn());

?>

<div id="footer">

    <div id="version_info">
        Huygens Remote Manager
        <?php
            $devel = '.hrm_devel_version';
            if (file_exists($devel)) {
                echo file_get_contents($devel);
            } else {
                echo "v" . System::getHRMVersionAsString();
            }
        ?>
    </div>
    <?php
    echo(Nav::actionStyleToggle());
    ?>
</div>

</div> <!-- basket -->

</body>

</html>


