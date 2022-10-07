<?php
// This is the default index.php for the /app folder
// It's only used to redirect the user to the installer
$url = substr($_SERVER['REQUEST_URI'], -4);
$prefix = ($url == ".php" || $url == "app/" || $url == "/app") ? "../" : "";
header("Location: {$prefix}install/index.php");
exit;
