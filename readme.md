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


## Overview

- Project plugins and themes can be added as Composer dependencies or manualy to this repository under `public/plugins/your-plugin` and `public/themes/your-theme`.

- Composer dependencies are placed under `public/plugins/vendor`.

- Composer autoloader `public/plugins/vendor/autoload.php` is included in `public/vip-config/vip-config.php`.

- [Composer installers](https://github.com/composer/installers) maps WordPress plugins, themes and mu-plugins to sub-directories under `public`.


## Usage

1. Create a fresh Git repository from this reference repository:

		composer create-project xwp/vip-go-site --stability dev

2. Add your theme and plugins as Composer dependencies:

		composer require your/theme your/plugin another/plugin

	or by manually copying them from existing repositories to `public/themes` or `public/plugins`. Remember to start tracking those directories by excluding them in `public/themes/.gitignore` and `public/plugins/.gitignore`.

3. If using Docker inside Vagrant, adjust the hostname of the development environment in `Vagrantfile` to match your project.

		- config.vm.hostname = "vipgo"
		+ config.vm.hostname = "your-project"
	
	which will create the development environment at `your-project.local`.

4. Start the development environment using Vagrant:

		vagrant up

	or using Docker Compose:

		docker-compose up

5. Visit [your-project.local](http://your-project.local) (or the default [vipgo.local](http://vipgo.local)) to view the development environment. 


## To Do

- Document project structure.
- Add a sample deployment pipeline.


## Maintance Tasks

- Keep the `public` directory in sync with any updates to the [VIP Go skeleton repository](https://github.com/automattic/vip-go-mu-plugins-built). It is not added as a project dependency because it requires plugins and themes to be placed _inside_ it.


## Contribute

All suggestions and contributions are welcome!
