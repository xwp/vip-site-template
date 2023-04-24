/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { TextControl, ToggleControl, Button, ButtonGroup, MenuItem } from '@wordpress/components';
import { moreVertical, chevronUp, chevronDown } from '@wordpress/icons';
import { useState } from '@wordpress/element';
import { ESCAPE } from '@wordpress/keycodes';

/**
 * External dependencies
 */
import { omit } from 'lodash';
import classnames from 'classnames';

/**
 * Internal dependencies
 */
import type { AdditionalField, EditProps } from '../../types';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore – these *are* exported by newspack-components.
import { Modal, Popover } from 'newspack-components';

const getUniqID = () => Math.random().toString( 36 ).substring( 2, 7 );
const BASE_CSS_CLASSNAME = 'newspack-blocks-additional-fields-editor';

type EditableKey = keyof Omit< AdditionalField, 'type' >;
const FIELD_PROPS = [
	[ 'label', __( 'Label', 'newspack-blocks' ) ],
	[
		'name',
		__( 'Name', 'newspack-blocks' ),
		__(
			'Name of the field which will be sent to the payment procesor and other third parties. Field names must be unique.',
			'newspack-blocks'
		),
	],
] as [ EditableKey, string, string ][];

const FieldOptions = ( { onEdit, onRemove }: { onEdit: () => void; onRemove: () => void } ) => {
	const [ isVisible, setIsVisible ] = useState( false );
	const toggleVisible = () => setIsVisible( ! isVisible );
	return (
		<div>
			<Button
				onClick={ toggleVisible }
				icon={ moreVertical }
				label={ __( 'Options', 'newspack-blocks' ) }
			/>
			{ isVisible && (
				<Popover
					position="bottom left"
					onFocusOutside={ toggleVisible }
					onKeyDown={ ( event: KeyboardEvent ) => ESCAPE === event.keyCode && toggleVisible }
					className={ `${ BASE_CSS_CLASSNAME }__options-popover` }
				>
					<MenuItem onClick={ onEdit } isLink>
						{ __( 'Edit', 'newspack-blocks' ) }
					</MenuItem>
					<MenuItem
						isDestructive
						onClick={ () => {
							onRemove();
							toggleVisible();
						} }
						isLink
					>
						{ __( 'Remove', 'newspack-blocks' ) }
					</MenuItem>
				</Popover>
			) }
		</div>
	);
};

const FieldEditor = ( {
	attributes,
	setAttributes,
	field,
	closeEditor,
	updateField,
}: Pick< EditProps, 'attributes' | 'setAttributes' > & {
	field: AdditionalField;
	closeEditor: () => void;
	updateField: ( key: EditableKey ) => ( value: string | boolean | number ) => void;
} ) => {
	const onSave = () => {
		const fieldToSave = omit( field, [ 'isNew', 'fieldIndex' ] );
		setAttributes( {
			additionalFields: field.isNew
				? [ ...attributes.additionalFields, fieldToSave ]
				: attributes.additionalFields.map( ( _field, i ) =>
						field.fieldIndex === i ? fieldToSave : _field
				  ),
		} );
		closeEditor();
	};
	const onRemove = () => {
		setAttributes( {
			additionalFields: attributes.additionalFields.filter( ( { name } ) => name !== field.name ),
		} );
		closeEditor();
	};
	const getValidationMessage = ( key: EditableKey ) => {
		switch ( key ) {
			case 'name':
				const isValid =
					attributes.additionalFields.filter(
						( { name }, i ) => i !== field.fieldIndex && name === field.name
					).length === 0;
				return ! isValid ? __( 'Name already exists.', 'newspack-blocks' ) : '';
		}
	};
	return (
		<>
			{ FIELD_PROPS.map( ( [ key, label, help ] ) => {
				const validationMessage = getValidationMessage( key );
				return (
					<div key={ key }>
						<TextControl
							label={ label }
							placeholder={ label }
							value={ field[ key ] }
							onChange={ updateField( key ) }
							className={ classnames( {
								[ `${ BASE_CSS_CLASSNAME }__field-edited--name` ]: key === 'name',
								[ `${ BASE_CSS_CLASSNAME }__field-edited--invalid` ]: validationMessage,
							} ) }
						/>
						{ validationMessage && (
							<div className={ `${ BASE_CSS_CLASSNAME }__field-edited__validation-message` }>
								{ validationMessage }
							</div>
						) }
						{ help && (
							<div className={ `${ BASE_CSS_CLASSNAME }__field-edited__help` }>{ help }</div>
						) }
					</div>
				);
			} ) }
			<ToggleControl
				label={ __( 'Required', 'newspack-blocks' ) }
				checked={ field.isRequired }
				onChange={ updateField( 'isRequired' ) }
			/>
			<div className={ `${ BASE_CSS_CLASSNAME }__field-edited__width` }>
				<div>{ __( 'Field width:', 'newspack-blocks' ) }</div>
				<ButtonGroup>
					{ [ 100, 66.66, 50, 33.33 ].map( width => (
						<Button
							key={ width }
							onClick={ () => updateField( 'width' )( width ) }
							isPrimary={ field.width === width }
						>
							{ Math.round( width ) }%
						</Button>
					) ) }
				</ButtonGroup>
			</div>
			<div className={ `${ BASE_CSS_CLASSNAME }__edit-buttons` }>
				<Button isLink onClick={ closeEditor }>
					{ __( 'Cancel', 'newspack-blocks' ) }
				</Button>
				{ ! field.isNew && (
					<Button variant="secondary" isDestructive onClick={ onRemove }>
						{ __( 'Remove', 'newspack-blocks' ) }
					</Button>
				) }
				<Button isPrimary onClick={ onSave }>
					{ field.isNew ? __( 'Save', 'newspack-blocks' ) : __( 'Update', 'newspack-blocks' ) }
				</Button>
			</div>
		</>
	);
};

const AdditionalFields = ( {
	attributes,
	setAttributes,
}: Pick< EditProps, 'attributes' | 'setAttributes' > ) => {
	const [ editedField, setEditedField ] = useState< AdditionalField | null >( null );
	const moveField = ( fieldIndex: number, targetIndex: number ) => () => {
		const withoutMovedField = attributes.additionalFields.filter( ( _, i ) => i !== fieldIndex );
		setAttributes( {
			additionalFields: [
				...withoutMovedField.slice( 0, targetIndex ),
				attributes.additionalFields[ fieldIndex ],
				...withoutMovedField.slice( targetIndex ),
			],
		} );
	};
	return (
		<>
			<p>
				{ __(
					'Collect additional data from donors by defining custom form fields.',
					'newspack-blocks'
				) }
			</p>
			<div className={ BASE_CSS_CLASSNAME }>
				{ attributes.additionalFields.map( ( field, i ) => {
					const onEdit = () => setEditedField( { ...field, fieldIndex: i } );
					return (
						<div key={ i } className={ `${ BASE_CSS_CLASSNAME }__field` }>
							<div className={ `${ BASE_CSS_CLASSNAME }__field__left-section` }>
								<div>
									<Button
										onClick={ moveField( i, i - 1 ) }
										icon={ chevronUp }
										label={ __( 'Move up', 'newspack-blocks' ) }
										disabled={ i === 0 }
									/>
									<Button
										onClick={ moveField( i, i + 1 ) }
										icon={ chevronDown }
										label={ __( 'Move down', 'newspack-blocks' ) }
										disabled={ i === attributes.additionalFields.length - 1 }
									/>
								</div>
								<span>{ field.label }</span>
							</div>

							<FieldOptions
								onEdit={ onEdit }
								onRemove={ () =>
									setAttributes( {
										additionalFields: attributes.additionalFields.filter(
											( value, index ) => index !== i
										),
									} )
								}
							/>
						</div>
					);
				} ) }
				<Button
					variant="secondary"
					onClick={ () => {
						const newField: AdditionalField = {
							type: 'text',
							name: `field-${ getUniqID() }`,
							label: `Field ${ attributes.additionalFields.length }`,
							isRequired: false,
							width: 100,
							isNew: true,
						};
						setEditedField( newField );
					} }
				>
					{ __( 'Add data field', 'newspack-blocks' ) }
				</Button>
			</div>

			{ editedField && (
				<Modal
					className={ `${ BASE_CSS_CLASSNAME }__modal` }
					title={
						editedField.isNew
							? __( 'Add data field', 'newspack-blocks' )
							: __( 'Edit data field', 'newspack-blocks' )
					}
					onRequestClose={ () => setEditedField( null ) }
				>
					<FieldEditor
						field={ editedField }
						setAttributes={ setAttributes }
						attributes={ attributes }
						closeEditor={ () => setEditedField( null ) }
						updateField={ ( key: EditableKey ) => ( value: string | boolean | number ) => {
							setEditedField( {
								...editedField,
								[ key ]: value,
							} );
						} }
					/>
				</Modal>
			) }
		</>
	);
};

export default AdditionalFields;
