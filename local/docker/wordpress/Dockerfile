FROM wordpress:php8.2-apache

# Can be overridden in docker-compose.yml via the build args.
ARG NODE_VERSION=18

# Set cache directories to a predictable location for re-use.
ENV COMPOSER_HOME /tmp/.composer
ENV npm_config_cache /tmp/.npmcache

# Add path to Composer binaries to this environment.
ENV PATH /var/www/html/wp-content/plugins/vendor/bin:$PATH

# Allow this to be customized by the environment.
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Replace the hardcoded location with an environment variable.
RUN sed -i 's#/var/www/html#\${APACHE_DOCUMENT_ROOT}#g' /etc/apache2/sites-available/*.conf
RUN sed -i 's#/var/www#\${APACHE_DOCUMENT_ROOT}#g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Development tooling dependencies
RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		curl pv bash less default-mysql-client ssh git zip unzip sudo gnupg \
		msmtp libz-dev libmemcached-dev libsecret-1-0 libssl-dev \
	&& rm -rf /var/lib/apt/lists/*

# Install Node.js per recommended setup.
RUN mkdir -p /etc/apt/keyrings \
	&& curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
	&& echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_VERSION}.x nodistro main" >> /etc/apt/sources.list.d/nodesource.list \
	&& apt-get update \
	&& apt-get -y --no-install-recommends install nodejs \
	&& rm -rf /var/lib/apt/lists/*

# Install PHP Composer.
RUN curl -s https://getcomposer.org/installer | \
	php --version=2.6.6 --filename=composer --install-dir=/usr/local/bin

# Include our custom config for PHP and Xdebug.
COPY config/php/* /usr/local/etc/php/conf.d/

# Setup xdebug and memcached.
RUN pecl install xdebug-3.3.1 memcached-3.2.0 \
	&& docker-php-ext-enable xdebug memcached

# Enable HTTPS endpoints for reverse proxy requests. Used for images.
COPY config/apache/proxy-ssl.conf /etc/apache2/conf-available/proxy-ssl.conf
RUN a2enconf proxy-ssl

# Enable the proxy module to reverse-proxy images to production server.
RUN a2enmod proxy proxy_http ssl
