<?php
// ReVanced Web Builder is not installed!
$url = substr($_SERVER['REQUEST_URI'], -4);
$prefix = ($url == ".php" || $url == "app/" || $url == "/app") ? "../" : "";
header("Location: {$prefix}install/index.php");
exit;
