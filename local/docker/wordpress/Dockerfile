FROM wordpress:php8.0-apache

# Add path to Composer binaries to this environment.
ENV PATH /var/www/html/wp-content/plugins/vendor/bin:$PATH

# Development tooling dependencies
RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		bash less default-mysql-client git zip unzip \
		nodejs npm curl pv \
		msmtp libz-dev libmemcached-dev \
	&& npm install --global npm@latest \
	&& rm -rf /var/lib/apt/lists/*

RUN curl -s https://getcomposer.org/installer | php --version=2.4.4 --filename=composer --install-dir=/usr/local/bin

# Include our custom config for PHP and Xdebug.
COPY config/php/* /usr/local/etc/php/conf.d/

# Setup xdebug and memcache.
RUN pecl install xdebug-3.1.5 memcache-8.0 \
	docker-php-ext-enable xdebug memcache;
