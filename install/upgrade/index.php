<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("../functions.php");
$file = new Files();

// Download information about the latest RWB release
$url = "https://api.github.com/repos/revanced-web-builder/revanced-web-builder/releases/latest";
$dl = fileDownload($url, "upgrade/latestRelease.json");
$data = $file->read("upgrade/latestRelease.json");

// Check current version
$config = new Config();
$versionInstalled = $config->versionLast;

echo "Version Installed: $versionInstalled<br />";

$branch = substr($data['tag_name'], 1);

echo "Branch: $branch<br />";

$version = substr($data['name'], 1);

echo "Version: $version<br />";


if (!isset($_GET['upgrade'])) {
  if (version_compare($versionInstalled, $version) == -1) {
    echo "<a href='?upgrade=true'>UPGRADE!</a>";
  } else {
    echo "NO NEED TO UPGRADE!";
  }
  die();
}

$url2 = $data['assets'][1]['browser_download_url'];
$dl2 = fileDownload($url2, "upgrade/release.zip");

if (class_exists("ZipArchive")) {
  $zip = new ZipArchive;
  $res = $zip->open('release.zip');
  if ($res === TRUE) {
    $zip->extractTo('release/');
    $zip->close();
    chmod("release/rwb", 0777);
  } else {
    echo "Upgrade failed to unzip...";
  }
} else {
  echo "You must install the php-zip module.";
}


// Make sure the /app folder is writable
if (!is_writable("../")) die("/app folder needs to be writable.");
$verbose = true;
// Scan the install folder for folders and directories to copy to the /app folder
$installFolder = scandir("release/rwb/install");
//unlink("../index.php");
foreach ($installFolder as $f) {
  if ($f == "." || $f == ".." || $f == "index.php" || $f == "upgrade") continue; // don't copy ., .., or the installer

  if (is_dir("release/rwb/install/".$f)) {
    if ($verbose) echo "Copying $f folder.<br />";
    $file->copydir("release/rwb/install/".$f, "../$f");
  } else {
    if ($verbose) echo "Copying $f.<br />";

    // Copy the file
    if ($f != "home.php") {
      copy("release/rwb/install/".$f, "../$f");
    } else { // make home.php the app/index.php file instead of it redirecting to the installer
      //unlink("../index.php");
      copy("release/rwb/install/".$f, "../index.php");
    }

  }

}

// Empty the upgrade/release folder
echo "Emptying upgrade/release folder<br />";
$file->rmdir("release");
echo "Deleting release.zip<br />";
unlink("release.zip");
?>
