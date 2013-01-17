<?php

  // This file is part of the Huygens Remote Manager
  // Copyright and license notice: see license.txt
      
        // Dialog to ask for the Omero credentials.
      if (isset($_POST['getOmeroData']) && !isset($omeroConnection)) {
          ?>
    <div id="floatingCredentialsDialog" title="Omero login credentials">
              
      <p>Your Omero username and password are needed for
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
                                                       
            $( "#floatingCredentialsDialog" ).dialog();
            
            $( "#floatingCredentialsDialog" ).dialog({
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

              <input name="OmeImageId" type="hidden">
              <input name="OmeImageName" type="hidden">
              <input name="OmeDatasetId" type="hidden">
              <input name="selectedFiles" type="hidden">
                   
         </div> <!-- omeroActions !-->
                   
     </form> <!-- omeroForm !-->
             
     <fieldset>
     <legend>Your Omero data</legend>
                   
     <div id="omeroTree">
        <br /> <br />

        <?php

          if ($omeroTree != null) {?>

            <script>              
            $(function() {
                  var data = <?php echo $omeroTree; ?>;
                            
                  $('#omeroTree').tree({
                        data: data,
                        selectable: true,
                        onCanSelectNode: function(node) {
                              
                            if (node.id == "-1") {

                               // Not selectable.  
                               return false;
                            } else {

                               // Selectable 
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