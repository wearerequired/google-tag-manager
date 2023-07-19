<?php
/**
 * Namespaced functions.
 */

declare( strict_types=1 );

namespace Required\GoogleTagManager;

const CONTAINER_ID_OPTION = 'required_gtm_container_id';

/**
 * Bootstraps the plugin.
 */
function bootstrap(): void {
	// Settings and options.
	add_action( 'init', __NAMESPACE__ . '\register_settings' );
	add_action( 'admin_init', __NAMESPACE__ . '\register_settings_ui' );
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), __NAMESPACE__ . '\add_settings_action_link', 10, 2 );

	// Frontend.
	add_filter( 'wp_resource_hints', __NAMESPACE__ . '\add_dns_prefetch', 10, 2 );
	add_action( 'wp_head', __NAMESPACE__ . '\print_script_head' );
	add_action( 'wp_body_open', __NAMESPACE__ . '\print_script_body', 1 );
}

/**
 * Registers settings and their data.
 */
function register_settings(): void {
	register_setting(
		'reading',
		CONTAINER_ID_OPTION,
		[
			'show_in_rest'      => true,
			'type'              => 'string',
			'description'       => __( 'Container ID of the Google Tag Manager.', 'required-google-tag-manager' ),
			'default'           => '',
			'sanitize_callback' => null, // Added below due to missing second argument, see https://core.trac.wordpress.org/ticket/15335.
		]
	);

	add_filter( 'sanitize_option_' . CONTAINER_ID_OPTION, __NAMESPACE__ . '\sanitize_gtm_container_id', 10, 2 );
}

/**
 * Sanitizes a Google Tag Manager container ID from user input.
 *
 * @param string $value The unsanitized option value.
 * @param string $option The option name.
 * @return string The sanitized option value.
 */
function sanitize_gtm_container_id( string $value, string $option ): string {
	$value = (string) $value;
	$value = trim( $value );

	if ( '' === $value ) {
		return $value;
	}

	$error = '';

	// Ensure ID is uppercase.
	$value = strtoupper( $value );

	// Check for the format.
	if ( ! preg_match( '/^GTM-[A-Z0-9]+$/', $value ) ) {
		$error = sprintf(
			/* translators: %s: GTM-XXXXXXX */
			__( 'The container ID doesn&#8217;t match the required format %s.', 'required-google-tag-manager' ),
			'<code>GTM-XXXXXXX</code>'
		);
	}

	// Fallback to previous value and register a settings error to be displayed to the user.
	if ( ! empty( $error ) ) {
		$value = get_option( $option );
		if ( \function_exists( 'add_settings_error' ) ) {
			add_settings_error( $option, "invalid_{$option}", $error );
		}
	}

	return $value;
}

/**
 * Registers the admin UI for the settings.
 */
function register_settings_ui(): void {
	add_settings_section(
		'required-gtm',
		'<span id="google-tag-manager">' . __( 'Google Tag Manager', 'required-google-tag-manager' ) . '</span>',
		'__return_empty_string',
		'reading'
	);

	add_settings_field(
		'required-gtm-id',
		__( 'Container ID', 'required-google-tag-manager' ),
		static function(): void {
			?>
			<input
				name="<?php echo esc_attr( CONTAINER_ID_OPTION ); ?>"
				type="text"
				id="required-gtm-container-id"
				aria-describedby="required-gtm-container-id-description"
				value="<?php echo esc_attr( get_option( CONTAINER_ID_OPTION ) ); ?>"
				class="regular-text code"
			>
			<p class="description" id="required-gtm-container-id-description">
				<?php
				printf(
					/* translators: %s GTM-XXXXXXX */
					__( 'The container ID in the format %s.', 'required-google-tag-manager' ),
					'<code>GTM-XXXXXXX</code>'
				);
				?>
			</p>
			<?php
		},
		'reading',
		'required-gtm',
		[
			'label_for' => 'required-gtm-container-id',
		]
	);
}

/**
 * Adds settings link to action links displayed in the Plugins list table.
 *
 * @param string[] $actions An array of plugin action links.
 * @return string[] An array of plugin action links.
 */
function add_settings_action_link( array $actions ): array {
	if ( current_user_can( 'manage_options' ) ) {
		$settings_action = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-reading.php#google-tag-manager' ) ),
			__( 'Settings', 'required-google-tag-manager' )
		);
		array_unshift( $actions, $settings_action );
	}

	return $actions;
}

/**
 * Checks if the Google Tag Manager code should be loaded.
 *
 * @return bool Whether the Google Tag Manager code should be loaded.
 */
function should_load(): bool {
	if ( 'production' !== wp_get_environment_type() ) {
		return false;
	}

	return (bool) get_option( CONTAINER_ID_OPTION );
}

/**
 * Add dns-prefetch for Google Tag Manger.
 *
 * @param array<int,string> $urls          URLs to print for resource hints.
 * @param string            $relation_type The relation type the URLs are printed.
 * @return array<int,string> URLs to print for resource hints.
 */
function add_dns_prefetch( array $urls, string $relation_type ): array {
	if ( ! should_load() ) {
		return $urls;
	}

	if ( 'dns-prefetch' === $relation_type ) {
		$urls[] = '//www.googletagmanager.com';
	}

	return $urls;
}

/**
 * Outputs Tag Manager script.
 */
function print_script_head(): void {
	if ( ! should_load() ) {
		return;
	}

	?>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo esc_js( get_option( CONTAINER_ID_OPTION ) ); ?>');</script>
	<?php
}

/**
 * Outputs Tag Manager iframe for when the browser has JavaScript disabled.
 */
function print_script_body(): void {
	if ( ! should_load() ) {
		return;
	}

	?>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo rawurlencode( get_option( CONTAINER_ID_OPTION ) ); ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
	<?php
}
