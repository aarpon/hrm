<?php

header('Content-Type: text/plain; charset=utf-8');

if (empty($_FILES) || $_FILES['file']['error']) {
    die('{"OK": 0, "info": "Failed to move uploaded file."}');
}

$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;

$fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : $_FILES["file"]["name"];
$filePath = "/tmp/$fileName";


// Open temp file
$out = @fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
if ($out) {
    // Read binary input stream and append it to temp file
    $in = @fopen($_FILES['file']['tmp_name'], "rb");

    if ($in) {
        while ($buff = fread($in, 4096))
            fwrite($out, $buff);
    } else
        die('{"OK": 0, "info": "Failed to open input stream."}');

    @fclose($in);
    @fclose($out);

    @unlink($_FILES['file']['tmp_name']);
} else
    die('{"OK": 0, "info": "Failed to open output stream."}');


// Check if file has been uploaded
if (!$chunks || $chunk == $chunks - 1) {
    // Strip the temp .part suffix off
    rename("{$filePath}.part", $filePath);
}

die('{"OK": 1, "info": "Upload successful."}');
