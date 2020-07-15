<img src="/src/icon.png" width="200px" alt="Servd Icon" title="Servd Icon" style="max-width:100%;">

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

If you are only using the Servd Asset volumes on projects within Servd's staging and production environments there's only one mandatory setting: `Base URL` which should be set to `https://cdn2.assets-servd.host`. 

Once set you can start uploading your assets and displaying them in your templates using Craft's standard asset URL generators and transforms.

## Local Development

If you would like to use Servd Asset volumes during local development you will need to fill in the `Project Slug` and `Secret Access Key` settings. These can be found in the Servd dashboard under Project Settings > Assets. We recommend you set these as environment variables to avoid them being added to your project config file.

You can create multiple Servd Asset volumes. If you do this you will need to supply a `subfolder` for each volume - otherwise your files will all get mixed up.

![Servd Volume Subfolder](/images/subfolder.png "Servd Volume Subfolder")

## Legacy Assets Platform

If you created a project on Servd before 15h June 2020, you might be using Servd's legacy Assets Platform. If so you'll need to do two things:

1. Change your asset volume `Base URL` to `https://cdn.assets-servd.host`
2. Add an environment variable to your local development environment: `USE_LEGACY_ASSETS=true`

Once both of those changes have been made the plugin will use the legacy platform for all of its functionality.

## Use With Craft Asset Transforms

The plugin will automatically intercet any `getUrl()` calls on assets in both your twig templates and from within other plugins.
If the asset exists on a Servd Asset Volume the returned URL will point towards the Servd Asset Platform. This also supports Craft
Asset Transforms, both pre-defined and dynamically generated:

In your twig template:

```
{{ asset->getUrl('large') }}

OR

{% set thumb = {
    mode: 'crop',
    width: 100,
    height: 100,
    quality: 75,
    position: 'top-center'
} %}

{{ asset->getUrl(thumb) }}
```

## Thanks

NYStudio107 for the Craft Transform -> SharpJS edits array transformation logic from [Image Optimize](https://github.com/nystudio107/craft-imageoptimize)
