<img src="/src/icon.png" width="200px" alt="Servd Icon" title="Servd Icon" style="max-width:100%;">

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

If you would like to use Servd Asset Storage volumes during local development you will need to fill in the `Project Slug` and `Security Key` settings in the main plugin settings. The values for these can be found in the Servd dashboard under Project Settings > Assets. We recommend you set these as environment variables to avoid them being added to your project config file.

## Environment Detection

The plugin will automatically select a subfolder within the Servd Assets Platform from the following:

- local
- staging
- production

based on your current working environment. This allows Servd to be able to clone assets between these environments.

I.E if you perform a full clone from staging -> production the platform will not only copy your database, but also your assets by copying them between these directories.

If you wish to prevent the plugin from auto-detecting the current environment (so that you can E.G. interact with production assets whilst working locally) you can override the environment detection using the Environment Override setting in the main plugin settings.

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

