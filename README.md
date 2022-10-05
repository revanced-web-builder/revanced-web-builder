# ReVanced Web Builder

HTML/JS frontend and PHP backend for the official ReVanced CLI builder.

ReVanced Web Builder is a tool that injects patches into the official Android Apps (APKs) of apps like YouTube, YouTube Music, Spotify, TikTok, Twitter, and Reddit to block advertisements, customize theme, and bring many additional features to the apps.

The ReVanced CLI is a powerful tool that lets you modify many apps with different patches and options. However, not everyone has the tools or experience to use the command line tool.

ReVanced Web Builder is an entire interface (with an admin panel) that allows you to customize and build all apps supported by ReVanced. Builds can be stored on your server and users can download them after building.

Admin chooses which apps and versions to allow users to build, along with other options to customize the script.

Note: Hosting and distributing patched APKs can be a legal issue. Be cautious about who has access to ReVanced Web Builder.
It's best to just use this for personal use, but I'm not responsible for what you do with this code or project on your own server.

## Requirements

- Apache Web Server (Windows, Mac, or Linux)
- Java JDK >= 17
- PHP >= 7.4
- cURL (System or PHP) or wGet

## Quick Installation

If you already have Apache, PHP, and Java set up:

1. Extract the release to anywhere in your web server.
2. (Linux/Mac only) Give write permissions to the "builds" and "app" folder.
3. Point your web browser to the RWB folder (example: http://localhost/rwb)

## Documentation

Full documentation including full setup instruction for Linux and Windows can be found at the [Documentation Website](https://revanced-web-builder.github.io/) or in the [/app/docs/](app/docs/index.html) folder of your RWB install.

Documentation also includes information about mod_rewrite, build info/stats, build durations, known issues, dev tools, and more.

## Screenshots

Home Screen

![Home Screen](app/docs/assets/screenshot_home.jpg)

Admin Panel

![Admin Panel](app/docs/assets/screenshot_admin.jpg)
