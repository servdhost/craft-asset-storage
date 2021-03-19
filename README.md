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
./craft install/plugin servd-asset-storage
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

## Setup

Once the plugin is installed you will be able to create 'Servd Asset Storage' volumes within the asset settings of Craft's control panel.

![Servd Volume Type](/images/volume-type.png "Servd Asset Storage Volume")

For every new Servd Assets Storage volume you should set the Base URL to `https://cdn2.assets-servd.host` or an environment variable which holds this value. 

Once set you can start uploading your assets and displaying them in your templates using Craft's standard asset URL generators and transforms.

## Multiple Volumes

You can create multiple Servd Asset volumes. If you do this you will need to supply a `subfolder` for each volume - otherwise your files will all get mixed up.

![Servd Volume Subfolder](/images/subfolder.png "Servd Volume Subfolder")

## Local Development

If you would like to use Servd Asset Storage volumes during local development you will need to fill in the `Servd Project Slug` and `Servd Security Key` settings. The values for these can be found in the Servd dashboard under **Project Settings > Assets**. 

The plugin will automatically try to load these values from the environment variables `SERVD_PROJECT_SLUG` and `SERVD_SECURITY_KEY`, so you can simply add these two environment variables to your local dev environment to get things working. Alternatively you can add them directly to the plugin's settings in which case they will be added to your Craft Project Config files (so make sure they are kept secret!).

### Force Local Volumes for Assets

You can force the use of local volumes when working outside of Servd to make development work a little quicker. To do so, just flip the 'Use Local Volumes During Dev' switch in the plugin settings. This will convert all Servd Asset Volumes into Local Volumes during local dev, but keep them unchanged when running inside Servd itself. This setting also ensures that the Local Volume overide does not leak into any Project Config files or database data which might inadvertantly break the staging or production environment.

This setting is very useful if you have multiple developers working on a single project and they all need to keep track of their own sets of assets. It also ensures that assets used during local development do not count towards your Servd Asset Platform usage total.

**This setting ignores the Assets Volume Environment setting (see below) and will be applied regardless of any value added there**

### Sync Assets

You can sync your assets from local to staging/production, or from staging/production to local. If you are using remote storage during local development (the default) this will simply trigger a clone task to copy the asset files between directories in the Servd Asset Platform. If you have 'Use Local Volumes During Dev' enabled this will sync assets between your local filesystem and the specified remote storage directory.

You can trigger asset syncs using the following commands:

`./craft servd-asset-storage/local/pull-assets`

`./craft servd-asset-storage/local/push-assets`

These commands will try their best to auto-detect all of the settings they need, even before Craft has been installed. If it cannot identify specific settings it will prompt you for them, or you can supply them via CLI flags:

`./craft servd-asset-storage/local/pull-assets --from=staging --servdSlug=my-project-slug --servdKey=my-servd-key --interactive=0`

`./craft servd-asset-storage/local/push-assets --to=production --servdSlug=my-project-slug --servdKey=my-servd-key --interactive=0`

### Pull/Push Local Database

You can pull down a database from Servd or push up your local database at any time using the commands:

`./craft servd-asset-storage/local/pull-database`

`./craft servd-asset-storage/local/push-database`

These commands will try their best to auto-detect all of the settings they need, even before Craft has been installed. If it cannot identify specific settings it will prompt you for them, or you can supply them via CLI flags:

`./craft servd-asset-storage/local/pull-database --from=staging --servdSlug=my-project-slug --servdKey=my-servd-key --skipBackup=1 --interactive=0`

`./craft servd-asset-storage/local/push-database --to=production --servdSlug=my-project-slug --servdKey=my-servd-key --interactive=0`

*Please note that while a database is being pushed to a Servd, the target environment is likely to become unresponsive*

## Static Caching

Servd has a static caching solution which is available for all projects to make use of. This plugin provides deep integration with your project's static cache by allowing brute force or intelligent cache invalidations to occur. You can select when the cache is purged and what mecahnism is used to perform the purge within the plugin settings.

### Full Purge

A full purge will destroy the entire static cache immediately whenever a 'live' Craft Element is updated. This is a brute force approach, but it ensures that any changes you have made are displayed on the front end immediately with no chance of any cached pages pages surviving.

### Automated Tag Based Purge

This purge mechanism tracks all of the Craft Elements which are loaded by Craft in order to render specific URLs. These Element -> URL associations are stored within an efficient structure for later retrieval.

When any 'live' Craft Element is updated, the plugin is able to determine which URLs need to be purged based on the Element -> URL associations and *only* these URLs are purged. This prevents sudden surges of traffic from reaching PHP each time an Element is updated, but relies on a relatively complex tagging system on the back end which might, in some edge case circumstances, result in some URLs being missed from purges.

### CSRF Token Injection

By default Craft uses CSRF tokens within &lt;form&gt; elements. Static caching breaks this functionality by serving incorrect tokens to the majority of users. In order to prevent this you can enable CSRF token injection in the plugin settings. This will inject a small piece of javascript into all of your templated pages which checks for and CSRF tokens and replaces them with an uncached value.

## Environment Detection

The plugin will automatically select a subfolder within the Servd Assets Platform from the following:

- local
- staging
- production

based on your current working environment. This allows Servd to be able to clone assets between these environments.

I.E if you perform a full clone from staging -> production the platform will not only copy your database, but also your assets by copying them between these directories.

### Forcing a Specific Environment 

If you wish to prevent the plugin from auto-detecting the current environment (so that you can E.G. interact with production assets whilst working locally) you can override the environment detection using the **Assets Volume Environment** setting in the main plugin settings. You should probably set this to an environment variable so that you can tweak it dynamically as required.

## Use With Craft Asset Transforms

The plugin will automatically intercept any `getUrl()` calls on assets which are stored in Servd Asset Storage volumes in both your twig templates and from within other plugins.

The returned URL will point towards the Servd Asset Platform. This also supports Craft Asset Transforms, both pre-defined and dynamically generated:

In your twig template:

```twig
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

## Use with Imager-X

The plugin contains storage and transform adapters for [ImagerX](https://github.com/spacecatninja/craft-imager-x). These allow you to use the ImagerX template syntax whilst utilising Servd's Asset Platform for storage, optimisation and transformation of your images.

You can use any combination of the storage and transformer components as you wish. Here are a few example ImagerX configurations:

### Use Servd For Everything (only works with Images stored on a Servd Assets Volume)

```php
return [
    'transformer' => 'servd',
];
```

### Use Servd For Storage Of Transformed Images Only (assets transformed on-server - should work with source assets stored anywhere)

```php
return [
    'storages' => ['servd'],
    'storageConfig' => [
        'servd' => [
            'folder' => 'transforms',
        ]
    ],
    'imagerUrl' => 'https://cdn2.assets-servd.host/[you-servd-project-slug]/transforms/',
];
```

### Use Imgix For Transforms, Servd For Storage (only works with Images stored on a Servd Assets Volume)

```php
return [
    'transformer' => 'imgix',
    'imgixConfig' => [
        'default' => [
            'domain' => '[your-imgix-domain].imgix.net',
            'useHttps' => true,
            'useCloudSourcePath' => true,
        ]
    ]
];
```

Combine the above with an imgix 'Web Folder' source set up to point to `https://cdn2.assets-servd.host/`

## Use with ImageOptimise

The plugin contains a transform adapter for [ImageOptimize](https://github.com/nystudio107/craft-imageoptimize). Simply select 'Servd' as the active transformer in ImageOptimise's settings. 

If you'd like to pre-generate resized images (not really necessary and can slow down the Craft CP) add the 
ImageOptimise Optimized Images Field to the Servd Asset Volume's fieldset.

