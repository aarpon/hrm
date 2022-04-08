<?php

// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// clear OMERO connection related variables if "disconnectOmero" was requested:
if (isset($_POST['disconnectOmero']) && isset($omeroConnection)) {
    unset($omeroConnection);
    unset($_SESSION['omeroConnection']);
}


// Dialog to ask for the OMERO credentials.
if (isset($_POST['getOmeroData']) && !isset($omeroConnection)) {
    ?>
    <div id="floatingCredentialsDialog" title="OMERO login credentials">

        <p>Your OMERO username and password are needed for
            retrieving data. </p>
        <p>Your login credentials will not be stored.</p>

        <form name="omeroCheckCredentials" method="post">

            <label for="omeroUser">Username:</label>
            <input name="omeroUser" title="OMERO user name" type="text" size="8"
                   value="">
            <br/>
            <label for="omeroPass">Password:</label>
            <input name="omeroPass" title="OMERO user password" type="password"
                   size="8" value="">

            <input name="omeroCheckCredentials" type="hidden" value="true">
            <input name="getOmeroData" type="hidden" value="true">

            <br/>

            <script>

                $(function () {
                    var floatingCredentialsDialog = $("#floatingCredentialsDialog");
                    // Workaround to bind the 'Enter' button to the 'Submit' action.
                    floatingCredentialsDialog.keypress(function (e) {
                        if (e.keyCode == $.ui.keyCode.ENTER) {
                            $(':button:contains("Submit")').click();
                        }
                    });

                    floatingCredentialsDialog.dialog({
                        show: {
                            effect: "slide",
                            duration: 300
                        },
                        hide: {
                            effect: "fade",
                            duration: 300
                        },
                        modal: true,
                        buttons: {
                            "Submit": function () {
                                omeroLogin();
                                $(this).dialog("close");
                            }
                        }
                    })
                });

            </script>

        </form> <!-- omeroCheckCredentials !-->

    </div> <!-- floatingCredentialsDialog !-->
    <?php
}   // endif (isset($_POST['getOmeroData']) && !isset($omeroConnection))


// if we are connected to an OMERO server, always show the tree by default:
if (isset($omeroConnection) && !isset($_POST['disconnectOmero'])) {
    ?>

    <script>
        // hide the web up-/download button to prevent confusion with the
        // OMERO transfer buttons as long as we're having an OMERO connection
        hide("webTransferButton");
    </script>


    <div id="activeTransfer" title="OMERO transfer in progress">
        <p>An OMERO transfer is currently running, please wait
            until it has finished.</p>
        <p>This message will automatically close upon completion.</p>
    </div>

    <script>

        // Hide the "activeTransfer" div in the beginning
        $(document).ready(function () {
            $("#activeTransfer").hide();
        });

        function activeTransferDialog() {
            $("#activeTransfer").dialog({
                modal: true
            })
        }

    </script>

    <fieldset id="OmeroData">
    <legend id="legendOmeroData">Your OMERO data</legend>

    <div id="omeroSelection">

        <form name="omeroForm"
              onsubmit="return omeroTransfer(this, fileSelection,
                  '<?php echo $browse_folder; ?>')"
              action="?folder=<?php echo $browse_folder; ?>"
              method="post">

            <div id="omeroActions">

                <?php
                if ($browse_folder == "src") {
                    ?>
                    <input name="importFromOmero" type="submit"
                           onclick="activeTransferDialog();"
                           value="" class="icon remove"
                           onmouseover="Tip('Transfer selected files from OMERO')"
                           onmouseout="UnTip()"/>
                    <?php
                } else {
                    ?>
                    <input name="exportToOmero" type="submit"
                           onclick="activeTransferDialog();"
                           value="" class="icon down"
                           onmouseover="Tip('Transfer selected files to OMERO')"
                           onmouseout="UnTip()"/>
                    <?php
                }
                ?>

                <input type="button" class="icon clearlist"
                       onclick="UnTip(); cancelOmeroSelection()"
                       onmouseover="Tip('Reset OMERO selection')"
                       onmouseout="UnTip()"/>

                <input name="refreshOmero" type="submit"
                       value="" class="icon update"
                       onclick="UnTip(); setActionToUpdate()"
                       onmouseover="Tip('Reload Omero tree view')"
                       onmouseout="UnTip()"/>

                <input type="submit" class="icon abort" id="disconnectOmero"
                       name="disconnectOmero"
                       onclick="UnTip();"
                       onmouseover="Tip('Disconnect from OMERO')"
                       onmouseout="UnTip()"/>

                <input name="OmeImages" type="hidden">
                <input name="OmeDatasetId" type="hidden">
                <input name="selectedFiles" type="hidden">

            </div> <!-- omeroActions !-->

        </form> <!-- omeroForm !-->

        <fieldset>

            <div id="omeroTree" data-url="omero_treeloader.php">
                <br/> <br/>

                <script>
                    // the global variable _GET is a (JSON) representation of the
                    // browser _GET variable that tells us which folder we are showing
                    // and we use to derive which nodes of the tree will be selectable:
                    var _GET = <?php echo json_encode($_GET); ?>;
                </script>

                <?php
                if ($omeroConnection->loggedIn) {
                    ?>

                    <script>

                        $(function () {
                            var oTree = $('#omeroTree');

                            if (_GET['folder'] == "src") {
                                // for downloading from OMERO we allow multi-selection:
                                oTree.bind('tree.click', handleMultiSelectClick);
                                oTree.tree({
                                    saveState: true,
                                    selectable: true,
                                    onCreateLi: processNodeHTML
                                });

                            } else {
                                // we are in the "dest" folder (upload to OMERO), so we
                                // only allow *single* selection of an image or dataset:
                                oTree.tree({
                                    saveState: true,
                                    selectable: true,
                                    onCreateLi: processNodeHTML,
                                    onCanSelectNode: allowNodeSelect
                                });
                            }
                        });
                    </script>

                    <?php
                } else echo "                        <option>&nbsp;</option>\n";
                ?>

            </div> <!-- omeroTree !-->

        </fieldset>

        <?php
                if ($browse_folder == "src") {
                    ?>
                    <p><img alt="Disclaimer: " src="./images/note.png"/>
                        <b>OME-TIFFs</b> provided by OMERO might be missing the
                        original metadata.</p>
                    <?php
                }
                ?>

    </div> <!-- omeroSelection -->

    </fieldset> <!-- fsOmeroData -->


    <?php
}  // endif (isset($omeroConnection))
?>
