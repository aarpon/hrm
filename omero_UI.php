<?php

// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Dialog to ask for the OMERO credentials.
if (isset($_POST['getOmeroData']) && !isset($omeroConnection)) {
?>
    <div id="floatingCredentialsDialog" title="OMERO login credentials">

      <p>Your OMERO username and password are needed for
         retrieving data. </p>
      <p>Your login credentials will not be stored.</p>

      <form name="omeroCheckCredentials" method="post">

        <label for="omeroUser">Username:</label>
        <input name="omeroUser" type="text" size="8" value="">
        <br />
        <label for="omeroPass">Password:</label>
        <input name="omeroPass" type="password" size="8" value="">

        <input name="omeroCheckCredentials" type="hidden" value="true">
        <input name="getOmeroData" type="hidden" value="true">

        <br />

    <script>

      $(function() {
            // Workaround to bind the 'Enter' button to the 'Submit' action.
            $( "#floatingCredentialsDialog" ).keypress(function(e){
                if(e.keyCode == $.ui.keyCode.ENTER) {
                   $(':button:contains("Submit")').click();
                }
            });

            $( "#floatingCredentialsDialog" ).dialog({
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
                        "Submit": function() {
                            omeroLogin();
                            $( this ).dialog( "close");
                        }
                    }
                })
      });

    </script>

    </form> <!-- omeroCheckCredentials !-->

    </div> <!-- floatingCredentialsDialog !-->
<?php
}   // endif (isset($_POST['getOmeroData']) && !isset($omeroConnection))


// TODO:
// - block web frontend with an overlay to signalize upload/download


// if we are connected to an OMERO server, always show the tree by default:
if (isset($omeroConnection)) {
?>

    <div id="omeroSelection">

    <form name="omeroForm"
              onsubmit="return omeroTransfer(this, fileSelection,
                           '<?php echo $browse_folder; ?>')"
              action="?folder=<?php echo $browse_folder;?>"
              method="post">

         <div id="omeroActions">

              <?php
              if ($browse_folder == "src") {
              ?>
                  <input name="importFromOmero" type="submit"
                  value="" class="icon remove"
                  onmouseover="Tip('Transfer selected files from OMERO')"
                  onmouseout="UnTip()"/>
              <?php
              } else {
                  ?>
                  <input name="exportToOmero" type="submit"
                      value="" class="icon down"
                      onmouseover="Tip('Transfer selected files to OMERO')"
                      onmouseout="UnTip()" />
              <?php
              }
              ?>

              <input type="button" class="icon abort"
                   onclick="UnTip(); cancelOmeroSelection()"
                   onmouseover="Tip('Reset OMERO selection.')"
                   onmouseout="UnTip()"/>

              <input name="refreshOmero" type="submit"
                   value="" class="icon update"
                   onclick="UnTip(); setActionToUpdate()"
                   onmouseover="Tip('Reload Omero tree view')"
                   onmouseout="UnTip()"/>

              <?php
              if ($browse_folder == "src") {
              ?>
              <p><img alt ="Disclaimer: " src="./images/note.png" />
              HRM cannot guarantee that <b>OME-TIFFs</b>
                 provided by OMERO contain the original metadata.</p>
              <?php
              }
              ?>
              <input name="OmeImages" type="hidden">
              <input name="OmeDatasetId" type="hidden">
              <input name="selectedFiles" type="hidden">

         </div> <!-- omeroActions !-->

    </form> <!-- omeroForm !-->

     <fieldset>
     <legend>Your OMERO data</legend>

     <div id="omeroTree" data-url="omero_treeloader.php">
        <br /> <br />

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
            var processNodeHTML = function(node, li) {
                var context = li.find('.jqtree-element').context;
                var orig = context.innerHTML;
                // console.log(orig);
                // console.log(node.class);
                var icon = '<img src="images/help.png">';
                var pat = 'jqtree-title-folder">';
                var rep = context.innerHTML.replace(pat, pat + icon);
                context.innerHTML = rep;
                // console.log(context.innerHTML);
            }

            $(function() {
                var oTree = $('#omeroTree');

                if (_GET['folder'] == "src") {
                    oTree.tree({
                        saveState: true,
                        selectable: true,
                        onCreateLi: processNodeHTML
                    });
                    // for downloading from OMERO we allow multi-selection:
                    oTree.bind(
                        'tree.click',
                        function(e) {
                            e.preventDefault();  // disable single selection

                            var clicked = e.node;

                            if (oTree.tree('isNodeSelected', clicked)) {
                                oTree.tree('removeFromSelection', clicked);
                            } else {
                                // allow only images to be selected:
                                if (clicked.class == "Image") {
                                    oTree.tree('addToSelection', clicked);
                                }
                            }
                        }
                    );
                } else {  // we are in the "dest" folder (upload to OMERO)
                    oTree.tree({
                        saveState: true,
                        selectable: true,
                        onCreateLi: processNodeHTML,
                        // allow an image or dataset as the target:
                        onCanSelectNode: function(node) {
                            if ((node.class == "Image") ||
                                (node.class == "Dataset")) {
                                   return true;
                            } else {
                               return false;
                            }
                        }
                    });
                }
            });
            </script>

        <?php
          }
          else echo "                        <option>&nbsp;</option>\n";
        ?>

      </div> <!-- omeroTree !-->

     </fieldset>

     </div> <!-- omeroSelection -->


<?php
}  // endif (isset($omeroConnection))
?>
