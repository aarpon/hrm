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
   <?php }



      if (isset($omeroTree)) {?>

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
                  onmouseover="Tip('Transfer selected files')"
                  onmouseout="UnTip()"/>
              <?php
              } else {
                  ?>
                  <input name="exportToOmero" type="submit"
                      value="" class="icon down"
                      onmouseover="Tip('Transfer selected files')"
                      onmouseout="UnTip()" />
              <?php
              }
              ?>

              <input type="button" class="icon abort"
                   onclick="UnTip(); cancelOmeroSelection()"
                   onmouseover="Tip('Cancel data transfer!')"
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
              <input name="OmeImageId" type="hidden">
              <input name="OmeImageName" type="hidden">
              <input name="OmeDatasetId" type="hidden">
              <input name="selectedFiles" type="hidden">

         </div> <!-- omeroActions !-->

     </form> <!-- omeroForm !-->

     <fieldset>
     <legend>Your OMERO data</legend>

     <div id="omeroTree" data-url="/hrm/omero_ondemand_loader.php">
        <br /> <br />

        <?php

          if ($omeroTree != null) {?>

            <script>
            $(function() {
                var data = <?php
                    // data was used with static trees, disabled for now:
                    // echo $omeroTree;
                    echo '""';
                    // FIXME: $omeroTree is still generated before (without the
                    // data being used here), resulting in another (redundant)
                    // call to ome_hrm.py: this should be avoided by lazy
                    // initialization of the tree data in
                    // OmeroConnection.inc.php
                ?>;

                $('#omeroTree').tree({
                    saveState: true,
                    selectable: true,
                    // set which nodes can be selected:
                    onCanSelectNode: function(node) {
                        if ((node.class == "Project") ||
                            (node.class == "Experimenter") ||
                            (node.class == "ExperimenterGroup")) {
                               return false;
                        } else {
                           return true;
                        }
                    }
                });
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
            }
  ?>
