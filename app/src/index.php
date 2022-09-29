<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$dir = __DIR__; // this may cause errors for PHP FPM. Look into that or allow user to define the root path if it fails to be autodetected?

$newVersion = "0.1.0929"; // version being packaged (make sure to change this)

require_once("../builder/functions.php");




$query = (isset($_GET['q']) && $_GET['q'] != "") ? $_GET['q'] : false;
$debug = new Debug();


if ($query == "create") {

  // We need to create a config file from scratch
  echo $debug->generate(); // this will combine all default config files from the src/ directory into a valid config.json file in /app/builder/

}




// Debug extends the Config class because it mostly manipulates the config file and needs the read/write functions of it
class Debug {

  // Combine all config files in app/src/ to create a valid config.json.dist file in /app/builder/
  function generate() {

    $configSrc = Files::read("../src/config.config.json"); // all paths start at the /app/builder folder. so ../ will be the apps folder
    $appSrc = Files::read("../src/config.apps.json");
    $toolSrc = Files::read("../src/config.tools.json");
    $themeSrc = Files::read("../src/config.themes.json");

    $configs = ["config" => $configSrc, "themes" => $themeSrc, "apps" => $appSrc, "tools" => $toolSrc, "version" => $GLOBALS['newVersion'], "versionLast" => $GLOBALS['newVersion']];

    Files::write("../builder/config.json.dist", json_encode($configs, JSON_PRETTY_PRINT));

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

}


?>
