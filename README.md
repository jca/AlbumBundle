# AlbumBundle

**This repository is no longer supported. You can use [webelop/album-bundle](https://github.com/webelop/album-bundle/) with Composer instead:**
```
composer install webelop/album-bundle
```

## What does this Symfony2 bundle do?

Album bundle is an still experimental lightweight photo management system that allows 
- Browsing through a folder structure and displaying slide shows of scaled images
- Creating and managing tags used to export public albums

## Installation

1. By downloading the source
    ```bash
    $ git submodule add https://github.com/jca/AlbumBundle.git src/Jcc/Bundle/AlbumBundle
    ```

2. ### Add the Jcc namespace to your autoloader

    If this is the first Jcc bundle in your Symfony 2 project, you'll
need to add the `Jcc` namespace to your autoloader. This file is usually located at `app/autoload.php`.

    ```php
    $loader->registerNamespaces(array(
        'Jcc' => __DIR__.'/../src'
        // ...
    ));
    ```

3. ### Configuration

Add

    ```yaml
    parameters:
        album_root:       /local/path/to/pictures/root/folder
        album_depth:      2
        album_sizes:      "http://slide.local/pictures/crop/200/200/{hash}.jpg|http://slide.local/pictures/fit/1024/780/{hash}.jpg"
        cache_path:       %kernel.root_dir%/../web/pictures
    ```
