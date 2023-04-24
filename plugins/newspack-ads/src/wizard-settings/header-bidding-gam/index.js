/**
 * Header Bidding Settings Hooks.
 */

/**
 * WordPress dependencies.
 */
import { addFilter } from '@wordpress/hooks';
import { sprintf, __, _n } from '@wordpress/i18n';
import { Fragment, useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Newspack dependencies.
 */
import { ActionCard, Card, Modal, Notice, Button } from 'newspack-components';

/**
 * Internal dependencies.
 */
import './style.scss';
import Order from './order';
import OrderPopover from './order-popover';

const { network_code } = window.newspack_ads_bidding_gam;

const getOrderUrl = orderId => {
	return `https://admanager.google.com/${ network_code }#delivery/order/order_overview/order_id=${ orderId }`;
};

const HeaderBiddingGAM = () => {
	const [ inFlight, setInFlight ] = useState( true );
	const [ bidders, setBidders ] = useState( {} );
	const [ unrecoverable, setUnrecoverable ] = useState( null );
	const [ isManaging, setIsManaging ] = useState( false );
	const [ editingOrder, setEditingOrder ] = useState( false );
	const [ orderName, setOrderName ] = useState( 'Newspack Header Bidding' );
	const [ orders, setOrders ] = useState( null );
	const [ error, setError ] = useState( null );
	const fetchOrders = async () => {
		setInFlight( true );
		let data;
		try {
			data = await apiFetch( {
				path: '/newspack-ads/v1/bidding/gam/orders',
				method: 'GET',
			} );
			setOrders( data );
			setError( null );
		} catch ( err ) {
			setError( err );
		} finally {
			setInFlight( false );
		}
		return data;
	};
	const getActiveOrders = () => {
		return orders?.filter( order => order.is_archived === false ) || [];
	};
	const archiveOrder = async orderId => {
		return await apiFetch( {
			path: '/newspack-ads/v1/bidding/gam/order',
			method: 'DELETE',
			data: {
				id: orderId,
			},
		} );
	};
	const canConfigure = () => {
		return ! inFlight && ! isManaging && error?.data?.status !== '500';
	};
	const getMissingOrderMessage = () => {
		return inFlight
			? __( 'Loading…', 'newspack-ads' )
			: __( 'There are no orders configured.', 'newspack-ads' );
	};

	useEffect( async () => {
		await fetchOrders();
		try {
			setBidders( await apiFetch( { path: '/newspack-ads/v1/bidders' } ) );
		} catch ( err ) {
			setError( err );
		}
	}, [] );

	useEffect( () => {
		if ( orders?.length ) {
			setOrderName( `Newspack Header Bidding v${ orders.length + 1 }` );
		} else {
			setOrderName( 'Newspack Header Bidding' );
		}
		// Switch to create new order if there are no orders and was previously managing.
		if ( ! getActiveOrders().length && isManaging ) {
			setIsManaging( false );
			setEditingOrder( 0 );
		}
	}, [ orders ] );

	useEffect( () => {
		if ( editingOrder !== false ) {
			window.onbeforeunload = () => {
				return __( 'Are you sure you want to leave this page?', 'newspack-ads' );
			};
		} else {
			window.onbeforeunload = null;
		}
	}, [ editingOrder ] );

	const activeOrders = getActiveOrders();

	const cardActionText = activeOrders.length
		? __( 'Manage Orders', 'newspack-ads' )
		: __( 'Create Order', 'newspack-ads' );

	return (
		<Fragment>
			<ActionCard
				className="newspack-header-bidding-gam-action-card"
				title={ __( 'Google Ad Manager Integration', 'newspack-ads' ) }
				description={ () => (
					<Fragment>
						{ error ? (
							error.message
						) : (
							<span className="newspack-ads__header-bidding-gam__order-description">
								{ activeOrders.length
									? sprintf(
											// Translators: Number of line items in the order.
											_n(
												'There is %s available order.',
												'There are %s available orders.',
												activeOrders.length,
												'newspack-ads'
											),
											activeOrders.length
									  )
									: getMissingOrderMessage() }
							</span>
						) }
					</Fragment>
				) }
				actionText={ canConfigure() ? cardActionText : null }
				onClick={ () => ( activeOrders.length ? setIsManaging( true ) : setEditingOrder( 0 ) ) }
			/>
			{ isManaging && editingOrder === false && (
				<Modal
					title={ __( 'Manage Orders', 'newspack-ads' ) }
					onRequestClose={ () => ! inFlight && setIsManaging( false ) }
				>
					{ activeOrders.length && (
						<>
							<Card noBorder>
								{ activeOrders.map( order => (
									<ActionCard
										key={ order.id }
										title={ order.name }
										badge={ order.status }
										description={ () => (
											<span>
												{ sprintf(
													// Translators: the bidder revenue share for this order.
													__( 'Bidder Revenue Share: %1$d%%', 'newspack-ads' ),
													order.revenue_share || 0
												) }{ ' ' }
												|{ ' ' }
												{ sprintf(
													// Translators: comma-separated list of adapters for the order or "any" if undefined.
													__( 'Bidders: %s', 'newspack-ads' ),
													order.bidders?.length
														? order.bidders
																.map( bidderKey => bidders[ bidderKey ]?.name || bidderKey )
																.join( ', ' )
														: __( 'any', 'newspack-ads' )
												) }
											</span>
										) }
										actionText={
											<OrderPopover
												isDraft={ order.status === 'DRAFT' }
												disabled={ inFlight }
												onArchive={ async () => {
													if (
														// eslint-disable-next-line no-alert
														confirm(
															__( "Are you sure you'd like to archive this order?", 'newspack-ads' )
														)
													) {
														setInFlight( true );
														await archiveOrder( order.id );
														setInFlight( false );
														await fetchOrders();
													}
												} }
												onEdit={ () => setEditingOrder( order.id ) }
												gamLink={ getOrderUrl( order.id ) }
											/>
										}
										className="mv0"
										isSmall
									/>
								) ) }
							</Card>
							{
								// Display warning if a bidder is being targeted by more than one order.
								Object.keys( bidders ).map( bidderKey => {
									const bidderOrders = activeOrders.filter(
										order =>
											order.bidders?.includes( bidderKey ) ||
											! order.bidders ||
											! order.bidders.length
									);
									return (
										bidderOrders.length > 1 && (
											<Notice
												key={ bidderKey }
												isWarning
												noticeText={ sprintf(
													// Translators: %1 The name of the bidder. %2 The number of orders for the bidder.
													__( '%1$s is being targeted by %2$d orders.', 'newspack-ads' ),
													bidders[ bidderKey ].name,
													bidderOrders.length
												) }
											/>
										)
									);
								} )
							}
							<Card buttonsCard noBorder className="justify-end">
								<Button isSecondary disabled={ inFlight } onClick={ () => setIsManaging( false ) }>
									{ __( 'Cancel', 'newspack-ads' ) }
								</Button>
								<Button isPrimary disabled={ inFlight } onClick={ () => setEditingOrder( 0 ) }>
									Create new order
								</Button>
							</Card>
						</>
					) }
				</Modal>
			) }
			{ editingOrder !== false && (
				<Modal
					title={
						editingOrder ? __( 'Edit Order', 'newspack-ads' ) : __( 'Create Order', 'newspack-ads' )
					}
					onRequestClose={ () => ! inFlight && setEditingOrder( false ) }
				>
					{ unrecoverable && (
						<Notice
							isError
							noticeText={ __(
								'We were unable to fix the issues with this order and have archived it. Please create a new order below.',
								'newspack-ads'
							) }
						/>
					) }
					<Order
						bidders={ bidders }
						orderId={ editingOrder }
						defaultName={ orderName }
						onPending={ pending => {
							setInFlight( pending );
						} }
						onUnrecoverable={ async ( { order_id }, err ) => {
							await archiveOrder( order_id );
							await fetchOrders();
							setUnrecoverable( err );
							setEditingOrder( 0 );
						} }
						onSuccess={ async () => {
							await fetchOrders();
							setUnrecoverable( false );
							setEditingOrder( false );
							setIsManaging( true );
						} }
						onError={ async err => {
							await fetchOrders();
							setError( err );
						} }
						onCancel={ () => ! inFlight && setEditingOrder( false ) }
					/>
				</Modal>
			) }
		</Fragment>
	);
};

addFilter(
	'newspack.settingSection.bidding.beforeControls',
	'newspack-ads/header-bidding-gam',
	( AfterControls, props ) => {
		if ( props.sectionKey === 'bidding' ) {
			return <HeaderBiddingGAM { ...props } />;
		}
		return AfterControls;
	}
);
