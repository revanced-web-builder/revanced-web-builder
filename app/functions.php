<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if there is a query
$query = (isset($_GET['q']) && $_GET['q'] != "") ? $_GET['q'] : false;

// Query: Checkin - Users will be checking in while they're not building to see if the builder is busy
if ($query == "checkin") {
  echo isBuilding(); // Show 1 if builder is busy, 0 if not
  die();
}

// Query: Stats - Update config.json with current statistics for all apps
if ($query == "stats") {
    die(statsUpdate());
}


// If no queries, start the classes and functions

// Read config.json file and create variables


/* Config Class
Loads and manages config.json. Can also read/write other files.

When loaded, all [config] variables in config.json will be set to variables.

They can be accessed like
$config = new Config()
echo $config->buildDirectory;

The Apps and Tools arrays can be accessed with
$appData = $config->apps;
$toolData = $config->tools;

App Info can be accessed with
$config->app(appName, version, key, val)
version and key are optional. If not included
If only appName is included, app() will return an array of the entire app's info
If only appName and version are included, app() will return an array of that app and version's info
If all are three are, app() will return a string of the key requested
IF VAL IS INCLUDED this will overwrite the current requested key. Be careful!

$config->tool() works the same way except without $version
*/
class Config {

  //public $test = "test";
  public $defaults = array(
    "buildEnabled" => 1,
    "buildDirectory" => "builds",
    "buildDirectoryPublic" => 0,
    "buildIDLength" => 8,
    "buildSuffix" => "ReVanced",
    "buildBeta" => 0,
    "buildUnsupported" => 0,
    "myBuildsHiddenToggle" => 1,
    "checkinInterval" => 5,
    "downloads" => 1,
    "downloadMethod" => "auto",
    "footer" => 1,
    "pageTitle" => "ReVanced Web Builder",
    "themeDefault" => "dark",
    "themeSwitcher" => 1,
    "timezone" => "Etc/Greenwich",
    "debugMenu" => 0,
    "admin" => 0
  );

  // Set variables from config.json's [config] section
  public function __construct() {
    $this->getVariables();
  }

  // [config] variables will be root so $this->buildDirectory, etc
  // [apps] and [tools] will be arrays at $this->apps and $this->tools
  public function getVariables() {
    // Open text file and return data about build
    $json = Files::read("config.json"); // Load Config
    $this->json = $json;

    // Loop through defaults and use the config.json value if it exists
    $updateConfig = false;
    foreach ($this->defaults as $key => $val) {
      if (!isset($json['config'][$key])) {
        $json['config'][$key] = $val; // add this to the config file since it's missing
        $updateConfig = true; // let script know config needs updated
      }
      $this->$key = $json['config'][$key];

    }

    // Update config.json with missing config variables
    //if ($updateConfig == true) $this->save(json_encode($json, JSON_PRETTY_PRINT));

    $this->apps = $json['apps'];
    $this->tools = $json['tools'];
    $this->themes = $json['themes'];
    $this->version = $json['version'];
    $this->versionLast = $json['versionLast'];
  }

  // Get information about an app, version, or key from app+version. $val will overwrite current $key!
  public function app($app=null, $version=null, $key=null, $val=null) {

    if ($app == null) return false; // An app has to be declared

    $json = $this->json;
    $appInfo = $this->apps;

    if ($key != null) {

      if ($val === null) {
        return $appInfo[$app]['versions'][$version][$key];
      } else {
        $json['apps'][$app]['versions'][$version][$key] = $val;
        $this->save(json_encode($json, JSON_PRETTY_PRINT));
        $this->json = $json;
        return true;
      }

    } else {

      if ($version === null) {
        return $appInfo[$app];
      } else {
        return $appInfo[$app]['versions'][$version];
      }
    }

  }

  public function tool($tool=null, $key=null, $val=null) {

    if ($tool == null) return false;

    $json = $this->json;
    $toolInfo = $this->tools;

    if ($key != null) {

      if ($val === null) {
        return $toolInfo[$tool][$key];
      } else {
        $json['tools'][$tool][$key] = $val;
        $this->save(json_encode($json, JSON_PRETTY_PRINT));
        $this->json = $json;
        return true;
      }

    } else {
      return $toolInfo[$tool];
    }

  }

  public function toolArray() {

    // Information needed for downloading ReVanced tools
    $json = $this->json;
    $toolData = $json['tools'];
    $verCLI = $toolData['CLI']['latest'];
    $verPat = $toolData['Patches']['latest'];
    $verInt = $toolData['Integrations']['latest'];
    //$verMic = $toolData['MicroG']['latest'];
    $toolsArray = array(
      "CLI" => ["version" => $verCLI, "output" => "tools/revanced-cli.jar", "download" => "https://github.com/revanced/revanced-cli/releases/download/v{$verCLI}/revanced-cli-{$verCLI}-all.jar"],
      "Patches" => ["version" => $verPat, "output" => "tools/revanced-patches.jar", "download" => "https://github.com/revanced/revanced-patches/releases/download/v{$verPat}/revanced-patches-{$verPat}.jar"],
      "Integrations" => ["version" => $verInt, "output" => "tools/revanced-integrations.apk", "download" => "https://github.com/revanced/revanced-integrations/releases/download/v{$verInt}/app-release-unsigned.apk"],
      "MicroG" => ["version" => $toolData['MicroG']['latest'], "output" => "../{$this->buildDirectory}/vanced-microg.apk", "download" => "https://v.frwd.app/revanced/vanced-microg.apk"]
    );

    return $toolsArray;

  }

  public function setStats($statsArray) {

    $json = $this->json;
    foreach($statsArray as $key => $val) {

      foreach ($val as $key2 => $val2) {
        $json['apps'][$key]['stats'][$key2] = $val2;
      }

    }

    $this->save(json_encode($json, JSON_PRETTY_PRINT));
    $this->json = $json;
    return $json;

  }

  public function update($configArray, $themeArray=null) { // Currently only supports arrays

    $json = $this->json; // get all data

    // Loop through provided config keys and add then to the config file. If they don't already exist they should be created
    foreach ($configArray as $key => $val) {
      $json['config'][$key] = $val;
    }

    // Also update theme if requested
    if ($themeArray != null) {
      foreach ($themeArray as $key => $val) {
        $json['themes'][$key] = $val;
      }
    }

    $this->json = $json;
  //  $this->config = $json['config'];
    $this->save(json_encode($json, JSON_PRETTY_PRINT));
    $this->__construct(); // update the live config vars
  }

  // Replace everything in the [apps] section and latest [tools] with the new config.json.dist
  public function replaceApps() {

    $json = $this->json; // Current config
    $new = Files::read("config.json.dist");

    // Completely replace the entire [apps] section
    $json['apps'] = $new['apps'];

    // Update the latest version of each Tool
    $json['tools']['CLI']['latest'] = $new['tools']['CLI']['latest'];
    $json['tools']['Patches']['latest'] = $new['tools']['Patches']['latest'];
    $json['tools']['Integrations']['latest'] = $new['tools']['Integrations']['latest'];
    $json['tools']['MicroG']['latest'] = $new['tools']['MicroG']['latest'];

    // Update the version and versionLast to let it know it's been updated
    $json['version'] = $new['version'];
    $json['versionLast'] = $new['version'];

    $this->json = $json;
    $this->save(json_encode($json, JSON_PRETTY_PRINT));


  }

  public function appSupported($app, $version) {
    // Check if config file has support for an app+version
    $json = $this->json;

    // Remove space from version in case it's a beta
    $version = explode(" ", $version)[0];

    // First check if this app is even in the config
    if (isset($json['apps'][$app]['versions'][$version])) {
      // Make sure it's a supported version
      $support = $json['apps'][$app]['versions'][$version]['support'] ?? 1; // apps are supported by default
      if ($support === 1) {
        return true;
      } else {
        return "unsupported";
      }
    } else {
      return "404";
    }

  }

  public function injectPatches() {

    $json = $this->json;

    $patches = Files::read("tools/revanced-patches.json");

    // Loop through each of the official patches and add the patch version, supported apk versions, etc
    $names = array(
      "com.google.android.youtube" => ["YouTube", "17.26.35"], // last in array is lowest/minimum supported version
      "com.google.android.apps.youtube.music" => ["YouTubeMusic", "5.21.52"],
      "com.reddit.frontpage" => ["Reddit", "2022.28.0"],
      "com.spotify.music" => ["Spotify", "8.7.68.568"],
      "com.ss.android.ugc.trill" => ["TikTok", "25.8.2"],
      "com.twitter.android" => ["Twitter", "9.52.0"],
      "com.garzotto.pflotsh.ecmwf_a" => ["Pflotsh", "3.5.4"],
      "de.dwd.warnapp" => ["WarnWetter", "3.7.2"]
    );

    foreach ($patches as $p => $v) {

      $appPkg = $v['compatiblePackages'][0]['name'];
      $appName = $names[$appPkg][0];
      $versions = $v['compatiblePackages'][0]['versions'];
      $filteredVersions = [];
      $ignore = array("settings", "client-spoof", "microg-support", "music-microg-support");
      $thisPatch = $patches[$p]['name'];

      // Don't attach any supported app versions that existed before RWB
      foreach ($versions as $key => $ver) {
        if (version_compare($ver, $names[$appPkg][1]) >= 0) $filteredVersions[] = $ver;
      }

      if (!in_array($thisPatch, $ignore)) {
        $json['apps'][$appName]['patches'][$thisPatch]['versions'] = $filteredVersions;
        $json['apps'][$appName]['patches'][$thisPatch]['version'] = $v['version'];
      }

    }

    $this->json = $json;
    $this->save(json_encode($json, JSON_PRETTY_PRINT));

  }

  // Update RWB and output the process.
  public function updateVersion() {
    // Compare the current and new configs
    $cur = $this->json;
    $new = Files::read("config.json.dist");
    $return = "";

    if ($cur['version'] >= $new['version']) {
      echo "<p>You have already updated to version {$new['version']}... <a href='{$_SERVER['PHP_SELF']}'>Back to Admin Panel.</a></p>";
      die();
    }

    $return .= "<h3 class='mb-3'>Updating from version {$cur['versionLast']} to {$new['version']}</h3>";

    $return .= "<p>Making backup of apps/config.json to apps/config-{$cur['versionLast']}.json</p>";
    $backupConfig = copy("config.json", "config-{$cur['versionLast']}.json");

    $return .= "<p>Updating apps and tools section of config.json</p>";
    $this->replaceApps();

    $return .= "<p>Recalculating app stats...</p>";
    statsUpdate();

    $return .= "<p>Checking for existing Tools and APKs...</p>";

    $files = scandir("apk/");
    $filesizes = [];

    // Get every File that ends with .apk
    foreach ($files as $key => $filefull) {

      if (substr($filefull, -4) != ".apk") continue; // only look at .apk files

      $file = substr($filefull, 0, -4); // remove .apk from file name

      // Split the app name and version
      $split = explode("-", $file);
      $appName = $split[0];
      $appVersion = $split[1];

      $isSupported = $this->appSupported($appName, $appVersion);

      if ($isSupported === true) {
        $isSupported = "";
        $filesizes[] = filesize("apk/".$filefull);
      } else if ($isSupported == "unsupported") {
        $isSupported = "<span class='badge bg-warning'>No Longer Supported</span>";
      } else if ($isSupported == "404") {
        $isSupported = "<span class='badge bg-danger'>Invalid</span>";
      }

      // Only show Unsupported and Invalid
      if ($isSupported != "") {
        $return .= "<p>{$appName} {$appVersion}{$isSupported}</p>";
      }

      if ($isSupported == "") {
        $this->app($appName, $appVersion, "enabled", 1); // Mark this app as enabled in config
      }

    }

    $totalsize = (array_sum($filesizes) / 1024 / 1024);
    $returnsize = ($totalsize >= 1024) ? round($totalsize / 1024, 2)." GB" : round($totalsize, 2)." MB";
    $return .= "<p>Total size of supported APKs: {$returnsize}</p>";

    $return .= "<button class='btn btn-lg btn-secondary'>Update Successful</button>";
    return "<div id='updateContainer' class='p-2 p-lg-3 mb-4 main-accent'>{$return}</div>";

  }

  // Mark all APKs as disabled (enabled = 0)
  public function disableAPKs() {

    $json = $this->json;

    // Loop through all the apps
    foreach ($json['apps'] as $appName => $vals) {
      // Loop through all versions in this app
      foreach ($vals['versions'] as $version => $versionVals) {
        $json['apps'][$appName]['versions'][$version]['enabled'] = 0;
      }
    }

    $this->json = $json;
    $this->save(json_encode($json, JSON_PRETTY_PRINT));

  }

  // Shortcut for Files::write("config.json", JSON)
  public function save($fileContents=null) {
    Files::write("config.json", $fileContents);
  }



}

class Files {

    // Read a .txt file and return it as a JSON object
    public static function read($file, $plain=0) { // $plain=1 will return plain text instead of JSON

      // Open text file and return data about build
      $dir = __DIR__;
      $fh = fopen($dir."/".$file, 'r');
      $currentTxtContents = fread($fh, filesize($dir."/".$file));
      fclose($fh);
      return ($plain == 0) ? json_decode($currentTxtContents, true) : $currentTxtContents;

    }

    public static function write($fileName = null, $fileContents = null) {

      if ($fileName == null) {
        return false;
      }

      $fileName = __DIR__."/".$fileName;

      $myFileLink2 = fopen($fileName, 'w+') or die("Can't open file.");
      fwrite($myFileLink2, $fileContents);
      fclose($myFileLink2);
    }

}



// Admin authentication
// This is going to be a very rudimentary system to begin with while I keep things basic and get things working
// Any security suggestions are heavily welcome.
class Auth {

  public $config;
  public $valid;

  function __construct() {

    $this->config = new Config(); // load config to get admin password hash (if exists)
    $this->valid = false;

    // If a session token is found, try to login right now
    if (isset($_SESSION['token'])) {
      $this->valid = $this->tokenCheck($_SESSION['token']);
    }

  }

  public function login($pass) {

    if ($pass == "") return false;

    // Check if there is currently an admin password
    if ($this->config->admin == "") {

      // Set this as the admin password
      $newPass = md5($pass); // md5 the newpass first which will be our session token and what the password is encrypted with.
      $_SESSION['token'] = $newPass;
      $newPass = password_hash($newPass, PASSWORD_DEFAULT);
      $this->config->update(["admin" => $newPass]);

      header("Location: ".$_SERVER['PHP_SELF']);
      exit();

      $this->valid = true;

    } else {
      // Check if password is valid
      $passHash = md5($pass);
      if (password_verify($passHash, $this->config->admin)) {
        $_SESSION['token'] = $passHash;
        $this->valid = true;
      } else {
        $this->valid = false;
      }
    }

    return $this->valid;

  }

  // Check if session token is valid
  public function tokenCheck($token) {
    return (password_verify($token, $this->config->admin)) ? true : false;
  }

  // Get admin password hash from config file (if exists)
  public function configHash() {
    return $this->config->admin ?? false;
  }

}







// Update build duration stats for all apps
function statsUpdate() {

  $getConfig = new Config();
  $appData = $getConfig->apps;
  $dir = "../{$getConfig->buildDirectory}/";
  $files = scandir($dir);
  $stats = [];

  // Get every File that starts with appName and ends with .txt, open it, and add its duration to the average
  foreach ($files as $key => $file) {

    if (substr($file, -9) != ".info.txt") continue; // only look at .txt files

    $json = Files::read($dir.$file);
    $currentApp = explode("-", $file)[0]; // Split filename by first hyphen
    $currentApp = explode(" ", $currentApp)[0]; // Split filename by first space in case there's a build suffix

    if (!isset($stats[$currentApp])) $stats[$currentApp] = [];

    array_push($stats[$currentApp], $json['buildDuration']);

  }

  $results = [];
  foreach ($stats as $app => $val) {
    if ($stats[$app])
    $averages = $stats[$app];
    $isArray = is_array($averages);
    $results[$app]['builds'] = ($isArray) ? count($averages) : 0;
    $results[$app]['min'] = ($isArray) ? min($averages) : 0;
    $results[$app]['max'] = ($isArray) ? max($averages) : 0;
    $results[$app]['avg'] = ($isArray) ? round(array_sum($averages)/count($averages)) : 0;
  }

  $getConfig->setStats($results);

  //$getConfig->write("appdata.json", json_encode($appData, JSON_PRETTY_PRINT));
  //return json_encode($appData);
  return json_encode($results);
}

function isBuilding() {

  // Run the "jps" command and search to see if any processes match "revanced-cli.jar"
  $checkJava = exec("jps", $output);
  $search = array_search_fuzzy($output, "revanced");
  return (is_numeric($search)) ? 1 : 0;

}

// Fuzzy search an array for a keyword
function array_search_fuzzy($arr, $keyword) {
  foreach($arr as $index => $string) {
    if (strpos($string, $keyword) !== FALSE)
    return $index;
  }
}

// Download Tool or APK (Admin panel)
function fileDownload($url, $filepath){

  $filepath = __DIR__."/".$filepath;

  // Check to see which download methods this system supports
  $config = new Config();
  $checkSysCurl = exec("curl --version", $outputSys);
  $checkPHPCurl = extension_loaded("curl");
  $checkWget = exec("wget --version", $outputWget);

  // If set to Auto, prioritze System cURL, then WGET, then PHP
  if ($config->downloadMethod == "auto") {
    if ($checkSysCurl != "") {
      $use = "curl";
    } else if ($checkWget != "") {
      $use = "wget";
    } else if ($checkPHPCurl) {
      $use = "php";
    } else {
      die("NO DL METHODS FOUND");
    }
  } else {
    $use = $config->downloadMethod;
  }

  // Check which download method the user prefers
  if ($use == "curl") { // Use system cURL

    // Split the directory and file
    $split = explode("/", $filepath);
    $filename = end($split);
    array_pop($split); // remove filename from path
    $split = implode("/", $split);
    $curl = exec("curl -sL --output-dir \"{$split}\" -o \"{$filename}\" {$url}", $output);
    return (filesize($filepath) > 0)? true : false;

  } else if ($use == "wget") {

    $wget = exec("wget --no-check-certificate --content-disposition -O \"{$filepath}\" {$url}", $output);
    return (filesize($filepath) > 0)? true : false;

  } else if ($use == "php") { // Use PHP cURL
    $ch = curl_init($url);
    // Most of these are probably unnecessary, but we need to make sure it can download from GitHub (or anywhere, really)
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 200);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    //$zipResource = fopen($filepath.".apk", "w");
    //curl_setopt($ch, CURLOPT_FILE, $zipResource);
    $raw_file_data = curl_exec($ch);

    if(curl_errno($ch)){
      echo 'generated error:' . curl_error($ch);
    }
    curl_close($ch);

    file_put_contents($filepath, $raw_file_data);

    // Delete the file that was attempted to be made if it failed
    if (curl_errno($ch)) {
      unlink($filepath);
    }

    return (filesize($filepath) > 0)? true : false;

  } else {
    die("ERROR: NO DOWNLOAD METHOD FOUND");
  }
}


// Check if something is numeric (don't allow scientific notation like PHP does)
function isNumeric($num) {
  if (preg_match("/^\-?[0-9]*\.?[0-9]+\z/", $num)) {
    return true;
  } else {
    return false;
  }
}
