<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$dir = __DIR__; // this may cause errors for PHP FPM. Look into that or allow user to define the root path if it fails to be autodetected?

$newVersion = "0.1.0929"; // version being packaged (make sure to change this)

require_once("../functions.php");

$query = (isset($_GET['q']) && $_GET['q'] != "") ? $_GET['q'] : false;
$debug = new Debug();

if ($query == "configCreate") {

  $version = $_GET['version'];

  // We need to create a config file from scratch
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
    echo "Emptying app/apk/ folder<br />";
    $debug->emptyDir("../apk"); // Delete all APKs
  }

  if ($keepBuilds !== 1) {
    echo "Emptying builds/ folder<br />";
    $debug->emptyDir("../../builds"); // Delete everything in /builds folder
  }

  if ($keepTools !== 1) {
    echo "Emptying app/tools/ folder<br />";
    $debug->emptyDir("../tools"); // Delete all ReVanced Tools
  }

}

if ($query == "emptyBuilds") {
  echo "Emptying builds/ folder<br />";
  $debug->emptyDir("../../builds"); // Delete everything in /builds folder
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
  <link rel="stylesheet" href="../css/bootstrap.min.css">
  <link rel="stylesheet" href="../css/builder.css">

  <!-- Favicons -->
  <link rel="shortcut icon" href="../img/favicons/.ico">
	<link rel="icon" sizes="16x16 32x32 64x64" href="../img/favicons/.ico">
	<link rel="icon" type="image/png" sizes="196x196" href="../img/favicons/favicon-192.png">
	<link rel="icon" type="image/png" sizes="160x160" href="../img/favicons/favicon-160.png">
	<link rel="icon" type="image/png" sizes="96x96" href="../img/favicons/favicon-96.png">
	<link rel="icon" type="image/png" sizes="64x64" href="../img/favicons/favicon-64.png">
	<link rel="icon" type="image/png" sizes="32x32" href="../img/favicons/favicon-32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="../img/favicons/favicon-16.png">
	<link rel="apple-touch-icon" href="../img/favicons/favicon-57.png">
	<link rel="apple-touch-icon" sizes="114x114" href="../img/favicons/favicon-114.png">
	<link rel="apple-touch-icon" sizes="72x72" href="../img/favicons/favicon-72.png">
	<link rel="apple-touch-icon" sizes="144x144" href="../img/favicons/favicon-144.png">
	<link rel="apple-touch-icon" sizes="60x60" href="../img/favicons/favicon-60.png">
	<link rel="apple-touch-icon" sizes="120x120" href="../img/favicons/favicon-120.png">
	<link rel="apple-touch-icon" sizes="76x76" href="../img/favicons/favicon-76.png">
	<link rel="apple-touch-icon" sizes="152x152" href="../img/favicons/favicon-152.png">
	<link rel="apple-touch-icon" sizes="180x180" href="../img/favicons/favicon-180.png">
	<meta name="msapplication-TileColor" content="#FFFFFF">
	<meta name="msapplication-TileImage" content="../img/favicons/favicon-144.png">
	<meta name="msapplication-config" content="../img/favicons/browserconfig.xml">

  <style type="text/css">

  body {
    padding-top: 10px;
    padding-left: 25px;
  }

  </style>

</head>

<body>

<h1>ReVanced Web Builder: Dev Tools</h1>

<h2>Create config.json.dist</h2>
<form method="get" action="index.php">
  <input type="hidden" name="q" value="configCreate" />
  <p>Version: <input name="version" size="8" type="text" /> <input type="submit" value="Create" /></p>
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

<h2>Empty Builds Folder</h2>
<form method="get" action="index.php">
  <input type="hidden" name="q" value="emptyBuilds" />
  <p><input type="submit" value="Empty Builds" /></p>
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
