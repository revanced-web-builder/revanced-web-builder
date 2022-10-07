<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



 echo "Placeholder";


require_once("../functions.php");
$file = new Files();

// Download information about the latest RWB release
$url = "https://api.github.com/repos/revanced-web-builder/revanced-web-builder/releases/latest";
$dl = fileDownload($url, "upgrade/latestRelease.json");
$data = $file->read("upgrade/latestRelease.json");

$branch = $data['tag_name'];

echo "Branch: $branch<br />";

$version = $data['name'];

echo "Version: $version<br />";

$url2 = $data['assets'][1]['browser_download_url'];
$dl2 = fileDownload($url2, "upgrade/release.zip");

if (class_exists("ZipArchive")) {
  $zip = new ZipArchive;
  $res = $zip->open('release.zip');
  if ($res === TRUE) {
    $zip->extractTo('release/');
    $zip->close();
    echo 'woot!';
    chmod("release/rwb", 0777);
  } else {
    echo 'doh!';
  }
} else {
  echo "NO ZIP!";
}




// Make sure the /app folder is writable
if (!is_writable("../")) die("/app folder needs to be writable.");
$verbose = true;
// Scan the install folder for folders and directories to copy to the /app folder
$installFolder = scandir("release/rwb/app");
//unlink("../index.php");
foreach ($installFolder as $f) {
  if ($f == "." || $f == ".." || $f == "upgrade") continue; // don't copy ., .., or the installer

  if (is_dir("release/rwb/app/".$f)) {
    if ($verbose) echo "Copying $f folder.<br />";
    upgrade_copy_folder("release/rwb/app/".$f, "../$f");
  } else {
    if ($verbose) echo "Copying $f.<br />";

    // Copy the file
    if ($f != "home.php") {
      copy("release/rwb/app/".$f, "../$f");
    } else { // make home.php the app/index.php file instead of it redirecting to the installer
      //unlink("../index.php");
      copy("release/rwb/app/".$f, "../index.php");
    }

  }

}



function upgrade_copy_folder($src, $dst) {

    // open the source directory
    $dir = opendir($src);

    // Make the destination directory if not exist
    @mkdir($dst, 0777);

    // Loop through the files in source directory
    while( $file = readdir($dir) ) {

        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) )
            {

                // Recursively calling custom copy function
                // for sub directory
                upgrade_copy_folder($src . '/' . $file, $dst . '/' . $file);

            }
            else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }

    closedir($dir);
}


?>
