<?php
/**
 * FileBrowser
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm;

require_once dirname(__FILE__) . '/bootstrap.php';

// JavaScript
$script = array("settings.js",
                "jquery-1.8.3.min.js",
                "jqTree/tree.jquery.js",
                "jquery-ui/jquery-ui-1.9.1.custom.js",
                "jquery-ui/jquery.bgiframe-2.1.2.js",
                "fineuploader/jquery.fine-uploader.js",
                "vue.js",
                "omero.js");

include("header_fb.inc.php");

?>

    <div id="nav">
        <div id="navleft">
            <ul>
            <?php
                if (isset($top_nav_left)) {
                    echo $top_nav_left;
                } else {
                    echo(Nav::linkWikiPage('HuygensRemoteManagerHelpFileManagement'));
                }
            ?>
            </ul>
        </div>
        <div id="navright">
            <ul>
            <?php
            if ( isset( $_SESSION['filemanager_referer'] ) ) {
                $referer = $_SESSION['filemanager_referer'];
                // the "Back" button is only displayed if the referring page
                // was not the dashboard (home.php) but e.g. the "Select
                // images" when creating a new job.
                if ( strpos( $referer, 'home.php' ) === False ) {
                    echo(Nav::linkBack($referer));
                }
            }
            if ( $browse_folder == "dest" ) {
                echo(Nav::linkRawImages());
            } else {
                echo(Nav::linkResults());
            }
            echo(Nav::textUser($_SESSION['user']->name()));
            echo(Nav::linkHome(Util::getThisPageName()));
            ?>
            </ul>
        </div>
        <div class="clear"></div>
    </div>

    <div id="content" >

    <div id="filebrowser">

            <div class="tree">
                <filetree :nodes="tree"></filetree>
            </div>

            <div class="list">
                <filelist :files="files"></filetree>
            </div>
    </div>

    <script type="text/javascript" src="scripts/filebrowser.js"></script>

    </div>

