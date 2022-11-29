<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0); // Set unlimited time limit for PHP because the build can take a while

require_once("functions.php"); // import classes/functions
$config = new Config();
$appData = $config->apps;
date_default_timezone_set($config->timezone); // Default Timezone

// Get the full URL of the currently running script and remove "/app/build.php" from it
// This guarantees the script will direct the user to the correct finished build location
$protocol = stripos($_SERVER['SERVER_PROTOCOL'],'https') === 0 ? 'https://' : 'http://';
$urlPrefix = substr($protocol.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'], 0, -22); // Remove "/app/build.php"

// Make sure something was submitted
if (!$_POST) die("NO POST");

// Make sure builder is online
if ($config->buildEnabled != 1) die("ERROR:OFFLINE");

$allowedApps = array('YouTube', 'YouTube Music', 'Crunchyroll', 'Reddit', 'Spotify', 'TikTok', 'Twitch', 'Twitter', 'Pflotsh', 'WarnWetter', 'HexEditor', 'IconPackStudio', 'My Expenses', 'Nyx Music');

// Make sure submitted App is allowed
if (!in_array($_POST['appName'], $allowedApps))
  die("ERROR:INVALIDAPP");

// If someone else is building, kill the process immediately. If not, check in at this time
if (isBuilding() === 1) {
  die("ERROR:FLOOD");
}



// Build Information
$buildDate = time(); // UNIX timestamp of when this build started
$buildDateFull = date("c");
$buildApp = $_POST['appName'];
$buildAppTrim = preg_replace("/\s+/", "", $buildApp);
$buildAppEncoded = preg_replace("/\s+/", ".", $buildApp);
$buildVersion = $_POST['appVersion'];
$buildVersionNoBeta = (substr($buildVersion, -5) == " Beta") ? substr($_POST['appVersion'], 0, -5) : $_POST['appVersion'];
$buildID = $buildApp.$buildVersion; // Start build ID with app info. patches will be added to the build_id later
$buildDirectory = $config->buildDirectory; // this will be $urlPrefix/$buildDirectory (can also be app/builds, etc)
$include = ""; // Leave blank to begin
$options = ""; // this too
$optionsArray = array();

// Make sure this app and version is enabled
if ($appData[$buildApp]['versions'][$buildVersionNoBeta]['enabled'] != 1) {
  die("ERROR:DISABLED");
}

// Check if this build is supported
$buildSupported = $appData[$buildApp]['versions'][$buildVersionNoBeta]['support'] ?? 1; // supported by default
if ($buildSupported !== 1 && $config->buildUnsupported != 1) die("ERROR:UNSUPPORTED");

// Check which patches we'll be using
$patches = $_POST["patches_{$buildAppTrim}"]; // Form names we'll be using for this app's patches

// These names will be appended to the buildID that will be put at the end of the apk name so we can detect if someone has already built this exact APK.
/*
This patchesAllowed system needs to be rewritten to be automated with the patches.json

Until then, it's a bit insecure because anything can be input/included..

$patchesAllowed = array(
  'swipe-controls', 'seekbar-tapping', 'minimized-playback', 'theme', 'hide-create-button', 'hide-cast-button', 'return-youtube-dislike', 'hide-autoplay-button', 'premium-heading', 'custom-branding', 'disable-fullscreen-panels', 'old-quality-layout', 'hide-shorts-button', 'hide-watermark', 'sponsorblock', 'enable-wide-searchbar', 'always-autorepeat', 'microg-support', 'enable-debugging', 'custom-playback-speed', 'hdr-auto-brightness', 'remember-video-quality', 'video-ads', 'general-ads', 'hide-infocard-suggestions', 'settings', 'custom-video-buffer', 'minimized-playback-music', 'tasteBuilder-remover', 'hide-get-premium', 'compact-header', 'upgrade-button-remover', 'background-play', 'music-video-ads', 'codecs-unlock', 'exclusive-audio-playback', 'timeline-ads', 'general-reddit-ads', 'tablet-mini-player', 'tiktok-ads', 'pflotsh-ecmwf-subscription-unlock', 'promo-code-unlock', 'downloads', 'premium-icon-reddit', 'client-spoof', 'tiktok-seekbar', 'tiktok-download', 'hide-premium-navbar', 'spotify-theme', 'hide-time-and-seekbar', 'disable-auto-captions', 'disable-auto-player-popup-panels', 'tiktok-settings', 'tiktok-feed-filter', 'tiktok-force-login', 'upgrade-button-remover', 'hide-email-address', 'monochrome-icon');
  */

// Loop through each selected patch and make sure it's an allowed patch
foreach ($patches as $id => $name) {
  // Kill script if an invalid patch was found (This system needs to be automated/updated)
  //if (!in_array($name, $patchesAllowed)) die("ERROR:NOPATCH");

  $include .= " -i ".$name; // append this patch to the included patches array
  $buildID .= $name; // append this patch's name to the buildID string
}

// Add MicroG and other patches if it's a YouTube app (required for Non-Root APKs) or needs ReVanced Integrations
if ($buildApp == "YouTube") {
  $include .= " -i client-spoof -i microg-support -i settings"; // Also automatically add ReVanced Settings and Spoof Patch (videos are being blocked in some countries using modded apks)
  $exclusive = "--exclusive"; // add the --exclusive tag because we're selecting which patches to include
} else if ($buildApp == "YouTube Music") {
  $include .= " -i music-microg-support";
  $exclusive = "";
} else if ($buildApp == "TikTok") {
  $include .= " -i tiktok-settings";
  $exclusive = "--exclusive";
} else if ($buildApp == "Twitch") {
  // I'm adding Twitch here because I'm not really sure if the --exclusive flag is still necessary in later ReVanced
  $exclusive = "";
}

// App Prefix for $buildID
$appNameShortcuts = array("YouTube"=>"yt", "YouTube Music"=>"ym", "Crunchyroll"=>"cr", "Reddit"=>"re", "Spotify" => "sp", "TikTok"=>"tt", "Twitch"=>"tc", "Twitter"=>"tw", "IconPackStudio"=>"ip", "Pflotsh"=>"pf", "WarnWetter"=>"ww", "HexEditor"=>"he", "My Expenses"=>"my", "Nyx Music"=>"nx");
$appPrefix = $appNameShortcuts[$buildApp];

// Patch Options
// These have to be in the same order as they appear in the HTML to make sure the buildID lines up

// theme (YouTube)
if (in_array("theme", $patches)) {
  $bgDark = $_POST['theme-bg-dark'];
  $bgLight = $_POST['theme-bg-light'];
  $options .= "[theme]\ndarkThemeBackgroundColor = \"{$bgDark}\"\nlightThemeBackgroundColor = \"{$bgLight}\"\n";
  $buildID .= "theme-bg-dark={$bgDark}theme-bg-light={$bgLight}";
  array_push($optionsArray, array("[theme]" => array("darkThemeBackgroundColor" => $bgDark, "lightThemeBackgroundColor" => $bgLight)));
}

// custom-branding (YouTube)
if (in_array("custom-branding", $patches)) {
  $custAppName = $_POST['custom-branding-appname'];
  $options .= "['custom-branding']\nappName = \"{$custAppName}\"\n";
  $buildID .= "custom-branding-appname={$custAppName}";
  array_push($optionsArray, array("['custom-branding']" => array("appName" => $custAppName)));
}

// custom-playback-speed
if (in_array("custom-playback-speed", $patches)) {
  $speedGran = $_POST['playback-speed-granularity'];
  $speedMin = $_POST['playback-speed-min'];
  $speedMax = $_POST['playback-speed-max'];
  $options .= "['custom-playback-speed']\ngranularity = \"{$speedGran}\"\nmin = \"{$speedMin}\"\nmax = \"{$speedMax}\"\n";
  $buildID .= "playback-speed-granularity={$speedGran}playback-speed-min={$speedMin}playback-speed-max={$speedMax}";
  array_push($optionsArray, array("['custom-playback-speed']" => array("granularity" => $speedGran, "min" => $speedMin, "max" => $speedMax)));
}

// spotify-theme
if (in_array("spotify-theme", $patches)) {
  $spotBg = $_POST['spotify-theme-bg'];
  $spotAccent = $_POST['spotify-theme-accent'];
  $spotAccentPressed = $_POST['spotify-theme-accent2'];
  $options .= "['spotify-theme']\nbackgroundColor = \"{$spotBg}\"\naccentColor = \"{$spotAccent}\"\naccentPressedColor = \"{$spotAccentPressed}\"\n";
  $buildID .= "spotify-theme-bg={$spotBg}spotify-theme-accent={$spotAccent}spotify-theme-accent2={$spotAccentPressed}";
  array_push($optionsArray, array("['spotify-theme']" => array("backgroundColor" => $spotBg, "accentColor" => $spotAccent, "accentPressedColor" => $spotAccentPressed)));
}

// Get the first X characters of md5 for the buildID and add the $appPrefix
$buildID = $appPrefix.substr(md5($buildID), 0, $config->buildIDLength-2); // Convert build_id into first idLength (minus 2) digits of md5 for shorter build_id (minus 2 to make up for prefix)

// Optional suffix after the AppName in the final .apk
$buildSuffix = ($config->buildSuffix != "") ? " ".$config->buildSuffix : "";

// Check if file already exists with this build name
// If it does, send the user the build information instead
if (file_exists("../{$buildDirectory}/{$buildAppEncoded}{$buildSuffix}-{$buildID}.apk")) {
  $cur = Files::read("../{$buildDirectory}/{$buildAppEncoded}{$buildSuffix}-{$buildID}.info.txt");
  die(json_encode($cur));
}

// Write options file if necessary
if ($options != "") {
  $createOptions = Files::write("../{$buildDirectory}/{$buildAppEncoded}{$buildSuffix}-{$buildID}.options.txt", $options);
  $optionsFile = "--options=\"../{$buildDirectory}/{$buildAppEncoded}{$buildSuffix}-{$buildID}.options.txt\"";
} else {
  $optionsFile = "--options=\"tools/options.toml\"";
}

// Directly execute the revanced-cli.jar file using a command built with all the selected info and patches
if ($buildApp == "YouTube" || $buildApp == "YouTube Music" || $buildApp == "Twitch" || $buildApp == "TikTok") { // These apps need the revanced-integrations

  $javaCMD = "java -jar \"tools/revanced-cli.jar\" -a \"apk/{$buildAppEncoded}-{$buildVersion}.apk\" -c -o \"../{$buildDirectory}/{$buildAppEncoded}{$buildSuffix}-{$buildID}.apk\" -b \"tools/revanced-patches.jar\" -m \"tools/revanced-integrations.apk\" --temp-dir=\"cache\" --keystore=\"../{$buildDirectory}/RWB-{$buildAppEncoded}.keystore\" {$include} {$optionsFile} {$exclusive}";

} else { // Other apps don't

  $javaCMD = "java -jar \"tools/revanced-cli.jar\" -a \"apk/{$buildAppEncoded}-{$buildVersion}.apk\" -c -o \"../{$buildDirectory}/{$buildAppEncoded}{$buildSuffix}-{$buildID}.apk\" -b \"tools/revanced-patches.jar\" --temp-dir=\"cache\" --keystore=\"../{$buildDirectory}/RWB-{$buildAppEncoded}.keystore\" {$include} {$optionsFile} --exclusive";

}

// If debug feature 'debugShowJava' is checked, output the JavaCMD line instead of building
if (isset($_POST['debugShowJava'])) {
  die($javaCMD);
}

// Everything seems okay. Start building
$execJava = exec($javaCMD, $javaOutput);

if ($execJava == "INFO: Finished") { // Success!

  $timeTotal = time() - $buildDate; // Calculate how long build took to make
  $fileMD5 = md5_file(__DIR__."/../{$buildDirectory}/{$buildAppEncoded}{$buildSuffix}-{$buildID}.apk"); // Get MD5 hash of generated build
  $filesize = filesize(__DIR__."/../{$buildDirectory}/{$buildAppEncoded}{$buildSuffix}-{$buildID}.apk"); // Get APK file size

  // Don't include MicroG for any version of Twitter or Reddit, etc
  $microg = ($buildApp == "YouTube" || $buildApp == "YouTube Music") ? "vanced-microg.apk":"";

  $txtData = array(
    'app' => $buildApp,
    'version' => $buildVersion,
    'url' => $buildApp.$buildSuffix.'-'.$buildID.'.apk',
    'id' => $buildID,
    'md5' => $fileMD5,
    'buildDate' => $buildDate,
    'buildDateFull' => $buildDateFull,
    'buildDuration' => $timeTotal,
    'buildSize' => $filesize,
    'microG' => $microg,
    'patches' => substr( str_replace(" -i ", "|", $include), 1 ), // convert -i to | and remove the first one
    'options' => $optionsArray
  );

  Files::write("../{$buildDirectory}/{$buildAppEncoded}{$buildSuffix}-{$buildID}.info.txt", json_encode($txtData, JSON_PRETTY_PRINT)); // Write text file with all the build information

  statsUpdate(); // Update duration averages for each app

  // Add java output to the return json
  $txtData['javaOutput'] = $javaOutput;

  // If AJAX/Javascript was used, return the JSON object, otherwise return a page of the results in HTML
  if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo json_encode($txtData);
  } else {
    $buildSize = round($filesize / 1024 / 1024); // build size in mb
    echo "
    <h3 style='margin-bottom: 10px'>Build Complete</h3>
    <p>App: {$buildApp}</p>
    <p>Version: {$buildVersion}</p>
    <p>Build ID: <a href='{$urlPrefix}#{$appPrefix}{$buildID}'>{$buildID}</a></p>
    <p class='buildInfo'>Build MD5: {$fileMD5}</p>
    <p class='buildInfo'>Build Duration: {$timeTotal} seconds</p>
    <p class='buildInfo'>Build Date: {$buildDateFull}</p>
    <p class='buildInfo'>Build Size: {$filesize} bytes ({$buildSize} MB)</p>
    <p>Patches: ".substr( str_replace(" -i ", "|", $include), 1 )."</p>";

    if ($microg != "") {
      echo "<p>MicroG: <a href='{$urlPrefix}/{$buildDirectory}/{$microg}'>{$urlPrefix}/{$buildDirectory}/{$microg}</a> (Required for Non-Root APKs)</p>";
    }

    $buildURL = $buildApp.'Vanced-'.$buildID.'.apk';
    echo "<p>{$buildApp} ReVanced: <a href='{$urlPrefix}/{$buildDirectory}/{$buildURL}'>{$urlPrefix}/{$buildDirectory}/{$buildURL}</a></p>";

  }

  // Delete default options.toml (in case new/default options come in ReVanced Patcher updates)
  if ($optionsFile == "--options=\"tools/options.toml\"") {
    unlink("tools/options.toml");
  }

  die();

} else {
  // Write javaOutput as an error message to the build's txt file so we know it failed
  // Edit: Don't do this yet, because the real errors show up after PHP quts.
  //$errorLog = array("error" => true, "javaOutput" => $javaOutput);
  //Files::write("../{$buildDirectory}/{$buildApp}{$buildSuffix}-{$buildID}.txt", json_encode($errorLog, JSON_PRETTY_PRINT)); // Write text file with all the build information
  $fullJava = "";
  foreach($javaOutput as $line) {
    $fullJava .= $line."<br />";
  }
  echo "ERROR:".$fullJava;
}
