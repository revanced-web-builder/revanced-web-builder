<?php
/*
ReVanced Web Builder: Installer

In order to let this PHP script auto-update,
this installer needs to create all the files/folders
in the /app folder with proper permissions
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require("functions.php");

$verbose = false; // true = Print out what the installer is doing. This won't automatically redirect you to Admin Panel when finished

// Make sure the /install folder is writable
if (!is_writable(".")) die("/install folder needs to be writable.");

// Make sure the /app folder is writable
if (!is_writable("../app")) die("/app folder needs to be writable.");

// Scan the install folder for folders and directories to copy to the /app folder
$installFolder = scandir(".");
foreach ($installFolder as $f) {
  if ($f == "." || $f == ".." || $f == "index.php") continue; // don't copy ., .., or the installer

  if (is_dir($f)) {
    if ($verbose) echo "Copying $f folder.<br />";
    copy_folder($f, "../app/$f");
  } else {
    if ($verbose) echo "Copying $f.<br />";

    // Copy the file
    if ($f != "home.php") {
      copy($f, "../app/$f");
    } else { // make home.php the app/index.php file instead of it redirecting to the installer
      unlink("../app/index.php");
      copy($f, "../app/index.php");
    }

    // If this is the config.json.dist file, also make it config.json and give it write permissions
    // (if it doesn't already exist)
    if ($f == "config.json.dist" && !file_exists("../app/config.json")) {
      if ($verbose) echo "Creating config.json.<br />";
      copy($f, "../app/config.json");
      chmod("../app/config.json.dist", 0775);
      chmod("../app/config.json", 0775);
    } else {
      if ($verbose) echo "config.json already exists.<br />";
    }

  }

}

// Create the apk and tools folder
$others = array("apk", "tools");
foreach ($others as $o) {
  if ($verbose) echo "Creating $o folder<br />";
  mkdir("../app/$o", 0775);
  chmod("../app/$o", 0775);
}

// Only make /app/builds folder if user didn't make /builds writable
if (!is_writable("../builds")) {
  if ($verbose) echo "Creating builds folder in /app instead of root";
  mkdir("../app/builds", 0775);
  chmod("../app/builds", 0775);
}

if ($verbose) {
  echo "<p>Installation Complete! <a href='../app/admin.php'>Continue to Admin Panel</a></p>";
} else {
  header("Location: ../app/admin.php");
  exit;
}
