<?php

require_once dirname(__FILE__) . '/../../inc/bootstrap.php';


use hrm\file\FileServer;


//if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
//    return;
//}

session_start();

$fs = new FileServer('felix');
$fs->deleteFiles(["src/test.tif", "src/imgs/series_t1.tif"]);

echo "
<html>
    <head>
        <script type=\"text/javascript\" src=\"../../scripts/jquery-1.8.3.min.js\"></script>
        <script>
        $(function() {
            $.post(\"http://localhost/hrm/ajax/filesystem.php?delete\", 
                    JSON.stringify({\"0\": \"src/test.tif\", \"1\": \"src/imgs/series_t1.tif\", \"2\": \"src/ij-samples/flybrain.tif\"}),
                    function(data) { $(\"#result\").html(JSON.stringify(data, null, 2));});
              });
          </script>
      </head>
      <body>
      'Server JSON response:'
      <div id='result'></div>
    </body>
</html>
";