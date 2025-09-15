#!/bin/bash

NETWORK_DOMAIN="network.local.wpenv.net"
NETWORK_THEME="twentytwentyfive"

MAIN_DOMAIN="main.local.wpenv.net"
MAIN_THEME="example-theme"
MAIN_TITLE="VIP Go"

USER="devgo"
PASS="devgo"

EMAIL="local@devgo.vip"

echo "Setting up WordPress multisite..."

# Get the current SUBDOMAIN_INSTALL value from wp-config.php
# Use grep to check if it exists and what value it has
if grep -q "define.*SUBDOMAIN_INSTALL.*false" ./local/public/wp-config.php 2>/dev/null; then
    ORIGINAL_SUBDOMAIN="false"
elif grep -q "define.*SUBDOMAIN_INSTALL.*true" ./local/public/wp-config.php 2>/dev/null; then
    ORIGINAL_SUBDOMAIN="true"
else
    ORIGINAL_SUBDOMAIN="not_set"
fi
echo "Original SUBDOMAIN_INSTALL value: $ORIGINAL_SUBDOMAIN"

# Temporarily set SUBDOMAIN_INSTALL to true for proper multisite setup
echo "Setting SUBDOMAIN_INSTALL to true for setup..."
if [ "$ORIGINAL_SUBDOMAIN" = "not_set" ]; then
    # Add the constant if it doesn't exist
    npm run cli -- wp config set SUBDOMAIN_INSTALL true --raw --type=constant
else
    # Update the existing constant
    sed -i.bak "s/define.*SUBDOMAIN_INSTALL.*/define( 'SUBDOMAIN_INSTALL', true );/" ./local/public/wp-config.php
fi

echo "Installing multisite with the network site..."
npm run cli -- wp core multisite-install

echo "Creating main site..."
SITE_ID=$(npm run cli --silent -- wp site create --slug=main --title="$MAIN_TITLE" --email=$EMAIL --porcelain)
echo "Main site ID: $SITE_ID"
CREATED_URL=$(npm run cli --silent -- wp site list --field=url --blog_id=$SITE_ID)
echo "Main site URL: $CREATED_URL"
CREATED_DOMAIN=$(echo $CREATED_URL | awk -F'[/:]' '{print $4}')
echo "Main site domain: $CREATED_DOMAIN"

echo "Updating main site from $CREATED_DOMAIN to $MAIN_DOMAIN..."
npm run cli -- wp search-replace "$CREATED_DOMAIN" "$MAIN_DOMAIN" --all-tables --precise --recurse-objects --skip-columns=guid

echo "Adding $USER user to main site as administrator..."
npm run cli -- wp user set-role $USER administrator --url=$MAIN_DOMAIN

echo "Enabling themes at network level (makes them available to all sites)..."
npm run cli -- wp theme enable $NETWORK_THEME --network
npm run cli -- wp theme enable $MAIN_THEME --network

echo "Activating $NETWORK_THEME theme on network site..."
npm run cli -- wp theme activate $NETWORK_THEME --url=$NETWORK_DOMAIN

echo "Activating $MAIN_THEME theme on main site..."
npm run cli -- wp theme activate $MAIN_THEME --url=$MAIN_DOMAIN

echo -e "\nSites created:"
npm run cli -- wp site list

echo "Flushing rewrite rules..."
npm run cli -- wp rewrite flush --url=$NETWORK_DOMAIN
npm run cli -- wp rewrite flush --url=$MAIN_DOMAIN

echo "Flushing cache..."
npm run cli -- wp cache flush --url=$NETWORK_DOMAIN
npm run cli -- wp cache flush --url=$MAIN_DOMAIN

echo "Restoring original SUBDOMAIN_INSTALL value..."
if [ "$ORIGINAL_SUBDOMAIN" = "false" ]; then
    sed -i.bak "s/define.*SUBDOMAIN_INSTALL.*/define( 'SUBDOMAIN_INSTALL', false );/" ./local/public/wp-config.php
    echo "Restored SUBDOMAIN_INSTALL to false"
elif [ "$ORIGINAL_SUBDOMAIN" = "not_set" ]; then
    # Remove the line if it wasn't there before
    sed -i.bak "/define.*SUBDOMAIN_INSTALL/d" ./local/public/wp-config.php
    echo "Removed SUBDOMAIN_INSTALL (wasn't originally set)"
else
    echo "SUBDOMAIN_INSTALL remains true"
fi

# Clean up backup file
rm -f ./local/public/wp-config.php.bak
