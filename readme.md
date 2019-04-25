# VIP Go Site Boilerplate

[![Build Status](https://travis-ci.com/xwp/vip-go-site.svg?branch=master)](https://travis-ci.com/xwp/vip-go-site)


A modern setup for [WordPress VIP Go](https://vip.wordpress.com/documentation/vip-go/) hosted projects with:

- 🏭 Composer for adding project dependencies, including plugins and themes.
- 🚀 Composer autoloader for using any of the popular PHP packages anywhere in the codebase.
- 👩‍💻 Local development environment based on Docker that can be run inside Vagrant without having to install Docker on the host machine.


## Requirements

- PHP 7.2 or higher
- [Composer](https://getcomposer.org)
- [Vagrant](https://www.vagrantup.com) or [Docker with Docker Compose](https://docs.docker.com/compose/install/)

We suggest using [Homebrew](https://brew.sh) for installing all project dependencies.


## Usage

- Project plugins and themes can be added as Composer dependencies or manualy to this repository under `public/plugins/your-plugin` and `public/themes/your-theme`.

- Composer dependencies are placed under `public/plugins/vendor`.

- Composer autoloader `public/plugins/vendor/autoload.php` is included in `public/vip-config/vip-config.php`.

- [Composer installers](https://github.com/composer/installers) is used to map WordPress plugins, themes and mu-plugins to sub-directories under `public`.


## To Do

- Document project structure.
- Add a sample deployment pipeline.


## Maintance Tasks

- Keep the `public` directory in sync with any updates to the [VIP Go skeleton repository](https://github.com/automattic/vip-go-mu-plugins-built). It is not added as a project dependency because it requires plugins and themes to be placed _inside_ it.


## Contribute

All suggestions and contributions are welcome!
