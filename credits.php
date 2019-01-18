<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Log;
use hrm\Nav;
use hrm\user\UserManager;
use hrm\user\proxy\ProxyFactory;
use hrm\Util;

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


$message = "";

$script = array("ajax_utils.js", "json-rpc-client.js");

include("header.inc.php");

?>

<div id="nav">
    <div id="navleft">
    </div>
    <div id="navright">
        <ul>
            <?php
            echo(Nav::linkBack("login.php"));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<div id="homepage">

    <h1>Authors and contributors</h1>

    <p>The following locations and developers have contributed to HRM over the years.</p>

    <h2>Original concept and implementation</h2>
    <ul>
        <li>
            <b>Pierre Travo</b> and <b>Volker Bäcker</b>,
            <a href="https://www.mri.cnrs.fr/" onclick="this.target='_blank'; return true;">
                Montpellier RIO Imaging (CNRS)</a>;
        </li>
        <li>
            <b>Patrick Schwarb</b>
            brought the original version of HRM to the
            <a href="https://www.fmi.ch" onclick="this.target='_blank'; return true;">
                Friedrich Miescher Institute</a>.
        </li>
    </ul>

    <h2>Current developers</h2>
    <ul>
        <li>
            <b>Aaron Ponti</b>,
            <a href="https://www.bsse.ethz.ch/scf" onclick="this.target='_blank'; return true;">
                Single Cell Facility</a>,
            <a href="https://www.bsse.ethz.ch" onclick="this.target='_blank'; return true;">
                Department of Biosystems Science and Engineering</a>,
            <a href="https://www.ethz.ch/en.html" onclick="this.target='_blank'; return true;">
                ETH Zurich</a>;
        </li>
        <li>
            <b>Daniel Sevilla</b>,
            <a href="https://www.svi.nl" onclick="this.target='_blank'; return true;">
                Scientific Volume Imaging</a>;
        </li>
        <li>
            <b>Niko Ehrenfeuchter</b>,
            <a href="https://www.biozentrum.unibas.ch/research/groups-platforms/overview/unit/imcf/"
               onclick="this.target='_blank'; return true;">
                Imaging Core Facility</a>,
            <a href="https://www.biozentrum.unibas.ch" onclick="this.target='_blank'; return true;">
                Biozentrum</a>,
            <a href="https://www.unibas.ch/en" onclick="this.target='_blank'; return true;">
                University of Basel</a>;
        </li>
        <li>
            <b>Torsten Stöter</b>,
            <a href="http://www.lin-magdeburg.de/" onclick="this.target='_blank'; return true;">
                Leibniz Institute for Neurobiology</a>;
        </li>
        <li>
            <b>Felix Meyenhofer</b>,
            <a href="https://www3.unifr.ch/bioimage" onclick="this.target='_blank'; return true;">
                Bioimage | Light Microscopy Facility</a>,
            <a href="https://www3.unifr.ch" onclick="this.target='_blank'; return true;">
                University of Fribourg</a>;
        </li>
        <li>
            <b>Olivier Burri</b>,
            <a href="https://biop.epfl.ch/" onclick="this.target='_blank'; return true;">
                BioImaging and Optics Platform</a>,
            <a href="https://www.epfl.ch/" onclick="this.target='_blank'; return true;">
                EPFL</a>;
        </li>
        <li>
            <b>Egor Zindy</b>,
            <a href="https://www.bmh.manchester.ac.uk/research/facilities/bioimaging/" onclick="this.target='_blank'; return true;">
                BioImaging Facility</a>,
            <a href="https://www.manchester.ac.uk/" onclick="this.target='_blank'; return true;">
                University of Manchester</a>.
        </li>
    </ul>

    <h2>Former developers</h2>
    <ul>
        <li>
            <b>Asheesh Gulati</b>,
            <a href="https://biop.epfl.ch/" onclick="this.target='_blank'; return true;">
                BioImaging and Optics Platform</a>,
            <a href="https://www.epfl.ch/" onclick="this.target='_blank'; return true;">
                EPFL</a>;
        </li>
        <li>
            <b>Alessandra Griffa</b>,
            <a href="https://biop.epfl.ch/" onclick="this.target='_blank'; return true;">
                BioImaging and Optics Platform</a>,
            <a href="https://www.epfl.ch/" onclick="this.target='_blank'; return true;">
                EPFL</a>;
        </li>
        <li>
            <b>José Viña</b>,
            <a href="https://www.svi.nl" onclick="this.target='_blank'; return true;">
                Scientific Volume Imaging</a>;
        </li>
        <li>
            <b>Frederik Grüll</b>,
            <a href="https://www.biozentrum.unibas.ch/research/groups-platforms/overview/unit/imcf/"
               onclick="this.target='_blank'; return true;">
                Imaging Core Facility</a>,
            <a href="https://www.biozentrum.unibas.ch" onclick="this.target='_blank'; return true;">
                Biozentrum</a>,
            <a href="https://www.unibas.ch/en" onclick="this.target='_blank'; return true;">
                University of Basel</a>.
        </li>
    </ul>

    <h2>Other contributors</h2>

    <ul>
        <li>
            <b>Roland Nitschke</b>,
            <a href="https://miap.eu/" onclick="this.target='_blank'; return true;">
                Microscopy and Image Analysis Platform</a>,
            <a href="https://www.uni-freiburg.de/" onclick="this.target='_blank'; return true;">
                University of Freiburg</a>,<br />
            for organizing and hosting several <b>HRM Hackathons</b>.
        </li>
    </ul>

    <h2>Third-party tools</h2>

    <p>A list of third-party tools and libraries used in HRM can be found on the
        <a href="http://www.huygens-rm.org/wp/?page_id=84" onclick="this.target='_blank'; return true;">
            HRM project page</a>.
    </p>

    <h2>License</h2>

    <p>The Huygens Remote Manager is open-source software, released under the conditions of the
        <a href="http://www.huygens-rm.org/wp/?page_id=81" onclick="this.target='_blank'; return true;">
            CeCILL license</a>.
    </p>

</div> <!-- home -->

<?php

include("footer.inc.php");

?>
