/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Fragment, useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Newspack dependencies.
 */
import { Card, Notice, TextControl, SelectControl, Button, ProgressBar } from 'newspack-components';

const { lica_batch_size } = window.newspack_ads_bidding_gam;

const Order = ( {
	orderId = null,
	defaultName = '',
	onPending = () => {},
	onError,
	onSuccess,
	onUnrecoverable,
	onCancel,
	...props
} ) => {
	const [ inFlight, setInFlight ] = useState( false );
	const [ bidders, setBidders ] = useState( props.bidders || {} );
	const [ error, setError ] = useState( null );
	const [ order, setOrder ] = useState( null );
	const [ step, setStep ] = useState( 0 );
	const [ totalBatches, setTotalBatches ] = useState( 1 );
	const [ totalSteps, setTotalSteps ] = useState( 4 );
	const [ isLastAttempt, setLastAttempt ] = useState( false );
	const [ config, setConfig ] = useState( {
		orderId,
		name: ! orderId ? defaultName : '',
		revenueShare: 0,
		bidders: [],
	} );

	const hasIssues = () =>
		order?.order_id &&
		( ! order?.line_item_ids?.length || totalBatches > ( order?.lica_batch_count || 0 ) );

	const canSubmit = () =>
		hasIssues() ||
		! orderId ||
		parseInt( config.revenueShare ) !== parseInt( order?.revenue_share ) ||
		JSON.stringify( config.bidders ) !== JSON.stringify( order?.bidders );

	const buttonText = () =>
		orderId ? __( 'Update Order', 'newspack-ads' ) : __( 'Create Order', 'newspack-ads' );

	const fetchLicaConfig = async id =>
		await apiFetch( { path: `/newspack-ads/v1/bidding/gam/lica_config?id=${ id }` } );

	const getStepName = () => {
		switch ( step ) {
			case 0:
				return '';
			case 1:
				return __( 'Creating Order…', 'newspack-ads' );
			case 2:
				return __( 'Creating Line Items…', 'newspack-ads' );
			default:
				return __( 'Associating Creatives…', 'newspack-ads' );
		}
	};

	useEffect( async () => {
		setInFlight( true );
		try {
			setBidders( await apiFetch( { path: '/newspack-ads/v1/bidders' } ) );
		} catch ( err ) {
			setError( err );
		}
		if ( orderId ) {
			// Fetch order.
			try {
				const data = await apiFetch( {
					path: `/newspack-ads/v1/bidding/gam/order?id=${ orderId }`,
					method: 'GET',
				} );
				setConfig( {
					orderId: data.order_id,
					name: data.order_name,
					revenueShare: data.revenue_share,
					bidders: data.bidders,
				} );
				setOrder( data );
			} catch ( err ) {
				setError( err );
			}
			// Fetch LICA config.
			try {
				const licaConfig = await fetchLicaConfig( orderId );
				const batches = Math.ceil( licaConfig.length / lica_batch_size );
				setTotalBatches( batches );
				setTotalSteps( 3 + batches );
			} catch ( err ) {
				setError( err );
			}
		} else {
			setConfig( {
				orderId: null,
				name: defaultName,
				revenueShare: 0,
				bidders: [],
			} );
		}
		setInFlight( false );
	}, [ orderId ] );

	useEffect( () => {
		onPending( inFlight );
	}, [ inFlight ] );

	const createType = async ( type, requestData = { batch: 0, fixing: false } ) => {
		return await apiFetch( {
			path: '/newspack-ads/v1/bidding/gam/create',
			method: 'POST',
			data: {
				...requestData,
				id: config?.orderId || requestData.id || null,
				type,
				config: {
					order_name: config?.name,
					revenue_share: config?.revenueShare,
					bidders: config?.bidders,
				},
			},
		} );
	};

	const create = async ( fixing = false ) => {
		setError( null );
		setInFlight( true );
		let pendingOrder = { ...order };
		try {
			if ( ! pendingOrder || ! pendingOrder.order_id ) {
				setStep( 1 );
				pendingOrder = await createType( 'order', { fixing } );
				setOrder( pendingOrder );
				setConfig( { ...config, orderId: pendingOrder.order_id } );
			}
			if ( ! pendingOrder?.line_item_ids?.length ) {
				setStep( 2 );
				pendingOrder = await createType( 'line_items', { id: pendingOrder.order_id, fixing } );
				setOrder( pendingOrder );
			}
			const licaConfig = await fetchLicaConfig( pendingOrder.order_id );
			const batches = Math.ceil( licaConfig.length / lica_batch_size );
			setTotalBatches( batches );
			setTotalSteps( 3 + batches );
			const start = pendingOrder?.lica_batch_count || 0;
			if ( batches > start ) {
				for ( let i = start; i < batches; i++ ) {
					const batch = i + 1;
					setStep( 2 + batch );
					pendingOrder = await createType( 'creatives', {
						id: pendingOrder.order_id,
						batch,
						fixing,
					} );
					setOrder( pendingOrder );
				}
			}
			setStep( 3 + batches );
			if ( typeof onSuccess === 'function' ) {
				await onSuccess( pendingOrder );
			}
		} catch ( err ) {
			if ( orderId || isLastAttempt ) {
				// Unrecoverable error.
				setLastAttempt( false );
				setOrder( null );
				setConfig( { ...config, orderId: null } );
				if ( typeof onUnrecoverable === 'function' ) await onUnrecoverable( pendingOrder, err );
			} else {
				// Make it fail unrecoverably if it fails on next attempt.
				if ( pendingOrder?.order_id ) {
					setLastAttempt( true );
				}
				setError( err );
				if ( typeof onError === 'function' ) await onError( err );
			}
		} finally {
			setStep( 0 );
			setInFlight( false );
		}
	};

	const update = async () => {
		setError( null );
		setInFlight( true );
		let data;
		try {
			data = await apiFetch( {
				path: '/newspack-ads/v1/bidding/gam/order',
				method: 'PUT',
				data: {
					id: orderId,
					config: {
						revenue_share: config?.revenueShare,
						bidders: config?.bidders,
					},
				},
			} );
			setOrder( data );
		} catch ( err ) {
			setError( err );
			if ( typeof onError === 'function' ) await onError( err );
		} finally {
			if ( typeof onSuccess === 'function' ) await onSuccess( data );
			setInFlight( false );
		}
	};

	const stepName = getStepName();

	return (
		<Card noBorder>
			{ ! orderId && (
				<p>
					{ __(
						'Create the order and line items on your Google Ad Manager network according to the pre-defined price bucket settings.',
						'newspack-ads'
					) }
				</p>
			) }
			{ error && error.data?.status !== '404' && <Notice isError noticeText={ error.message } /> }
			<TextControl
				label={ __( 'Order name', 'newspack-ads' ) }
				disabled={ inFlight || order?.order_name }
				value={ order?.order_name ? order.order_name : config.name }
				onChange={ value =>
					setConfig( {
						...config,
						name: value,
					} )
				}
			/>
			<TextControl
				type="number"
				min="0"
				max="100"
				label={ __( 'Bidder Revenue Share', 'newspack-ads' ) }
				help={ __(
					'This is agreed upon revenue share between you and the bid partner. Input the percentage that goes to the bidder, i.e. 20 for 20%.',
					'newspack-ads'
				) }
				disabled={ inFlight }
				value={ config.revenueShare }
				onChange={ value =>
					setConfig( {
						...config,
						revenueShare: value,
					} )
				}
			/>
			<SelectControl
				label={ __( 'Bidders', 'newspack-ads' ) }
				disabled={ inFlight }
				value={ config.bidders }
				help={ __(
					'Which bidders to include in this order. Select bidders that all have the same revenue share and make sure to not include the same bidder in more than one header bidding order.',
					'newspack-ads'
				) }
				options={ Object.keys( bidders ).map( bidderKey => ( {
					value: bidderKey,
					label: bidders[ bidderKey ].name,
				} ) ) }
				multiple
				onChange={ value =>
					setConfig( {
						...config,
						bidders: value,
					} )
				}
			/>
			{ ! inFlight && hasIssues() && (
				<Notice
					isWarning
					noticeText={ __( "Order exists but it's misconfigured.", 'newspack-ads' ) }
				/>
			) }
			{ step && stepName ? (
				<Fragment>
					<Notice
						isWarning
						noticeText={ __(
							'This may take up to 15 minutes, please do not close the window.',
							'newspack-ads'
						) }
					/>
					<ProgressBar completed={ step } total={ totalSteps } label={ stepName } />
				</Fragment>
			) : null }
			<Card buttonsCard noBorder className="justify-end">
				{ typeof onCancel === 'function' && (
					<Button
						isSecondary
						disabled={ inFlight }
						onClick={ () => {
							onCancel();
						} }
					>
						{ __( 'Cancel', 'newspack-ads' ) }
					</Button>
				) }
				<Button
					isPrimary
					disabled={ ! canSubmit() || ! config.name || inFlight }
					onClick={ () => {
						const fixing = hasIssues();
						if ( fixing || ! config.orderId ) {
							create( fixing );
						} else {
							update();
						}
					} }
				>
					{ hasIssues() ? __( 'Fix issues', 'newspack-ads' ) : buttonText() }
				</Button>
			</Card>
		</Card>
	);
};

export default Order;
