![Servd Icon](/src/icon.png "Servd Icon")

# Servd Asset Storage for Craft CMS

This plugin provides a [Servd](https://servd.host) Asset Storage integration for [Craft CMS](https://craftcms.com/).

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
./craft install/plugin servd-asset-storage
```

## Features

* Asset management for [Servd](https://servd.host) projects
* (Nearly) zero-config
* Automatically separates local, staging and production assets
* Built-in CDN + edge caching for super-fast delivery to users
* Zero config, off-server image transforms - never worry about image resizing ruining your life again

## Setup

Once the plugin is installed you will be able to create 'Servd Asset Storage' volumes within the asset settings of Craft's control panel.

![Servd Volume Type](/images/volume-type.png "Servd Asset Storage Volume")

If you are only using the Servd Asset volumes on projects within Servd's staging and production environments there's only one mandatory setting: `Base URL` which should be set to `https://cdn.assets-servd.host`. 

![Servd Volume Base URL](/images/base-url.png "Servd Volume Base URL")

Once set you can start uploading your assets and displaying them in your templates using Craft's standard asset URL generators and transforms.

If you would like to use Servd Asset volumes during local development you will need to fill in the `Project Slug` and `Secret Access Key` settings. These can be found in the Servd dashboard under Project > Assets.

You can create multiple Servd Asset volumes (maybe to keep public and private files separate). If you do this you will need to supply a `subfolder` for each of the volumes - otherwise your files will all get mixed up.

![Servd Volume Subfolder](/images/subfolder.png "Servd Volume Subfolder")

## Thanks

NYStudio107 for the Craft Transform -> SharpJS edits array transformation logic from [Image Optimize](https://github.com/nystudio107/craft-imageoptimize)
