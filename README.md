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
* Zero config, off-server image transforms
* Imager-X extensions to make use of the Servd Assets Platform
* CSRF token injection to help with static caching
* Automatic static cache busting upon entry save events

## Setup

Once the plugin is installed you will be able to create 'Servd Asset Storage' volumes within the asset settings of Craft's control panel.

![Servd Volume Type](/images/volume-type.png "Servd Asset Storage Volume")

If you are only using the Servd Asset volumes on projects within Servd's staging and production environments there's only one mandatory setting: `Base URL` which should be set to `https://cdn2.assets-servd.host`. 

Once set you can start uploading your assets and displaying them in your templates using Craft's standard asset URL generators and transforms.

## Local Development

If you would like to use Servd Asset volumes during local development you will need to fill in the `Project Slug` and `Security Key` settings in the main plugin settings. The values for these can be found in the Servd dashboard under Project Settings > Assets. We recommend you set these as environment variables to avoid them being added to your project config file.

You can create multiple Servd Asset volumes. If you do this you will need to supply a `subfolder` for each volume - otherwise your files will all get mixed up.

![Servd Volume Subfolder](/images/subfolder.png "Servd Volume Subfolder")

## Use With Craft Asset Transforms

The plugin will automatically intercet any `getUrl()` calls on assets in both your twig templates and from within other plugins.
If the asset exists on a Servd Asset Volume the returned URL will point towards the Servd Asset Platform. This also supports Craft
Asset Transforms, both pre-defined and dynamically generated:

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

The plugin contains storage and tranform adapters for [ImagerX](https://github.com/spacecatninja/craft-imager-x). These allow you to use
the ImagerX template syntax whilst utilising Servd's Asset Pltform for storage, optimisation and transformation of your images.

You can use any combination of the storage and transformer components as you wish. Here are a few example ImagerX configurations:

### Use Servd For Everything (only works with Images stored on a Servd Assets Volume)

```php
return [
    'transformer' => 'servd',
];
```

### Use Servd For Storage Only (assets transformed on-server - should work with assets stored anywhere)

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

### Known Issues with Imager-X

- Currently, focal point overrides are not respected when using the 'servd' transformer
