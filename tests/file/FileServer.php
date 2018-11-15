<?php

require_once dirname(__FILE__) . '/../../inc/bootstrap.php';

use hrm\file\FileServer;

/*
// just in case...
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    return;
}*/

session_start();

$dir = "/data/images/user/src";

$_SESSION[FileServer::$SESSION_KEY] = new FileServer($dir);

echo "
<html>
    <head>
        <script>
           function fs_query() {
                var format = document.getElementById(\"format\").value;
                var str  = document.getElementById(\"dir\").value;
                var collapse = document.getElementById(\"collapse\").checked;
                if (str.length === 0) {
                    document.getElementById(\"tree\").innerHTML = \"\";
                } else {
                    var xmlhttp = new XMLHttpRequest();
                    xmlhttp.onreadystatechange = function() {
                        if (this.readyState === 4 && this.status === 200) {
                            var node = document.getElementById(\"tree\");
                            while (node.firstChild) {
                                   node.removeChild(node.firstChild);
                           }
                           var pretty = this.responseText
                                    .replace(/\\\\/g, \"\")
                                    .replace(/{/g, \"{\\n\")
                                    .replace(/}/g, \"\\n}\\n\")
                                    .replace(/,/g, \",\\n\" )
                                    .replace(\"[\", \"\\n\\t[\\n\")
                                    .replace(\"]\", \"\\n\\t]\\n\")
                                    .replace(/(\"[0-9])/g, \"\\t\\t$1\");
                           node.appendChild( document.createTextNode( pretty ));
                        }
                    };               
                    var url = \"../../ajax/FileServer.php?dir=\" + str
                    if (!(format === '')){
                        url += \"&ext=\" + format
                    }
                    url += \"&collapse=\" + collapse; 
                    xmlhttp.open(\"GET\", url, true);
                    xmlhttp.send();
                }
            }
        </script>
    </head>
    <body>
        <form> 
            <label>directory:<input id=\"dir\" onkeyup=\"fs_query()\"></label>
            <label>format:<select id=\"format\" onclick=\"fs_query()\">
                <option></option>
                <option>tif</option>
                <option>czi</option>
                <option>lif</option>   
                <option>stk</option>          
            </select></label>
            <label><input id='collapse' type='checkbox' onchange='fs_query()'>collapse</label>
        </form>
        <p>content: <pre id=\"tree\"></pre></p>
    </body>
</html>";
