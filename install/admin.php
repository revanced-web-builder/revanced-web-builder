<?php
// These should probably be disabled when cleaning code up
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set("memory_limit", 512 * 1024 * 1024); // Set memory limit to 512mb so PHP cURL files successfully download
error_reporting(E_ALL);

require_once("functions.php"); // import classes/functions

// Check if user is using the mod_rewrite page (/admin) or the full path (/app/admin.php)
$dirPrefix = (substr(trim($_SERVER['REQUEST_URI'], "/"), -5) == "admin") ? "app/" : "..";

// Get the full URL of the currently running script and remove "/app/build.php" from it
// This guarantees the script will direct the user to the correct finished build location
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https://' : 'http://';
$urlPrefix = substr($protocol.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'], 0, -14); // Remove "/app/admin.php"

$setupErrors = []; // Array of errors that will stop the script from continuing at certain points

// Detect if we're using the mod_rewrite page (/admin) or the full path (/app/admin.php)
//$apacheModules = apache_get_modules();
//$isRewrite = (in_array('mod_rewrite', $apacheModules)) ? "Detected" : "../";

session_start();

// User has requested to log out.
if (isset($_GET['logout'])) {
  unset($_SESSION['token']);
}

set_time_limit(0); // if the file is large set the timeout.

// First of all, the app/ folder has to be writable. End the script because we can't do anything without this.
if (!is_writable(".")) {
  die("<h2>ReVanced Web Builder</h2><p>app/ folder needs to be writable to install and configure RWB.</p>");
}

// Make sure config.json.dist exist and is readable so we can create the config file for user
if (!file_exists("config.json.dist") || !is_readable("config.json.dist")) {
  echo "<h2>ReVanced Web Builder</h2><p>app/config.json.dist needs to exist and be readable to continue installation.</h2>";
  echo "<p>Download it at <a href='https://rwb.frwd.app/app/config.json.dist' target='_blank'>https://rwb.frwd.app/app/config.json.dist</a> and place it in the app/ folder.</p>";
  die();
}

// Check if a config file exists. If not, copy the .dist file to config.json
// $created will be used later to confirm the default config was created
if (!file_exists("config.json")) {
  $copyConfig = copy("config.json.dist", "config.json"); // copy bundled config file as the active config.json
  if ($copyConfig) { // config.json created
    $created = "<span class='badge bg-light'>Created</span> ";
  } else { // Config file creation failed (it shouldn't if app/ is writable, right?)
    die("<h2>ReVanced Web Builder</h2><p>Failed to create app/config.json</p>
    <p>Manually copy app/config.json.dist to app/config.json and make it writable.</p>");
    // Server owner will have to manually create a valid config.json file before the script will continue
  }
} else {
  $created = "";
}


// Gather all Config and AppData JSON
$config = new Config();

$auth = new Auth();
// Simple password/authentication system
$loginPassword = $_POST['adminPass'] ?? null;
if ($loginPassword != null) {
  $loginAttempt = $auth->login($loginPassword);
}

$appData = $config->apps;
$toolData = $config->tools;
$themeData = $config->themes;

// Automatically check for RWB updates if enabled
$versionUpdate = null;
if ($config->autoUpdate == 1) {
  // Download information about the latest RWB release
  $url = "https://api.github.com/repos/revanced-web-builder/revanced-web-builder/releases/latest";
  $dl = fileDownload($url, "update/latestRelease.json");
  $data = Files::read("update/latestRelease.json");

  // Check current version
  $config = new Config();
  $versionInstalled = $config->versionLast;
  $version = substr($data['tag_name'], 1);

  if (version_compare($versionInstalled, $version) == -1) {
    $versionUpdate = $version;
  }
}


// Check if there's an update available
$distConfig = Files::read("config.json.dist");
if ($config->versionLast < $distConfig['versionLast']) {

  $config->updateVersion();

  //Inject patches from revanced-patches.json (this should eventually be part of updateVersion())
  if (file_exists("tools/revanced-patches.json")) {
    $config->injectPatches();
  }

  // Reload config
  $config = new Config();
  $tools = $config->toolArray();

  /*echo "<h3>ReVanced Web Builder has been updated from version {$config->versionLast} to {$distConfig['versionLast']}</h3>
  <p class='mt-3'><a href='?q=update'><button class='btn btn-primary'>Update</button></a></p>";*/
  header("Location: $urlPrefix/app/admin.php?update=".$distConfig['versionLast']);
  exit;
  die();
}


// Information needed for downloading ReVanced tools
$tools = $config->toolArray();

// Check if there are any queries in case user is trying to download files
$query = (isset($_GET['q'])) ? $_GET['q'] : "";

// User is trying to download, delete, or toggle a build
if ($query == "dl" || $query == "del" || $query == "toggle") {

  $file = $_GET['file'];
  $name = (isset($_GET['name'])) ? $_GET['name'] : "";
  $version = (isset($_GET['version'])) ? $_GET['version'] : "";
  $toggleOnly = (isset($_GET['toggleOnly'])) ? $_GET['toggleOnly'] : 0;
  $isBeta = "";
  $isBeta2 = "";

  // If version ends with " Beta"
  if (substr($version, -5) == " Beta") {
    $version = substr($version, 0, -5);
    $isBeta = " Beta";
    $isBeta2 = ".Beta";
  }

  // Download APK/Tool
  if ($query == "dl") {

    // Only show array with apk info if this isn't a tool. Otherwise, show main $tools array
    if ($file == "apk") {
      $files = array(
        "apk" => array("download" => "https://github.com/revanced-web-builder/revanced-web-builder/releases/download/apks/{$name}-{$version}{$isBeta2}.apk", "output" => "apk/{$name}-{$version}{$isBeta}.apk")
      );
    } else {
      $files = $tools;
    }

    $dl = fileDownload($files[$file]["download"], $files[$file]["output"]);

    // Also download patches.json from ReVanced Patches repo if necessary
    // Inject it into the config.json
    if ($file == "Patches") {
      $dlP = fileDownload("https://github.com/revanced/revanced-patches/releases/download/v{$toolData['Patches']['latest']}/patches.json", "tools/revanced-patches.json");
      $config->injectPatches(); // inject official revanced patches.json compatibility information into RWB config.json
    }

    if ($dl == true) {

      // Update config.json to show this version is enabled
      if ($file == "apk") {
        $config->app($name, $version, "enabled", 1);
      } else {
        $config->tool($file, "enabled", $files[$file]['version']);
      }

      die("OK"); // return OK to javascript
    } else {
      die("NOT OK");
    }

  }

  // Delete APK/Tool
  if ($query == "del") {
    if ($file != "apk") {
      unlink($tools[$file]['output']);
      $config->tool($file, "enabled", 0);
      die("1");
    } else {
      unlink("apk/".$name."-".$version.$isBeta.".apk");

      // Mark as not enabled
      $config->app($name, $version, "enabled", 0);

      die("1");
    }
  }

  if ($query == "toggle") {
    $isEnabled = $config->app($name, $version, "enabled");
    $newToggle = ($isEnabled == 1) ? 0 : 1; // if enabled, disable
    $updateConfig = $config->app($name, $version, "enabled", intval($newToggle));
    die("toggled|".$newToggle); // return toggled|1 or 0
  }

}

// User is trying to edit Config File
if ($query == "config") {

  // Data will be in $_POST. Make a normal variable for each one
  // (Config array will be manually built so no worries about extra data being added)
  foreach ($_POST as $key => $val) {
    $$key = $val;
  }

  // These vars must be binary
  $requireBinary = array("buildEnabled", "downloads", "buildDirectoryPublic", "themeSwitcher", "debugMenu", "buildUnsupported", "buildBeta", "footer", "autoUpdate");
  foreach ($requireBinary as $bin) {
    if (!isset($_POST[$bin]) || $_POST[$bin] != "1") {
      $$bin = "0";
    }
  }

  // Make sure buildIDLength is a number, >=6 and <=34
  if (isNumeric($buildIDLength) !== true || $buildIDLength < 6 || $buildIDLength > 34) {
    die("buildIDLength must be numeric, greater than 6, and less than 34.");
  }

  // If $downloads is enabled, clear the .htaccess file in the builds directory
  if (intval($downloads) === 1) {
    // Check if build directory should be public
    $write = (intval($buildDirectoryPublic) === 1) ? "Options +Indexes" : "Options -Indexes";
  } else {
    // Deny accessing folder and files (builder should still work)
    $write = "Order Allow,Deny\nDeny from all";
  }

  // Write new .htaccess file in builds directory
  Files::write("../{$config->buildDirectory}/.htaccess", $write);
  chmod(__DIR__."/../{$config->buildDirectory}/.htaccess", 0775);

  // Build the new config.json
  $configs = array(
    "buildEnabled" => $buildEnabled,
    "buildDirectory" => $buildDirectory,
    "buildDirectoryPublic" => $buildDirectoryPublic,
    "buildIDLength" => $buildIDLength,
    "buildSuffix" => $buildSuffix,
    "buildBeta" => $buildBeta,
    "buildUnsupported" => $buildUnsupported,
    "checkinInterval" => $checkinInterval,
    "downloads" => $downloads,
    "downloadMethod" => $downloadMethod,
    "autoUpdate" => $autoUpdate,
    "footer" => $footer,
    "autoUpdate" => $autoUpdate,
    "pageTitle" => $pageTitle,
    "themeDefault" => $themeDefault,
    "themeSwitcher" => $themeSwitcher,
    "timezone" => $timezone,
    "debugMenu" => $debugMenu
  );

  // Add theme variables to end of $configs (if customThemeReset isn't called)

  if ($themeReset != "1") {
    $buttons = array("main", "input", "primary", "secondary", "warning", "danger");

    $themes = [];
    $themes['custom']["name"] = "custom";
    foreach($buttons as $b) {

      // For all
      $themes['custom'][$b]['bg'] = ${$b."bg"};
      $themes['custom'][$b]['font'] = ${$b."font"};

      // Different vars for different sections
      if ($b != "main") { // Anything but Main
        $themes['custom'][$b]['border'] = ${$b."border"};
        $themes['custom'][$b]['radius'] = ${$b."radius"};
      } else { // just for Main
        $themes['custom'][$b]['accent'] = ${$b."accent"};
        $themes['custom'][$b]['url'] = ${$b."url"};
      }
    }

  } else {

    // User is trying to reset custom theme. Grab it from config.json.dist file
    // Download config.json.dist if it doesn't exist
    if (file_exists("config.json.dist")) {
      $getDist = fileDownload("https://rwb.frwd.app/app/config.json.dist", "config.json.dist");
      chmod("config.json.dist", 0775);
    }

    $distFile = Files::read("config.json.dist");
    $themes = array("custom" => $distFile['themes']['custom']);

  }

  $config->update($configs, $themes); // update the config.json file with the new config and theme

  die("OK"); // return full config.json

}

?>

<!DOCTYPE html>
<head>

  <meta charset="utf-8">
  <title>ReVanced Web Builder</title>
  <meta name="description" content="">
  <meta name="author" content="">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Favicons -->
  <link rel="shortcut icon" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon.ico">
  <link rel="icon" sizes="16x16 32x32 64x64" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon.ico">
  <link rel="icon" type="image/png" sizes="196x196" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-192.png">
  <link rel="icon" type="image/png" sizes="160x160" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-160.png">
  <link rel="icon" type="image/png" sizes="96x96" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-96.png">
  <link rel="icon" type="image/png" sizes="64x64" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-64.png">
  <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-57.png">
  <link rel="apple-touch-icon" sizes="114x114" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-114.png">
  <link rel="apple-touch-icon" sizes="72x72" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-72.png">
  <link rel="apple-touch-icon" sizes="144x144" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-144.png">
  <link rel="apple-touch-icon" sizes="60x60" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-60.png">
  <link rel="apple-touch-icon" sizes="120x120" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-120.png">
  <link rel="apple-touch-icon" sizes="76x76" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-76.png">
  <link rel="apple-touch-icon" sizes="152x152" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-152.png">
  <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-180.png">
  <meta name="msapplication-TileColor" content="#FFFFFF">
  <meta name="msapplication-TileImage" content="<?php echo $urlPrefix; ?>/app/assets/icons/favicon-144.png">
  <meta name="msapplication-config" content="<?php echo $urlPrefix; ?>/app/assets/icons/browserconfig.xml">

  <link rel="stylesheet" href="<?php echo $urlPrefix; ?>/app/assets/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo $urlPrefix; ?>/app/assets/builder.css">

  <style>
  .btn-sm {
    margin-left: 5px;
    padding: 0px 7px !important;
  }

  .revancedTool, .fileDownload, .fileDelete {
    min-width: 30px !important;
  }
  </style>


  <script src="<?php echo $urlPrefix; ?>/app/js/jquery-3.6.1.min.js"></script>
  <script src="<?php echo $urlPrefix; ?>/app/js/bootstrap.min.js"></script>

  <script type="text/javascript">

  var configAll
  var config
  var appData
  var themeData

  loadConfig()

  function loadConfig() {

    $.getJSON("<?php echo $urlPrefix; ?>/app/config.json", function(json) {
      configAll = json
      config = json['config']
      appData = json['apps']
      tools = json['tools']
      themeData = json['themes']
      startupAdmin()
    })

  }

  $(document).ready(function(e) {



  })

  function startupAdmin() {

    if ($("#adminLoginForm").is(":visible")) $("#adminHeader").addClass("text-center")

    // Show custom theme inputs if set to custom
    if ($("#themeDefault").val() == "custom") {
      $("#configThemeContainer").show()
    }

    // Update page with proper theme colors
    themeSetAdmin(config.themeDefault)

    if ($("#adminConfiguration").is(":visible")) {
      $("#adminLogout").show()
    }

    // Check if all 4 tools are downloaded/updated before showing the APK and Config panels
    var dloaded = $("#downloadTools .btn-secondary").length
    if (dloaded == 4) {
      $("#downloadAll,#toolsNotice").hide()
      $(".configComplete").slideDown()
    }

    if (config.buildUnsupported != 1) $("p[data-support='0']").hide() // Hide unsupported builds (if necessary)
    if (config.buildBeta != 1) $("p[data-beta='1']").hide() // Hide beta builds (if necessary)

    // Focus on password field if admin is not logged in
    if ($("#adminLoginForm").is(":visible")) $("#adminPass").focus()
  }

  function toggleSection(element) {
    var action = $(element).data("action")
    var parent = $(element).parent().parent()
    var select = (action == "enable") ? "button.btn-orange" : "button.btn-secondary"
    $(parent).find(select).trigger("click") // click only the chosen buttons
  }

  function fileToggle(element) {

    var button = $(this)
    var file = $(this).data("file")
    var name = $(this).data("name")
    var version = $(this).data("version")

    $.ajax({
      type: "GET",
      url: "<?php echo $urlPrefix; ?>/app/admin.php?q=toggle&file="+file+"&name="+name+"&version="+version,
      success: function (data) {

      }
    })


  }

  // Download APK or Tool
  $(document).on("click", ".fileDownload", function(e) {

    if ($(this).hasClass("btn-secondary") || $(this).hasClass("btn-orange")) { // Toggle app instead of download
      if ($(this).data("file") != "apk") return false // this is a tool, don't allow it to be toggled
      var query = "toggle" // Toggle
    } else {
      var query = "dl" // Download
    }

    var button = $(this)
    var file = $(this).data("file")
    var name = $(this).data("name")
    var version = $(this).data("version")

    $.ajax({
      type: "GET",
      url: "<?php echo $urlPrefix; ?>/app/admin.php?q="+query+"&file="+file+"&name="+name+"&version="+version,
      beforeSend: function(b) {
        $(button).removeClass("btn-primary btn-secondary btn-warning btn-danger btn-info btn-orange").addClass("btn-warning")
      },
      success: function (data) {

        if (data == "OK") {
          $(button).next().remove();
          $(button).html("&#10003;").removeClass("btn-warning").addClass("btn-secondary").after(`<button class='fileDelete btn btn-danger btn-sm' data-file='`+file+`' data-name='`+name+`' data-version='`+version+`'><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
          <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
          <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
          </svg></button>`)

          // Check if all 4 tools are downloaded/updated before showing the APK and Config panels
          var dloaded = $("#downloadTools .btn-secondary:visible").length
          if (dloaded == 4) {
            $("#downloadAll,#toolsNotice").slideUp()
            $(".configComplete").slideDown()
          }

        } else if (data.substr(0, 7) == "toggled") {
          var newToggle = data.substr(8)
          if (newToggle == 1) {
            $(button).removeClass("btn-warning").addClass("btn-secondary").html("✓").attr("title", "Disable Version")
          } else {
            $(button).removeClass("btn-warning").addClass("btn-orange").html("X").attr("title", "Enable Version")
          }
        } else {
          $(button).html("&#10006;").removeClass("btn-warning").addClass("btn-danger")
        }

      }
    });

  })

  // Delete APK or Tool
  $(document).on("click", ".fileDelete", function(e) {

    var button = $(this)
    var file = $(this).data("file")
    var name = $(this).data("name")
    var version = $(this).data("version")
    var parent = $(this).parent()

    $.ajax({
      type: "GET",
      url: "<?php echo $urlPrefix; ?>/app/admin.php?q=del&file="+file+"&name="+name+"&version="+version,
      beforeSend: function(b) {
        $(button).removeClass("btn-primary btn-info btn-danger btn-secondary btn-orange").addClass("btn-warning")
      },
      success: function (data) {

        // If this was a tool, hide the APK and Configuration panels until all tools are downloaded again
        if (file != "apk") {
          $(".configComplete").slideUp()
          $("#toolsNotice").slideDown()
        }
      }

    })


    $(this).remove() // delete the delete button

    // Turn the success button back into a download button
    $(parent).find(".fileDownload").removeClass("btn-secondary btn-info btn-warning btn-danger").addClass("btn-primary").html("&darr;");

  })

  // Show custom theme inputs if custom is selected for default theme
  $(document).on("change", "#themeDefault", function(e) {

    var theme = $(this).val()

    if (theme == "custom") {
      $("#configThemeContainer").slideDown()
      $('html, body').animate({scrollTop:$(document).height()}, 'slow') // scroll to bottom of page
    } else {
      $("#configThemeContainer").slideUp()
    }

  })

  $(document).on("change", "#configForm", function(e) {

    var errors = 0

    // Going to one by one make sure every config input is okay.
    var buildIDLength = parseInt($("#buildIDLength").val())
    var checkinInterval = parseInt($("#checkinInterval").val())

    // Make sure buildIDLength is a number
    if (isNaN(buildIDLength) === true) {
      $("#buildIDLength").addClass("configError")
      errors++
    } else {
      // Make sure buildIDLength is > 6 and < 34
      if (buildIDLength < 6) buildIDLength = 6
      if (buildIDLength > 34) buildIDLength = 34
      $("#buildIDLength").val(buildIDLength).removeClass("configError")
    }

    // Make sure checkinInterval is a number
    if (isNaN(checkinInterval) === true) {
      $("#checkinInterval").addClass("configError")
      errors++
    } else {
      if (checkinInterval < 0) checkinInterval = 0
      $("#checkinInterval").val(checkinInterval).removeClass("configError")
      $("#checkinInterval").removeClass("configError")
    }

    if (errors > 0) return false

    var configFinal = $("#configForm").serialize()

    // Verify changes with server and write to config.json
    $.ajax({
      type: "POST",
      url: "<?php echo $urlPrefix; ?>/app/admin.php?q=config",
      data: configFinal,
      beforeSend: function(b) {
      },
      success: function (data) {
        if ($("#themeReset").val() == 1) {
          location.reload()
        }

        // Hide/show beta builds
        if ($("#buildBeta").prop("checked")) {
          $("p[data-beta='1']").slideDown()
        } else {
          $("p[data-beta='1']").slideUp()
        }

        // Hide/show unsupported builds
        if ($("#buildUnsupported").prop("checked")) {
          $("p[data-support='0']").slideDown()
        } else {
          $("p[data-support='0']").slideUp()
        }

        setTimeout("loadConfig()", 200) // reload config and theme
        //themeSetAdmin($("#themeDefault").val())
      }

    })

    e.preventDefault()

  })


  $(document).on("click", "#customThemeReset", function(e) {
    $("#themeReset").val(1) // set themeReset to 1 so the script knows to remove theme info from config and refresh page
    $("#configForm").trigger("change")
  })

  function themeSetAdmin(theme=undefined) {

    if (theme == undefined) {
      // Check current theme
      var current = $("body").data("theme")
      if (current == "custom") {
        theme = "dark" // Custom always goes to dark
      } else if (current == "dark") {
        theme = "light" // Dark always goes to light
      } else if (current == "light") {
        // Light goes to custom if it exists, dark if it doesn't
        theme = (config.themeDefault == "custom") ? "custom" : "dark"
      }
    }

    // Change all the custom CSS variables so the new ones that appear in the DOM are also themed
    $(":root").css({
      "--main-bg": themeData[theme]['main']['bg'],
      "--main-accent": themeData[theme]['main']['accent'],
      "--main-font": themeData[theme]['main']['font'],
      "--main-url": themeData[theme]['main']['url'],
      "--input-bg": themeData[theme]['input']['bg'],
      "--input-font": themeData[theme]['input']['font'],
      "--input-border": themeData[theme]['input']['border'],
      "--input-hover": hexToRGB(themeData[theme]['input']['bg'], 0.7), // Convert Hex to RGBA for the hover background color
      "--input-radius": themeData[theme]['input']['radius']+"px",
      "--btn-primary-bg": themeData[theme]['primary']['bg'],
      "--btn-primary-font": themeData[theme]['primary']['font'],
      "--btn-primary-border": themeData[theme]['primary']['border'],
      "--btn-primary-hover": hexToRGB(themeData[theme]['primary']['bg'], 0.7),
      "--btn-primary-radius": themeData[theme]['primary']['radius']+"px",
      "--btn-secondary-bg": themeData[theme]['secondary']['bg'],
      "--btn-secondary-font": themeData[theme]['secondary']['font'],
      "--btn-secondary-border": themeData[theme]['secondary']['border'],
      "--btn-secondary-hover": hexToRGB(themeData[theme]['secondary']['bg'], 0.7),
      "--btn-secondary-radius": themeData[theme]['secondary']['radius']+"px",
      "--btn-warning-bg": themeData[theme]['warning']['bg'],
      "--btn-warning-font": themeData[theme]['warning']['font'],
      "--btn-warning-border": themeData[theme]['warning']['border'],
      "--btn-warning-hover": hexToRGB(themeData[theme]['warning']['bg'], 0.7),
      "--btn-warning-radius": themeData[theme]['warning']['radius']+"px",
      "--btn-danger-bg": themeData[theme]['danger']['bg'],
      "--btn-danger-font": themeData[theme]['danger']['font'],
      "--btn-danger-border": themeData[theme]['danger']['border'],
      "--btn-danger-hover": hexToRGB(themeData[theme]['danger']['bg'], 0.7),
      "--btn-danger-radius": themeData[theme]['danger']['radius']+"px"
    })

    $("body").data("theme", theme) // store last loaded theme into body

  }

  // Hex to RGB(A) converter
  // From: https://stackoverflow.com/questions/21646738/convert-hex-to-rgba
  function hexToRGB(hex, alpha) {
    var r = parseInt(hex.slice(1, 3), 16),
    g = parseInt(hex.slice(3, 5), 16),
    b = parseInt(hex.slice(5, 7), 16);

    if (alpha) {
      return "rgba(" + r + ", " + g + ", " + b + ", " + alpha + ")";
    } else {
      return "rgb(" + r + ", " + g + ", " + b + ")";
    }
  }


  // Download All or Update All
  $(document).on("click", ".downloadAll", function(e) {
    var choice = $(this).data("choice")
    if (choice == "download") {
      $(".revancedTool").trigger("click") // Download All button in the "ReVanced Tools" section
    } else if (choice == "update") {
      $(".btn-info").trigger("click")
    }
  })

  // EVENTS
  $(document).on("click", ".toggleSection", function(e) { toggleSection($(this)) }) // Download all of a certain App
  $(document).on("click", "#updateHide", function(e) { $("#updateContainer").slideUp() }) // Hide RWB version update box
  </script>

</head>

<body>

  <!-- Back button -->
  <div id="adminBack" title="Back to RWB">
    <a href="<?php echo $urlPrefix;?>" title="Back to RWB">
      <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16">
      <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
    </svg></a>

    <a id="adminLogout" href="?logout" title="Logout" class="ms-2" style="display: none">
      <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="currentColor" class="bi bi-door-open" viewBox="0 0 16 16">
        <path d="M8.5 10c-.276 0-.5-.448-.5-1s.224-1 .5-1 .5.448.5 1-.224 1-.5 1z"/>
        <path d="M10.828.122A.5.5 0 0 1 11 .5V1h.5A1.5 1.5 0 0 1 13 2.5V15h1.5a.5.5 0 0 1 0 1h-13a.5.5 0 0 1 0-1H3V1.5a.5.5 0 0 1 .43-.495l7-1a.5.5 0 0 1 .398.117zM11.5 2H11v13h1V2.5a.5.5 0 0 0-.5-.5zM4 1.934V15h6V1.077l-6 .857z"/>
      </svg></a>
  </div>


  <div id="adminContainer" class="container">
    <div id="adminHeader" class="row">
      <div class="col-12 mb-4">
        <h1 class="d-none d-md-block">ReVanced Web Builder: Admin Panel</h1>
        <h1 class="d-block d-md-none">ReVanced Web Builder<br />Admin Panel</h1>
      </div>
    </div>
    <?php

    // Check if RWB needs to be updated
    if ($versionUpdate != null) {
      echo "<div id='updateContainer' class='accentContainer p-2 p-lg-3 mb-4 main-accent'>
        <h2 class='mb-4'>Update Available!</h2>
        <a href='{$urlPrefix}/app/update'><input type='button' class='btn btn-primary me-2' value='Update to version {$versionUpdate}' /></a>
      </div>";
    }

    // Check if RWB was updated
    if (isset($_GET['update'])) {
      echo "<div id='updateContainer' class='accentContainer p-2 p-lg-3 mb-4 main-accent'>
        <h2 class='mb-4'>Updated to version ".$_GET['update']."!</h2>
        <a href='https://github.com/revanced-web-builder/revanced-web-builder/releases/tag/v".$_GET['update']."' target='_blank'><input type='button' class='btn btn-primary me-2' value='Changelog' /></a> <input id='updateHide' type='button' class='btn btn-secondary' value='Okay' />
      </div>";
    }

    if ($auth->valid !== true) {
      echo "<div class='container'>
      <form id='adminLoginForm' method='post' action='{$_SERVER['PHP_SELF']}' class='row justify-content-center'>";

      // Check if an admin password is set
      if ($auth->configHash() == false) {
        echo "<div class='col-12 text-center'>
          <p>You need to set up an Admin Password. Anything you enter here will be your new password.</p>
        </div>";
      }

      echo "<div class='col-12 col-md-auto'>
          <input type='password' class='form-select' id='adminPass' name='adminPass' placeholder='Admin Password' />
        </div>
        <div class='col-12 col-md-auto'>
          <p><input type='submit' class='btn btn-primary w-100 mt-4 mt-md-auto' value='Login' /></p>
        </div>";
        // Check if previously entered password is incorrect
        if (isset($_POST['adminPass']) && $auth->valid == false) {
          echo "<div class='col-12 text-center'>
            <h3><span class='badge bg-danger'>Incorrect Password</span></h3>
          </div>";
        }

      echo "</form>
      </div>";
      die();
    }
    ?>
    <div class="row">

      <div class="col-12 col-lg-4">


        <h3>Permissions</h3>
        <?php

        // SVGs for File and Folder icons
        $fileIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text me-1" viewBox="0 0 16 16">
        <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5z"/>
        <path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5L9.5 0zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/>
        </svg>';

        $folderIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-folder me-1" viewBox="0 0 16 16">
        <path d="M.54 3.87.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.826a2 2 0 0 1-1.991-1.819l-.637-7a1.99 1.99 0 0 1 .342-1.31zM2.19 4a1 1 0 0 0-.996 1.09l.637 7a1 1 0 0 0 .995.91h10.348a1 1 0 0 0 .995-.91l.637-7A1 1 0 0 0 13.81 4H2.19zm4.69-1.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981l.006.139C1.72 3.042 1.95 3 2.19 3h5.396l-.707-.707z"/>
        </svg>';

        // We've already declared that this folder is writable because it's required for anything to work
        echo "<p>{$folderIcon} app <span class='badge bg-secondary'>Writable</span></p>";

        // Show info about the Config.json (whether it was created at beginning of the script)
        $permConfig = (is_writable("config.json")) ? "{$created}<span class='badge bg-secondary'>Writable</span>" : "{$created}<span class='badge bg-warning'>Not Writable</span>";
        echo "<p>{$fileIcon} app/config.json {$permConfig}</p>";

        // Loop through folders and check if they exist. Try to create them if they don't.
        // "relativePath" -> "rootPath"
        $folders = array("apk" => "app/apk", "tools" => "app/tools", "../".$config->buildDirectory => $config->buildDirectory);

        foreach ($folders as $f => $rootDir) {

          $isFolder = (substr($f, -4, -3) != ".") ? $folderIcon : $fileIcon; // lazy way to check if something is a folder or file

          $created = "";

          if (!file_exists($f)) {

            if (substr($f, -4, -3) == ".") {
              // Write a blank file
              $file = fopen($f, 'w+') or die("Can't open file.");
              fwrite($file, "");
              fclose($file);
              $makeFolder = true; // it's a file but we made it anyway
            } else {
              $makeFolder = @mkdir($f, 0777); // suppress built in error because there's a custom one
            }

            if ($makeFolder) {
              $created = "<span class='badge bg-light'>Created</span> ";
            } else {
              echo "<p><span class='badge bg-warning'>Error</span> Could not create {$f}.</p>";
            }
          }

          // Check if folder is writable
          if (is_writable($f)) {
            $isWritable = "{$created}<span class='badge bg-secondary'>Writable</span>";
          } else {
            $isWritable = "{$created}<span class='badge bg-danger'>Not Writable</span>";
            $setupErrors[] = "{$rootDir} is not writable.";
          }

          echo "<p>{$isFolder} {$rootDir} {$isWritable}</p>";

        }

        echo "</div> <!--end Permission section -->
        <div class='col-12 col-lg-4'>
        <h3>Server</h3>";

        echo "<p>OS <span class='badge bg-secondary'>".PHP_OS_FAMILY."</span></p>";

        // Make sure they have the minimum PHP version
        if (version_compare(PHP_VERSION, '7.4') >= 0) {
          $phpSupport = "<span class='badge bg-secondary'>".PHP_VERSION."</span>";
        } else {
          $phpSupport = "<span class='badge bg-danger'>".PHP_VERSION."</span>";
          $setupErrors[] = "PHP must be at least version 7.4";
        }

        echo "<p>PHP {$phpSupport}</p>";

        // Check the status of System cURL, PHP cURL, and Wget
        $checkDload = [];
        $checkDload['cURL'] = (exec("curl --version") != "") ? true : false;
        $checkDload['PHP'] = (extension_loaded("curl")) ? true : false;
        $checkDload['Wget'] = (exec("wget --version")) ? true : false;

        echo "<p>Downloaders";
        foreach ($checkDload as $name => $val) {
          $badge = ($val == true) ? "bg-secondary" : "bg-danger";
          echo "<span class='badge {$badge} me-2'>{$name}</span>";
          if ($val == true) {
            $downloaders[] = $name; // add to list of available downloaders
          }
        }
        echo "</p>";

        // Make sure they have at least one download tool
        if (count($downloaders) <= 0) {
          echo "<p>cURL or Wget is <u>required</u> to download tools and APKs.</p>";
          $setupErrors[] = "You need System cURL, PHP cURL, or Wget to download APKs and Tools.";
        }

        /* Removing this for now. One works on PHP FPM one doesn't
        if ($_SERVER['HTTP_MOD_REWRITE'] == 'On') {
          return TRUE;
        } else {
          return FALSE;
        }
        $isRewrite = ($_SERVER['HTTP_MOD_REWRITE'] == "On") ? "<span class='badge bg-secondary'>Detected</span>" : "<span class='bg bg-danger'>Not Detected</span>";
        echo "<p>mod_rewrite: {$isRewrite}</p>";*/

        echo "</div> <!-- end Server section -->

        <div class='col-12 col-lg-4'>
        <h3>Java JDK</h3>";

        $output=null;
        $retval=null;
        exec('java --version', $output, $retval);
        //echo "Returned with status $retval and output:\n";

        if (count($output) === 0) {
          echo "<p>Java <span class='badge bg-danger'> Not Found</span></p>";
          echo "<p>Install it with <em>sudo apt install openjdk-18-jdk</em></p><p>You may have to restart your web server.</p>";
          $setupErrors[] = "Java is not installed.";
        } else {
          echo "<p>Java <span class='badge bg-secondary'>".$output[0]."</span></p>";
          //$search = array_search_fuzzy($output, "jdk");

          $javaVersion = exec("javac --version"); // Check java compiler version to make sure they have JDK and not just JRE
          if ($javaVersion != "") {
            $javaVersion = explode(" ", $javaVersion)[1]; // separate by space from javac output and get full version
            $javaVersion = explode(".", $javaVersion)[0]; // separate by period from previous output to get just the main version number

            if ($javaVersion >= 17) {
              echo "<p>Version <span class='badge bg-secondary'>{$javaVersion}</span></p>";
            } else {
              echo "<p>Version <span class='badge bg-danger'>{$javaVersion}</span></p>";
              $setupErrors[] = "Java JDK >= 17 required";
            }
          } else {
            $setupErrors[] = "Can't use <em>javac</em>. Make sure you have Java JDK, not just JRE";
          }
        }

        // JPS is built into newer versions of Java. It is used to detect running Java processes. We use this to detect whether or not the builder is busy.
        // We're only going to show this as a requirement if it isn't found since it should just be found if the Java version is 17+
        $checkJPS = exec("jps", $output);

        if ($checkJPS == "") {
          echo "<p>JPS: <span class='badge bg-danger'>Not Found</span></p>";
          $setupErrors[] = "JPS Not Found - You may need to install a newer version of Java JDK.";
        }

        echo "</div> <!-- end Java section -->
        </div> <!-- end row -->
        <div class='row'>
        <div id='downloadTools' class='col-12'>

        <hr />";

        // Show errors instead of the rest of the page because we can't continue.

        if (count($setupErrors) > 0) {

          echo "<h3>Almost there...</h3>
          <p>It seems you're missing some basic requirements.</p>
          <p>Correct these issues to continue:</p>
          <ul>";

          foreach ($setupErrors as $error) {
            echo "<li>{$error}</li>";
          }

          echo "</ul>
          <p><input type='button' class='btn btn-primary' value='Refresh' onclick='location.reload()' /></p>";
          die();

        }

        echo "<h3>ReVanced Tools</h3>";

        echo "<p id='toolsNotice'>All of these tools are required to properly build APKS. Builder will be disabled until all are downloaded and up to date.</p>";

        $revancedDownloaded = 0;
        $revancedUpdate = 0;

        echo "
        <div class='container mt-3 mb-2 p-0'>
        <div class='row'>";

        foreach($tools as $tool => $val) {

          $deleteFile = '<button class="fileDelete btn btn-danger btn-sm" data-file="'.$tool.'"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
          <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
          <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
          </svg></button>';

          if (file_exists($val['output']) && $toolData[$tool]['enabled'] != 0) {
            $val[1] = "btn-secondary";
            $val[2] = "&#10003;";
            $val[3] = $deleteFile;
            $revancedDownloaded++;
          } else {
            $val[1] = "btn-primary";
            $val[2] = "&darr;";
            $val[3] = "";
          }

          // Check if this is the latest version
          if ($toolData[$tool]['enabled'] != 0 && $toolData[$tool]['enabled'] != $val["version"]) {
            $val[1] = "btn-info";
            $val[2] = "&uarr;";
            $revancedUpdate++;
          }

          echo "<div class='col-12 col-md-6 col-lg-3'><p><button class='btn {$val[1]} btn-sm fileDownload revancedTool' data-file='{$tool}'>{$val[2]}</button>{$val[3]} {$tool} &nbsp;{$val['version']}</p></div>";

        }

        echo "</div>
        </div>
        ";

        if ($revancedDownloaded < 4) {
          echo "<input id='downloadAll' data-choice='download' type='button' class='downloadAll btn btn-primary' value='Download All' />";
        }

        if ($revancedUpdate > 0) {
          echo " <input id='updateAll' data-choice='update' type='button' class='downloadAll btn btn-primary' value='Update All' />";
        }

        echo "</div> <!-- end #downloadTools -->

        <div id='apkVersions' class='configComplete' ".(($revancedDownloaded < 4) ? "style='display: none'":"").">

        <hr />

        <h3>APKs</h3>

        <div class='container-fluid'>

        <div class='row'>
        ";

        foreach($appData as $app => $val) {

          echo "<div class='col-12 col-md-6 col-lg-3 mb-3 mt-2 p-0'>";

          $avgBuildTime = (isset($val['stats']['avg'])) ? "<br />Avg Build: {$val['stats']['avg']} sec" : "";
          echo "<p>{$app}&nbsp;&nbsp;[{$val['size']}]{$avgBuildTime}</p>";

          $disabledCount = count($val['versions']);

          foreach($val['versions'] as $ver => $verVal) {

            $beta = $val['versions'][$ver]['beta'] ?? 0;
            $isBeta = ($beta == 1) ? " Beta":"";


            $deleteFile = '<button class="fileDelete btn btn-danger btn-sm" data-file="apk" data-name="'.$app.'" data-version="'.$ver.$isBeta.'"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
            <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
            <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
            </svg></button>';

            if (!file_exists("apk/{$app}-{$ver}{$isBeta}.apk") == true) {
              $btn = array("btn-primary", "&darr;", "", "Download Version");
            } else if ($val['versions'][$ver]['enabled'] == 1) {
              $btn = array("btn-secondary", "&#10003;", $deleteFile, "Disable Version");
              $disabledCount--;
            } else {
              $btn = array("btn-orange", "X", $deleteFile, "Enable Version");
              $disabledCount--;
            }

            // Check if file is unsupported
            $support = $val['versions'][$ver]['support'] ?? 1;
            $isUnsupported = ($support === 1) ? "" : " (Unsupported)";

            echo "<p data-support='{$support}' data-beta='{$beta}'><button class='btn {$btn[0]} btn-sm fileDownload' data-file='apk' data-name='{$app}' data-version='{$ver}{$isBeta}' title='{$btn[3]}'>{$btn[1]}</button>{$btn[2]} {$ver}{$isBeta}{$isUnsupported}</p>";

          }

          echo "
            <p><input type='button' class='btn btn-secondary btn-sm toggleSection' data-action='enable' value='&#10003; All' /> <input type='button' class='btn btn-orange btn-sm toggleSection' data-action='disable' value='X All' /></p>
          </div>";

        }

        echo "
        </div> <!-- end .row -->
        </div> <!-- end .container-fluid -->
        </div> <!-- end #apkVersions -->";
        ?>
      </div> <!-- end .col -->

    </div> <!-- end .row -->

  </div>

  <div id="adminConfiguration" class="container configComplete" <?php echo ($revancedDownloaded < 4) ? "style='display: none'":""; ?>>
    <div class="row">
      <hr />
      <div class="col-12">

        <form id="configForm">

        <h3 class="mb-4">Configuration</h3>

        <div class="py-2 row">
          <label for="buildEnabled" class="col-12 col-md-4 col-lg-2 col-form-label">Builder Online</label>
          <div class="col-sm-10">
            <label><input type="checkbox" class="mt-3 me-2" id="buildEnabled" name="buildEnabled" value="1" <?php echo ($config->buildEnabled == 1) ? "checked='checked'":""; ?>/> Disable this to prevent new builds from being made.</label>
          </div>
        </div>

        <div class="py-2 row">
          <label for="downloads" class="col-12 col-md-4 col-lg-2 col-form-label">Downloads</label>
          <div class="col-sm-10">
            <label><input type="checkbox" class="mt-3 me-2" id="downloads" name="downloads" value="1" <?php echo ($config->downloads == 1) ? "checked='checked'":""; ?>/> Disable this to prevent builds from being downloaded.</label>
          </div>
        </div>

        <div class="py-2 row">
          <label for="buildDirectoryPublic" class="col-12 col-md-4 col-lg-2 col-form-label">Public Build Directory</label>
          <div class="col-sm-10">
            <label><input type="checkbox" class="mt-3 me-2" id="buildDirectoryPublic" name="buildDirectoryPublic" value="1" <?php echo ($config->buildDirectoryPublic == 1) ? "checked='checked'":""; ?>/> Allow build list to be accessed publicly from builds directory. (If downloads enabled)</label>
          </div>
        </div>

        <div class="py-3 row">
          <label for="buildDirectory" class="col-12 col-md-4 col-lg-2 col-form-label">Build Directory</label>
          <div class="col-sm-10">
            <div class="row">
              <div class="col-6 col-lg-3">
                <input type="text" class="form-control mb-2" id="buildDirectory" name="buildDirectory" value="<?php echo $config->buildDirectory; ?>" />
              </div>
              <label class="col-12 col-lg-9 mt-2" for="buildDirectory">Build folder from root directory. (ex: root/<em>builds</em>)</label>
              </div>
            </div>
        </div>

        <div class="py-3 row">
          <label for="buildIDLength" class="col-12 col-md-4 col-lg-2 col-form-label">Build ID Length</label>
          <div class="col-sm-10">
            <div class="row">
              <div class="col-6 col-lg-3">
                <input type="number" class="form-control mb-2 w-100" id="buildIDLength" name="buildIDLength" value="<?php echo $config->buildIDLength; ?>" min="6" max="34" />
              </div>
              <label class="col-12 col-lg-9 mt-2" for="buildIDLength">Character length of unique Build IDs. At least 6 for less chance of collision.</label>
            </div>
          </div>
        </div>

        <div class="py-3 row">
          <label for="buildDirectory" class="col-12 col-md-4 col-lg-2 col-form-label">Build Suffix</label>
          <div class="col-sm-10">
            <div class="row">
              <div class="col-6 col-lg-3">
                <input type="text" class="form-control mb-2" id="buildSuffix" name="buildSuffix" value="<?php echo $config->buildSuffix; ?>" maxlength="64" />
              </div>
              <label class="col-12 col-lg-9 mt-2">Optional text after app name in build. (ex: YouTube <em>ReVanced</em>-yt1010.apk)</label>
            </div>
          </div>
        </div>

        <div class="py-3 row">
          <label for="pageTitle" class="col-12 col-md-4 col-lg-2 col-form-label">Page Title</label>
          <div class="col-sm-10">
            <div class="row">
              <div class="col-6 col-lg-3">
                <input type="text" class="form-control" id="pageTitle" name="pageTitle" value="<?php echo $config->pageTitle; ?>" />
              </div>
              <div class="col-12 col-lg-9 mt-2"></div>
            </div>
          </div>
        </div>

        <div class="py-3 row">
          <label for="checkinInterval" class="col-12 col-md-4 col-lg-2 col-form-label">Checkin Interval</label>
          <div class="col-sm-10">
            <div class="row">
              <div class="col-6 col-lg-3">
                <input type="number" class="form-control mb-2" id="checkinInterval" name="checkinInterval" step="1" min="0" value="<?php echo $config->checkinInterval; ?>" />
              </div>
              <label class="col-12 col-lg-9 mt-2">Seconds between checking if builder is busy. (0 to disable)</label>
            </div>
          </div>
        </div>

        <div class="py-3 row">
          <label for="timezone" class="col-12 col-md-4 col-lg-2 col-form-label">Timezone</label>
          <div class="col-sm-10">
            <div class="row">
              <div class="col-6 col-lg-3">
                <?php
                $timezones = array(
                  "Etc/GMT+12" => "GMT-12:00",
                  "Pacific/Midway" => "GMT-11:00",
                  "Pacific/Honolulu" => "GMT-10:00",
                  "US/Alaska" => "GMT-09:00",
                  "America/Los_Angeles" => "GMT-08:00",
                  "US/Arizona" => "GMT-07:00",
                  "US/Central" => "GMT-06:00",
                  "US/Eastern" => "GMT-05:00",
                  "Canada/Atlantic" => "GMT-04:00",
                  "America/Argentina/Buenos_Aires" => "GMT-03:00",
                  "America/Noronha" => "GMT-02:00",
                  "Atlantic/Azores" => "GMT-01:00",
                  "Etc/Greenwich" => "GMT+00:00",
                  "Europe/Amsterdam" => "GMT+01:00",
                  "Europe/Helsinki" => "GMT+02:00",
                  "Europe/Moscow" => "GMT+03:00",
                  "Asia/Tehran" => "GMT+03:30",
                  "Asia/Yerevan" => "GMT+04:00",
                  "Asia/Kabul" => "GMT+04:30",
                  "Asia/Karachi" => "GMT+05:00",
                  "Asia/Calcutta" => "GMT+05:30",
                  "Asia/Katmandu" => "GMT+05:45",
                  "Asia/Dhaka" => "GMT+06:00",
                  "Asia/Rangoon" => "GMT+06:30",
                  "Asia/Bangkok" => "GMT+07:00",
                  "Asia/Hong_Kong" => "GMT+08:00",
                  "Asia/Seoul" => "GMT+09:00",
                  "Australia/Adelaide" => "GMT+09:30",
                  "Australia/Canberra" => "GMT+10:00",
                  "Asia/Magadan" => "GMT+11:00",
                  "Pacific/Auckland" => "GMT+12:00",
                  "Pacific/Tongatapu" => "GMT+13:00"
                );

                echo "<select id='timezone' name='timezone' class='form-select'>";
                  foreach($timezones as $key => $val) {
                    echo "<option value='{$key}' ".(($config->timezone == $key) ? "selected":"").">{$val}</option>";
                  }
                echo "</select>";
                ?>
              </div>
              <div class="col-12 col-lg-9 mt-2"></div>
            </div>
          </div>
        </div>

        <?php
        // Allow user to choose APK/Tool download method if both System and PHP cURL were detected
        if (count($downloaders) >= 2) { ?>
        <div class="py-3 row">
          <label for="downloadMethod" class="col-12 col-md-4 col-lg-2 col-form-label">APK Download Method</label>
          <div class="col-sm-10">
            <div class="row">
              <div class="col-6 col-lg-3">
                <select id="downloadMethod" name="downloadMethod" class="form-control">
                  <option id="downloadauto" value="auto" <?php echo ($config->downloadMethod == "auto") ? "selected":""; ?>>Auto</option>
                  <?php
                  // Only make choices if these were detected
                  foreach($downloaders as $cd) {
                    echo '<option id="download'.$cd.'" value="'.strtolower($cd).'" '.($config->downloadMethod == strtolower($cd) ? "selected":"").'>'.$cd.'</option>';
                  }
                  ?>
                </select>
              </div>
            </div>
          </div>
        </div>
      <?php } else { echo "<input type='hidden' name='downloadMethod' value='auto' checked='checked' />"; } // set "auto" to default if user can't choose ?>

        <div class="py-2 row">
          <label for="configautoUpdate" class="col-12 col-md-4 col-lg-2 col-form-label">Auto Update</label>
          <div class="col-sm-10">
            <label><input type="checkbox" class="mt-3 me-2" id="configautoUpdate" name="autoUpdate" value="1" <?php echo ($config->autoUpdate == 1) ? "checked='checked'":""; ?>/> Automatically check for ReVanced Web Builder updates.</label>
          </div>
        </div>

        <div class="py-2 row">
          <label for="configfooter" class="col-12 col-md-4 col-lg-2 col-form-label">Footer</label>
          <div class="col-sm-10">
            <label><input type="checkbox" class="mt-3 me-2" id="configfooter" name="footer" value="1" <?php echo ($config->footer == 1) ? "checked='checked'":""; ?>/> Display footer to support ReVanced and Web Builder.</label>
          </div>
        </div>

        <div class="py-2 row">
          <label for="buildBeta" class="col-12 col-md-4 col-lg-2 col-form-label">Beta Builds</label>
          <div class="col-sm-10">
            <label><input type="checkbox" class="mt-3 me-2" id="buildBeta" name="buildBeta" value="1" <?php echo ($config->buildBeta == 1) ? "checked='checked'":""; ?>/> Allow users to make beta builds.</label>
          </div>
        </div>

        <div class="py-2 row">
          <label for="buildUnsupported" class="col-12 col-md-4 col-lg-2 col-form-label">Unsupported Builds</label>
          <div class="col-sm-10">
            <label><input type="checkbox" class="mt-3 me-2" id="buildUnsupported" name="buildUnsupported" value="1" <?php echo ($config->buildUnsupported == 1) ? "checked='checked'":""; ?>/> Allow users to make builds RWB no longer supports.</label>
          </div>
        </div>

        <div class="py-2 row">
          <label for="configdebugMenu" class="col-12 col-md-4 col-lg-2 col-form-label">Debug Menu</label>
          <div class="col-sm-10">
            <label><input type="checkbox" class="mt-3 me-2" id="configdebugMenu" name="debugMenu" value="1" <?php echo ($config->debugMenu == 1) ? "checked='checked'":""; ?>/> Allow access to the hidden debug menu.</label>
          </div>
        </div>

        <div class="py-2 row">
          <label for="configthemeSwitcher" class="col-12 col-md-4 col-lg-2 col-form-label">Theme Switcher</label>
          <div class="col-sm-10">
            <label><input type="checkbox" class="mt-3 me-2" id="configthemeSwitcher" name="themeSwitcher" value="1" <?php echo ($config->themeSwitcher == 1) ? "checked='checked'":""; ?>/> Allow user to toggle between themes.</label>
          </div>
        </div>


        <div class="py-3 row">
          <label for="themeDefault" class="col-12 col-md-4 col-lg-2 col-form-label">Default Theme</label>
          <div class="col-sm-10">
            <div class="row">
              <div class="col-6 col-lg-3">
                <select id="themeDefault" name="themeDefault" class="form-control">
                  <option value="dark" <?php echo ($config->themeDefault == "dark") ? "selected":""; ?>>Dark</option>
                  <option value="light" <?php echo ($config->themeDefault == "light") ? "selected":""; ?>>Light</option>
                  <option value="custom" <?php echo ($config->themeDefault == "custom") ? "selected":""; ?>>Custom</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div id="configThemeContainer" class="px-3" style="display: none">

          <?php
          // Customizable buttons and their defaults
          $buttons = array(
            "main" => "Custom Theme",
            "input" => "Input/Select Boxes",
            "primary" => "Primary Button",
            "secondary" => "Secondary Button",
            "warning" => "Warning Button",
            "danger" => "Danger Button"
          );

          foreach ($buttons as $b => $v) {
            $bg = $themeData['custom'][$b]['bg'];
            $font = $themeData['custom'][$b]['font'];

            echo "
            <div class='py-3 row'>
              <label class='col-12 col-md-4 col-lg-2 col-form-label'>{$v}</label>
              <div class='col-12 col-md-8 col-lg-10'>
                <div class='row'>
                  <div class='col-sm-auto'>
                    <label>Background <input type='color' class='ms-1' id='{$b}bg' name='{$b}bg' value='{$bg}' /></label>
                  </div>
                  <div class='col-sm-auto'>
                    <label>Font <input type='color' class='ms-1' id='{$b}font' name='{$b}font' value='{$font}' /></label>
                  </div>";

                  if ($b != "main") {
                    $border = $themeData['custom'][$b]['border'];
                    $radius = $themeData['custom'][$b]['radius'];
                    echo "
                    <div class='col-sm-auto'>
                      <label>Border <input type='color' class='ms-1' id='{$b}border' name='{$b}border' value='{$border}' /></label>
                    </div>
                    <div class='col-sm-auto pe-0'><label for='{$b}radius'>Radius</label></div>
                    <div class='col-sm-1'>
                      <input type='number' class='form-control btn-sm' id='{$b}radius' name='{$b}radius' min='0' max='100' value='{$radius}' />
                    </div>";
                    if ($b != "Input") {
                      echo "
                      <div class='col-sm-auto'>
                        <button id='example".$b."' class='form-control btn btn-sm btn-{$b}'>Example</button>
                      </div>";
                    }
                  }

                  if ($b == "main") {
                    $accent = $themeData['custom'][$b]['accent'];
                    $url = $themeData['custom'][$b]['url'];
                    echo "
                    <div class='col-sm-auto'>
                      <label>Accent <input type='color' class='ms-1' name='{$b}accent' value='{$accent}' /></label>
                    </div>
                    <div class='col-sm-auto'>
                      <label>URL <input type='color' class='ms-1' name='{$b}url' value='{$url}' /></label>
                    </div>";
                  }


                echo "
                </div> <!-- end .row -->
              </div>
            </div> <!-- end main .row -->";
          }
          ?>

          <div class="row">
            <div class="col-12">
              <p><input id="customThemeReset" type="button" class="btn btn-primary" name="customThemeReset" value="Reset Custom Theme" /> <input type="hidden" id="themeReset" name="themeReset" value="0" /></p>
            </div>
          </div>

        </div> <!-- end #configThemeContainer -->

        </form>

      </div>

    </div>
  </div>

  <?php
  // Detect if Install folder exists
  if (file_exists("../install"))
  { ?>

  <div id="adminInstallFound" class="container configComplete" <?php echo ($revancedDownloaded < 4) ? "style='display: none'":""; ?>>
    <div class="row">
      <hr />
      <div class="col-12">
        <h3 class="mb-4">Install Folder Detected</h3>
        <p>You should delete the /install/ folder for security purposes.</p>
      </div>
    </div>
  </div>

  <?php
  }
  ?>

  <?php
  // Detect if Documentation exists
  if (file_exists("docs/index.html"))
  { ?>

  <div id="adminDocs" class="container configComplete" <?php echo ($revancedDownloaded < 4) ? "style='display: none'":""; ?>>
    <div class="row">
      <hr />
      <div class="col-12">
        <h3 class="mb-4">Documentation</h3>
        <p>RWB has documentation that includes information about build info/stats, build durations, known issues, mod_rewrite, dev tools, and more.</p>
        <p><a href="docs/" target="_blank" class="me-2"><input type="button" class="btn btn-primary" value="Go to Documentation" /></a> <a href="https://github.com/revanced-web-builder/revanced-web-builder/" target="_blank"><input type="button" class="btn btn-primary" value="Go to Github" /></a></p>
      </div>
    </div>
  </div>

  <?php
  }
  ?>


  <?php
  // Detect if Dev Tools exist
  if (file_exists("dev/index.php"))
  { ?>

  <div id="adminDev" class="container configComplete" <?php echo ($revancedDownloaded < 4) ? "style='display: none'":""; ?>>
    <div class="row">
      <hr />
      <div class="col-12">
        <h3 class="mb-4">Development Tools</h3>
        <p class="badge bg-danger">Dev Tools have been detected</p>
        <p>Even though your Admin Password will protect the Dev Tools, it is highly suggested that you do not include the /app/dev folder on your public instance of RWB.</p>

        <p><a href="dev/index.php"><input type="button" class="btn btn-primary" value="Go to Dev Tools" /></a></p>
      </div>
    </div>
  </div>

  <?php
  }
  ?>




  <div id="footer" class="container mt-3">
    <div class="row">
      <hr />
      <div class="col-12 col-md-9 pt-1">
        <p>ReVanced Web Builder is not an official <a href="https://github.com/revanced" target="_blank">ReVanced Project</a>, but thankfully uses their open source tools to make this all happen!</p>
        <p>Version <span class="rwbVersion"><?php echo $config->version; ?></span></p>
      </div>
      <div class="col-12 col-md-3" style="text-align: right">
        <!--<a href="https://twitter.com/revwebbuilder" target="_blank" class="me-2"><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 24 24" fill="currentcolor" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="ReVanced Web Builder Twitter"><path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm6.066 9.645c.183 4.04-2.83 8.544-8.164 8.544-1.622 0-3.131-.476-4.402-1.291 1.524.18 3.045-.244 4.252-1.189-1.256-.023-2.317-.854-2.684-1.995.451.086.895.061 1.298-.049-1.381-.278-2.335-1.522-2.304-2.853.388.215.83.344 1.301.359-1.279-.855-1.641-2.544-.889-3.835 1.416 1.738 3.533 2.881 5.92 3.001-.419-1.796.944-3.527 2.799-3.527.825 0 1.572.349 2.096.907.654-.128 1.27-.368 1.824-.697-.215.671-.67 1.233-1.263 1.589.581-.07 1.135-.224 1.649-.453-.384.578-.87 1.084-1.433 1.489z"/></svg></a> <a href="https://discord.com" target="_blank" class="me-2"><svg width="42" height="42" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" fill="currentcolor" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="ReVanced Web Builder Discord"><path d="M12 0c-6.626 0-12 5.372-12 12 0 6.627 5.374 12 12 12 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12zm3.248 18.348l-.371-1.295.896.833.847.784 1.505 1.33v-12.558c0-.798-.644-1.442-1.435-1.442h-9.38c-.791 0-1.435.644-1.435 1.442v9.464c0 .798.644 1.442 1.435 1.442h7.938zm-1.26-3.206l-.462-.567c.917-.259 1.267-.833 1.267-.833-.287.189-.56.322-.805.413-.35.147-.686.245-1.015.301-.672.126-1.288.091-1.813-.007-.399-.077-.742-.189-1.029-.301-.161-.063-.336-.14-.511-.238l-.028-.016-.007-.003-.028-.016-.028-.021-.196-.119s.336.56 1.225.826l-.469.581c-1.547-.049-2.135-1.064-2.135-1.064 0-2.254 1.008-4.081 1.008-4.081 1.008-.756 1.967-.735 1.967-.735l.07.084c-1.26.364-1.841.917-1.841.917l.413-.203c.749-.329 1.344-.42 1.589-.441l.119-.014c.427-.056.91-.07 1.414-.014.665.077 1.379.273 2.107.672 0 0-.553-.525-1.743-.889l.098-.112s.959-.021 1.967.735c0 0 1.008 1.827 1.008 4.081 0 0-.573.977-2.142 1.064zm-.7-3.269c-.399 0-.714.35-.714.777 0 .427.322.777.714.777.399 0 .714-.35.714-.777 0-.427-.315-.777-.714-.777zm-2.555 0c-.399 0-.714.35-.714.777 0 .427.322.777.714.777.399 0 .714-.35.714-.777.007-.427-.315-.777-.714-.777z"/></svg></a> -->
        <a href="https://github.com/revanced-web-builder/revanced-web-builder" target="_blank" class="me-2"><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" fill="currentcolor" viewBox="0 0 24 24" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="GitHub"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg></a> <a href="#" class=""><img src="<?php echo $urlPrefix; ?>/app/assets/icons/ak.webp" height="42"  data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Made in Alaska" /></a>
      </div>
    </div>
  </div>

</body>
</html>
