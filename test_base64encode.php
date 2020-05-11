<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once dirname(__FILE__) . '/inc/bootstrap.php';

session_start();


if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

// Add needed JS scripts
$script = array("json-rpc-client.js");

include("header.inc.php");

?>

<div id="content">
    <div id="message"><p /></div>
    <div id="xy_preview"></div>
    <div id="xz_preview"></div>
</div> <!-- content -->

<?php

include("footer.inc.php");

?>

<!-- Ajax functions -->
<script type="text/javascript">

    $(document).ready(function () {
        // Call jsonGetFileFormats on the server
        JSONRPCRequest({
            method: 'jsonGetBase64EncodedPreviewsForImage',
            params: ["src", "Chrom/chrom.h5"]
        }, function (response) {
            if (response["success"] === "false") {
                // Display error message
                $("#message p").text(response["message"]);
            } else {

                // Clear current content of 'xy_preview'
                const xy_preview_div = $("#xy_preview");
                xy_preview_div.empty();

                if (response["xy"] !== null) {
                    // Create a new image and add the base64-encoded image
                    let img_xy_div = $("<img />",
                        {
                            src: response["xy"],
                            display: "inline",
                            title: "XY"
                        });
                    xy_preview_div.append(img_xy_div);
                }

                if (response["xz"] !== null) {
                    // Clear current content of 'xz_preview'
                    const xz_preview_div = $("#xz_preview");
                    xz_preview_div.empty();

                    // Create a new image and add the base64-encoded image
                    let img_xz_div = $("<img />",
                        {
                            src: response["xz"],
                            display: "inline",
                            title: "XZ"
                        });
                    xz_preview_div.append(img_xz_div);
                }
            }
        });
    });
</script>
