# VIP Go Site Boilerplate

[![Build Status](https://travis-ci.com/xwp/vip-go-site.svg?branch=master)](https://travis-ci.com/xwp/vip-go-site)


Site setup, development environment and deploy tooling for [WordPress VIP Go](https://wpvip.com/documentation/vip-go/):

- Uses Composer for adding project dependencies, including plugins and themes.
- Uses Composer autoloader for using any of the popular PHP packages anywhere in the codebase.
- Includes a local development environment based on Docker with support for PHP Xdebug and a mail catcher.
- Includes automated build and deploy pipelines to WordPress VIP Go using Travis CI.


## Links & Resources

- [VIP Go dashboard](https://dashboard.wpvip.com)
- [VIP Go documentation](https://wpvip.com/documentation/)
- [VaultPress](https://vaultpress.com)
- [NewRelic dashboard](https://rpm.newrelic.com)


## Requirements

- PHP 7.2 or higher
- [Composer](https://getcomposer.org)
- [Node.js](https://nodejs.org) version 12
- [Docker with Docker Compose](https://docs.docker.com/compose/install/)
- [rsync](https://rsync.samba.org) for deployments


### VIP Go Configuration

The following configuration must be requested from VIP Go to use this site repository:

1. Deployments from `*-built` branches such as `master-built` and `develop-built`.
2. Staging environment tracking the `develop-built` branch.


### Install Dependencies

We suggest using [Homebrew](https://brew.sh) on macOS or [Chocolatey](https://chocolatey.org) for Windows to install the project dependencies.

	brew install git php composer node@12 mkcert
	brew cask install docker


### Code Editor and Git Client

This repository includes a list of suggested extensions for the [Visual Studio Code editor](https://code.visualstudio.com) and Xdebug support in the `.vscode` directory.

A user-friendly Git client such as [GitHub Desktop](https://desktop.github.com) or [Tower](https://www.git-tower.com/mac) enables smaller commits and simplifies merge conflict resolution.


## Overview

- Project plugins and themes can be added as Composer dependencies or manualy to this repository under `plugins/your-plugin` and `themes/your-theme`.
- Composer dependencies are placed under `plugins/vendor` since it has to be in the same location relative to the project root (which is not the case for `vip-config` which is mapped to the WP root directory on the server).
- Composer autoloader `plugins/vendor/autoload.php` is included in `publicvip-config/vip-config.php`.


## Initial Setup

Important: This section can be deleted once you've completed the initial setup from the VIP Go Site template.

1. Ensure that VIP has configured the site to deploy from the `*-built` branches.

2. Create a fresh Git repository from this reference repository:

		composer create-project xwp/vip-go-site --stability dev

3. Add your theme and plugins as Composer dependencies:

		composer require your/theme your/plugin another/plugin

	or by manually copying them to `themes` or `plugins`. Remember to start tracking those directories by excluding them in `themes/.gitignore` and `plugins/.gitignore`.

3. Add the VIP Go upstream repository as another remote to this repository locally and force-push the current `master` to that upstream repository to override the `master` branch with this. Do the same for the `develop` branch.


## Setup 🛠

1. Clone this repository:

		git clone git@github.com:wpcomvip/example.git

2. Move into the project directory:

		cd example

3. Install the project dependencies:

		npm install

4. Start the development environment using Docker:

		npm run start

	and `npm run stop` to stop the virtual environment at any time. Run `npm run start-debug` to start the environment in debug mode where all output from containers is displayed. Run `npm run stop-all` to stop all active Docker containers in case you're running into port conflicts.

5. Install the local WordPress multisite environment:

		npm run setup

	with the configuration from `local/wp-cli.yml`.

6. Visit [local.devgo.vip](https://local.devgo.vip) to view the development environment. WordPress username `devgo` and password `devgo`.

7. Visit [mail.local.devgo.vip](https://local.devgo.vip) to view all emails sent by WordPress.

The local development environment uses a self-signed SSL sertificate for HTTPS so the "Your connection is not private" error can be ignored to visit the site.


## Contribute

1. Setup the local environment environment as described in the "Setup" section above.

2. Create a Git branch such as `feature/name` or `fix/vertical-scroll` when you start working on a feature or a bug fix. Commit your work to that branch until it's ready for quality assurance and testing.

3. Open [a pull request](https://help.github.com/en/desktop/contributing-to-projects/creating-a-pull-request) from your feature branch to the `develop` branch or the staging environment.

4. Review any feedback from the automated checks. Note that your local environment is configured to automatically check for any issues before each commit so there should be very few issues if you commit early and often.

5. Merge the feature branch into `develop` on GitHub if all check pass. The automated [Travis CI workflow](https://travis-ci.com/xwp/vip-go-site) (see the "Deployments" section below for details) will deploy it to the `develop-built` branch.

6. Test your feature on the VIP Go staging server. Open a new pull request from the same feature branch to `develop` if any fixes or changes are necessary.

7. Once the feature is ready for production, open a new pull request from the same feature branch to the `master` branch.

8. Ensure that all automated checks pass and merge in the pull request. The automated [Travis CI workflow](https://travis-ci.com/xwp/vip-go-site) will deploy it to the `master-build` branch.


## Plugins and Themes

Add new themes and plugins as Composer dependencies:

	composer require your/theme your/plugin another/plugin

or manually copy them to `themes`, `plugins` or `client-mu-plugins` directories. Remember to start tracking the directories copied manually by excluding them from being ignored in `themes/.gitignore` and `plugins/.gitignore`.

Use `client-mu-plugins/plugin-loader.php` to force-enable certain plugins.

To update plugins and themes added as Composer dependencies, use `composer install package/name` or `composer install --dev package/name` where `package/name` is the name of the plugin or theme package. Be sure to commit the updated `composer.json` with `composer.lock` to the GitHub repository.

For manually installed plugins and themes replace the directory with the updated set of files and commit them to the GitHub repository.


## Local Development Environment

We use Docker containers to replicate the VIP Go production environment with all VIP dependencies added as Composer packages and mapped to specific directories inside the containers as defined in `docker-compose.yml`.

Requests to port 80 of the container host are captured by an Nginx proxy container that routes all requests to the necessary service container based on the HTTP host name.


### Importing and Exporting Data

Use [VaultPress](https://vaultpress.com) to download the database data from the production environment.

- Run `npm run cli -- wp db export` to export and backup the database of your local development environment which will place a file like `wordpress-2020-03-04-448b132.sql` in the `local/public/wp` directory.

- Run `npm run cli -- wp db import export.sql` to import `local/public/wp/export.sql` into your local development environment. Run `cat export/*.sql > combined.sql` to combine all `.sql` files in the `export` directory into one `combined.sql` file for quicker import (useful for working with exports from VaultPress).


## Scripts 🧰

We use `npm` as the canonical task runner for things like linting files and creating release bundles. Composer scripts (defined in `composer.json`) are used only for PHP related tasks and they have a wrapper npm script in `package.json` for consistency with the rest of the registered tasks.

- `npm run start` and `npm run stop` to start and stop the local development environment. Run `npm run start-debug` to start the environment in debug mode where all output from containers is displayed. Run `npm run stop-all` to stop all active Docker containers in case you're running into port conflicts.

- `npm run lint` to check source code against the defined coding standards.

- `npm run cli -- wp help` where `wp help` is any command to run inside the WordPress docker container. For example, run `npm run cli -- wp plugin list` to list all of the available plugins or `npm run cli -- composer update` to update the Composer dependencies using the PHP binary in the container instead of your host machine. Run `npm run cli -- wp user create devgo local@devgo.vip --role=administrator --user_pass=devgo` to create a new administrator user with `devgo` as username and password.

- `npm run vip` to run [VIP CLI](https://wpvip.com/documentation/vip-go/vip-cli/) commands on staging and production environments.


## Deployments 🚀

The deployment process always starts from the same clean state which enables reproducable builds accross different environments such as local development machines and continuous integration services.

Deployments to the VIP Go upstream repository are handled automatically by the [Travis CI build process](https://travis-ci.com/xwp/vip-go-site) after a feature branch is merged into `master` for production or `develop` for staging.

The Travis CI process (see [`.travis.yml`](.travis.yml)) checks the code against the [VIP coding standards](https://github.com/Automattic/VIP-Coding-Standards), builds the release bundle and pushes the changes to the `master-built` branch for production or `develop-built` for staging deployment.

Internally it runs the `local/scripts/deploy.sh` script which does a clean checkout of the deploy source branch to `local/deploy/src`, runs the build process and copies the project files with the release artifects to `deploy/dist` using `rsync`. It then commits the changes to the matching `*-built` branch which is then imported by the VIP Go servers.

⏳Check the [Travis CI dashboard](https://travis-ci.com/xwp/vip-go-site) to monitor the progress of the automated deployments and check the [NewRelic reports](https://rpm.newrelic.com) for any issues or errors.
