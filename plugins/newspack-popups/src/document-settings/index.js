/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { withSelect, withDispatch } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { registerPlugin } from '@wordpress/plugins';
import { ToggleControl } from '@wordpress/components';

const PopupsSettingsPanel = ( { hasDisabledPopups, onChange } ) => (
	<PluginDocumentSettingPanel
		name="newsletters-popups-settings-panel"
		title={ __( 'Newspack Campaigns Settings', 'newspack-popups' ) }
	>
		<ToggleControl
			checked={ hasDisabledPopups }
			onChange={ () => onChange( ! hasDisabledPopups ) }
			label={ __( 'Disable prompts on this post or page', 'newspack-popups' ) }
		/>
	</PluginDocumentSettingPanel>
);

const PopupsSettingsPanelWithSelect = compose( [
	withSelect( select => {
		const { getEditedPostAttribute } = select( 'core/editor' );
		const meta = getEditedPostAttribute( 'meta' );
		return { hasDisabledPopups: meta && meta.newspack_popups_has_disabled_popups };
	} ),
	withDispatch( dispatch => {
		const { editPost } = dispatch( 'core/editor' );
		return {
			onChange: hasDisabledPopups => {
				editPost( { meta: { newspack_popups_has_disabled_popups: hasDisabledPopups } } );
			},
		};
	} ),
] )( PopupsSettingsPanel );

registerPlugin( 'newspack-popups-post-status-info', {
	render: PopupsSettingsPanelWithSelect,
	icon: false,
} );
