# Image Cleanup module for Magento 2

## Purpose

When adding products in your webshop, eventually you'll also have to delete some of those products.
But sometimes Magento doesn't remove images associated with the products that you delete.  
So you'll have to manually delete them from time to time from disk, which is hard to do manually.
This module gives you some options to delete those lingering unused images from the disk so you can recover some diskspace on your server.

## Implemented Features

- It can find product image files on disk that are not referenced in the database and remove them
- It can do the same for the resized (cached) versions of those images
- It tries to not delete dynamically generated images files (like `webp` of `avif` files) if the original file is still being used, see [configuration](#configuration)
- It can detect entire unused resized (cached) directories that are no longer valid and remove them with all the files in there, see [below](#documentation-about-resizedcached-directories)
- It can detect and remove obsolete values in the `catalog_product_entity_media_gallery` database table
- It can find resized product image files that are corrupt and remove them

## Watch out

- The module will always first output what it will delete, make sure you check the entire list before confirming, so that you aren't removing files you don't want to remove. Do **not** _test_ this module on a production environment first before you fully understand what it will do!
- This module hasn't been tested when your Magento shop is configured to store image files in the database, your mileage may vary when you use that way of working. Feel free to open issues in case any occur, and we'll see if we can fix something...

## Compatibility

- This module should work with Magento 2.3.4 or higher
- The module should be compatible with PHP 7.3, 7.4, 8.1, 8.2 and 8.3

## Installation

You can use composer to install this module:

```sh
composer require baldwin/magento2-module-image-cleanup
```

Or download the code and put all the files in the directory `app/code/Baldwin/ImageCleanup`

After which you can then activate it in Magento using:

```sh
bin/magento setup:upgrade
```

## Usage

There are 4 command line commands you can use execute:

- `bin/magento catalog:images:remove-obsolete-db-entries`
- `bin/magento catalog:images:remove-unused-hash-directories`
- `bin/magento catalog:images:remove-unused-files`
- `bin/magento catalog:images:remove-corrupt-resized-files`

There are some extra options for some of these commands:

```
      --no-stats        Skip calculating and outputting stats (filesizes, number of files, ...), this can speed up the command in case it runs slowly.
  -n, --no-interaction  Do not ask any interactive question
```

The `-n` option can be used if you want to setup a cronjob to regularly call these cleanup commands, it will not ask for confirmation before removing files, and will just assume you said 'yes, go ahead' (which can be dangerous!)

The module will output all the things it deleted in a log file `{magento-project}/var/log/baldwin-imagecleanup.log` so you can inspect it later in case you want to figure out why something got removed.

For optimal & fastest cleanup, it's advised to run the commands in this order:

1. `bin/magento catalog:images:remove-obsolete-db-entries`
2. `bin/magento catalog:images:remove-unused-hash-directories`
3. `bin/magento catalog:images:remove-unused-files`
4. `bin/magento catalog:images:remove-corrupt-resized-files`

If you don't run these in this order, it might mean you'll need to run some of them a second time for them to find more things to cleanup or it might mean that they'll take longer then needed.


## Configuration

There is a configuration section in the backoffice under: Stores > Configuration > Catalog > Catalog > Product Image Cleanup Settings

- **List of dynamically generated image file extensions**: Some Magento shops might have modules installed to dynamically generate `webp` or `avif` image files out of the original product image files. These files are usually not referenced in the database of Magento so by specifying those file extensions in the configuration, we can prevent them from being deleted accidentally. The module will still be able to remove those type of files when the original file is no longer referenced in the database.  
This feature only works properly when the dynamically generated image files use the same filename as the original file, so they can only be different in the file extension being used (either replaced or appended).

## Documentation about resized/cached directories

Magento saves resized product images in certain directories in `pub/media/catalog/product/cache`
The directory names are basically an md5 hash of a bunch of parameters like: width, height, background-color, quality, rotation, ... (which tend to be defined in the `etc/view.xml` file of themes)
Sometimes, Magento tweaks how the hash gets calculated in certain newer versions of Magento, or your theme changes some parameter which both can make those hashes no longer being used.

This module has the option to detect such directories and can remove them together with all the files in there.

## Note to self

In our class `Baldwin\ImageCleanup\Finder\UnusedCacheHashDirectoriesFinder`, we borrowed some code from core Magento that was private and not easily callable. We made only very slight changes to deal with coding standards and static analysis, but it's mostly the same as the original source. These pieces of code were based on code that didn't really change since Magento 2.3.4.

It's important that we check with every single new Magento version that gets released, that the code in `Magento\MediaStorage\Service\ImageResize` doesn't change in such a way that we need to adapt our own implementation.

So this is something that needs to be double checked with every new Magento release.
