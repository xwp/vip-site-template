<?php
/**
 * Feature registry.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme\Components;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use XWP\VIP_Site_Template\Theme\Path;
use XWP\VIP_Site_Template\Theme\Utils;
use XWP\VIP_Site_Template\Theme\Features\Feature;

use function XWP\VIP_Site_Template\Theme\theme;

/**
 * Feature registry.
 *
 * Automatically registers and manages theme features as components.
 */
class Feature_Registry implements Component {

	/**
	 * Feature source directory relative to the theme root.
	 *
	 * @var Path
	 */
	protected $source_directory;

	/**
	 * Feature build directory relative to the theme root.
	 *
	 * @var Path
	 */
	protected $build_directory;

	/**
	 * Setup the features registry.
	 *
	 * @param Path $source_directory Features source directory.
	 * @param Path $build_directory  Features build directory.
	 *
	 * @return void
	 */
	public function __construct( Path $source_directory, Path $build_directory ) {
		$this->source_directory = $source_directory;
		$this->build_directory  = $build_directory;
	}

	/**
	 * Register features immediately as components.
	 * No WordPress hooks needed - features are components.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_features();
	}

	/**
	 * Register all features as components.
	 *
	 * Features are auto-discovered and added to the theme's component registry.
	 *
	 * @return void
	 */
	protected function register_features(): void {
		// Look for feature directories that contain php/ subdirectories.
		$feature_directories = glob( $this->source_directory->to( '*' ), GLOB_ONLYDIR );

		if ( ! is_array( $feature_directories ) ) {
			return; // No features found, that's okay.
		}

		// Ensure deterministic order when loading features.
		natsort( $feature_directories );

		foreach ( $feature_directories as $directory ) {
			$this->register_feature( $directory );
		}
	}

	/**
	 * Register a single feature as a component.
	 *
	 * @param string $directory Feature directory path.
	 *
	 * @return void
	 */
	protected function register_feature( string $directory ): void {
		$feature_name = basename( $directory );
		$feature_file = $directory . '/php/class-' . $feature_name . '-feature.php';
		if ( ! file_exists( $feature_file ) ) {
			return; // Skip if no feature file.
		}

		// Load all non-main and non-CLI feature's PHP files.
		$this->require_feature_php_files( $directory, $feature_name );

		// Load the main feature file (must exist and follow naming convention).
		require_once $feature_file;

		// Create and initialize the feature component.
		$feature_component = $this->create_feature_component( $feature_name );

		if ( $feature_component && $feature_component instanceof Feature ) {
			theme()->components()->add( $feature_component );
			$feature_component->init();
		}

		// 2. Load CLI commands if in CLI context.
		if ( Utils::is_wp_cli_request() && Utils::is_vip_wp_cli_available() ) {
			$this->load_cli_commands( $directory );
		}
	}

	/**
	 * Create a feature component instance.
	 *
	 * @param string $feature_name Feature directory name.
	 *
	 * @return Feature|null Feature instance or null if not found.
	 */
	protected function create_feature_component( string $feature_name ): ?Feature {
		// Convert kebab-case to Pascal_Case: theme-protection -> Theme_Protection_Feature.
		$class_name = str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $feature_name ) ) ) . '_Feature';
		$full_class = "XWP\\VIP_Site_Template\\Theme\\Features\\{$class_name}";

		if ( class_exists( $full_class ) && is_subclass_of( $full_class, Feature::class ) ) {
			return new $full_class();
		}

		return null;
	}

	/**
	 * Load and initialize CLI commands for a feature.
	 *
	 * @param string $directory Feature directory path.
	 *
	 * @return void
	 */
	private function load_cli_commands( string $directory ): void {
		// Only proceed in CLI contexts supported on VIP.
		if ( ! Utils::is_wp_cli_request() || ! Utils::is_vip_wp_cli_available() ) {
			return;
		}

		$cli_directory = $directory . '/php/cli';

		if ( ! is_dir( $cli_directory ) ) {
			return;
		}

		// Check if base CLI classes are available.
		$has_xwp_cli = class_exists( '\XWP\Enterprise_Migration_Tools\XWP_CLI_Command' );
		$has_vip_cli = class_exists( '\WPCOM_VIP_CLI_Command' );

		if ( ! $has_xwp_cli && ! $has_vip_cli ) {
			return; // No base classes available.
		}

		$cli_files = glob( $cli_directory . '/*.php' );

		if ( ! is_array( $cli_files ) || empty( $cli_files ) ) {
			return;
		}

		foreach ( $cli_files as $cli_file ) {
			require_once $cli_file;
			$this->init_cli_command( $cli_file, $has_xwp_cli, $has_vip_cli );
		}
	}

	/**
	 * Initialize a CLI command if it extends the right base class.
	 *
	 * @param string $file_path    CLI file path.
	 * @param bool   $has_xwp_cli  Whether XWP CLI class exists.
	 * @param bool   $has_vip_cli  Whether VIP CLI class exists.
	 *
	 * @return void
	 */
	private function init_cli_command( string $file_path, bool $has_xwp_cli, bool $has_vip_cli ): void {
		$filename = basename( $file_path, '.php' );

		if ( ! str_starts_with( $filename, 'class-' ) ) {
			return;
		}

		// Convert: class-theme-cleanup-cli-command -> Theme_Cleanup_CLI_Command.
		$class_name = str_replace( ' ', '_', ucwords( str_replace( [ 'class-', '-' ], [ '', ' ' ], $filename ) ) );
		$full_class = "XWP\\VIP_Site_Template\\Theme\\Features\\{$class_name}";

		if ( ! class_exists( $full_class ) ) {
			return;
		}

		// Only initialize if it extends one of the CLI base classes.
		if (
			( $has_xwp_cli && is_subclass_of( $full_class, '\XWP\Enterprise_Migration_Tools\XWP_CLI_Command' ) )
			|| ( $has_vip_cli && is_subclass_of( $full_class, '\WPCOM_VIP_CLI_Command' ) )
		) {
			$instance = new $full_class();

			if ( method_exists( $instance, 'init' ) ) {
				$instance->init();
			}
		}
	}

	/**
	 * Require all non-CLI PHP files for a feature.
	 *
	 * Loads every PHP file under the feature's php/ directory except:
	 * - the main feature file (class-{$feature_name}-feature.php)
	 * - any file under php/cli/
	 *
	 * Load order priority:
	 * 1. Traits (trait-*.php)
	 * 2. Interfaces (interface-*.php)
	 * 3. Abstract classes (abstract-*.php)
	 * 4. Other classes
	 *
	 * @param string $directory    Feature directory path.
	 * @param string $feature_name Feature directory name (kebab-case).
	 *
	 * @return void
	 */
	private function require_feature_php_files( string $directory, string $feature_name ): void {
		$php_root     = $directory . '/php';
		$main_feature = $directory . '/php/class-' . $feature_name . '-feature.php';

		if ( ! is_dir( $php_root ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $php_root, FilesystemIterator::SKIP_DOTS )
		);

		$traits     = [];
		$interfaces = [];
		$abstracts  = [];
		$classes    = [];

		/** @var SplFileInfo $file */
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$path     = $file->getPathname();
			$filename = $file->getFilename();

			// Only load PHP files.
			if ( ! str_ends_with( $path, '.php' ) ) {
				continue;
			}

			// Skip CLI files; those are conditionally loaded elsewhere.
			if ( str_contains( $path, '/php/cli/' ) ) {
				continue;
			}

			// Skip the main feature file (already loaded).
			if ( $path === $main_feature ) {
				continue;
			}

			// Categorize files by type for proper load order.
			if ( str_starts_with( $filename, 'trait-' ) ) {
				$traits[] = $path;
			} elseif ( str_starts_with( $filename, 'interface-' ) ) {
				$interfaces[] = $path;
			} elseif ( str_starts_with( $filename, 'abstract-' ) ) {
				$abstracts[] = $path;
			} else {
				$classes[] = $path;
			}
		}

		// Load in dependency order: traits → interfaces → abstracts → classes.
		foreach ( [ $traits, $interfaces, $abstracts, $classes ] as $group ) {
			// Sort each group for deterministic order.
			natsort( $group );
			foreach ( $group as $path ) {
				require_once $path;
			}
		}
	}
}
