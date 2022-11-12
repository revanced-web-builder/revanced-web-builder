<?php
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https://' : 'http://';
$urlPrefix = substr($protocol.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'], 0, -14); // Remove "/app/index.php"
?>

<!DOCTYPE html>
<head>

  <meta charset="utf-8">
  <title>ReVanced Web Builder</title>
  <meta name="description" content="">
  <meta name="author" content="">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="manifest" href="<?php echo $urlPrefix; ?>/manifest.json">

  <!-- Styles -->
  <link rel="stylesheet" href="<?php echo $urlPrefix; ?>/app/assets/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo $urlPrefix; ?>/app/assets/builder.css">

  <!-- Favicons -->
  <link rel="shortcut icon" href="<?php echo $urlPrefix; ?>/app/assets/icons/.ico">
	<link rel="icon" sizes="16x16 32x32 64x64" href="<?php echo $urlPrefix; ?>/app/assets/icons/.ico">
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

</head>

<body>

<div id="buildOffline">
  <span id="buildOfflineMsg">Builder is offline. No new builds can be made.</span>
  <span id="downloadsOfflineMsg">Downloads are currently disabled.</span>
</div>

<div id="instructions" class="container patchContainer">
  <div class="row" style="margin-bottom: 0px">
    <div class="col-12">

      <h1>Installation Instructions</h1>

      <p>ReVanced Web Builder is a tool that injects patches into the official Android Apps (APKs) of apps like YouTube, YouTube Music, TikTok, Twitter, and Reddit to block advertisements and bring additional features to the apps.</p>
      <p>This is a frontend for the official <a href="https://github.com/revanced/revanced-cli" target="_blank">ReVanced CLI builder</a> and uses all official ReVanced tools.</p>

      <p>Because the APKs built with this web builder are modified versions of the official applications, their signatures will not match the official ones and you may get a couple warnings on your device while installing a ReVanced app.</p>

      <hr />

      <br /><h2>Install MicroG</h2>

      <p>To use the YouTube and YouTube Music apps, you will need to install Vanced MicroG. <a href="https://microg.org/" target="_blank">MicroG is a free and open-source implementation of proprietary Google libraries</a> used to safely sign into the modified apps.
      <p><strong>Non-YouTube apps do not need to install MicroG</strong>.</p>

      <p>Your browser will likely warn you that the file may be harmful because you are downloading a .apk file which is a direct installer similar to downloading a .exe file on Windows.</p>
      <p>Press <strong>Download Anyway</strong> to continue.</p>
      <p><img src="<?php echo $urlPrefix; ?>/app/assets/instructions/microg1.png" /></p>
      <p>Once MicroG is done downloading, open it and you will likely see this window if this is your first time installing an APK from your browser.</p>
      <p>If so, click on <strong>Settings</strong>.</p>
      <p><img src="<?php echo $urlPrefix; ?>/app/assets/instructions/microg2.png" /></p>
      <p>Enable <strong>Allow from this source</strong>.</p>
      <p><img src="<?php echo $urlPrefix; ?>/app/assets/instructions/microg3.png" /></p>
      <p>Press the <strong>Back</strong> button and you should see a window now allowing you to install MicroG.</p>
      <p><img src="<?php echo $urlPrefix; ?>/app/assets/instructions/microg4.png" /></p>

      <br /><h2>Install ReVanced Application</h2>

      <p>Once MicroG is done installing, go back to your browser to download and open the ReVanced application of your choice.</p>
      <p>When installing a ReVanced application, you may see a Play Protect warning that the developer of this app is not recognized.</p>
      <p>Press <strong>Install Anyway</strong> to continue.</p>
      <p><img src="<?php echo $urlPrefix; ?>/app/assets/instructions/revanced1.png" /></p>

      <p>During or after installation, you may see another window asking if you would like to send the app for a security scan.</p>
      <p>Press <strong>Don't Send</strong> and you're all ready to go with your ReVanced app!</p>
      <p><img src="<?php echo $urlPrefix; ?>/app/assets/instructions/revanced2.png" /></p>

      <h2>Disable Battery Optimizations</h2>

      <p>MicroG leaves a persistent notification letting you know that you should disable battery optimizations for the app so it can remain in the background.</p>

      <p><img src="<?php echo $urlPrefix; ?>/app/assets/instructions/battery1.png" /></p>
      <p><img src="<?php echo $urlPrefix; ?>/app/assets/instructions/battery2.png" /></p>
      <p><img src="<?php echo $urlPrefix; ?>/app/assets/instructions/battery3.png" /></p>
      <p><img src="<?php echo $urlPrefix; ?>/app/assets/instructions/battery4.png" /></p>

      <p><input type="button" class="instructionsToggle btn btn-primary" value="Back to Builder" /></p>
      <div id="instructionsClose"><input type="button" class="instructionsToggle btn btn-primary" value="Close Instructions" /></div>
    </div>
  </div>
</div>

<div id="header" class="container">
  <div class="row" style="margin-bottom: 0px">
    <div class="col-12">
      <h1>ReVanced Web Builder</h1>
    </div>
  </div>
</div>

<form id="patchesForm" action="app/build.php" method="post">

<div id="patchesGeneral" class="container">

  <div class="row my-3">

    <div id="generalApp" class="col-auto">
      <label class="form-label" for="appName"><strong>Application</strong></label>
      <select class="form-select" name="appName" id="appName">
      </select>
    </div>

    <div id="generalVersion" class="col-auto">
      <label class="form-label" for="appVersion"><strong>Version</strong></label>
      <select class="form-select" name="appVersion" id="appVersion">
      </select>
    </div>

  </div>

</div> <!-- end #patchesGeneral -->

<div id="generatePatches"></div>

<div id="buildNewContainer" class="container my-4 p-2 p-lg-4">

  <div id="buildNewRow" class="row">

    <div id="buildNew" class="col-md-12">
      <h3 style="margin-bottom: 7px">Package Build</h3>
      <p><span class="appName"></span> <span class="appVersion"></p>
      <p>Build ID: <span id="buildIDText"></span>&nbsp;&nbsp;<a class="buildSave" data-exists="0">Save</a></p>
      <p>You have created a build that doesn't currently exist!</p>
      <p>All unique builds are packaged as requested, and usually take about <span id="buildAverages"></span> to complete.</p>
      <p>Building will continue even if you leave this page.</p>
      <p class="d-block d-lg-none">Your mobile device will be prevented from falling asleep for the duration of the build process.</p>

      <div id="buildBusy" style="display: none">
        <p style="font-weight: bold; text-decoration: underline;">It appears that the builder is currently busy. This page will automatically check when the builder is ready again.</p>
        <p><input id="buildBusyButton" type="button" class="btn btn-warning" value="Waiting..." /></p>
      </div>

      <div id="buildReady">
        <p><input id="buildButton" type="submit" name="submitted" class="btn btn-primary" value="Build" /></p>
      </div>

      <p id="buildTime" style="display: none">Elapsed Time: <span id="buildTimeElapsed">0</span> seconds</p>
      <p id="buildError" style="display: none">ERROR: Sorry, it seems that the builder failed. </p>

      <input type="button" class="btn btn-secondary instructionsToggle" value="Install Instructions" />

    </div>

  </div>

  <div id="buildComplete" class="row" style="display: none">
    <div id="buildCompleteData" class="col-12"></div>
  </div>
</div>



<div id="myBuildsContainer" class="container">
  <div class="row">

    <div id="myBuilds" class="col-md-12">
      <h3 style="margin-bottom: 7px">My Builds</h3>
    </div>
    <div id="myBuildsData" class="col-md-12">

      <p><a id="myBuildsShowHidden" class="buildHiddenToggle btn btn-primary" style="display: none">Toggle Hidden</a></p>
    </div>
  </div>
</div>

<!--<div id="footer" style="margin-top: 150px;">Something about ReVanced project and licenses here</div>-->

<div id="debugMenuToggle" class="debugMenuToggle"></div>
<div id="debugMenu" class="" style="display: none">
  <span>Debug (<a class="debugMenuToggle">Hide</a>)</span><br />
  <label><input type="checkbox" id="debugShowJava" name="debugShowJava" value="1" /> Show Java command instead of building.</label><br />
  <a id="debugClearMyBuilds" class="underline">Clear My Builds</a>
</div>

</form>

<div id="footer" class="container mt-3">
  <div class="row">
    <hr />
    <div class="col-12 col-md-9 pt-1">
      <p>ReVanced Web Builder is not an official <a href="https://github.com/revanced" target="_blank">ReVanced Project</a>, but thankfully uses their open source tools to make this all happen!</p>
    </div>
    <div class="col-12 col-md-3" style="text-align: right">
      <!--<a href="https://twitter.com/revwebbuilder" target="_blank" class="me-2"><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 24 24" fill="currentcolor" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="ReVanced Web Builder Twitter"><path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm6.066 9.645c.183 4.04-2.83 8.544-8.164 8.544-1.622 0-3.131-.476-4.402-1.291 1.524.18 3.045-.244 4.252-1.189-1.256-.023-2.317-.854-2.684-1.995.451.086.895.061 1.298-.049-1.381-.278-2.335-1.522-2.304-2.853.388.215.83.344 1.301.359-1.279-.855-1.641-2.544-.889-3.835 1.416 1.738 3.533 2.881 5.92 3.001-.419-1.796.944-3.527 2.799-3.527.825 0 1.572.349 2.096.907.654-.128 1.27-.368 1.824-.697-.215.671-.67 1.233-1.263 1.589.581-.07 1.135-.224 1.649-.453-.384.578-.87 1.084-1.433 1.489z"/></svg></a> <a href="https://discord.com" target="_blank" class="me-2"><svg width="42" height="42" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" fill="currentcolor" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="ReVanced Web Builder Discord"><path d="M12 0c-6.626 0-12 5.372-12 12 0 6.627 5.374 12 12 12 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12zm3.248 18.348l-.371-1.295.896.833.847.784 1.505 1.33v-12.558c0-.798-.644-1.442-1.435-1.442h-9.38c-.791 0-1.435.644-1.435 1.442v9.464c0 .798.644 1.442 1.435 1.442h7.938zm-1.26-3.206l-.462-.567c.917-.259 1.267-.833 1.267-.833-.287.189-.56.322-.805.413-.35.147-.686.245-1.015.301-.672.126-1.288.091-1.813-.007-.399-.077-.742-.189-1.029-.301-.161-.063-.336-.14-.511-.238l-.028-.016-.007-.003-.028-.016-.028-.021-.196-.119s.336.56 1.225.826l-.469.581c-1.547-.049-2.135-1.064-2.135-1.064 0-2.254 1.008-4.081 1.008-4.081 1.008-.756 1.967-.735 1.967-.735l.07.084c-1.26.364-1.841.917-1.841.917l.413-.203c.749-.329 1.344-.42 1.589-.441l.119-.014c.427-.056.91-.07 1.414-.014.665.077 1.379.273 2.107.672 0 0-.553-.525-1.743-.889l.098-.112s.959-.021 1.967.735c0 0 1.008 1.827 1.008 4.081 0 0-.573.977-2.142 1.064zm-.7-3.269c-.399 0-.714.35-.714.777 0 .427.322.777.714.777.399 0 .714-.35.714-.777 0-.427-.315-.777-.714-.777zm-2.555 0c-.399 0-.714.35-.714.777 0 .427.322.777.714.777.399 0 .714-.35.714-.777.007-.427-.315-.777-.714-.777z"/></svg></a> -->
      <a href="https://github.com/revanced-web-builder/revanced-web-builder" target="_blank" class="me-2"><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" fill="currentcolor" viewBox="0 0 24 24" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="GitHub"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg></a> <a href="#" class=""><img src="<?php echo $urlPrefix; ?>/app/assets/icons/ak.webp" height="42"  data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Made in Alaska" /></a>
    </div>
  </div>
</div>

<div id="themeSwitcher" class="darkTheme" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="Change Theme"></div>

<!-- Modal -->
<div class="modal fade" id="rwbModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rwbModalTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div id="rwbModalContent" class="modal-body">
        ...
      </div>
      <div id="rwbModalFooter" class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo $urlPrefix; ?>/app/js/jquery-3.6.1.min.js"></script>
<script src="<?php echo $urlPrefix; ?>/app/js/popper.min.js"></script>
<script src="<?php echo $urlPrefix; ?>/app/js/bootstrap.min.js"></script>
<script src="<?php echo $urlPrefix; ?>/app/js/md5.min.js"></script>
<script src="<?php echo $urlPrefix; ?>/app/js/NoSleep.min.js"></script>
<script src="<?php echo $urlPrefix; ?>/app/js/jquery.deserialize.js"></script>
<script src="<?php echo $urlPrefix; ?>/app/js/builder.js"></script>
