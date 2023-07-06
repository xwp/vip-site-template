# VIP Go Site Boilerplate

[![Travis CI Status](https://app.travis-ci.com/xwp/vip-site-template.svg?branch=master)](https://app.travis-ci.com/xwp/vip-site-template)
[![GitHub Action CI Status](https://github.com/xwp/vip-site-template/actions/workflows/test-deploy.yml/badge.svg)](https://github.com/xwp/vip-site-template/actions/workflows/test-deploy.yml)


Site setup, development environment and deploy tooling for [WordPress VIP](https://docs.wpvip.com/technical-references/vip-platform/):

- Uses Composer for adding project dependencies, including plugins and themes.
- Uses Composer autoloader for using any of the popular PHP packages anywhere in the codebase.
- Includes a local development environment based on Docker with support for PHP Xdebug and a mail catcher.
- Includes automated build and deploy pipelines to WordPress VIP Go using Travis CI or GitHub Actions.


## Links & Resources

- [VIP Go dashboard](https://dashboard.wpvip.com)
- [VIP Go documentation](https://docs.wpvip.com)
- [NewRelic dashboard](https://rpm.newrelic.com)


## Requirements

- PHP 8.0
- [Composer](https://getcomposer.org)
- [Node.js](https://nodejs.org) version 16
- [Docker with Docker Compose](https://docs.docker.com/compose/install/)
- [rsync](https://rsync.samba.org) for deployments


### Install Dependencies

We suggest using [Homebrew](https://brew.sh) on macOS or [Chocolatey](https://chocolatey.org) for Windows to install the project dependencies.

	brew install git php@8.0 composer node@16 mkcert
	brew install --cask docker


### Code Editor and Git Client

This repository includes a list of suggested extensions for the [Visual Studio Code editor](https://code.visualstudio.com) and Xdebug support in the `.vscode` directory.

A user-friendly Git client such as [GitHub Desktop](https://desktop.github.com) or [Tower](https://www.git-tower.com/mac) enables smaller commits and simplifies merge conflict resolution.


## Overview

- Project plugins and themes can be added as Composer dependencies or manualy to this repository under `plugins/your-plugin` and `themes/your-theme`.
- Composer dependencies are placed under `plugins/vendor` since it has to be in the same location relative to the project root (which is not the case for `vip-config` which is mapped to the WP root directory on the server).
- Composer autoloader `plugins/vendor/autoload.php` is included in `vip-config/vip-config.php`.


## Initial Setup

**Important:** This section can be deleted once you've completed the initial setup from the VIP Go Site template.

The site project generated from this template is designed to be hosted under the [WP VIP GitHub organization](https://github.com/wpcomvip) which is why it uses Travis for deployments since VIP repositories currently don't support GitHub actions. It also includes a [GitHub Action based workflow](.github/workflows) which can be used for projects hosted under any GitHub organization that does support GitHub Actions.

### VIP Platform Configuration

The following configuration must be requested from VIP Go to use this site repository:

1. Deployments from `*-built` branches such as `master-built` and `develop-built`.
2. Staging environment tracking the `develop-built` branch.

### VIP Repository Setup

1. Ensure that VIP has configured the site to deploy from the `*-built` branches.

2. Create a fresh local Git repository from this reference repository:

		composer create-project xwp/vip-site-template --stability dev

3. Add your theme and plugins as Composer dependencies:

		composer require your/theme your/plugin another/plugin

	or by manually copying them to `themes` or `plugins`. Remember to start tracking those directories by excluding them in `themes/.gitignore` and `plugins/.gitignore`.

4. Adjust strings and URLs in all files match your project. Search and replace the following strings: `xwp/vip-site-template`, `wpcomvip/devgo-vip`, `XWP\Vip_Site_Template`, `local.wpenv.net`.

4. If hosting this source repository under the [VIP GitHub organization](https://github.com/wpcomvip), add the VIP Go upstream repository as another remote to this repository locally and force-push the current `master` to that upstream repository to override the `master` branch with this. Do the same for the `develop` branch.

   For hosting this source repository under any other GitHub organization, simply push it to that repository.

5. Generate a fresh SSH key pair and add the private part [to the Travis CI configuration](https://docs.travis-ci.com/user/private-dependencies/#user-key) or [as a `DEPLOY_SSH_KEY` GitHub Actions secret](https://docs.github.com/en/actions/security-guides/encrypted-secrets), and the public part as the [Deploy key to the VIP GitHub repository](https://docs.github.com/en/developers/overview/managing-deploy-keys).

        ssh-keygen -f deploy-key -t rsa -b 4096 -C "technology+project-name@xwp.co"

   This provides Travis CI or GitHub Actions with access to the VIP repository for deployments.

6. Remove references to either Travis CI or GitHub actions from this README depending on which deploy strategy was selected.

7. Remove these initial setup instructions from the README after the initial project setup.

## Setup 🛠

1. Clone this repository:

		git clone git@github.com:wpcomvip/devgo-vip.git

2. Move into the project directory:

		cd devgo-vip

3. Install the project dependencies:

		npm install

4. Start the development environment using Docker:

		npm run start

	and `npm run stop` to stop the virtual environment at any time. Run `npm run start-debug` to start the environment in debug mode where all output from containers is displayed. Run `npm run stop-all` to stop all active Docker containers in case you're running into port conflicts.

5. Install the local WordPress multisite environment:

		npm run setup

	with the configuration from `local/public/wp-cli.yml`.

6. Visit [local.wpenv.net](https://local.wpenv.net) to view the development environment. WordPress username `devgo` and password `devgo`.

7. Visit [mail.local.wpenv.net](https://mail.local.wpenv.net) to view all emails sent by WordPress.

The local development environment uses a self-signed SSL sertificate for HTTPS so the "Your connection is not private" error can be ignored to visit the site.

### Resolving Port Conflicts

Docker engine shares the networking interface with the host computer so all the ports used by the containers need to be free and unused by any other services such as a DNS resolver on port 53, MySQL service on port 3306 or another web server running on port 80.

Use the included `npm run stop-all` command to stop all containers running Docker containers on the host machine.

On Debian and Ubuntu systems use `sudo systemctl stop ...` to disable those services. For example:

- `sudo systemctl stop mysql` to stop MySQL
- `sudo systemctl stop apache2` to stop Apache
- `sudo systemctl stop systemd-resolved` to stop the local name server.

Alternativelly, you can adjust the port mappings in `docker-compose.yml` to use different ports.


## Contribute

1. Setup the local environment environment as described in the "Setup" section above.

2. Create a Git branch such as `feature/name` or `fix/vertical-scroll` when you start working on a feature or a bug fix. Commit your work to that branch until it's ready for quality assurance and testing.

3. Open [a pull request](https://help.github.com/en/desktop/contributing-to-projects/creating-a-pull-request) from your feature branch to the `develop` branch or the staging environment.

4. Review any feedback from the automated checks. Note that your local environment is configured to automatically check for any issues before each commit so there should be very few issues if you commit early and often.

5. Merge the feature branch into `develop` on GitHub if all check pass. The automated [Travis CI workflow](https://travis-ci.com/xwp/vip-site-template) (see the "Deployments" section below for details) or [GitHub Actions workflow](https://github.com/xwp/vip-site-template/actions) will deploy it to the `develop-built` branch.

6. Test your feature on the VIP Go staging server. Open a new pull request from the same feature branch to `develop` if any fixes or changes are necessary.

7. Once the feature is ready for production, open a new pull request from the same feature branch to the `master` branch.

8. Ensure that all automated checks pass and merge in the pull request. The automated [Travis CI workflow](https://travis-ci.com/xwp/vip-site-template) or [GitHub Action workflow]() will deploy it to the `master-built` branch.


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

Use [VIP dashboard or VIP-CLI](https://docs.wpvip.com/technical-references/vip-dashboard/backups/) to download the database data from the production environment.

- Run `npm run vip -- export sql --output=local/public/wp/vip-export.sql` to download the latest available backup to your local computer.

- Run `npm run cli -- wp db export` to export and backup the database of your local development environment which will place a file like `wordpress-2020-03-04-448b132.sql` in the `local/public/wp` directory.

- Run `npm run cli -- wp db import vip-export.sql` to import `local/public/wp/vip-export.sql` into your local development environment.

- Run `npm run cli -- bash -c "pv import.sql | wp db query"` to import a large database file `local/public/wp/vip-export.sql` while monitoring the progress with [`pv`](https://linux.die.net/man/1/pv) which is bundled with the WordPress container. The `bash -c` prefix allows us to run multiple commands inside the container without affecting the main `npm run cli` command.


## Scripts 🧰

We use `npm` as the canonical task runner for things like linting files and creating release bundles. Composer scripts (defined in `composer.json`) are used only for PHP related tasks and they have a wrapper npm script in `package.json` for consistency with the rest of the registered tasks.

- `npm run start` and `npm run stop` to start and stop the local development environment. Run `npm run start-debug` to start the environment in debug mode where all output from containers is displayed. Run `npm run stop-all` to stop all active Docker containers in case you're running into port conflicts. Run `npm run stop -- --volumes` to stop the project containers and delete the database data volume.

- `npm run lint` to check source code against the defined coding standards.

- `npm run cli -- wp help` where `wp help` is any command to run inside the WordPress docker container. For example, run `npm run cli -- wp plugin list` to list all of the available plugins or `npm run cli -- composer update` to update the Composer dependencies using the PHP binary in the container instead of your host machine. Run `npm run cli -- wp user create devgo local@devgo.vip --role=administrator --user_pass=devgo` to create a new administrator user with `devgo` as username and password.

- `npm run vip` to run [VIP CLI](https://wpvip.com/documentation/vip-go/vip-cli/) commands on staging and production environments.


## Deployments 🚀

The deployment process always starts from the same clean state which enables reproducable builds accross different environments such as local development machines and continuous integration services.

Deployments to the VIP upstream repository are handled automatically by the [Travis CI build process](https://travis-ci.com/xwp/vip-site-template) or [GitHub Actions workflow](.github/workflows) after a feature branch is merged into `master` for production or `develop` for staging.

The CI process checks the code against the [VIP coding standards](https://github.com/Automattic/VIP-Coding-Standards), builds the release bundle and pushes the changes to the `master-built` branch for production or `develop-built` for staging deployment.

	┌──────────┐   ┌───────────────────────────┐   ┌────────────────┐
	│  master  ├──►│  Travis / GitHub Actions  ├──►│  master-built  │
	└──────────┘   └───────────────────────────┘   └────────────────┘

Internally it runs the `local/scripts/deploy.sh` script which does a clean checkout of the deploy source branch to `local/deploy/src`, runs the build process and copies the project files with the release artifects to `deploy/dist` using `rsync`. It then commits the changes to the matching `*-built` branch which is then imported by the VIP Go servers.
