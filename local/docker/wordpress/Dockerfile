FROM wordpress:php8.0-apache

# Set cache directories to a predictable location for re-use.
ENV COMPOSER_HOME /tmp/.composer
ENV npm_config_cache /tmp/.npmcache

# Add path to Composer binaries to this environment.
ENV PATH /var/www/html/wp-content/plugins/vendor/bin:$PATH

# Development tooling dependencies
RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		curl netcat pv bash less default-mysql-client ssh git zip unzip sudo \
		msmtp libz-dev libmemcached-dev libsecret-1-0 \
	&& rm -rf /var/lib/apt/lists/*

# Install Node.js.
RUN curl -sL https://deb.nodesource.com/setup_16.x | bash - \
	&& apt-get install -y nodejs

# Install PHP Composer.
RUN curl -s https://getcomposer.org/installer | php --version=2.4.4 --filename=composer --install-dir=/usr/local/bin

# Include our custom config for PHP and Xdebug.
COPY config/php/* /usr/local/etc/php/conf.d/

# Setup xdebug and memcache.
RUN pecl install xdebug-3.1.5 memcache-8.0 \
	&& docker-php-ext-enable xdebug memcache;

# Enable HTTPS endpoints for reverse proxy requests. Used for images.
COPY config/apache/proxy-ssl.conf /etc/apache2/conf-available/proxy-ssl.conf
RUN a2enconf proxy-ssl

# Enable the proxy module to reverse-proxy images to production server.
RUN a2enmod proxy proxy_http ssl
