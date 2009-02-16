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

function deleteImages() {

    control = document.getElementById('selection').innerHTML;
    changeDiv('selection', 'Selected files will be deleted, please confirm:' 
       + '<br><input name="delete" type="submit" value="" class="icon delete"/>'
       + ' <img src="images/cancel.png" onClick="cancelSelection()">');

}

function downloadImages() {

    control = document.getElementById('selection').innerHTML;
    changeDiv('selection', 'Selected files will be packed for downloading '
       +  '(that may take a while). Please confirm and wait:' 
       + '<br><input name="download" type="submit" value="" '
       + 'class="icon download"/>'
       + ' <img src="images/cancel.png" onClick="cancelSelection()">');

}

function cancelSelection() {
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
           html = "<img src=\"images/no_preview.jpg\"><br>No preview available";

           } else {

           // Preview doesn't exist, but you can create it now.
           link = "file_management.php?genPreview=" + infile + "&src=" + dir + "&data=" + data + '&index=' + index;

           // html = "<a href=\"" + referer + "\" onClick=\"changeDiv('info','<center><img src=\"images/spin.gif\"><p>Generating preview in another window.</p><p><small>Please wait...</small></p></center>'); openTool('" + link + "');\"><img src=\"images/no_preview.jpg\"><br>Generate preview now</a>";

           onClick =  '<center><img src=\\\'images/spin.gif\\\'><br>'
                + '<small>Generating preview in another window.<br>'
                + 'Please wait...</small></center>';

           html =   '<input type="button" name="genPreview" value="" '
                  +    'class="icon noPreview" '
                  +    'onClick="'
                  +        'changeDiv(\'info\',\'' + onClick + '\'); '
                  +        'openTool(\'' + link + '\'); '
                  +    '"'
                  + '>'
                  + '<br>'
                  + '<small>Click to generate preview</small>';
           }

           break;
        case 2:
           // 2D Preview exists
           html = '<img src="file_management.php?getThumbnail='
                  + infile + '.preview_xy.jpg&dir=' + dir + '">';
           break;
        case 3:
           // 3D Preview exists
           html = '<img src="file_management.php?getThumbnail='
                  + infile + '.preview_xy.jpg&dir=' + dir + '">';
           html = html + '<br><img src="file_management.php?getThumbnail='
                  + infile + '.preview_xz.jpg&dir=' + dir + '">';
           break;

    }

    if ( gen == 1 && mode > 1 && dir == "src" ) {

           // Preview exists, and you can re-create it now.
           link = "file_management.php?genPreview=" + infile + "&src=" + dir + "&data=" + data + '&index=' + index;

           onClick =  '<center><img src=\\\'images/spin.gif\\\'><br>'
                + '<small>Generating preview in another window.<br>'
                + 'Please wait...</small></center>';

           html = html +  '<br><a '
                  +    'onClick="'
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

           html = '<br><a '
                  +    'onClick="'
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
