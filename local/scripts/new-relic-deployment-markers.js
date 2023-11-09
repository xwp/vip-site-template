#!/usr/bin/env node
/* eslint-disable no-console */
// run this command with the following arguments:
// -s, --search: The search term to use to find the entity GUIDs to create deployment markers for.
// -a, --api_key: The API key to use to authenticate with New Relic, follow the instructions here to create one: https://docs.newrelic.com/docs/apis/get-started/intro-apis/types-new-relic-api-keys#user-api-key
// -c, --commit: The commit hash to use for the deployment marker.
// -u, --user: The user to use for the deployment marker.
// -d, --description: The description to use for the deployment marker.
// e.g. node new-relic-deployment-markers.js -s "my-site" -a "my-api-key" -c "my-commit-hash" -u "my-user" -d "my-description"

const https = require( 'https' );
const process = require( 'process' );

// Parse command line arguments
const args = process.argv.slice( 2 );
let search, apiKey, commit, user, description;

while ( args.length > 0 ) {
	const key = args.shift();

	switch ( key ) {
		case '-s':
		case '--search':
			search = args.shift();
			break;
		case '-a':
		case '--api_key':
			apiKey = args.shift();
			break;
		case '-c':
		case '--commit':
			commit = args.shift();
			break;
		case '-u':
		case '--user':
			user = args.shift();
			break;
		case '-d':
		case '--description':
			description = args.shift();
			break;
		default:
			// eslint-disable-next-line no-console
			console.error( `Unknown option: ${ key }` );
			process.exit( 1 );
	}
}

if ( ! search ) {
	// eslint-disable-next-line no-console
	console.error( 'Missing required argument: --search' );
	process.exit( 1 );
}

if ( ! apiKey ) {
	// eslint-disable-next-line no-console
	console.error( 'Missing required argument: --api_key' );
	process.exit( 1 );
}

const searchQuery = {
	query: `{
		actor {
			entitySearch( queryBuilder: { name: ${ JSON.stringify( search ) } } ) {
				count
				query
				results {
					entities {
						name
						guid
					}
				}
			}
		}
	}`,
	variables: '',
};

const searchRequestOptions = {
	method: 'POST',
	headers: {
		'Content-Type': 'application/json',
		'API-Key': apiKey,
	},
};

const searchRequest = https.request( 'https://api.newrelic.com/graphql', searchRequestOptions, ( searchResponse ) => {
	let responseBody = '';

	searchResponse.on( 'data', ( chunk ) => {
		responseBody += chunk;
	} );

	searchResponse.on( 'end', () => {
		const guids = JSON.parse( responseBody ).data.actor.entitySearch.results.entities.map( ( entity ) => entity.guid );

		guids.forEach( ( guid ) => {
			const timestamp = Date.now() + ( 120 * 1000 ); // Add two minutes to current timestamp to acocunt for difference between actual deployment from VIP.

			const deploymentMarkerQuery = {
				query: `mutation {
					changeTrackingCreateDeployment(
							deployment: {
								version: ${ JSON.stringify( commit ) },
								entityGuid: ${ JSON.stringify( guid ) },
								timestamp: ${ timestamp },
								commit: ${ JSON.stringify( commit ) },
								user: ${ JSON.stringify( user ) },
								description: ${ JSON.stringify( description ) }
							}
						) {
						changelog
						commit
						deepLink
						deploymentId
						deploymentType
						description
						groupId
						user
					}
				}`,
				variables: '',
			};

			const deploymentRequestOptions = {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'API-Key': apiKey,
				},
			};

			const deploymentRequest = https.request( 'https://api.newrelic.com/graphql', deploymentRequestOptions, ( deploymentResponse ) => {
				let deploymentResponseBody = '';

				deploymentResponse.on( 'data', ( chunk ) => {
					deploymentResponseBody += chunk;
				} );

				deploymentResponse.on( 'end', () => {
					if ( deploymentResponse.statusCode >= 200 && deploymentResponse.statusCode < 300 ) {
						console.log( `Creating deployment marker for ${ guid } was successful.` );
						console.log( 'Response:', JSON.parse( deploymentResponseBody ) );
					} else {
						console.error( `Failed to create deployment marker for ${ guid }.` );
						console.error( 'Error response:', JSON.parse( deploymentResponseBody ) );
					}
				} );
			} );

			deploymentRequest.write( JSON.stringify( deploymentMarkerQuery ) );
			deploymentRequest.end();
		} );
	} );
} );

searchRequest.write( JSON.stringify( searchQuery ) );
searchRequest.end();
