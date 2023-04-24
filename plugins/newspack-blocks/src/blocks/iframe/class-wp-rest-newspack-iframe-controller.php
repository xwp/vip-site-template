<?php
/**
 * WP_REST_Newspack_Iframe_Controller file.
 *
 * @package WordPress
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

/**
 * Class WP_REST_Newspack_Iframe_Controller.
 */
class WP_REST_Newspack_Iframe_Controller extends WP_REST_Controller {
	const IFRAME_UPLOAD_DIR = '/newspack_iframes/';
	const IFRAME_ENTRY_FILE = 'index.html';

	/**
	 * Constructs the controller.
	 *
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'newspack-blocks/v1';
	}

	/**
	 * Registers the necessary REST API routes.
	 *
	 * @access public
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/newspack-blocks-iframe-archive',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'import_iframe_from_uploaded_file' ],
					'permission_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/newspack-blocks-iframe-archive-from-media',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'import_iframe_archive_from_media_library' ],
					'permission_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/newspack-blocks-iframe-mode-from-url',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'get_iframe_mode_from_url' ],
					'permission_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/newspack-blocks-remove-iframe-archive',
			[
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'remove_iframe_archive' ],
					'permission_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
				],
			]
		);
	}

	/**
	 * Receive the iframe content, handle it, and returns the URL and the folder name.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function import_iframe_from_uploaded_file( $request ) {
		$response = [];
		$files    = $request->get_file_params();

		if ( count( $files ) > 0 && array_key_exists( 'iframe_file', $files ) ) {
			$iframe_file   = $files['iframe_file'];
			$iframe_folder = pathinfo( $iframe_file['name'] )['filename'] . '-' . wp_generate_password( 8, false );

			if ( in_array( $iframe_file['type'], array_keys( Newspack_Blocks::iframe_document_accepted_file_mimes() ), true ) ) {
				$file_extension = pathinfo( $iframe_file['name'], PATHINFO_EXTENSION );
				$response       = $this->process_iframe_document( $request, "$iframe_folder.$file_extension", $iframe_file['tmp_name'] );
			} elseif ( in_array( $iframe_file['type'], array_keys( Newspack_Blocks::iframe_archive_accepted_file_mimes() ), true ) ) {
				$response = $this->process_iframe_archive( $request, $iframe_folder, $iframe_file['tmp_name'] );
			} else {
				$response = new WP_Error(
					'newspack_blocks',
					__( 'Could not find the iframe file on your request.', 'newspack-blocks' ),
					[ 'status' => '400' ]
				);
			}
		} else {
			$response = new WP_Error(
				'newspack_blocks',
				__( 'Could not find the iframe file on your request.', 'newspack-blocks' ),
				[ 'status' => '400' ]
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Receive the iframe archive, unzip it, and returns the URL and the folder name.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function import_iframe_archive_from_media_library( $request ) {
		$response = [];
		$data     = $request->get_body_params();

		if ( array_key_exists( 'media_id', $data ) ) {
			$media      = get_post( $data['media_id'] );
			$media_path = get_attached_file( $data['media_id'] );

			if ( $media_path && in_array( $media->post_mime_type, array_keys( Newspack_Blocks::iframe_document_accepted_file_mimes() ), true ) ) {
				$iframe_folder  = $media->post_title . '-' . wp_generate_password( 8, false );
				$file_extension = Newspack_Blocks::iframe_document_accepted_file_mimes()[ $media->post_mime_type ];

				$response = $this->process_iframe_document( $request, "$iframe_folder.$file_extension", $media_path );
			} elseif ( $media_path && in_array( $media->post_mime_type, array_keys( Newspack_Blocks::iframe_archive_accepted_file_mimes() ), true ) ) {
				$iframe_folder = $media->post_title . '-' . wp_generate_password( 8, false );

				$response = $this->process_iframe_archive( $request, $iframe_folder, $media_path );
			} else {
				$response = new WP_Error(
					'newspack_blocks',
					__( 'Please choose a valid (.zip) assets archive.', 'newspack-blocks' ),
					[ 'status' => '400' ]
				);
			}
		} else {
			$response = new WP_Error(
				'newspack_blocks',
				__( 'Please choose a valid (.zip) assets archive.', 'newspack-blocks' ),
				[ 'status' => '400' ]
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Receive the iframe content, handle it, and returns the URL and the folder name.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_iframe_mode_from_url( $request ) {
		$data = $request->get_body_params();
		$mode = 'iframe';

		// If we need to remove the previous document, it's a good place to do it here.
		if ( array_key_exists( 'iframe_url', $data ) && ! empty( $data['iframe_url'] ) ) {
			$head = wp_safe_remote_head( $data['iframe_url'] );
			if ( is_wp_error( $head ) ) {
				return rest_ensure_response( $head );
			}
			$content_type = $head['headers']->offsetGet( 'content-type' );
			if ( ! empty( $content_type ) && in_array( $content_type, array_keys( Newspack_Blocks::iframe_document_accepted_file_mimes() ), true ) ) {
				$mode = 'document';
			}
		}

		return rest_ensure_response( [ 'mode' => $mode ] );
	}

	/**
	 * Process iframe source from uploaded document file or from media file.
	 *
	 * @param WP_REST_REQUEST $request Request object.
	 * @param string          $document_filename Document filename.
	 * @param string          $media_source_path iframe assets zip file path.
	 * @return WP_REST_Response
	 */
	private function process_iframe_document( $request, $document_filename, $media_source_path ) {
		$wp_upload_dir     = wp_upload_dir();
		$iframe_upload_dir = $wp_upload_dir['path'] . self::IFRAME_UPLOAD_DIR;
		$iframe_path       = $iframe_upload_dir . $document_filename;
		$data              = $request->get_body_params();

		// Create iframe directory if not existing.
		if ( ! file_exists( $iframe_upload_dir ) ) {
			wp_mkdir_p( $iframe_upload_dir );
		}

		// If we need to remove the previous document, it's a good place to do it here.
		if ( array_key_exists( 'archive_folder', $data ) && ! empty( $data['archive_folder'] ) ) {
			$this->remove_folder( $wp_upload_dir['basedir'] . $data['archive_folder'] );
		}

		// Copy uploaded document file to destination.
		if ( copy( $media_source_path, $iframe_path ) ) {
			return [
				'mode' => 'document',
				'src'  => $wp_upload_dir['url'] . self::IFRAME_UPLOAD_DIR . $document_filename,
				'dir'  => path_join( $wp_upload_dir['subdir'] . self::IFRAME_UPLOAD_DIR, $document_filename ),
			];
		}

		return new WP_Error(
			'newspack_blocks',
			__( 'Could not upload your document.', 'newspack-blocks' ),
			[ 'status' => '400' ]
		);
	}

	/**
	 * Process iframe archive from uploaded zip or from media file.
	 *
	 * @param WP_REST_REQUEST $request Request object.
	 * @param string          $iframe_folder where to extract the iframe assets.
	 * @param string          $media_path iframe assets zip file path.
	 * @return WP_REST_Response
	 */
	private function process_iframe_archive( $request, $iframe_folder, $media_path ) {
		$wp_upload_dir     = wp_upload_dir();
		$iframe_upload_dir = $wp_upload_dir['path'] . self::IFRAME_UPLOAD_DIR;
		$iframe_path       = $iframe_upload_dir . $iframe_folder;
		$data              = $request->get_body_params();

		// create iframe directory if not existing.
		if ( ! file_exists( $iframe_upload_dir ) ) {
			wp_mkdir_p( $iframe_upload_dir );
		}

		// unzip iframe archive.
		$zip = new ZipArchive();
		$res = $zip->open( $media_path );

		if ( true === $res ) {
			$zip->extractTo( $iframe_path );
			$zip->close();

			// check if iframe entry file is there.
			if ( file_exists( path_join( $iframe_path, self::IFRAME_ENTRY_FILE ) ) ) {
				$response = [
					'mode' => 'iframe',
					'src'  => $wp_upload_dir['url'] . self::IFRAME_UPLOAD_DIR . $iframe_folder . DIRECTORY_SEPARATOR,
					'dir'  => path_join( $wp_upload_dir['subdir'] . self::IFRAME_UPLOAD_DIR, $iframe_folder ),
				];

				// if we need to remove the previous archive, it's a good place to do it here.
				if ( array_key_exists( 'archive_folder', $data ) && ! empty( $data['archive_folder'] ) ) {
					$this->remove_folder( $wp_upload_dir['basedir'] . $data['archive_folder'] );
				}
			} else {
				// check if its not a one folder archive: archive.zip > archive > index.html.
				$archive_folders = array_values(
					array_filter(
						glob( "$iframe_path/*", GLOB_ONLYDIR ),
						function( $file ) {
							return false === strpos( $file, '__MACOSX' );
						}
					)
				);

				if ( 1 === count( $archive_folders ) && file_exists( path_join( $archive_folders[0], self::IFRAME_ENTRY_FILE ) ) ) {
					$response = [
						'mode' => 'iframe',
						'src'  => $wp_upload_dir['url'] . self::IFRAME_UPLOAD_DIR . $iframe_folder . DIRECTORY_SEPARATOR . basename( $archive_folders[0] ) . DIRECTORY_SEPARATOR,
						'dir'  => path_join( $wp_upload_dir['subdir'] . self::IFRAME_UPLOAD_DIR, $iframe_folder . DIRECTORY_SEPARATOR ),
					];

					// if we need to remove the previous archive, it's a good place to do it here.
					if ( array_key_exists( 'archive_folder', $data ) && ! empty( $data['archive_folder'] ) ) {
						$this->remove_folder( $wp_upload_dir['basedir'] . $data['archive_folder'] );
					}
				} else {
					// if not, remove the uploaded archive data and raise an error.
					$this->remove_folder( $iframe_path );

					$response = new WP_Error(
						'newspack_blocks',
						/* translators: %s: iframe entry file (e.g. index,html) */
						sprintf( __( '%s was not found in the iframe archive.', 'newspack-blocks' ), self::IFRAME_ENTRY_FILE ),
						[ 'status' => '400' ]
					);
				}
			}
		} else {
			$response = new WP_Error(
				'newspack_blocks',
				__( "Could not unzip the iframe archive, make sure it's a valid .zip archive file.", 'newspack-blocks' ),
				[ 'status' => '400' ]
			);
		}

		return $response;
	}

	/**
	 * Delete iframe archive, this is called when changing the iframe source.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function remove_iframe_archive( $request ) {
		$wp_upload_dir = wp_upload_dir();
		$data          = $request->get_json_params();

		if ( array_key_exists( 'archive_folder', $data ) && ! empty( $data['archive_folder'] ) ) {
			$this->remove_folder( $wp_upload_dir['basedir'] . $data['archive_folder'] );
		}

		return rest_ensure_response( [] );
	}

	/**
	 * Remove a folder. Called to remove the iframe archive folder.
	 *
	 * @param string $folder folder path to remove.
	 * @return void
	 */
	private function remove_folder( $folder ) {
		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require_once ABSPATH . '/wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . '/wp-admin/includes/class-wp-filesystem-direct.php';
		}

		$file_system = new WP_Filesystem_Direct( false );
		$file_system->rmdir( $folder, true );
	}
}
