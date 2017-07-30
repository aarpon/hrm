<?php

require_once dirname(__FILE__) . '/../../inc/bootstrap.php';

use hrm\file\FileServer;

session_start();

$dir = "/data/images/felix/src";

$server = new FileServer($dir);
$file = $server->getDirectories();
$tree = $server->getFileTree($dir);

$_SESSION['fileserver'] = $server;

echo "
<html>
    <head>
        <script>
           function fs_query() {
                var format = document.getElementById(\"format\").value;
                var str  = document.getElementById(\"dir\").value;
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
                    xmlhttp.open(\"GET\", \"../../ajax/FileServer.php?dir=\" + str + \"&ext=\" + format, true);
                    xmlhttp.send();
                }
            }
        </script>
    </head>
    <body>
        <form>directory: 
            <input id=\"dir\" onkeyup=\"fs_query()\">
            <select id=\"format\" onclick=\"fs_query()\">
                <option></option>
                <option>tif</option>
                <option>czi</option>
                <option>lif</option>          
            </select>
        </form>
        <p>content: <pre id=\"tree\"></pre></p>
    </body>
</html>";