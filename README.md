<img src="/src/icon.svg" width="200px" alt="Servd Icon" title="Servd Icon" style="max-width:100%;">

# Servd Assets and Helpers for Craft CMS

This plugin provides a [Servd](https://servd.host) Asset Storage integration for [Craft CMS](https://craftcms.com/) along with a host of other deep integration functionality.

## Requirements

This plugin requires Craft CMS 3.1.5 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for "Servd". Then click on the "Install" button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require servd/craft-asset-storage

# tell Craft to install the plugin
./craft plugin/install servd-asset-storage
```

## Features

* Asset management for [Servd](https://servd.host) projects
* (Nearly) zero-config
* Automatically separates local, staging and production assets
* Built-in CDN + edge caching for super-fast delivery to users
* Zero config, off-server image transforms
* Imager-X extensions to make use of the Servd Assets Platform
* ImageOptimise extensions to make use of the Servd Assets Platform
* CSRF token injection to help with static caching
* Automatic static cache busting upon entry save events
* Support for using the debug bar in a load balanced environment (like Servd)
* Easy dynamic content includes when static caching is enabled

## Docs

You can find plugin info and docs here: [servd.host/docs/the-servd-plugin](https://servd.host/docs/the-servd-plugin)