# Omeka S CLI

A CLI application to manage [**Omeka S**](https://omeka.org/s/) installations.

## Installation

1. Clone the repository to a location of your choice
    ``` shell
    git clone https://github.com/indic-archive/omeka-s-cli.git
    ```
2. Install the composer packages
    ``` shell
    cd omeka-s-cli
    composer install
    ```
3. Create a symlink to the application in your bin directory.
    ``` shell
    ln -s /path/to/where/you/clone/above/repo/app/app.php ~/bin/omeka-s-cli
    chmod +x ~/bin/omeka-s-cli
    ```

## Configuration

You may create the file `.omeka-s-cli.yml` in your home directory and put this content in to it:

``` yaml
backup-dir: /home/omeka/backups/
backup-restore-disallow:
  - '/home/omeka/www'
```

* **backup-dir**
    * Directory path where backups will be kept on creation and taken for restoring.
* **backup-restore-disallow**
    * List of directory of Omeka S installation to prevent restoring backups. This will protect production sites from accidental overwriting.

## Usage

You can run the base command to get help and list of sub-commands.

``` shell
omeka-s-cli
```

### Examples

Let's assume your Omeka S instance is installed at `/home/omeka/www`

#### Creating a Backup

``` shell
omeka-s-cli backup:create --site-dir=/home/omeka/www "A backup before doing something unexpected"
```

#### Restore from a Backup

``` shell
omeka-s-cli backup:restore --site-dir=/home/omeka/www
```

This will list all available backups already in the backup directory and you can choose one to restore.

> [!WARNING]
> Make sure to select correct backup when restoring a production site. The files and database of the site will be   replaced

#### Update Omeka S

This will prompt to update to the latest release available.

``` shell
omeka-s-cli update --site-dir=/home/omeka/www
```

If you want to update to a specific version, run below given command. It will give you options to choose.

``` shell
omeka-s-cli update --site-dir=/home/omeka/www --list
```

> [!NOTE]
> Right now there is no detection of existing Omeka S version installed. So, it is your responsibility to make sure you are updating from a lower version to higher.


## Update

To update. Pull code changes:

``` shell
git pull
```

and run composer update:

``` shell
composer update
```