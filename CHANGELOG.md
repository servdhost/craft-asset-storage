# Release Notes for Servd Assets and Helpers

## 4.2.2 - 2025-11-18

### Added

- Added the "Disable SERVD_LOGGED_IN_STATUS cookie" plugin setting. Note that enabling this setting will prevent the "Logged in users skip the cache" static caching setting from working.
- Added a setting for Servd Asset Storage filesystems which allows transforms and optimisations to be disabled for specific filesystems.

## 4.2.1 - 2025-11-07

### Updated

- Set SERVD_LOGGED_IN_STATUS cookie to be 'secure' by default

## 4.2.0 - 2025-09-17

### Fixed

- Encoding issue with commas in filenames.

### Added

- Added `clear-caches/servd-edge-caches` CLI command.

### Removed

- Removed deprecated `clear-caches/servd-asset-storage` CLI command.

## 4.1.3 - 2025-08-22

### Fixed

- Fixed a bug that prevented the focal point coordinates from being passed to the Servd transformer if either the X or Y values were set to zero.

## 4.1.2 - 2025-07-21

### Fixed

- Added support for local mysql client CLI v8.4 to database push/pull commands


## 4.1.1 - 2025-07-19

### Updated

- Store SSH keys in runtime directory to prevent git trying to commit them

## 4.1.0 - 2025-07-17

### Updated

- Switched database push/pull CLI commands to use SSH Session tunnelling 

## 4.0.17 - 2025-06-13

### Added

- Added the "Clear Asset CDN cache when an asset file changes" plugin setting to control the cache purging behaviour when an asset is deleted and when it's file is replaced

## 4.0.16 - 2025-04-19

### Added

- Added the "Transform SVGs" plugin setting to control how SVGs are transformed to other image formats

## 4.0.15 - 2025-02-13

### Fixed

- Updated the automatic cache purging of edited assets to handle renamed assets

## 4.0.14 - 2025-02-03

### Fixed

- Added a fix for an update to the AWS S3 SDK which adds headers incompatible with Servd asset platform storage providers

## 4.0.13 - 2025-01-27

### Added

- Added the `--emptyDatabase` flag for the `servd/local/pull-database` CLI command to fully empty the local DB before running the import

## 4.0.12 - 2024-12-11

### Updated

- Reduced the cache memory usage of the "Automated Tag Based Purge" option for the "Cache Clear Strategy" plugin field.

## 4.0.11 - 2024-12-03

### Fixed

- Another fix for compatibility with previewPlaceholderHtml changes in Craft 5.5.0

## 4.0.10 - 2024-11-22

### Updated

- Force image transforms to have a width and height compatible with backend transform function memory limits
- Automatically clear caches for non-image files that are replaced or deleted within the Craft CP

## 4.0.9 - 2024-11-15

### Fixed

- Speculative fix for compatibility with previewPlaceholderHtml changes in Craft 5.5.0

## 4.0.8 - 2024-09-30

### Fixed

- Added handling for image transform strings passed as an array

## 4.0.7 - 2024-09-24

### Fixed

- Replaced a removed twig function with an alternative

## 4.0.6 - 2024-07-25

### Fixed

- Fixed a bug which caused asset files with accented characters to not load properly on the V3 asset platform.

## 4.0.5 - 2024-07-16

### Fixed

- Fixed a bug which caused images to be served in their original size instead of being transformed

## 4.0.4 - 2024-07-15

### Updated

- Switched asset platform image manipulation availability detection from Craft's native to a custom implementation to support transforms of HEIC/HEIF files even when local ImageMagick/GD isn't able to do so.

## 4.0.3 - 2024-04-08

### Fixed

- Update class references in StaticCache to match Craft 5 deprecations
- Removed a reference to a composer package which is  no longer installed with Craft 5

## 4.0.2 - 2024-03-29

### Added

- Support for Craft 5 volume subpaths

### Updated

- Moved image do-not-upscale logic over to asset platform
- Merged recent v3.x plugin changes

### Fixed

- Fixed a bug when purging static cache URLs with no defined path

## 4.0.1 - 2024-03-08

### Fixed

- Fixed a call to a method which has changed signature in Craft 5

## 4.0.0 - 2024-02-14

### Added

- Preliminary Craft 5 Support. Happy valentines day 💕

## 3.5.11 - 2024-03-11

### Updated

- Added rawurlencoding to custom file pattern URLs to match non-custom handling

## 3.5.10 - 2024-02-13

### Fixed

- Fixed an issue where parentheses in v3 asset filenames would cause the image to fail to load.

## 3.5.9 - 2024-01-25

### Fixed

- Fixed a bug with the ImagerX integration

## 3.5.8 - 2024-01-24

### Added

- Respect image upscaling settings in Craft general settings and directly on transforms

## 3.5.7 - 2023-12-18

### Added

- Added some useful debugging info for failed dynamicInclude calls

## 3.5.6 - 2023-11-30

### Fixed

- Fixed an order of execution bug when integrating the Servd Plugin with Blitz

## 3.5.5 - 2023-11-20

### Added

- Added a CLI command to convert accent modifier characters in asset filenames in the database to their equivalent absolute accented characters as is used by the S3 protocol.

## 3.5.4 - 2023-11-07

### Updated

- Normalise any accent modifier characters in asset filenames which upset our Asset Platform storage provider's API

## 3.5.3 - 2023-11-02

### Added

- Added a new `./craft servd-asset-storage/command` command which can be used to run Craft console commands in a Servd environment.

## 3.5.2 - 2023-10-27

### Added

- Added validation to the Servd Filesystem's CDN URL Pattern field to prevent the `{{params}}` placeholder from being added.
- Added validation to the Servd Filesystem's Image Transform URL Pattern field to check the `{{params}}` placeholder is present.

## 3.5.1 - 2023-10-26

### Fixed

- Ampersands in filenames of assets are now encoded to prevent security token hash mismatches

## 3.5.0 - 2023-09-19

> [!IMPORTANT]
> If you are using `{% dynamicInclude %}` twig tags, you will need to clear any static caches to regenerate their HTML with this update.

### Updated

- Improve security of dynamic include calls

## 3.4.11 - 2023-08-25

### Fixed

- Fix for an issue where the URLs for non-Servd assets were getting handled when the disable transforms setting was enabled.

## 3.4.10 - 2023-08-24

### Added

- A `--wait` option to the `./craft servd-asset-storage/clone` command that polls the Servd task runner until completion.

### Fixed

- Relaxed environment constraint for where the `./craft servd-asset-storage/clone` command could be run from.

## 3.4.9 - 2023-08-23

### Added

- It's now possible to trigger clones between remote Servd environments using the `./craft servd-asset-storage/clone` console command.
- The database optimisation step performed by the `./craft servd-asset-storage/local/push-database` command is now controlled by an internal back-end config setting, and only run when determined by the Servd task runner.
- Non-transformed file URLs now get a `dm` query parameter appended to allow for cache busting if the underlying asset changes.

## 3.4.8 - 2023-07-31

### Fixed

- Fixed an incompatibility between Asset Platform V3 and ImagerX integration when Imager is passed a URL as a string (like it is when retcon is transforming images)

## 3.4.7 - 2023-06-13

### Fixed

- Fixed a reference to a const which has recently been removed which broke the ImagerX storage adapter

## 3.4.6 - 2023-06-06

### Updated

- Removed an instance of urlencoding for non-image assets which was causing problems when interacting with some other plugins which also urlencoded URLs

## 3.4.5 - 2023-05-24

### Fixed

- Craft does not adhere to its own `addTrailingSlashesToUrls` setting for some multisite URLs. That is now handled when purging static cache URLs

## 3.4.4 - 2023-05-22

### Fixed

- Fixed a couple of bugs when syncing assets between asset platform V3 and the local filesystem

## 3.4.3 - 2023-05-15

### Added

- Added additional request validation for dynamicInclude endpoint to prevent annoying exceptions being thrown by bots

## 3.4.2 - 2023-05-03

### Fixed

- Removed all URL encoding in transform URLs, it was upsetting things

## 3.4.1 - 2023-05-03

### Fixed

- Fixed a URL encoding issue involving `@` characters in asset file names which caused 401 security token errors to be returned.

## 3.4.0 - 2023-04-29

### Added

- [Asset Platform V3 Support](https://servd.host/blog/asset-platform-v3)

## 3.3.3 - 2023-04-24

### Added

- Fix for missing "Purge Product URL(s)" buttons on Craft Commerce product pages.

## 3.3.2 - 2023-04-16

### Added

- Support for fillmax image transform mode

## 3.3.1 - 2023-04-13

### Fixed

- Fix a bug when pulling or pushing the database but the local DB's password is empty

## 3.3.0 - 2023-03-17

### Added

- Support for 'letterbox' image cropping in Craft 4.4

## 3.2.11 - 2023-02-20

### Fixed

- Fixed a missing change from the previous commit

## 3.2.10 - 2023-02-20

### Added

- Added a `-v` flag for the CLI commands to provide verbose output to help track down errors

## 3.2.9 - 2023-02-20

### Fixed

- Fix for the cookie SERVD_LOGGED_IN_STATUS was not cleared as expected when using a wildcard cookie domain.

## 3.2.8 - 2023-02-13

### Updated

- The plugin's CSRF token and dynamic content injection JS functions can now be deferred and executed manually to avoid collisions with other ajax requests which might run on intial page load (causing csrf session issues).

## 3.2.7 - 2023-02-10

### Added

- The priority of the static cache purge job can now be controlled by setting an optional SERVD_PURGE_PRIORITY environment variable to an integer value. By default, the priority is set to 1025.

## 3.2.6 - 2023-02-08

### Fixed

- Introduced batching to the code that purges specific URLs from the static cache. The batch size can be controlled by setting a SERVD_PURGE_BATCH_SIZE environment variable to an integer value.

## 3.2.5 - 2023-02-03

### Fixed

- Added a fix for when an owner entry can't be located when attempting to get tags for a draft/revision entry.

## 3.2.4 - 2022-12-21

### Fixed

- Fixed the fix which didn't fix the thing the fix was supposed to fix

## 3.2.3 - 2022-12-20

### Fixed

- Fixed compatibility with Imager-X's Imgix transformer when using Servd's Asset Platform for storage

## 3.2.2 - 2022-12-19

### Added

- Servd's Imager-X integration will now play nicely with RetCon modified `img` tags, including srcset

## 3.2.1 - 2022-12-14

### Fixed

- Fixed an edge case which could result in static cache purges getting stuck in a redirect loop

## 3.2.0 - 2022-12-05

### Added

- You can now disable automatic image format conversion if webp isn't treating your images nicely
- Alternatively, you can now use AVIF as your auto-format of choice (check the plugin's settings for the new option)

## 3.1.8 - 2022-11-08

### Fixed

- Yet more fixes for the changes in Craft 4.3 which broke asset URL generations. This change stops Craft from generating image transforms unnecessarily

## 3.1.7 - 2022-11-01

### Fixed

- More fixes for the changes in Craft 4.3 which broke asset URL generations

## 3.1.6 - 2022-11-01

### Fixed

- Set the `$handled` property on asset url events to prevent Craft undoing our good work

## 3.1.5 - 2022-10-21

### Fixed

- Use correct action URLs when a site is using a subfolder basepath

## 3.1.4 - 2022-10-12

### Fixed

- Detection of video files updated to work when custom filetype definitions are included in general.php

## 3.1.3 - 2022-10-05

### Updated

- Cleaned up some static cache busting code which was no longer needed

## 3.1.2 - 2022-09-14

### Fixed

- Auto detect the Servd 'development' environment when determining the current assets environment

## 3.1.1 - 2022-08-16

### Fixed

- Added some protection to stop the Blitz cache purger from running when developing locally

## 3.1.0 - 2022-08-16

### Added

- Added an integration with Blitx to allow it to make use Servd's static cache as a caching reverse proxy. This allows you to use the speed and throughput of the Static Caching layer, whilst keeping all of your cache configuration and functionality within Blitz. [Servd + Blitz Docs](https://servd.host/docs/caching-with-blitz)

## 3.0.6 - 2022-08-06

### Added

- Added a tweak to Yii's Redis session management to make it work in the way everyone expects. The PHP session's TTL is now reset whenever is is opened, which prevents the session from expiring after a specific length of time. It now expires after a specific period of user inactivity which will also shortly be configurable via the Servd dashboard.

## 3.0.5 - 2022-08-04

### Fixed

- We didn't noticed that the cache clear buttons which we embed in the sidebar of Entry Edit pages had disappeared! It's back now 👌

## 3.0.4 - 2022-07-06

### Fixed

- Fixed a bug when rendering a non-image file with the Image Optimize integration.

## 3.0.3 - 2022-06-28

### Fixed

- Fixed a bug when moving assets between folders

## 3.0.2 - 2022-06-20

### Fixed

- Fixed a bug when manually clearing the static cache using a Commerce Product's 'tag'

## 3.0.1 - 2022-06-17

### Fixed

- Fixed incorrect function defs for ImageOptimize integration


## 3.0.0 - 2022-06-03

### Fixed

- Fixed a bug when using an environment variable as the 'subfolder' on a Servd Volume

## 3.0.0-beta.12 - 2022-05-30

### Fixed

- ImagerX function typings

## 3.0.0-beta.11 - 2022-05-26

### Fixed

- Fallback to the [defaultImageQuality](https://craftcms.com/docs/3.x/config/config-settings.html#defaultimagequality) config variable if no image quality is specified when defining a transformation.

## 3.0.0-beta.10 - 2022-05-26

### Fixed

- Apply stricter checks when hydrating dynamicInclude contexts

## 3.0.0-beta.9 - 2022-05-22

### Fixed

- Added return codes to all CLI commands

## 3.0.0-beta.8 - 2022-05-19

### Updated

- Feed Me logs now work as originally intended, browsable in the Craft CP, even when running on a load balanced infra or in an isolated task runner

## 3.0.0-beta.7 - 2022-05-13

### Fixed

- Some fun with static const overloading. Should they be public? Should they be private? No-one knows.
- Typo

## 3.0.0-beta.6 - 2022-05-12

### Fixed

- Fixed Feed Me logs integration by adding some typings

## 3.0.0-beta.5 - 2022-05-04

### Updated

- Merge master 2.5.5

## 3.0.0-beta.4 - 2022-05-02

### Fixed

- Tentative fix for a bug with the plugin's Flysystem S3 tweaks

## 3.0.0-beta.3 - 2022-04-20

### Added

- The local db push command now runs a MySQL optimize command to fix any indexes that were corrupted during the import

## 3.0.0-beta.2 - 2022-04-15

### Updated

- Craft 4 compatibility, including:
- Complete rework of Volume and the new Filesystem objects
- Rewrite of pull and push-asset commands to support filesystems
- Allow mapping of Servd Filesystems to Local Folder filesystems to allow copy of assets between them
- Loads of changes to ImageTransforms and related Events
- A few changes to support the new craft-flysystem package

## 2.5.3 - 2022-04-11

### Added

- Support for video on the Asset Platform

### Fixed

- Don't break filename icons for non image files in the Craft CP assets view
- Additional fixes for yii debug bar's recent changes

## 2.5.2 - 2022-04-07

### Fixed

- Fixed an incompatibility with the latest release of yii2-debug which changed the way data is serialised, breaking our debug logs redis target.

## 2.5.1 - 2022-02-25

### Changed

- The option to skip the cache for logged in users now differentiates between users who have cp access and not - so front-end-only users don't have to have all static caching disabled when they log in.
- Background tasks for static cache purges now have a priority of 1025 in order to try to get them to run after more important things as they sometimes can take quite a while.

## 2.5.0 - 2022-01-28

### Added

- Added an optional integration with the feed-me plugin to allow feed logs to be pushed to standard log output. This allows Servd to collect and display them using its normal log aggregation services

### Updated

- Rearranged the plugin settings page so that it's a little more organised
- Removed README content and added a link to the relevant Servd docs page

## 2.4.20 - 2022-01-27

### Updated

- Significantly improve the performance of tag-based static cache clearing for sites with a very large number of unique URLs

## 2.4.19 - 2022-01-25

### Updated

- Fire an event in JS when CSRF tokens have been loaded into the DOM if using static caching

## 2.4.18 - 2022-01-24

### Updated

- Ensure objects passed into {% dynamicInclude %} contexts, that do not have an id set, are removed (because they can't be rehydrated later)
- [Craft CMS Hosting on Servd](https://servd.host)

## 2.4.17 - 2022-01-18

### Fixed

- 2.4.16 caused another bug when multiple blocks were loaded onto the same page. Now fixed.

## 2.4.16 - 2022-01-18

### Fixed

- Fixed a bug which caused only a subset of `dynamicInclude` blocks to be included in the `dynamicLoaded` JS event.

## 2.4.15 - 2022-01-07

### Fixed

- Fixed a bug (recently introduced by changes to users and permissions in Servd's nginx processes) which prevented static cache purges from working as expected in some circumstances.

## 2.4.14 - 2022-01-06

### Updated

- Changed the download timeout when syncing assets to/from local volumes to 300 seconds instead of the previous 30 seconds.

## 2.4.13 - 2022-01-03

### Fixed

- Image transforms alignments using the (e.g.) 'top-left' syntax were being ignored in Servd asset transform URLs. Now they are not.

## 2.4.12 - 2021-12-28

### Fixed

- Twig extensions were only being registered for 'site' requests which caused problems when rendering templates from a CLI command. This is now fixed.

## 2.4.11 - 2021-12-15

### Updated

- The event which fires when the plugin has loaded in any `{% dynamicInclude %}` content now includes a list of all the blocks which have been added to the DOM, allowing you to target them in JS and do things with them (like init alpine JS objects etc).

## 2.4.10 - 2021-11-25

### Updated

- Allowed Servd powered ImagerX transforms to work when passed an existing ImagerX model instead of an Asset. Not sure why you'd do it, but folk do, and now they can.

## 2.4.9 - 2021-11-18

### Updated

- Added a timeout to the redis connection which clears Servd's static cache. There are now some legitimate situations in which these components might not exist all of the time, but PHP doesn't necesserily know about it.

## 2.4.8 - 2021-10-20

### Updated

- Disabled SEOMatic's automatic meta inclusion in templates generated by dynamicInclude - they aren't needed and slow things down

## 2.4.7 - 2021-10-20

### Fixed

- Fixed an issue with dynamicInclude blocks which are placed within a loop

## 2.4.6 - 2021-09-17

### Fixed

- Bug fix for projects which don't have commerce installed

## 2.4.5 - 2021-09-15

### Fixed

- A variable check error introduced in 2.4.4 #28

## 2.4.4 - 2021-09-15

### Updated

- Added the Servd Static Cache clear button to commerce product pages

## 2.4.3 - 2021-09-10

### Added

- Added a `--skipDelete` flag for preventing deletion of assets when cloning up or down from the local filesystem.

## 2.4.2 - 2021-09-03

### Updated

- Added a link to the Servd SMTP docs when the sendmail CP alert is shown. This provides information on why sendmail is disabled and also actionable alternative.

## 2.4.1 - 2021-08-27

### Added

- You can now disable image asset transforms and optimisations with a plugin setting. This can be useful if you're using another plugin or service to perform the necessary transforms and you only require the original image's URL.

## 2.4.0 - 2021-07-12

### Added

- You can now specify 'placeholder' on the 'dynamicInclude' tag, and combine with the 'endDynamicInclude' tag, in order to display placeholder content which is visible during dynamic content loading.

## 2.3.3 - 2021-07-09

### Fixed

- Fixed a bug with dynamicIncludes which prevented the plugin from automatically detecting the availability of ESI

## 2.3.2 - 2021-06-14

### Fixed

- Fixed a bug with dynamicIncludes which would strip certain values from context arrays if their keys matched values from Craft's global context

## 2.3.1 - 2021-06-11

### Fixed

- When the static cache is cleared, also clear all associated metadata. This ensures any cached 301/302 redirects are purged.
- dynamicInclude blocks whose responses contain multiple top level nodes no longer break things.

## 2.3.0 - 2021-05-06

### Added

- Support for super simple dynamic content in combination with static caching. Also supports zero-config ESI dynamic content if avaialble on Servd

## 2.2.6 - 2021-04-26

### Updated

- Updated the static cache clear button to purge all URLs associated with an entry, rather than just the one for the default site

## 2.2.5 - 2021-04-12

### Updated

- Altered the method used to detect whether users are logged in when deciding whether or not to skip the static cache for those users

## 2.2.4 - 2021-03-31

### Fixed

- Fixed the 2.2.3 fix which didn't fix the thing it was supposed to fix

## 2.2.3 - 2021-03-30

### Fixed

- Fixed a bug when using a mysql 8 client to locally to pull database dumps from Servd

## 2.2.2 - 2021-03-25

### Fixed

- Tweaked a couple of control panel routes which were throwing access denied errors for non-admin users.

## 2.2.1 - 2021-03-23

### Updated

- Remove composer.json version constraint which was added to prevent an AWS SDK bug from being downloaded (which is now fixed)

## 2.2.0 - 2021-03-22

### Added

- Added local development tooling

### Fixed

- Constrained AWS SDK version to prevent the bugged 3.175 update from being used

## 2.1.16 - 2021-03-08

### Updated

- If another plugin sets the URL for an image or thumbnail stored on the Servd asset platform, don't replace it with a CDN link. (Fixes an issue when using the plugin alongside https://github.com/spicywebau/craft-embedded-assets)

## 2.1.15 - 2021-03-05

### Updated

- Improved warnings and error messages for problems with Servd's asset platform
- [Enterprise Hosting for Craft CMS](https://servd.host/solutions/enterprise)

## 2.1.14 - 2021-03-02

### Added

- Allow Servd's warning banners in the Craft control panel to be suppressed

## 2.1.13 - 2021-02-10

### Updated

- Purge URLs in batches of 50 to ensure the Craft queue doesn't get clogged up when large purge requests are triggered
- Only track static cache tags on URLs which are actually going to be cached

## 2.1.12 - 2021-02-09

### Updated

- Include the triggers for cache purges in the background task for informational purposes

## 2.1.11 - 2021-02-05

### Fixed

- Do not track static cache tags against pages which return a non-200 response code, they won't get cached anyway

## 2.1.10 - 2021-02-04

### Added

- Compatibility with optional GET param inclusion in static cache keys

### Updated

- Improved cache tag garbage collection

## 2.1.9 - 2021-02-03

### Updated

- Further improvements to static cache tagging performance

## 2.1.8 - 2021-02-03

### Fixed

- Fixed a bug which was causing more tags to be associated with statically cached URLs than necessary.

## 2.1.7 - 2021-02-03

### Updated

- Batched redis commands during url <-> tag associations
- Deduplicated static cache tags

## 2.1.6 - 2021-02-03

### Fixed

- Pinned the AWS PHP SDK version allowed to those that do not contain a critical bug: https://github.com/aws/aws-sdk-php/issues/2189

## 2.1.5 - 2021-02-03

### Fixed

- Prevented the S3 URL structure from being mangled by any other competing configurations

## 2.1.4 - 2021-02-01

### Fixed

- Removed use of a deprecated redis call

## 2.1.3 - 2021-01-28

### Added

- Manual control panel button for clearing individual Entries from the static cache
- Protected against a potential error when an asset is deleted while another CP user is doing things

## 2.1.2 - 2021-01-21

### Fixed

- Fixed a bug when using full purge static caching and saving a Craft Section

## 2.1.1 - 2021-01-21

### Fixed

- Fixed an upgrade path error from V1 to V2.1

## 2.1.0 - 2021-01-21

### Added

- Automatic, tag based static cache invalidation

### Deprecated

- CLI command `clear-caches/servd-asset-storage` use `clear-caches/servd-static-cache` instead

### Removed

- Handling of filesystem based static cache which is no longer used

## 2.0.7 - 2021-01-20

### Fixed

- Fixed a bug which prevented the `settings` property from being accessed on a Servd Assets Volume

## 2.0.6 - 2021-01-10

### Fixed

- Fixed a bug which prevented the CDN cache being invalidated for non-image assets when the volume used a subfolder.

## 2.0.5 - 2021-01-05

> {note} This update contains a migration which attempts to maintain any existing custom domains which you have used in previous versions of the plugin. Please double check your volume settings after updating to make sure that this migration has had the intended effect.

### Added
- You can now define fully custom URL structures for source files and optimised images which make use of custom domains. You'll need to provide your own logic (in a Cloudflare worker or Lambda@edge) to proxy this request and convert the URL structure back to Servd's expected format.

### Updated
- Servd asset volumes are now forced to use the correct Base URL to avoid incorrect settings for this value

## 2.0.4 - 2020-12-14

### Added
- The data for the Yii2 Debug Bar will be stored in Redis instead of the filesystem, allowing it to work on ephemeral or load balanced environments.

## 2.0.3 - 2020-12-14

### Added
- You can now clear the CDN cache for the Servd Assets Platform by clicking a button in the Craft CP. This will cause any image transforms to be re-applied and any caches for original files will be destroyed.

## 2.0.2 - 2020-11-30

### Fixed
- CORS tokens were being injected into pages even when they were disabled. These overexicted tokens are now firmly back under control.

## 2.0.1 - 2020-11-13

### Fixed
- Graceful handling of a situation in which the $SERVD_ASSETS_ENVIRONMENT is explicitly set in the plugin settings, but the env var doesn't actually exist
- Fixed display of a control panel alert which prompts user to add appropriate plugin settings

## 2.0.0 - 2020-11-08

### Added
- ImageOptimize support for Servd assets platform
- ImagerX support for Servd assets platform (requires ImagerX Pro)
- Control panel warnings for Servd related misconfigurations

### Updated
- Large code refactor to plan for upcoming new features
- Moved Servd Project Slug and Security Key config param to plugin settings instead of volume settings

### Fixed
- Some things that previously broke, but nobody noticed 🤫
- No default env var fallback for $SERVD_ASSETS_ENVIRONMENT (#11)

### Removed
- Support for Servd's legacy asset platform

## 1.3.12 - 2020-10-17

### Fixed

- Fixed a bug introduced with Craft 3.5 which prevents assets being downloaded from the control panel

## 1.3.11 - 2020-09-11

### Updated
- Only asynchronously load CSRF tokens if there's an element on the page which will actually use it. Reduces precious PHP executions for statically cached sites.

## 1.3.10 - 2020-09-10

### Fixed
- Optimised assets with special characters in their filename were generating an incorrect security token because of some url encoding things. That is now longer the case and everyone can now safely include spaces, copyright symbols or aubergine emojis in their filenames.

## 1.3.9 - 2020-09-03

### Added
- Ability to override the automatically detected environment for assets. This allows, for E.G., developers to use the assets stored in their production environment whilst working locally.

## 1.3.8 - 2020-09-03

### Fixed
- Fixed a typo in the plugin settings

## 1.3.7 - 2020-08-25

### Added
- Ability to disable automatic static cache clearing or restrict it to only occur when entries are saved as part of a control panel request.

## 1.3.6 - 2020-08-07

### Added
- Ability to replace the domain used for optimised images. This allows the use of custom domains for assets stored within the Servd Assets Platform. [Read how].

[Read how]: https://servd.host/docs/can-i-use-my-own-domain-for-assets

## 1.1.0 - 2019-08-09

Added static cache busting on element updates (beta)

## 1.0.0 - 2019-06-30

Initial release.
