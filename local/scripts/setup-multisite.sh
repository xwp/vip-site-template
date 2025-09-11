#!/bin/bash

# Setup WordPress multisite with two sites
echo "Setting up WordPress multisite..."

# Install multisite with the network (site 1) at network.local.wpenv.net
npm run cli -- wp core multisite-install

# Create a subdomain site with slug "main" (will be main.network.local.wpenv.net)
echo "Creating main site (expected to be site 2)..."
SITE_ID=$(npm run cli --silent -- wp site create --slug=main --title="VIP Go" --email=local@devgo.vip --porcelain)
echo "Site created with ID: $SITE_ID"
CREATED_URL=$(npm run cli --silent -- wp site list --field=url --blog_id=$SITE_ID)
echo "Site created with URL: $CREATED_URL"

# Strip protocol and trailing slash from URL for search-replace
CREATED_DOMAIN=$(echo $CREATED_URL | awk -F'[/:]' '{print $4}')
echo "Created domain: $CREATED_DOMAIN"

# Update the main site URL from main.network.local.wpenv.net to main.local.wpenv.net
echo "Updating site from $CREATED_DOMAIN to main.local.wpenv.net..."
npm run cli -- wp search-replace "$CREATED_DOMAIN" "main.local.wpenv.net" --all-tables --precise --recurse-objects --skip-columns=guid

# Add devgo user to site 2 as administrator
echo "Adding devgo user to main site..."
npm run cli -- wp user set-role devgo administrator --url=main.local.wpenv.net

# Enable both themes at network level (makes them available to all sites)
echo "Enabling themes at network level..."
npm run cli -- wp theme enable twentytwentyfive --network
npm run cli -- wp theme enable example-theme --network

# Activate Twenty Twenty-Five on the network site (site 1)
echo "Activating Twenty Twenty-Five on network site..."
npm run cli -- wp theme activate twentytwentyfive --url=network.local.wpenv.net

# Activate Example Theme on the main site (site 2)
echo "Activating Example Theme on main site..."
npm run cli -- wp theme activate example-theme --url=main.local.wpenv.net

# List all sites to confirm
echo -e "\nSites created:"
npm run cli -- wp site list

# Flush rewrite rules
echo "Flushing rewrite rules..."
npm run cli -- wp rewrite flush --url=network.local.wpenv.net
npm run cli -- wp rewrite flush --url=main.local.wpenv.net
