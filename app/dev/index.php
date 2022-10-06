<?php
session_start();
$rwbVersion = "0.1.10051";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is using the mod_rewrite page (/admin) or the full path (/app/dev/index.php)
$dirPrefix = (substr(trim($_SERVER['REQUEST_URI'], "/"), -3) == "dev") ? "app/" : "../";
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https://' : 'http://';
$urlPrefix = substr($protocol.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'], 0, -14); // Remove /app/dev/index.php from URL (for now)

$dir = __DIR__; // this may cause errors for PHP FPM. Look into that or allow user to define the root path if it fails to be autodetected?

require_once("../functions.php");

$query = (isset($_GET['q']) && $_GET['q'] != "") ? $_GET['q'] : false;
$debug = new Debug();

// Check if there is a config file and an admin password
if (file_exists("../config.json")) {

  $auth = new Auth();

  // Check if user is trying to login
  $loginPassword = $_POST['adminPass'] ?? null;
  if ($loginPassword != null) {
    $loginAttempt = $auth->login($loginPassword);
  }
  $config = new Config();

  if ($config->admin != "") {
    if (!$auth->valid) {
      echo "<p><form method='post' action='index.php'>Admin Password: <input id='adminPass' name='adminPass' type='password' /> <input type='submit' value='Login' /></form></p>";
      die();
    }
  }

}

if ($query == "configCreate") {

  $version = ($_GET['version'] != "") ? $_GET['version'] : $rwbVersion;

  // We need to create a config file from scratch
  echo "Generating app/config.json.dist<br />";
  echo $debug->configCreate($version); // this will combine all default config files from the dev/ directory into a valid config.json file in /app/

}

if ($query == "prepareRelease") {

  // Check what user wants to keep
  $keepConfig = (isset($_GET['keepConfig'])) ? 1 : 0;
  $keepAPKs = (isset($_GET['keepAPKs'])) ? 1 : 0;
  $keepBuilds = (isset($_GET['keepBuilds'])) ? 1 : 0;
  $keepTools = (isset($_GET['keepTools'])) ? 1 : 0;

  if ($keepConfig !== 1) {
    echo "Deleting app/config.json...<br />";
    unlink("../config.json");
  }

  if ($keepAPKs !== 1) {
    echo "Deleting app/apk/ folder<br />";
    $debug->emptyDir("../apk"); // Delete all APKs
    rmdir("../apk");
  }

  if ($keepBuilds !== 1) {
    echo "Emptying builds/ folder<br />";
    $debug->emptyDir("../../builds"); // Delete everything in /builds folder
  }

  if ($keepTools !== 1) {
    echo "Deleting app/tools/ folder<br />";
    $debug->emptyDir("../tools"); // Delete all ReVanced Tools
    rmdir("../tools");
  }

}

// Empty APKs folder
if ($query == "deleteapks" || $query == "deleteapk") {
  echo "Emptying app/apk/ folder<br />";
  $debug->emptyDir("../apk"); // Delete everything in /apk folder

  // Mark all APKs as enabled = 0
  $config = new Config();
  echo $config->disableAPKs();

}

// Disable all Tools in config.json
if ($query == "disabletools" || $query == "disabletool") {
  echo "Disabling all Tools in config.json<br />";
  $config = new Config();
  echo $config->disableTools();
}

// Empty APKs folder
if ($query == "deletetools" || $query == "deletetool") {
  echo "Emptying app/tools/ folder<br />";
  $debug->emptyDir("../tools"); // Delete everything in /tools folder

  // Mark all Tools as enabled = 0
  $config = new Config();
  echo $config->disableTools();

}

// Disable all APKs in config.json
if ($query == "disableapks" || $query == "disableapk") {
  echo "Disabling all APKs in config.json<br />";
  $config = new Config();
  echo $config->disableAPKs();
}

// Empty Builds folder
if ($query == "deletebuilds" || $query == "deletebuild") {
  echo "Emptying builds/ folder<br />";
  $config = new Config();
  $debug->emptyDir($config->buildDirectory);
}

// Inject revance-patches.json version compatibility into config.json
if ($query == "injectpatches") {
  echo "Injecting patches<br />";
  $config = new Config();
  $config->injectPatches();
}

// Generate a new config.json file from config.json.dist
if ($query == "confignew") {
  // Note: generate new config.json.dist if it doesn't exist (later)
  echo "Generating app/config.json from app/config.json.dist<br />";

  if (!file_exists("../config.json.dist"))
    $debug->configCreate($rwbVersion); // Create new config.json.dist file

  copy("../config.json.dist", "../config.json");
  chmod("../config.json.dist", 0777);

  $config = new Config();
  $config->injectPatches(); // some day this will be built in...
}

// Generate a new config.json file from config.json.dist
if ($query == "deleteconfig") {
  echo "Deleting app/config.json<br />";
  unlink("../config.json");
}

// Delete config.json.dist
if ($query == "deleteconfigdist") {
  echo "Deleting app/config.json.dist<br />";
  unlink("../config.json.dist");
}
?>

<!DOCTYPE html>
<head>

  <meta charset="utf-8">
  <title>ReVanced Web Builder: Dev Tools</title>
  <meta name="description" content="">
  <meta name="author" content="">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="manifest" href="manifest.json">

  <!-- Styles -->
  <link rel="stylesheet" href="<?php echo $urlPrefix; ?>/assets/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo $urlPrefix; ?>/assets/builder.css">

  <!-- icons -->
  <link rel="shortcut icon" href="<?php echo $urlPrefix; ?>/assets/icons/.ico">
	<link rel="icon" sizes="16x16 32x32 64x64" href="<?php echo $urlPrefix; ?>/assets/icons/.ico">
	<link rel="icon" type="image/png" sizes="196x196" href="<?php echo $urlPrefix; ?>/assets/icons/favicon-192.png">
	<link rel="icon" type="image/png" sizes="160x160" href="<?php echo $urlPrefix; ?>/assets/icons/favicon-160.png">
	<link rel="icon" type="image/png" sizes="96x96" href="<?php echo $urlPrefix; ?>/assets/icons/favicon-96.png">
	<link rel="icon" type="image/png" sizes="64x64" href="<?php echo $urlPrefix; ?>/assets/icons/favicon-64.png">
	<link rel="icon" type="image/png" sizes="32x32" href="<?php echo $urlPrefix; ?>/assets/icons/favicon-32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="<?php echo $urlPrefix; ?>/assets/icons/favicon-16.png">

  <style type="text/css">

  body {
    padding-top: 10px;
    padding-left: 25px;
  }

  #adminBack {
    position: absolute;
    top: 2%;
    right: 2%;
    cursor: pointer;
  }


  </style>

</head>

<body>

<!-- Back button -->
<div id="adminBack" title="Back to Admin Panel">
  <a href="../admin.php" title="Back to Admin panel">
    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16">
    <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
  </svg></a>

  <a id="adminLogout" href="../admin.php?logout" title="Logout" class="ms-2">
    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="currentColor" class="bi bi-door-open" viewBox="0 0 16 16">
      <path d="M8.5 10c-.276 0-.5-.448-.5-1s.224-1 .5-1 .5.448.5 1-.224 1-.5 1z"/>
      <path d="M10.828.122A.5.5 0 0 1 11 .5V1h.5A1.5 1.5 0 0 1 13 2.5V15h1.5a.5.5 0 0 1 0 1h-13a.5.5 0 0 1 0-1H3V1.5a.5.5 0 0 1 .43-.495l7-1a.5.5 0 0 1 .398.117zM11.5 2H11v13h1V2.5a.5.5 0 0 0-.5-.5zM4 1.934V15h6V1.077l-6 .857z"/>
    </svg></a>
</div>

<?php
if (isset($auth) && !$auth->valid) {
  echo "<h2>You should set up an <a href='../admin.php'>Admin Password</a> as soon as you have a config.json file!</h2><hr />";
}
?>

<h1>ReVanced Web Builder: Dev Tools</h1>

<h2>Create config.json.dist</h2>
<form method="get" action="index.php">
  <input type="hidden" name="q" value="configCreate" />
  <p>Version: <input name="version" size="8" type="text" value="<?php echo $rwbVersion; ?>" /> <input type="submit" value="Create" /></p>
</form>

<h2>Prepare for Release</h2>
<form method="get" action="index.php">
  <input type="hidden" name="q" value="prepareRelease" />
  <p><label><input type="checkbox" name="keepConfig" value="1" /> Keep config.json</label></p>
  <p><label><input type="checkbox" name="keepAPKs" value="1" /> Keep APKs</label></p>
  <p><label><input type="checkbox" name="keepTools" value="1" /> Keep Tools</label></p>
  <p><label><input type="checkbox" name="keepBuilds" value="1" /> Keep Builds</label></p>
  <p><input type="submit" value="Prepare for Release" /></p>
</form>

<h2>Quick Actions</h2>
<form method="get" action="<?php echo $urlPrefix; ?>/dev/index.php">
  <input type="hidden" name="q" value="quick" />
  <p><a href="<?php echo $urlPrefix; ?>/dev/index.php?q=confignew"><input type="button" value="Generate config.json" /></a> <a href="<?php echo $urlPrefix; ?>/dev/index.php?q=deleteconfig"><input type="button" value="Delete config.json" /></a> <a href="<?php echo $urlPrefix; ?>/dev/index.php?q=deleteconfigdist"><input type="button" value="Delete config.json.dist" /></a></p>

  <p><a href="<?php echo $urlPrefix; ?>/dev/index.php?q=deletebuilds"><input type="button" value="Delete Builds" /></a> <a href="<?php echo $urlPrefix; ?>/dev/index.php?q=disableapks"><input type="button" value="Disable APKs" /></a> <a href="<?php echo $urlPrefix; ?>/dev/index.php?q=deleteapks"><input type="button" value="Delete APKs" /></a></p>

  <p><a href="<?php echo $urlPrefix; ?>/dev/index.php?q=disabletools"><input type="button" value="Disable Tools" /></a> <a href="<?php echo $urlPrefix; ?>/dev/index.php?q=deletetools"><input type="button" value="Delete Tools" /></a></p>

  <p><a href="<?php echo $urlPrefix; ?>/dev/index.php?q=injectpatches"><input type="button" value="Inject Patches" /></a></p>
</form>

<?php
// Debug extends the Config class because it mostly manipulates the config file and needs the read/write functions of it
class Debug {

  // Combine all config files in app/dev/ to create a valid config.json.dist file in /app/
  function configCreate($version) {

    $configSrc = Files::read("dev/config.config.json"); // all paths start at the /app/ folder. so ../ will be the apps folder
    $appSrc = Files::read("dev/config.apps.json");
    $toolSrc = Files::read("dev/config.tools.json");
    $themeSrc = Files::read("dev/config.themes.json");

    $configs = ["config" => $configSrc, "themes" => $themeSrc, "apps" => $appSrc, "tools" => $toolSrc, "version" => $version, "versionLast" => $version];

    Files::write("config.json.dist", json_encode($configs, JSON_PRETTY_PRINT));

  }

  // Read a .txt file and return it as a JSON object
  public function read($file, $plain=0) { // $plain=1 will return plain text instead of JSON

    // Open text file and return data about build
    $dir = __DIR__;
    $fh = fopen($dir."/".$file, 'r');
    $currentTxtContents = fread($fh, filesize($dir."/".$file));
    fclose($fh);
    return ($plain == 0) ? json_decode($currentTxtContents, true) : $currentTxtContents;

  }

  public function write($fileName = null, $fileContents = null) {

    if ($fileName == null) {
      return false;
    }

    $fileName = __DIR__."/".$fileName;

    $myFileLink2 = fopen($fileName, 'w+') or die("Can't open file.");
    fwrite($myFileLink2, $fileContents);
    fclose($myFileLink2);
  }

  // Shortcut for Files::write("config.json", JSON)
  public function save($fileContents=null) {
    Files::write("config.json", $fileContents);
  }

  // Remove all files in a directory
  public function emptyDir($directory) {
    array_map( 'unlink', array_filter((array) glob($directory."/*") ) );
  }


}


?>
