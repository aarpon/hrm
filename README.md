# HRM - The Huygens Remote Manager


The Huygens Remote Manager is an open-source, efficient, multi-user web-based interface to the Huygens software by Scientific Volume Imaging for parallel batch deconvolutions.

For more information please see:
 * [HRM project website](http://huygens-rm.org/)
 * [HRM documentation on ReadTheDocs.org](http://huygens-remote-manager.readthedocs.org/en/latest/)
 * [HRM API](http://api.huygens-rm.org/html/index.html)

## Download the latest production version

You can download the latest version from https://www.huygens-rm.org/wp/?page_id=11. This is a complete distribution, ready to configure and install as explained in the [installation instruction](https://huygens-remote-manager.readthedocs.io/en/latest/admin/index.html).

## For developers 

HRM requires several third-party libraries to work. After checking out the code, a setup step is required before the HRM is ready to be configured and deployed. Both a **development** and a **release** environment can easily be bootstrapped. Please follow the instructions below.

### Set up a development environment

In the console, run:

```bash
$ cd $HRM_ROOT
$ ./setup/setup_devel.sh 
```

This will update composer, and download and install all third-party libraries used for development. Please notice that the the development dependencies are way more than those needed for release (see below).

### Package an HRM release

In the console, run:

```bash
$ cd $HRM_ROOT
$ ./setup/package_release.sh workdir archive_name 
```

This will update composer, download and install all third-party libraries necessary for the release version of HRM, and then package everything into a zip file ready for distribution.

Example:

```bash
$ cd $HRM_ROOT
$ ./setup/package_release.sh /tmp ~/hrm_3.7.1.zip
```
