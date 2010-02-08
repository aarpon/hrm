// common functions

var popup;
var generated = new Array();
var debug = '';

function clean() {
    if (popup != null) popup.close();
}

function warn(form) {
    if (confirm("Do you really want to delete this user?")) {
        form.elements["annihilate"].value = "yes";
        form.submit();
    }
}

function openWindow(url) {
    var name = "";
    //var features = "directories = no, menubar = no, scrollbars = yes, status = no, outerWidth = 800, outerHeight = 800";
    var features = "";
    var win = window.open(url, name, features);
    win.focus();
}

function openPopup(target) {
    var url = target + "_popup.php";
    var name = "popup";
    var options = "directories = no, menubar = no, status = no, width = 560, height = 280";
    popup = window.open(url, name, options);
    popup.focus();
}

function openTool(url) {
    var name = "popupTool";
    var options = "directories = no, menubar = no, status = no, width = 560, height = 480";
    popup = window.open(url, name, options);
    popup.focus();
}

function changeDiv(div, html) {
    document.getElementById(div).innerHTML= html;
}

function changeOpenerDiv (div, html) {
    window.opener.document.getElementById(div).innerHTML= html;
}


function setPrevGen(index, mode) {
    window.opener.generated[index] = mode;
    window.generated[index] = mode;
}

function updateListing() {

    action = 'update';
    document.file_browser.submit();
}


function deleteImages() {

    if (!checkSelection()) {
        changeDiv('upMsg', 'Select one or more images to delete.');
        return;
    }

    control = document.getElementById('selection').innerHTML;
    action = 'delete';
    changeDiv('selection', 'Selected files will be deleted, please confirm:'
       + '<br />'
       + '<input name="delete" type="submit" value="" class="icon delete" '
       +     'onmouseover="Tip(\'Confirm deletion\')" onmouseout="UnTip()"/>'
       + ' <img src="images/cancel.png" onclick="UnTip(); cancelSelection()" '
       +        'alt="cancel" '
       +        'onmouseover="Tip(\'Cancel\')" onmouseout="UnTip()"/>');

}

function checkSelection() {

    sel = document.getElementsByTagName('select');
    if (sel[0].selectedIndex == -1) {
        // Nothing selected.
        return false;
    }
    return true;

}

function confirmSubmit() {

    if (action != '') {
        changeDiv('upMsg', 'Please wait...<input type="hidden" name="'+action+'" value="1">');
    } else {
        changeDiv('upMsg', '');
    }
    if (action != 'upload') {
        changeDiv('selection', control);
    }
    action = '';
    return true;
}

function confirmUpload() {


    //sel = document.uploadForm.elements;
    form = document.getElementById("uploadForm");

    if ( form.elements[0].value == '' ) {
        alert("Please choose a file to upload, or cancel.")
        return false;
    }

    changeDiv('upMsg', 'Please wait until your browser finishes the file transfer: do not reload or go away from this page.');

    spin =  '<center><img src="images/spin.gif" '
        +     'alt=\\\'busy\\\'><br />Please wait...</center>'
    changeDiv('info', spin);

    return true;
}



function removeFile(file) {

    changeDiv('upfile_'+file, '');
    UnTip();

    sel = document.getElementsByName('inputFile');
    cnt =  sel.length;
    if (cnt == 0) {
        cancelSelection();
    }

}

function addFileEntry() {

    /* flist = document.getElementById('upload_list').innerHTML;
    sel = document.getElementsByName('inputFile');
    newFile =  sel.length;
    */

    c = '<div class="inputFile" name="inputFile"><input type="file" name="upfile[]" size="30" onchange="handleAddMore()">&nbsp;<a onclick="removeFile('+fileInputs+')" class="removeFile" onmouseover="Tip(\'Remove this file\')" onmouseout="UnTip()"><img src="images/cancel_help.png" width="11"></a></div>';

    changeDiv('upfile_'+fileInputs, c);
    fileInputs = fileInputs + 1;
    disableAddMore();
}

function handleAddMore() {
    if (fileInputs > 19) {
        return;
    }

    sel = document.getElementsByTagName('input');
    for (i=0; i<sel.length; i++) {
        if ( sel[i].name != 'upfile\[\]') { continue; }
        if ( sel[i].value == '' ) {
            disableAddMore();
            return;
        }

    }
    enableAddMore();
}


function enableAddMore() {
    changeDiv('addanotherfile', 
              '<a onclick="addFileEntry()">Add another file</a>');
}
function disableAddMore() {
    changeDiv('addanotherfile', '');
}

function uploadImages(maxFile, maxPost, archiveExt) {

    control = document.getElementById('selection').innerHTML;
    action = 'upload';
    changeDiv('selection','');
    changeDiv('message', '');
    changeDiv('upMsg', 'Select a file to upload. Multiple files in a series '
            + 'can also be uploaded in a single archive ('+archiveExt+'). '
            + 'Maximum single file size is ' + maxFile
            +', maximum total transfer size is ' + maxPost + '.');
    changeDiv('up_form', 
        '<form id="uploadForm" enctype="multipart/form-data" action="?folder=src&upload=1" method="POST" onSubmit="return confirmUpload()">'
       + '<div id="upload_list">'
       +      '<div id="upfile_0"></div>'
       +      '<div id="upfile_1"></div>'
       +      '<div id="upfile_2"></div>'
       +      '<div id="upfile_3"></div>'
       +      '<div id="upfile_4"></div>'
       +      '<div id="upfile_5"></div>'
       +      '<div id="upfile_6"></div>'
       +      '<div id="upfile_7"></div>'
       +      '<div id="upfile_8"></div>'
       +      '<div id="upfile_9"></div>'
       +      '<div id="upfile_10"></div>'
       +      '<div id="upfile_11"></div>'
       +      '<div id="upfile_12"></div>'
       +      '<div id="upfile_13"></div>'
       +      '<div id="upfile_14"></div>'
       +      '<div id="upfile_15"></div>'
       +      '<div id="upfile_16"></div>'
       +      '<div id="upfile_17"></div>'
       +      '<div id="upfile_18"></div>'
       +      '<div id="upfile_19"></div>'
       +      '<div id="upfile_20"></div>'
       + '<div id="addanotherfile"></div></div>'
       +  '<input name="upload" type="submit" value="" '
       + 'class="icon upload" '
       +   'onmouseover="Tip(\'Upload selected files\')" onmouseout="UnTip()"/>'
       + ' <img src="images/cancel.png" onclick="cancelSelection()" '
       +           'alt="cancel" '
       +        'onmouseover="Tip(\'Cancel\')" onmouseout="UnTip()"/>'
       + ' </form>' );

    fileInputs = 0;

    addFileEntry();

}



function downloadImages() {

    changeDiv('message', '');
    if (!checkSelection()) {
        changeDiv('upMsg', 'Select one or more images to download.');
        return;
    }

    control = document.getElementById('selection').innerHTML;
    action = 'download';
    changeDiv('selection', 'Selected files will be packed for downloading '
       +  '(that may take a while). Please confirm and wait:' 
       + '<br /><input name="download" type="submit" value="" '
       + 'class="icon download" '
       +     'onmouseover="Tip(\'Confirm download\')" onmouseout="UnTip()"/>'
       + ' <img src="images/cancel.png" onclick="cancelSelection()" '
       +           'alt="cancel" '
       +        'onmouseover="Tip(\'Cancel\')" onmouseout="UnTip()"/>');

}

function cancelSelection() {
    action = '';
    changeDiv('message', '');
    changeDiv('upMsg', '');
    changeDiv('up_form', '');
    changeDiv('selection', control);
}

function imgPrev(infile, mode, gen, compare, index, dir, referer, data) {

    file = unescape(infile);

    if (mode == 0 && gen == 1) {
        try
        {
            if (generated[index] == 2) {
                mode = 2;
            }
            if (generated[index] == 3) {
                mode = 3;
            }
        }
        catch (err) 
        {
            mode = 0;
        }
    }

    switch (mode)
    {
        case 0:
           if ( gen == 0 ) {
           // Preview doesn't exist
           html = "<img src=\"images/no_preview.jpg\" alt=\"No preview\">"
                  + "<br />No preview available";

           } else {

           // Preview doesn't exist, but you can create it now.
           link = "file_management.php?genPreview=" + infile + "&src=" + dir 
                  + "&data=" + data + '&index=' + index;

           // html = "<a href=\"" + referer + "\" onclick=\"changeDiv('info','<center><img src=\"images/spin.gif\" alt=\"busy\"><p>Generating preview in another window.</p><p><small>Please wait...</small></p></center>'); openTool('" + link + "');\"><img src=\"images/no_preview.jpg\" alt=\"No preview\"><br />Generate preview now</a>";

           onClick =  '<center><img src=\\\'images/spin.gif\\\' '
                +                           'alt=\\\'busy\\\'><br />'
                + '<small>Generating preview in another window.<br />'
                + 'Please wait...</small></center>';

           html =   '<input type="button" name="genPreview" value="" '
                  +    'class="icon noPreview" '
                  +    'onclick="'
                  +        'changeDiv(\'info\',\'' + onClick + '\'); '
                  +        'openTool(\'' + link + '\'); '
                  +    '"'
                  + '>'
                  + '<br />'
                  + '<small>Click to generate preview</small>';
           }

           break;
        case 2:
           // 2D Preview exists
           html = '<img src="file_management.php?getThumbnail='
                  + infile + '.preview_xy.jpg&dir=' + dir + '" alt="Preview">';
           break;
        case 3:
           // 3D Preview exists
           html = '<img src="file_management.php?getThumbnail='
                  + infile + '.preview_xy.jpg&dir=' + dir
                  + '" alt="XY preview">';
           html = html + '<br /><img src="file_management.php?getThumbnail='
                  + infile + '.preview_xz.jpg&dir=' + dir
                  + '" alt="XZ preview">';
           break;

    }

    if ( gen == 1 && mode > 1 && dir == "src" ) {

           // Preview exists, and you can re-create it now.
           link = "file_management.php?genPreview=" + infile + "&src=" + dir 
                  + "&data=" + data + '&index=' + index;

           onClick =  '<center><img src=\\\'images/spin.gif\\\' '
                +                     'alt=\\\'busy\\\'><br />'
                + '<small>Generating preview in another window.<br />'
                + 'Please wait...</small></center>';

           html = html +  '<br /><a '
                  +    'onclick="'
                  +        'changeDiv(\'info\',\'' + onClick + '\'); '
                  +        'openTool(\'' + link + '\'); '
                  +    '"'
                  + '>'
                  + '<small>Re-create preview</small>'
                  + '</a>';
    }
    if ( compare > 0 ) {
           link = "file_management.php?compareResult=" + infile
                  + "&size=" + compare + "&op=close";

           html = '<br /><a '
                  +    'onclick="'
                  +        'openWindow(\'' + link + '\'); '
                  +    '"'
                  + '>'
                  + '<small>Expanded view</small>'
                  + '</a>'
                  + html ;
    }


    changeDiv('info', html);
}

function changeVisibility(id) {
    blockElement = document.getElementById(id);
    if (blockElement.style.display == "none")
        blockElement.style.display = "block";
    else if (blockElement.style.display == "block")
        blockElement.style.display = "none";
}

function hide(id) {
    blockElement = document.getElementById(id);
    blockElement.style.display = "none";
}

function show(id) {
    blockElement = document.getElementById(id);
    blockElement.style.display = "block";
}
