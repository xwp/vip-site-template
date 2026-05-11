<?php
/**
 * Base Feature class.
 *
 * @package XWP\VIP_Site_Template\Theme
 */

namespace XWP\VIP_Site_Template\Theme\Features;

use XWP\VIP_Site_Template\Theme\Components\Component;

use function XWP\VIP_Site_Template\Theme\theme;

/**
 * Base Feature class.
 *
 * Handles asset loading and provides hooks for feature-specific logic.
 */
abstract class Feature implements Component {

	/**
	 * Feature name (used for asset paths).
	 *
	 * @var string
	 */
	protected $feature_name;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// Auto-detect feature name from class name.
		$this->feature_name = $this->get_feature_name_from_class();
	}

	/**
	 * Initialize the feature.
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook into asset loading.
		add_action( 'enqueue_block_assets', [ $this, 'maybe_enqueue_assets' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'maybe_enqueue_editor_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_admin_assets' ] );

		// Let child classes add their specific initialization.
		$this->feature_init();
	}

	/**
	 * Feature-specific initialization.
	 * Override this in child classes.
	 *
	 * @return void
	 */
	abstract protected function feature_init(): void;

	/**
	 * Determine if assets should be loaded for this feature.
	 * Override this in child classes for custom logic.
	 * Defaults to true (always load).
	 *
	 * @return bool
	 */
	protected function should_assets_load(): bool {
		return true;
	}

	/**
	 * Determine if editor assets should be loaded for this feature.
	 * Override this in child classes for custom logic.
	 * Defaults to true (always load).
	 *
	 * @return bool
	 */
	protected function should_editor_assets_load(): bool {
		return true;
	}

	/**
	 * Determine if admin assets should be loaded for this feature.
	 * Override this in child classes for custom logic.
	 * Defaults to true (always load).
	 *
	 * @return bool
	 */
	protected function should_admin_assets_load(): bool {
		return true;
	}

	/**
	 * Maybe enqueue assets if feature should load them.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets(): void {
		if ( $this->should_assets_load() ) {
			$this->enqueue_feature_assets();
		}
	}

	/**
	 * Maybe enqueue editor assets if feature should load them.
	 *
	 * @return void
	 */
	public function maybe_enqueue_editor_assets(): void {
		if ( $this->should_editor_assets_load() ) {
			$this->enqueue_editor_feature_assets();
		}
	}

	/**
	 * Maybe enqueue admin assets if feature should load them.
	 *
	 * @return void
	 */
	public function maybe_enqueue_admin_assets(): void {
		if ( $this->should_admin_assets_load() ) {
			$this->enqueue_admin_feature_assets();
		}
	}

	/**
	 * Enqueue feature assets (CSS and JS).
	 *
	 * @return void
	 */
	protected function enqueue_feature_assets(): void {
		$this->enqueue_feature_styles();
		$this->enqueue_feature_scripts();
	}

	/**
	 * Enqueue editor feature assets (CSS and JS).
	 *
	 * @return void
	 */
	protected function enqueue_editor_feature_assets(): void {
		$this->enqueue_editor_feature_styles();
		$this->enqueue_editor_feature_scripts();
	}

	/**
	 * Enqueue admin feature assets (CSS and JS).
	 *
	 * @return void
	 */
	protected function enqueue_admin_feature_assets(): void {
		$this->enqueue_admin_feature_styles();
		$this->enqueue_admin_feature_scripts();
	}

	/**
	 * Enqueue feature styles.
	 *
	 * Provides the absolute filesystem path via wp_style_add_data() so
	 * WordPress can inline small stylesheets instead of emitting a
	 * render-blocking <link> tag. Inlining is governed by the cumulative
	 * styles_inline_size_limit (set to 50 KB in Foundation).
	 *
	 * @return void
	 */
	protected function enqueue_feature_styles(): void {
		$style_asset = theme()->asset( "build/features/{$this->feature_name}/frontend.css" );

		if ( $style_asset->exists() ) {
			$handle = "{$this->feature_name}-feature-style";

			wp_enqueue_style(
				$handle,
				$style_asset->url(),
				$this->get_style_dependencies(),
				$style_asset->version()
			);

			// Opt-in to CSS inlining for small stylesheets.
			wp_style_add_data( $handle, 'path', $style_asset->path() );
		}
	}

	/**
	 * Enqueue feature scripts.
	 *
	 * @return void
	 */
	protected function enqueue_feature_scripts(): void {
		$script_asset = theme()->asset( "build/features/{$this->feature_name}/frontend.js" );

		if ( $script_asset->exists() ) {
			$asset_file = theme()->asset( "build/features/{$this->feature_name}/frontend.asset.php" );
			$asset_data = $asset_file->exists()
				? require $asset_file->path()
				: [
					'dependencies' => [],
					'version'      => wp_get_theme()->get( 'Version' ),
				];

			wp_enqueue_script(
				"{$this->feature_name}-feature-script",
				$script_asset->url(),
				array_merge( $this->get_script_dependencies(), $asset_data['dependencies'] ),
				$asset_data['version'],
				[
					'in_footer' => true,
					'strategy'  => 'defer',
				]
			);

			// Add inline data if provided.
			$inline_data = $this->get_script_data();
			if ( ! empty( $inline_data ) ) {
				wp_localize_script(
					"{$this->feature_name}-feature-script",
					$this->get_script_object_name(),
					$inline_data
				);
			}
		}
	}

	/**
	 * Enqueue editor feature styles.
	 *
	 * @return void
	 */
	protected function enqueue_editor_feature_styles(): void {
		$style_asset = theme()->asset( "build/features/{$this->feature_name}/editor.css" );

		if ( $style_asset->exists() ) {
			wp_enqueue_style(
				"{$this->feature_name}-editor-feature-style",
				$style_asset->url(),
				$this->get_editor_style_dependencies(),
				$style_asset->version()
			);
		}
	}

	/**
	 * Enqueue editor feature scripts.
	 *
	 * @return void
	 */
	protected function enqueue_editor_feature_scripts(): void {
		$script_asset = theme()->asset( "build/features/{$this->feature_name}/editor.js" );

		if ( $script_asset->exists() ) {
			$asset_file = theme()->asset( "build/features/{$this->feature_name}/editor.asset.php" );
			$asset_data = $asset_file->exists()
				? require $asset_file->path()
				: [
					'dependencies' => [],
					'version'      => wp_get_theme()->get( 'Version' ),
				];

			wp_enqueue_script(
				"{$this->feature_name}-editor-feature-script",
				$script_asset->url(),
				array_merge( $this->get_editor_script_dependencies(), $asset_data['dependencies'] ),
				$asset_data['version'],
				true
			);

			// Add inline data if provided.
			$inline_data = $this->get_editor_script_data();
			if ( ! empty( $inline_data ) ) {
				wp_localize_script(
					"{$this->feature_name}-editor-feature-script",
					$this->get_editor_script_object_name(),
					$inline_data
				);
			}
		}
	}

	/**
	 * Enqueue admin feature styles.
	 *
	 * @return void
	 */
	protected function enqueue_admin_feature_styles(): void {
		$style_asset = theme()->asset( "build/features/{$this->feature_name}/admin.css" );

		if ( $style_asset->exists() ) {
			wp_enqueue_style(
				"{$this->feature_name}-admin-feature-style",
				$style_asset->url(),
				$this->get_admin_style_dependencies(),
				$style_asset->version()
			);
		}
	}

	/**
	 * Enqueue admin feature scripts.
	 *
	 * @return void
	 */
	protected function enqueue_admin_feature_scripts(): void {
		$script_asset = theme()->asset( "build/features/{$this->feature_name}/admin.js" );

		if ( $script_asset->exists() ) {
			$asset_file = theme()->asset( "build/features/{$this->feature_name}/admin.asset.php" );
			$asset_data = $asset_file->exists()
				? require $asset_file->path()
				: [
					'dependencies' => [],
					'version'      => wp_get_theme()->get( 'Version' ),
				];

			wp_enqueue_script(
				"{$this->feature_name}-admin-feature-script",
				$script_asset->url(),
				array_merge( $this->get_admin_script_dependencies(), $asset_data['dependencies'] ),
				$asset_data['version'],
				true
			);

			// Add inline data if provided.
			$inline_data = $this->get_admin_script_data();
			if ( ! empty( $inline_data ) ) {
				wp_localize_script(
					"{$this->feature_name}-admin-feature-script",
					$this->get_admin_script_object_name(),
					$inline_data
				);
			}
		}
	}

	/**
	 * Get CSS dependencies.
	 * Override in child classes if needed.
	 *
	 * @return array<string>
	 */
	protected function get_style_dependencies(): array {
		return [];
	}

	/**
	 * Get JS dependencies.
	 * Override in child classes if needed.
	 *
	 * @return array<string>
	 */
	protected function get_script_dependencies(): array {
		return [];
	}

	/**
	 * Get inline script data.
	 * Override in child classes if needed.
	 *
	 * @return array<string>
	 */
	protected function get_script_data(): array {
		return [];
	}

	/**
	 * Get editor CSS dependencies.
	 * Override in child classes if needed.
	 *
	 * @return array<string>
	 */
	protected function get_editor_style_dependencies(): array {
		return [];
	}

	/**
	 * Get editor JS dependencies.
	 * Override in child classes if needed.
	 *
	 * @return array<string>
	 */
	protected function get_editor_script_dependencies(): array {
		return [];
	}

	/**
	 * Get inline editor script data.
	 * Override in child classes if needed.
	 *
	 * @return array<string>
	 */
	protected function get_editor_script_data(): array {
		return [];
	}

	/**
	 * Get admin CSS dependencies.
	 * Override in child classes if needed.
	 *
	 * @return array<string>
	 */
	protected function get_admin_style_dependencies(): array {
		return [];
	}

	/**
	 * Get admin JS dependencies.
	 * Override in child classes if needed.
	 *
	 * @return array<string>
	 */
	protected function get_admin_script_dependencies(): array {
		return [];
	}

	/**
	 * Get inline admin script data.
	 * Override in child classes if needed.
	 *
	 * @return array<string>
	 */
	protected function get_admin_script_data(): array {
		return [];
	}

	/**
	 * Get script object name for wp_localize_script.
	 *
	 * @return string
	 */
	protected function get_script_object_name(): string {
		// Convert hello-world to helloWorldFeature.
		$camel_case = str_replace( '-', '', ucwords( $this->feature_name, '-' ) );
		return lcfirst( $camel_case ) . 'Feature';
	}

	/**
	 * Get editor script object name for wp_localize_script.
	 *
	 * @return string
	 */
	protected function get_editor_script_object_name(): string {
		// Convert hello-world to helloWorldEditorFeature.
		$camel_case = str_replace( '-', '', ucwords( $this->feature_name, '-' ) );
		return lcfirst( $camel_case ) . 'EditorFeature';
	}

	/**
	 * Get admin script object name for wp_localize_script.
	 *
	 * @return string
	 */
	protected function get_admin_script_object_name(): string {
		// Convert hello-world to helloWorldAdminFeature.
		$camel_case = str_replace( '-', '', ucwords( $this->feature_name, '-' ) );
		return lcfirst( $camel_case ) . 'AdminFeature';
	}

	/**
	 * Get feature name from class name.
	 * Converts Hello_World_Feature to hello-world
	 *
	 * @return string
	 */
	private function get_feature_name_from_class(): string {
		$class_name = get_class( $this );
		$short_name = basename( str_replace( '\\', '/', $class_name ) );

		// Remove _Feature suffix if present.
		$short_name = preg_replace( '/_?Feature$/', '', $short_name );

		// Convert Pascal_Case to kebab-case.
		$kebab_case = strtolower( preg_replace( '/([A-Z])/', '-$1', str_replace( '_', '', $short_name ) ) );

		return ltrim( $kebab_case, '-' );
	}
}
