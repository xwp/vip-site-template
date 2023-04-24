/* globals newspack_popups_settings */

/**
 * WordPress dependencies
 */
import { render, useState } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	FlexBlock,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';

/**
 * Newspack dependencies.
 */
import { NewspackLogo } from 'newspack-components';

/**
 * Internal dependencies
 */
import './style.scss';

const App = () => {
	const [ inFlight, setInFlight ] = useState( false );
	const [ settings, setSettings ] = useState( newspack_popups_settings );
	const [ settingsToUpdate, setSettingsToUpdate ] = useState( {} );
	const [ error, setError ] = useState( null );
	const handleSettingChange = option_name => option_value => {
		const newSettings = { ...settingsToUpdate };
		newSettings[ option_name ] = option_value;
		setSettingsToUpdate( newSettings );
	};
	const handleSave = () => {
		setError( null );
		setInFlight( true );
		apiFetch( {
			path: '/newspack-popups/v1/settings/',
			method: 'POST',
			data: { settingsToUpdate },
		} )
			.then( response => {
				setSettingsToUpdate( {} );
				setSettings( response );
			} )
			.catch( e => {
				setError( e.message || __( 'Error updating settings.', 'newspack-popups' ) );
			} )
			.finally( () => {
				setInFlight( false );
			} );
	};

	const renderSetting = setting => {
		if ( setting.description && 'active' !== setting.key ) {
			const props = {
				key: setting.key,
				label: setting.description,
				help: setting.help,
				disabled: inFlight,
				onChange: handleSettingChange( setting.key ),
			};
			switch ( setting.type ) {
				case 'string':
					return (
						<TextControl
							{ ...props }
							value={
								settingsToUpdate.hasOwnProperty( setting.key )
									? settingsToUpdate[ setting.key ]
									: setting.value
							}
						/>
					);
				case 'select':
					return (
						<SelectControl
							{ ...props }
							value={
								settingsToUpdate.hasOwnProperty( setting.key )
									? settingsToUpdate[ setting.key ]
									: setting.value
							}
							options={ setting.options }
						/>
					);
				default:
					return (
						<CheckboxControl
							{ ...props }
							checked={
								settingsToUpdate.hasOwnProperty( setting.key )
									? settingsToUpdate[ setting.key ]
									: !! setting.value
							}
						/>
					);
			}
		}
		return null;
	};

	return (
		<div className="newspack-campaigns__wrapper">
			<div className="newspack-logo__wrapper">
				<Button
					className="newspack-logo-button"
					href="https://newspack.pub/"
					target="_blank"
					label={ __( 'By Newspack' ) }
				>
					<NewspackLogo height={ 32 } />
				</Button>
			</div>
			<Card>
				<CardHeader isShady>
					<FlexBlock>
						<h2>{ __( 'Settings', 'newspack-popups' ) }</h2>
					</FlexBlock>
				</CardHeader>
				<CardBody>
					{ settings.map( renderSetting ) }
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<div className="newspack-popups-save">
						<Button
							disabled={ inFlight || 0 === Object.keys( settingsToUpdate ).length }
							onClick={ handleSave }
						>
							{ __( 'Save', 'newspack-popups' ) }
						</Button>
					</div>
				</CardBody>
			</Card>
		</div>
	);
};

domReady( () => {
	const element = document.getElementById( 'newspack-popups-settings-root' );
	render( <App />, element );
} );
