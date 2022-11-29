var noSleep = new NoSleep() // NoSleep to prevent phone from falling asleep while building

// Get appdata.json, then get config.json, then start things up
var configAll
var appData
var config
var href = location.href; //returns the entire url
var urlPrefix = href.substr(-4) // check last 4 chars of url
if (urlPrefix != ".php" && urlPrefix != "app/" && urlPrefix != "/app") { // mod_rewritten
  urlPrefix = "app/"
} else {
  urlPrefix = "" // no mod_rewrite
}

$.ajax({
  dataType: "json",
  type: "GET",
  url: urlPrefix+"config.json",
  cache: false
}).done(function (data, textStatus, errorThrown) {

  configAll = data
  config = data['config']
  appData = data['apps']
  themeData = data['themes']

  if (config.admin == "") window.location = urlPrefix+"admin.php" // go to admin panel if password isn't setup yet

  config.checkinInterval *= 1000 // Multiply checkin interval by 1000 to convert seconds to milliseconds

  startup()

}).fail(function (jqXHR, textStatus, errorThrown) {
  console.log("CONFIG Not Found")
  window.location = urlPrefix+"admin.php"
})


$(document).ready(function() {
  $('[data-bs-toggle="tooltip"]').tooltip({trigger: "hover"}) // enable tooltips
})


function startup() {

  // First loop should be the name of each app
  for (appNames in appData) {

    var prefix = appPrefix(appNames) // App prefix such as YouTube == yt
    var appName = appNames

    // Check if app has a full name
    // This code may need to be deprecated
    if (appData[appNames]['fullName']) {
      var appName = appData[appNames]['fullName']
    }

    var bit64Only = (appData[appNames]['64bit']) ? 1 : 0 // Check if app is 64-bit only

    // Add App to Applications select box
    $("#appName").append("<option id='option"+prefix+"' value='"+appNames+"' data-bit64='"+bit64Only+"'>"+appName+"</option>")

    // Load versions into select box
    for (ver in appData[appNames]['versions']) {
      if (appData[appNames]['versions'][ver]['enabled'] != 1) continue // don't show versions that aren't enabled/downloaded
      if (appData[appNames]['versions'][ver]['support'] == 0 && config.buildUnsupported != 1) continue  // don't show unsupported versions (unless enabled)
      var isBeta = (appData[appNames]['versions'][ver]['beta'] == 1) ? " Beta":""
      var isUnsupported = (appData[appNames]['versions'][ver]['support'] == 0) ? " (Unsupported)":""
      var isDefault = (appData[appNames]['versions'][ver]['rec'] == 1) ? 1:0
      $("#appVersion").append("<option value='"+ver+isBeta+"' data-app='"+appNames+"' data-isdefault='"+isDefault+"'>"+ver+isBeta+isUnsupported+"</option>")
    }

    var appNameDiv = appName.replace(/\s+/g, '') // Remove spaces from appName for the DIV IDs

    // Create div for this app
    var mainDiv = `
    <!-- `+appNames+` patches -->
    <div id="patches`+appNameDiv+`" class="container patchContainer">
    <div class="row patchRow"></div>
    </div>
    `
    $("#generatePatches").append(mainDiv)

    var patchDivs = ""

    // Loop through the "patches" part of the object to get the categories
    for (patch in appData[appNames]['patches']) {

      var thisPatch = appData[appNames]['patches'][patch]

      // Check if this section already exists
      if ($("#section"+appNameDiv+thisPatch.section).is(":visible") != true) {
        var patchDiv = `
        <div class="row patchSection" id="section`+appNameDiv+thisPatch.section+`">
          <div class="col-12 mt-3">
            <h3>`+thisPatch.section+` <input type="button" value="Select All" class="btn btn-primary btn-sm selectButton" /> <input type="button" value="Select None" class="btn btn-primary btn-sm selectButton" /></h3>
          </div>
        </div>
        `
        $("#patches"+appNameDiv).find(".patchRow").append(patchDiv)
      }

      var isChecked = (thisPatch.checked == 1) ? "checked":""
      patchDivs += `
      <div class="patch col-12 col-md-6" data-versions="`+thisPatch.versions+`">
      <input type="checkbox" name="patches_`+appNameDiv+`[]" value="`+patch+`" `+isChecked+` /> <strong>`+thisPatch.name+`</strong><br />
      `+thisPatch.desc+`
      </div>
      `

      $("#section"+appNameDiv+thisPatch.section).append(patchDivs)
      patchDivs = ""

      // Add stats of this app to the page
      var avgs = (appData[appNames]['stats']) ? appData[appNames]['stats']['avg']+" Seconds" : "an unknown amount of time (no builds have been made)"
      $("#buildAverages").after('<strong id="buildAvg'+appNameDiv+'" class="buildAvg" style="display: none"> '+avgs+'</strong>')

    }

    // Remove app entirely if no versions for it are enabled
    var versionsVisible = $("#appVersion option[data-app='"+appNames+"']").length
    if (versionsVisible <= 0) $("#appName option[value='"+appNames+"'], #patches"+appNameDiv).remove()



  }

  // If no apps are enabled, redirect to admin panel.
  if ($("#appName option").length == 0) {
    window.location = urlPrefix+"admin.php"
  }

  $("#buildAvg"+$("#appName").val()).show() // Show the build time of the currently selected App
  $("#appVersion option[data-app!='YouTube']").hide() // hide all apps that aren't YouTube
  $("#appVersion option[data-app='YouTube'][data-isdefault='1']").prop("selected", true) // show the default YouTube app

  $(".patchContainer:gt(1)").hide() // Hide all containers after YouTube

  // Create an empty object for myBuilds if doesn't exist
  if (!localStorage.myBuilds) localStorage.set("myBuilds", {})

  // Set page title
  $("title").text(config.pageTitle)

  // Theme Switcher, Default Theme, and Debug Menu
  themeSet(config.themeDefault) // theme the page
  //if (data.themeDefault != "dark" || (localStorage.themeOverride && localStorage.themeOverride == "light")) themeToggle() // Toggle theme to light if themeDefault isn't dark
  if (config.themeSwitcher == 1) $("#themeSwitcher").show() // Show theme switcher?
  if (config.debugMenu == 0) $("#debugMenu,#debugMenuToggle").remove() // Remove debugMenu if it's not enabled
  if (config.footer == 0) $("#footer").hide() // Hide footer based on config

  // If builder is online, set interval for checkIn() to repeatedly see if the builder is busy
  if (config.buildEnabled == 1 && config.checkinInterval != 0) window.autoCheckIn = setInterval("checkIn()", config.checkinInterval)

  // If builder is offline or downloads are disabled, show the top banner
  if (config.buildEnabled != 1 || config.downloads != 1) {
    if (config.buildEnabled != 1) $("#buildOfflineMsg").show()
    if (config.downloads != 1) $("#downloadsOfflineMsg").show()
    $("#buildOffline").slideDown()
    $("#buildButton").val("Builder Offline").removeClass("btn-primary").addClass("btn-danger").attr({"type":"button"})
  }

  // Get URL hash to check if we need to auto-build a build
  if (location.hash != "") {
    var urlHash = location.hash.substr(1) // remove #
    if (urlHash != "") buildSetup(urlHash)
  } else {
    setTimeout("checkBuildID()") // check if default build ID exists
  }

  // Get user's current builds and their status
  var myBuilds = localStorage.get("myBuilds")
  for (b in myBuilds) {
    var app = appPrefix(b.substr(0,2), 1) // reverse search the prefix to get app title
    var ver = myBuilds[b]['version']
    var stat = myBuilds[b]['status']

    // Show the View button or Status message
    if (stat == "Success") {
      var status = "<a href='#"+b+"' class='buildSetup'>View</a>"
    } else if (stat == "Saved") {
      var status = "<a href='#"+b+"' class='buildDeserialize'>View</a>"
    } else if (stat == "Failed") {
      var status = "<span class='me-3' title='"+myBuilds[b]['error']+"'>Failed</span><a href='#"+b+"' class='buildDeserialize'>View</a>"
    } else {
      var status = myBuilds[b]['status']
    }

    // Hide build if necessary
    if (myBuilds[b]['hidden'] == 1) {
      var hidden = "buildHidden"
      var statusButton1 = "myBuildDelete"
      var statusButton2 = "Delete"
      $("#myBuildsShowHidden").show() // show the Toggle Hidden  button if one build was hidden
    } else {
      var hidden = ""
      var statusButton1 = "myBuildHide"
      var statusButton2 = "Hide"
    }

    $("#myBuildsData").prepend("<p id='myBuild"+b+"' class='myBuild "+hidden+"'>"+app+" "+ver+" [<span class='myBuildID'>"+b+"</span>]<span class='myBuildStatus ms-3'>"+status+"</span><a class='"+statusButton1+" ms-3' data-build='"+b+"'>"+statusButton2+"</a></p>")

    // Check status of build if it was last Building
    if (myBuilds[b]['status'] == "Building") {
      checkBuildID(b, true) // check if build is finished (true makes it so it doesn't update the page)
    }

  }

  if ($("p.myBuild").length == 0) $("#myBuildsContainer").hide()

  // Show Select All/None buttons when hovering over a category
  $("div.patchSection").on({
    mouseenter: function () {
      $(this).find("input.selectButton").show()
    },
    mouseleave: function () {
      $(this).find("input.selectButton").hide()
    }
  });

}


function themeSet(theme=undefined) {

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



// Set up the page for a specified build (usually on first page load)
function buildSetup(urlHash) {

  // Check which app they requested
  var prefix = urlHash.substr(0,2)
  var appName = appPrefix(prefix, 1) // reverse lookup prefix -> appname
  var appNameEncoded = appName.replace(/\s/g, '.') // Replace spaces with periods for filenames

  var buildSuffix = (config.buildSuffix != "") ? " "+config.buildSuffix : ""
  var buildDirPrefix = (urlPrefix == "") ? "../" : "" // build directory is one directory back if not mod_rewritten

  $.ajax({
    type: "GET",
    url: buildDirPrefix+config.buildDirectory+"/"+appNameEncoded+buildSuffix+"-"+urlHash+".info.txt",
    cache: false
  }).done(function (data, textStatus, errorThrown) {

    data = JSON.parse(data)
    var patches = data.patches
    // Clear all input boxes on the page and only check the ones with listed patches for this build
    patches = patches.split("|") // separate all the patches into an array

    $("#appName").val(data.app)
    $("#appName").trigger("change")
    $("#appVersion").val(data.version)

    $("input[type='checkbox']").prop({checked: false})
    for (x in patches) {
      $("input[value='"+patches[x]+"']").prop({checked: true})
    }

    setTimeout("checkBuildID()", 350)

  }).fail(function (jqXHR, textStatus, errorThrown) {
    console.log("Build Not Found")
  })
}

function buildStart() {

  if ($("#buildButton").val() == "Building...") return false // Don't let user click this button twice (it'll be disabled too)

  var dataString = $("#patchesForm").serialize();
  var thisBuild = $("#buildIDText").text()

  window.buildAjax = $.ajax({
    type: "POST",
    url: urlPrefix+"build.php",
    data: dataString,
    beforeSend: function() {
      $("#buildError").slideUp()
      $("#buildButton").val("Building...").prop({disabled: true})
      $("#buildTime").slideDown()
      noSleep.enable() // Prevent device from sleeping while building
      window.countID = setInterval("countUp()", 1000)
      clearInterval(window.autoCheckIn) // stop checking in while building

      // Add (or update) this build to myBuilds
      var tstamp = Math.round((new Date()).getTime() / 1000);
      var myBuilds = localStorage.get("myBuilds")
      myBuilds[thisBuild] = {"app": $('#appName').val(), "version": $('#appVersion').val(), "status": "Building", "timestamp": tstamp, "serial": dataString}
      localStorage.assign("myBuilds", myBuilds)

      // Delete existing build from My Builds (in case it was a saved build) and add this one to the top
      $("#myBuild"+thisBuild).remove()

      $("#myBuildsData").prepend("<p id='myBuild"+thisBuild+"'>"+$('#appName').val()+" "+$('#appVersion').val()+" [<span class='myBuildID'>"+thisBuild+"</span>]<span class='myBuildStatus ms-3'>Building...</span></p>")
    },
    success: function (msg) {

      // Check if returned msg was an object (which means it worked) or a string, which would be an error message
      if(msg) {

        try {
          data = JSON.parse(msg);

          buildCompleteMessage(data)

          $("#buildNew").slideUp()
          $("#buildComplete").slideDown()
          countStop()
          $("#buildButton").val("Build").prop({disabled: false})

          if (config.checkinInterval != 0) window.autoCheckIn = setInterval("checkIn()", config.checkinInterval) // Start Check if builder is busy every 5 seconds

          // Let user know this build succeeded
          $("#myBuild"+thisBuild).find(".myBuildStatus").html("<a href='#"+thisBuild+"' class='buildSetup'>View</a> <a class='myBuildHide ms-3' data-build='"+thisBuild+"'>Hide</a>")
          var myBuilds = localStorage.get("myBuilds")
          myBuilds[thisBuild]['status'] = "Success"
          localStorage.assign("myBuilds", myBuilds)

        } catch(e) {

          console.log("ERROR: "+msg)

          if (msg == "ERROR:CRASH") {
            var errMsg = "The builder broke... Try again in a few minutes."
            $("#buildError").slideDown()
          } else if (msg == "ERROR:DISABLED") {
            var errMsg = "Building "+$('#appName').val()+" "+$("#appVersion").val()+" is currently disabled..."
            alert(errMsg)
          } else if (msg == "ERROR:UNSUPPORTED") {
            var errMsg = "Building unsupported app "+$('#appName').val()+" "+$("#appVersion").val()+" is currently disabled..."
            alert(errMsg)
          } else if (msg == "ERROR:FLOOD") {
            var errMsg = "Someone else is already buiding... Try again in a few minutes." // They should not be seeing these next few errors at all...
          } else if (msg == "ERROR:EXISTS") {
            var errMsg = "This build already exists..." // ...
          } else if (msg == "ERROR:INVALIDAPP") {
            var errMsg = "You submitted an invalid Application..." // ...
          } else if (msg == "ERROR:NOPATCH") {
            var errMsg = "You selected a patch that doesn't exist..." // ...
          } else {
            console.log(msg)
          }

          // Let user know this build failed
          $("#myBuild"+thisBuild).find(".myBuildStatus").text("Failed").attr("title", errMsg)
          var myBuilds = localStorage.get("myBuilds")
          myBuilds[thisBuild]['status'] = "Failed"
          myBuilds[thisBuild]['error'] = errMsg
          localStorage.assign("myBuilds", myBuilds)

          countStop() // Stop/reset the elapsed time
          clearInterval(window.checkin) // stop checking in
          $("#buildButton").val("Build").prop({disabled: false})
          if (config.checkinInterval != 0) window.autoCheckIn = setInterval("checkIn()", config.checkinInterval) // Start Check if builder is busy every 5 seconds

          return false

        }
      }

    }
  });

  // cancel request after 5 second
  /*  setTimeout(function() {
  window.buildAjax.abort();
  alert("Request canceled.");
}, 4000);*/

}

// Gather serialized data from unfinished build in localStorage.myBuilds and rebuild the patches form
function buildDeserialize(button) {

  // Get serialized data of requested build
  var buildID = $(button).attr("href").substr(1)
  var myBuilds = localStorage.get("myBuilds")
  var serial = myBuilds[buildID]['serial']
  $("#patchesForm").trigger("reset")
  $("#patchesForm").deserialize(serial)
  checkBuildID()

}



// Check in to the builder to see if it's available or busy
function checkIn() {

  $.ajax({
    type: "GET",
    url: urlPrefix+"build.php?q=checkin",
    success: function (isBusy) {
      if (isBusy == 1) { // Builder is busy. Disable Build button.
        $("#buildButton").prop({disabled: true})
        $("#buildReady").hide()
        $("#buildBusy").show()
      } else {
        $("#buildBusy").hide()
        $("#buildReady").show()
        $("#buildButton").prop({disabled: false})
      }

      // Check user's build status if the last check was busy and it no longer is
      if (window.lastCheckin == 1 && isBusy == 0) checkMyBuilds()
      window.lastCheckin = isBusy // store the last checkin status
    }
  });

}

function checkMyBuilds() {
  // Get user's current builds and check their current status if it's "Building"
  var myBuilds = localStorage.get("myBuilds")
  for (b in myBuilds) {
    if (myBuilds[b]['status'] == "Building") checkBuildID(b, true) // check if build is finished (true makes it so it doesn't update the page)
  }
}

// When Application Name is changed, re-arrange the page to show patches for that app
function appChange(element=undefined) {

  var appName = $("#appName").val() // Selected Application
  var appNameDiv = appName.replace(/\s+/g, '') // Remove spaces from appName for the DIV IDs

  // Hide all versions and show only ones for this app
  $("#appVersion option[data-app!='"+appName+"']").hide() // hide all app versions that aren't this one
  $("#appVersion option[data-app='"+appName+"']").show().prop("selected", true) // show all versions that aren't this one
  $("#appVersion option[data-app='"+appName+"'][data-isdefault='1']").prop("selected", true) // show the default app

  // Show average build time for this app
  $("strong.buildAvg").hide()
  $("#buildAvg"+appNameDiv).show() // Show the build time of the currently selected App

  $("div.patchContainer").slideUp()
  $("#patches"+appNameDiv).slideDown()

  // Show appName and Version in Package Build section
  $(".appName").text($("#appName option:selected").text())
  $(".appVersion").text($("#appVersion").val())

  // Show/hide the "64-bit Only" box
  if (appName != "YouTube" && $("#appName option:selected").data("bit64")) {
    $("#general64Bit").show()
  } else {
    $("#general64Bit").hide()
  }

  checkBuildID()

}


// Check if a current build already exists
// updateStatus will update the myBuilds section showing if the build succeeded instead of updating the page
function checkBuildID(buildID=undefined, updateStatus=undefined) {

  var buildString = ""
  var appName = $("#appName").val()
  var appNameDiv = appName.replace(/\s+/g, '') // Remove spaces from appName for the DIV IDs
  var appNameEncoded = appName.replace(/\s/g, '.') // Replace spaces with periods for filenames
  var appVersion = $("#appVersion").val()
  var buildString = appName+appVersion
  var buildSuffix = (config.buildSuffix != "") ? " "+config.buildSuffix : ""

  $("#patches"+appNameDiv+" div.patch").show() // Show all patches that may be hidden
  // Hide all patches that don't belong to this version
  $("#patches"+appNameDiv+" div.patch:not([data-versions*='"+appVersion+"'])").each(function(key,val) {
    // Only hide if it's a patch that has supported versions (otherwise it supports all versions)
    if ($(this).data("versions") != "") $(this).hide()
  })

  // Loop through the patches of the currently selected App that are checked
  $("#patches"+appNameDiv+" input[type='checkbox']:checked").each(function(index, value) {
    buildString = buildString+$(this).val()
  })

  $("#patches"+appNameDiv+" .patchOption").each(function(index, value) {
    var patchID = $(this).attr("id")
    var patchVal = $(this).val()
    buildString = buildString+patchID+"="+patchVal
  })
  console.log(buildString)
  // Get Prefix of App for build Build ID URL
  var buildPrefix = appPrefix(appName)

  // Create the buildID out of the currently selected patches if one isn't requested.
  buildID = (buildID == undefined) ? buildPrefix+md5(buildString).substr(0, config.buildIDLength-2) : buildID

  if (updateStatus == undefined) {
    $("#buildIDText").text(buildID)
    $("#buildIDCheck").slideDown()
  }

  var buildDirPrefix = (urlPrefix == "") ? "../" : "" // build directory is one directory back if not mod_rewritten

  var checkBuild = $.ajax({
    type: "GET",
    url: buildDirPrefix+config.buildDirectory+"/"+appNameEncoded+buildSuffix+"-"+buildID+".info.txt",
    cache: false
  }).done(function (data, textStatus, errorThrown) {
    // only update the page if updateStatus is false
    if (updateStatus == undefined) {

      buildCompleteMessage(JSON.parse(data))
      $("#buildCompleteData").slideDown()
      $("#buildNew").slideUp()

    } else {
      // If this is one of the user's builds, update the status
      if ($("#myBuild"+buildID).is(":visible")) {
        var myBuilds = localStorage.get("myBuilds")
        myBuilds[buildID]['status'] = "Success"
        $("#myBuild"+buildID).find(".myBuildStatus").html("<a href='#"+buildID+"' class='buildSetup'>View</a>")
        localStorage.assign("myBuilds", myBuilds)
      }
    }
  }).fail(function (jqXHR, textStatus, errorThrown) {
    if (updateStatus == undefined) {
      $("#buildCompleteData").empty()
      $("#buildNew").slideDown()
    }
  })

  $("#buildIDCheck").hide()

}

// Save current build to "My Builds". Requires clicked $(.buildSave) to be passed
function buildSave(button) {

  var buildID = $("#buildIDText").text()
  var app = $("#appName").val()
  var ver = $("#appVersion").val()

  // Check if this build already exists
  var exists = $(button).data("exists")

  if ($("#myBuild"+buildID).is(":visible")) return false

  $("#myBuildsContainer").slideDown()

  // If build already exists, add the link to My Builds if it's not already in there
  if (exists == 1) {

    $("#myBuildsData").prepend("<p id='myBuild"+buildID+"'>"+app+" [<span class='myBuildID'>"+buildID+"</span>]<span class='myBuildStatus ms-3'><a href='#"+buildID+"'>View</a></span><a class='myBuildHide ms-3' data-build='"+buildID+"'>Hide</a></p>")
    var myBuilds = localStorage.get("myBuilds")
    myBuilds[buildID] = {"app": app, "version": ver, "status":"Success"}
    localStorage.assign("myBuilds", myBuilds)

  } else {

    // If build doesn't exist, serialize the current form and save it to the build info in localStorage
    $("#myBuildsData").prepend("<p id='myBuild"+buildID+"'>"+app+" "+ver+" [<span class='myBuildID'>"+buildID+"</span>]<span class='myBuildStatus ms-3'><a href='#"+buildID+"' class='buildDeserialize'>View</a></span> <a class='myBuildHide ms-3' data-build='"+buildID+"'>Hide</a></p>")
    var serial = $("#patchesForm").serialize()
    var myBuilds = localStorage.get("myBuilds")
    myBuilds[buildID] = {"app": app, "version": ver, "status":"Saved", "serial": serial}
    localStorage.assign("myBuilds", myBuilds)
  }

}

// Hide build from MyBuilds
function buildHide(element) {

  var build = $(element).data("build") // get buildID from element clicked

  // Set build to Hidden in My Builds (it can be shown again later, probably in the debug panel for now)
  var myBuilds = localStorage.get("myBuilds")
  myBuilds[build]['hidden'] = 1
  localStorage.assign("myBuilds", myBuilds)
  $("#myBuild"+build).slideUp(function(e) {
    $(this).addClass("buildHidden")

    // Turn "Hide" button into "Delete"
    $(element).addClass("myBuildDelete").removeClass("myBuildHide").text("Delete")
  })

  $("#myBuildsShowHidden").slideDown() // Reveal the "Toggle Hidden" button

}

// Delete build from MyBuilds (once it's already hidden)
// THIS DOES NOT DELETE THE ACTUAL APK. It only removes from the user's localStorage
function buildDelete(element) {

  var build = $(element).data("build") // get buildID from element clicked

  // Set build to Hidden in My Builds (it can be shown again later, probably in the debug panel for now)
  var myBuilds = localStorage.get("myBuilds")
  myBuilds[build] = undefined
  localStorage.assign("myBuilds", myBuilds)

  // Hide the build and then remove it from DOM
  $("#myBuild"+build).slideUp(function(e) { $(this).remove() })

}

function checkOptions() {
  // custom-branding (YouTube)
  var $branding = $("input[value='custom-branding']")
  if ($branding.prop("checked") == true) {
    if ($(".custom-brandingOption").length == 2) {
      $(".custom-brandingOption").slideDown()
    } else {
      $($branding).parent().append('<p class="custom-brandingOption mt-2" style="display: none">Note: YouTube App icon will be changed to ReVanced\'s.</p><p class="custom-brandingOption mt-2" style="display: none">App Name: <input type="text" class="btn-input patchOption" value="YouTube ReVanced" id="custom-branding-appname" name="custom-branding-appname" /></p>')
      $(".custom-brandingOption").slideDown()
    }
  } else {
    $(".custom-brandingOption").slideUp()
  }

  // theme (YouTube)
  var $branding = $("input[value='theme']")
  if ($branding.prop("checked") == true) {
    if ($(".themeOption").length == 2) {
      $(".themeOption").slideDown()
    } else {
      $($branding).parent().append('<p class="themeOption mt-2" style="display: none">Dark Background: <input type="color" class="btn-input ms-2 patchOption" value="#000000" id="theme-bg-dark" name="theme-bg-dark" /></p><p class="themeOption mt-2" style="display: none">Light Background: <input type="color" class="btn-input ms-2 patchOption" value="#FFFFFF" id="theme-bg-light" name="theme-bg-light" /></p>')
      $(".themeOption").slideDown()
    }
  } else {
    $(".themeOption").slideUp()
  }

  // custom-playback-speed (YouTube)
  var $branding = $("input[value='custom-playback-speed']")
  if ($branding.prop("checked") == true) {
    if ($(".custom-playback-speedOption").length == 3) {
      $(".custom-playback-speedOption").slideDown()
    } else {
      $($branding).parent().append('<p class="custom-playback-speedOption mt-2" style="display: none">Granularity: <input type="text" class="btn-input ms-2 patchOption" value="16" id="playback-speed-granularity" name="playback-speed-granularity" size="4" /></p><p class="custom-playback-speedOption mt-2" style="display: none">Min: <input type="text" class="btn-input ms-2 patchOption" value="0.25" id="playback-speed-min" name="playback-speed-min" size="4" /></p><p class="custom-playback-speedOption mt-2" style="display: none">Max: <input type="text" class="btn-input ms-2 patchOption" value="5.0" size="4" id="playback-speed-max" name="playback-speed-max" /></p>')
      $(".custom-playback-speedOption").slideDown()
    }
  } else {
    $(".custom-playback-speedOption").slideUp()
  }

  // spotify-theme
  var $branding = $("input[value='spotify-theme']")
  if ($branding.prop("checked") == true) {
    if ($(".spotify-themeOption").length == 3) {
      $(".spotify-themeOption").slideDown()
    } else {
      $($branding).parent().append('<p class="spotify-themeOption mt-2" style="display: none">Background: <input type="color" class="btn-input ms-2 patchOption" value="#000000" id="spotify-theme-bg" name="spotify-theme-bg" /></p><p class="spotify-themeOption mt-2" style="display: none">Accent: <input type="color" class="btn-input ms-2 patchOption" value="#ff1ed7" id="spotify-theme-accent" name="spotify-theme-accent" /></p><p class="spotify-themeOption mt-2" style="display: none">Accent Pressed: <input type="color" class="btn-input ms-2 patchOption" value="#ff169c" id="spotify-theme-accent2" name="spotify-theme-accent2" /></p>')
      $(".spotify-themeOption").slideDown()
    }
  } else {
    $(".spotify-themeOption").slideUp()
  }
}


// EVENTS

$(document).on("click", "#instructDownloads", function(e) {

  $("#rwbModalTitle").html("Setup Downloads")
  $("#rwbModalContent").html(`
    <p>Downloads requires a separate program that ReVanced will bring up from within YouTube.</p>
    <p>You can choose between <a href='https://newpipe.net/' target='_blank'>NewPipe</a> or <a href='https://github.com/razar-dev/PowerTube/releases' target='_blank'>PowerTube</a></p>
    <p>If YouTube ReVanced auto-detected the wrong app, you'll have to manually set it in the ReVanced Settings.</p>
    <p>Click your avatar in the top right of the YouTube app.</p>
    <p>Click Settings >> ReVanced >> Interaction >> Download Settings >> Downloader Package Name</p>
    <p>Set to the correct package name</p>
    <p>NewPipe: <strong>org.schabi.newpipe</strong></p>
    <p>PowerTube: <strong>ussr.razar.youtube_dl</strong></p>
  `)
  $("#rwbModal").modal("show")

})

// When Application Name is changed, show which versions of that App are supported
$(document).on("change", "#appName", function(e) { appChange($(this)) })
$(document).on("change", "#appVersion", function(e) { $(".appVersion").text($("#appVersion").val()) })

// Check new BuildID for each input changed
$(document).on("change", "input, select", function(e) {
  $("#buildCompleteData").empty()
  checkBuildID()
})
$(document).on("click", ".selectButton", function(e) {
  $("#buildCompleteData").empty()
  checkBuildID()
})

// Start build when patch form has been submit
$(document).on( "submit", "#patchesForm", function(e) {
  buildStart()
  e.preventDefault();
})


function countUp() {
  var currentTime = $("#buildTimeElapsed").text()
  currentTime++
  $("#buildTimeElapsed").text(currentTime)
}
function countStop() {
  clearInterval(window.countID)
  $("#buildTimeElapsed").text("0")
}

function buildCompleteMessage(data) {

  var patches = data.patches
  patches = patches.replaceAll("|", ", ") // Replace all | with , to look more nice

  var rootDir = window.location.href
  rootDir = rootDir.split("#")[0]
  var buildDir = config.buildDirectory
  var buildSuffix = (config.buildSuffix != "") ? " "+config.buildSuffix : ""
  var buildSize = Math.round(data.buildSize / 1000 / 1000)

  var successMsg = `
  <h3 style="margin-bottom: 10px">Download</h3>
  <p>App: `+data.app+` `+data.version+`</p>
  <p>Build ID: <a href="`+rootDir+`#`+data.id+`">`+data.id+`</a>&nbsp;&nbsp;<a id="buildInfo" href="#">Info</a>&nbsp;&nbsp;<a class="buildSave" data-exists="1">Save</a></p>
  <p class="buildInfo">Build MD5: `+data.md5+`</p>
  <p class="buildInfo">Build Duration: `+data.buildDuration+` seconds</p>
  <p class="buildInfo">Build Date: `+data.buildDateFull+`</p>
  <p class="buildInfo">Build Size: `+data.buildSize+` bytes (`+buildSize+` MB)</p>
  <p class="buildInfo">Keystore: <a href="`+rootDir+buildDir+`/RWB-`+data.app+`.keystore">Download</a></p>`
  if (data.options.length != 0) {
    successMsg += `<p class="buildInfo">Options: <a href="`+rootDir+buildDir+`/`+data.app+buildSuffix+`-`+data.id+`.options.txt" target="_blank">View</a></p>`
  }
  successMsg += `<p class="buildInfo">JSON: <a href="`+rootDir+buildDir+`/`+data.app+buildSuffix+`-`+data.id+`.info.txt" target="_blank">View</a></p>
  <p>Patches: `+patches+`</p>`

  if (data.microG != "") {
    successMsg += `<p><a href='`+rootDir+buildDir+`/`+data.microG+`'><input type='button' value='Download MicroG' class='btn btn-primary' /></a> <input id='microgInfoToggle' type='button' value='?' class='btn btn-secondary' title='About MicroG' /></p>
    <p id='microgInfo' style='display: none'>&uarr; To use the YouTube and YouTube Music apps, you will need to install Vanced MicroG. <a href="https://microg.org/" target="_blank">MicroG is a free and open-source implementation of proprietary Google libraries</a> used to safely sign into the modified apps.</p>`
  }

  successMsg += `<p><a href='`+rootDir+buildDir+`/`+data.url+`'><input type='button' value='Download `+data.app+` ReVanced' class='btn btn-primary' /></a></p>`
  successMsg += `<input type="button" class="btn btn-secondary instructionsToggle" value="Install Instructions" />`
  $("#buildCompleteData").html(successMsg)
  $("#buildComplete").slideDown()

}

function instructionsToggle() {

  if ($("#instructions").is(":visible")) {
    $("#header,#patchesForm").slideDown()
    $("#instructions").slideUp()
    $('html, body').animate({scrollTop:$(document).height()}, 'slow') // scroll to bottom of page
  } else {
    $("#instructions").slideDown()
    $("#header,#patchesForm").slideUp()
  }

}

// Get the prefix for an application name (or reverse it to get the application name of a prefix)
function appPrefix(app, reverse=undefined) {
  var prefixes = {"YouTube": "yt", "YouTube Music": "ym", "Citra Emulator":"ci", "Crunchyroll": "cr", "Reddit": "re", "Spotify": "sp", "TikTok": "tt", "Twitch": "tc", "Twitter": "tw", "IconPackStudio":"ip", "Pflotsh": "pf", "WarnWetter": "ww", "HexEditor": "he", "My Expenses": "my", "Nyx Music": "nx"}
  return (reverse == undefined || reverse != 1) ? prefixes[app] : getObjKey(prefixes, app)
}

// Get object key by value
function getObjKey(obj, value) {
  return Object.keys(obj).find(key => obj[key] === value);
}

// Select All/None
$(document).on("click", ".selectButton", function(e) {

  var parent = $(this).parent().parent().parent() // Get which section this is part of
  var isChecked = ($(this).val() == "Select All") ? true : false
  $(parent).find("input").prop({checked: isChecked})
  setTimeout("checkBuildID()", 250)
  checkOptions()

})

// When a column/patch is clicked, check/uncheck the box instead of using a label
$(document).on("click", ".col-md-6", function(e) {

  if (e.target.type != "checkbox" && e.target.type != "text" && e.target.type != "color" && e.target.type != "number") { // Ignore this if the checkbox or textbox was clicked
    var input = $(this).find("input")
    if ($(input).prop("checked") == true) {
      $(input).prop({checked: false})
    } else {
      $(input).prop({checked: true})
    }
  }

  checkBuildID()

  checkOptions()

})

// When a buildSetup link is clicked, get the hash from the href and build it on the patch form
$(document).on("click", ".buildSetup", function(e) {

  // Get URL hash to check which build to show
  var urlHash = $(this).attr("href").substr(1)
  if (urlHash != "") buildSetup(urlHash)
  e.preventDefault()

})


// EVENTS
$(document).on("click", "#themeSwitcher", function(e) { themeSet() })
$(document).on("click", "#buildInfo", function(e) { e.preventDefault(); $("p.buildInfo").slideToggle() })
$(document).on("click", ".buildSave", function(e) { buildSave($(this)) })
$(document).on("click", ".buildDeserialize", function(e) { buildDeserialize($(this)) })
$(document).on("click", ".instructionsToggle", function(e) { instructionsToggle() })
$(document).on("click", "#microgInfoToggle", function(e) { $("#microgInfo").slideToggle() })
$(document).on("click", ".myBuildHide", function(e) { buildHide($(this)) })
$(document).on("click", ".myBuildDelete", function(e) { buildDelete($(this)) })
$(document).on("click", ".buildHiddenToggle", function(e) { $(".buildHidden").slideToggle() }) // Toggle Hidden "My Builds"
$(document).on("change keyup", ".patchOption", function(e) { checkBuildID() })

// Show Debug Menu
$(document).on("click", ".debugMenuToggle", function(e) {

  if ($("#debugMenuToggle").is(":visible")) {
    $("#debugMenuToggle").fadeOut()
    $("#debugMenu").fadeIn()
  } else {
    $("#debugMenu").fadeOut()
    $("#debugMenuToggle").fadeIn()
  }

})

// Clear all builds from My Builds and refresh
$(document).on("click", "#debugClearMyBuilds", function(e) {
  localStorage.set("myBuilds", {})
  location.reload()
})

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

// Extend localStorage to easily store/retrieve arrays and json
// Thanks to https://github.com/zevero/simpleWebstorage
'use strict';

Storage.prototype.set = function(key, obj) {
  var t = typeof obj;
  if (t==='undefined' || obj===null ) this.removeItem(key);
  this.setItem(key, (t==='object')?JSON.stringify(obj):obj);
  return obj;
};
Storage.prototype.get = function(key) {
  var obj = this.getItem(key);
  try {
    var j = JSON.parse(obj);
    if (j && typeof j === "object") return j;
  } catch (e) { }
  return obj;
};
Storage.prototype.assign = function(key, obj_merge) {
  var obj = this.get(key);
  if (typeof obj !== "object" || typeof obj_merge !== "object") return null;
  Object.assign(obj, obj_merge);
  return this.set(key,obj);
};

// Remove all localStorage keys that start with a certain Prefix. THIS IS DANGEROUS!
Storage.prototype.clearPrefix = function(prefix) {

  var prefixLength = prefix.length

  var arr = []; // Array to hold the keys
  // Iterate over localStorage and insert the keys that meet the condition into arr
  for (var i = 0; i < localStorage.length; i++){
    if (localStorage.key(i).substring(0,prefixLength) == prefix) {
      arr.push(localStorage.key(i));
    }
  }

  // Iterate over arr and remove the items by key
  for (var i = 0; i < arr.length; i++) {
    localStorage.removeItem(arr[i]);
  }

  console.log("REMOVED: "+arr)

};

Storage.prototype.has = window.hasOwnProperty;
Storage.prototype.remove = window.removeItem;

Storage.prototype.keys = function(){
  return Object.keys(this.valueOf());
};
