<?php session_start(); ?>
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>

  <title>Huygens Remote Manager</title>
    <link rel="SHORTCUT ICON" href="images/hrm.ico"/>
    <link rel="stylesheet" href="scripts/jqTree/jqtree.css">
    <link rel="stylesheet" href="scripts/jquery-ui/jquery-ui-1.9.1.custom.css">


    <style type="text/css">

@import url("css/default.css");      
    </style>
    
</head>

<body ng-app="hrmApplication">


<div id="basket">

	  <div id="title">
	  <h1>
          Huygens Remote Manager
            <span id="about">
            v3.1.1</span>
      </h1>
  	  <div id="logo"></div>
	  </div>



<div id="nav">
    <div id="navleft">
        <ul>
            <li><a href="http://www.svi.nl/HuygensRemoteManagerHelpSelectImages" onclick="this.target='_blank';return true;"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>        </ul>
    </div>
    <div id="navright">
        <ul>
            <li>
    <img src="images/user.png" alt="user" />
    &nbsp;oli</li>
<li>
    <a href="file_management.php?folder=src">
        <img src="images/rawdata_small.png" alt="raw images" />
        &nbsp;Raw images
    </a>
</li>
<li>
    <a href="select_images.php?home=home">
        <img src="images/home.png" alt="home" />&nbsp;Home
    </a>
</li>
        </ul>
    </div>
    <div class="clear"></div>
</div>

    <div id="content">
       <h3><img alt="SelectImages" src="./images/select_images.png"
           width="40"/>
           &nbsp;Step
           1/5           - Select images
       </h3>
            <form method="post" action="" id="fileformat" ng-controller="hrmFileManagerController">
                      <fieldset class="setting" >

                <legend>
                    <a href="">
                        <img src="images/help.png" alt="?" />
                    </a>
                    Image file format
                </legend>

                    <select name="ImageFileFormat" 
                            id="ImageFileFormat" 
                            size="1"
                            ng-model="selectedFormat"
                            ng-options="format.name for format in formats"
                            ng-change="updateFileList()"
                      <option value="">Please Select File Format</option>
</select>
                        Selected Files = {{selectedFormat}}
</fieldset>

            <fieldset>
                <legend>Images available on server</legend>
                <div id="userfiles" onmouseover="showPreview()">

                    <select ng-change="javascript:imageAction(this)"
                            id = "filesPerFormat"
                            name="userfiles[]"
                            size="10"
                            ng-model="filesOfSelectedType"
                            multiple="multiple"
                            ng-options="file.filename for file in allFiles">
                    </select>
                  <ul>

                </div>

                <label id="autoseries_label">

                    <input type="checkbox"
                           name="autoseries"
                           class="autoseries"
                           id="autoseries"
                           value="TRUE"
                           onclick="javascript:storeFileFormatSelection(ImageFileFormat,this)"
                           onchange="javascript:storeFileFormatSelection(ImageFileFormat,this)"
    />

                    Automatically load file series if supported

                </label>

            </fieldset>

            <div id="selection">

              <input name="down"
                type="submit"
                value=""
                class="icon down"
                onmouseover="TagToTip('ttSpanDown')"
                onmouseout="UnTip()" />

              <input name="up"
                type="submit"
                value=""
                class="icon remove"
                onmouseover="TagToTip('ttSpanUp')"
                onmouseout="UnTip()" />

            </div>

            <fieldset>
                <legend>Selected images</legend>
                <div id="selectedfiles" onmouseover="showPreview()">
                    <select onclick="javascript:imageAction(this)"
                            onchange="javascript:imageAction(this)"
                            id = "selectedimages"
                            name="selectedfiles[]"
                            size="5"
                            multiple="multiple" disabled="disabled">
                        <option>&nbsp;</option>
                    </select>
                </div>
            </fieldset>

            <div id="actions" class="imageselection"
                 onmouseover="showInstructions()">
                <input name="update"
                       type="submit"
                       value=""
                       class="icon update"
                       onmouseover="TagToTip('ttSpanRefresh')"
                       onmouseout="UnTip()"
                       />
                <input name="OK" type="hidden" />
            </div>

            <div id="controls"
                 onmouseover="showInstructions()">
              <input type="submit"
                     value=""
                     class="icon next"
                     onclick="process()"
                     onmouseover="TagToTip('ttSpanForward')"
                     onmouseout="UnTip()" />
            </div>

        </form>

    </div> <!-- content -->
  


    <div id="rightpanel">

        <div id="info">
        <h3>Quick help</h3><p>Here you can select the files to be restored from the list of available images. The file names are filtered by the selected file format. Use SHIFT- and CTRL-click to select multiple files.</p><p>Where applicable, the files belonging to a series can be condensed into one file name by checking the 'autoseries' option. These files will be loaded and deconvolved as one large dataset. Unchecking 'autoseries' causes each file to be deconvolved independently.</p><p>Click on a file name in any of the fields to get (or to create) a preview.</p>        </div>

        <div id="message">
<p></p>        </div>

    </div> <!-- rightpanel -->
<hrm-footer>

  </hrm-footer>
    <div id="bottom">
    </div>

  <script type="text/javascript" src="angular/js/angular.min.js"></script>
  <script type="text/javascript" src="angular/js/app.js"></script>
  <script type="text/javascript" src="angular/js/controllers.js"></script>
  <script type="text/javascript" src="angular/js/services.js"></script>
  <script type="text/javascript" src="angular/js/directives.js"></script>
  <script type="text/javascript" src="scripts/jquery-1.8.3.min.js"></script>
  

    </div> <!-- basket -->

</body>

</html>


