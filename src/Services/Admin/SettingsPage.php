<?php
/**
 * The AI connector preference screen.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Admin;

use ASDevs\AIAssistant\Http\Controllers\Controller;
use ASDevs\AIAssistant\Services\Ai\ProviderRegistry;
use ASDevs\AIAssistant\Services\Ai\Settings;
use ASDevs\AIAssistant\Services\Discovery\CapabilityMap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level admin screen: pick which WordPress AI connector the assistant uses.
 *
 * Keys are managed under Settings → Connectors, not here.
 */
final class SettingsPage {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'asdevs_ai_assistant_save_settings';

	/**
	 * Style handle.
	 */
	private const STYLE_HANDLE = 'asdevs-ai-assistant-admin';

	/**
	 * Script handle.
	 */
	private const SCRIPT_HANDLE = 'asdevs-ai-assistant-admin';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Providers.
	 *
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $providers;

	/**
	 * Site capability map the assistant discovers.
	 *
	 * @var CapabilityMap
	 */
	private CapabilityMap $capabilities;

	/**
	 * Constructor.
	 *
	 * @param Settings         $settings     Settings.
	 * @param ProviderRegistry $providers    Providers.
	 * @param CapabilityMap    $capabilities Capability map.
	 */
	public function __construct( Settings $settings, ProviderRegistry $providers, CapabilityMap $capabilities ) {
		$this->settings     = $settings;
		$this->providers    = $providers;
		$this->capabilities = $capabilities;
	}

	/**
	 * Register a top-level admin menu item.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'AI Assistant', 'asdevs-ai-assistant' ),
			__( 'AI Assistant', 'asdevs-ai-assistant' ),
			'manage_options',
			ASDEVS_AI_ASSISTANT_SLUG,
			array( $this, 'render' ),
			$this->menu_icon(),
			79
		);
	}

	/**
	 * Load screen styles and connector AJAX script only on this page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_' . ASDEVS_AI_ASSISTANT_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			ASDEVS_AI_ASSISTANT_URL . 'assets/admin/settings.css',
			array(),
			ASDEVS_AI_ASSISTANT_VERSION
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			ASDEVS_AI_ASSISTANT_URL . 'assets/admin/settings.js',
			array(),
			ASDEVS_AI_ASSISTANT_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'asdevsAiAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( Controller::NAMESPACE ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'savedTitle'     => __( 'Settings saved', 'asdevs-ai-assistant' ),
					'savedReady'     => __( 'The assistant is ready. Open it from the button on any admin screen.', 'asdevs-ai-assistant' ),
					'savedPending'   => __( 'Saved — one step left', 'asdevs-ai-assistant' ),
					'savedNeedsKey'  => sprintf(
						/* translators: 1: opening anchor tag, 2: closing anchor tag */
						__( 'Add an API key in %1$sConnectors%2$s to finish setup.', 'asdevs-ai-assistant' ),
						'<a class="asdevs-ai-admin__toast-link" href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">',
						'</a>'
					),
					'testOkTitle'    => __( 'Connection works', 'asdevs-ai-assistant' ),
					'testFailTitle'  => __( 'Connection failed', 'asdevs-ai-assistant' ),
					'testing'        => __( 'Testing…', 'asdevs-ai-assistant' ),
					'testConnection' => __( 'Test connection', 'asdevs-ai-assistant' ),
					'errorTitle'     => __( 'Something went wrong', 'asdevs-ai-assistant' ),
					'errorGeneric'   => __( 'Could not save right now. Try again.', 'asdevs-ai-assistant' ),
				),
			)
		);
	}

	/**
	 * Save the submitted settings.
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'asdevs-ai-assistant' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION );

		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';

		if ( '' !== $provider && ! isset( $this->providers->all()[ $provider ] ) ) {
			$provider = $this->settings->provider();
		}

		$this->settings->save( $provider );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => ASDEVS_AI_ASSISTANT_SLUG,
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/**
	 * Render the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$selected = $this->providers->selected();
		$current  = $this->settings->provider();

		if ( '' === $current && null !== $selected ) {
			$current = $selected->id();
		}

		$ready     = $this->providers->is_ready();
		$providers = $this->providers->all();
		$updated   = isset( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$map       = $this->capabilities->all();
		$caps      = is_array( $map['capabilities'] ?? null ) ? $map['capabilities'] : array();
		?>
		<div class="wrap asdevs-ai-admin-wrap">
			<h1><?php esc_html_e( 'AI Assistant', 'asdevs-ai-assistant' ); ?></h1>

			<div class="asdevs-ai-admin">
				<header class="asdevs-ai-admin__hero">
					<div class="asdevs-ai-admin__hero-main">
						<span class="asdevs-ai-admin__mark" aria-hidden="true">
							<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path fill="currentColor" d="M16 3.2c1.15 3.05 2.1 4.05 5.15 5.2-3.05 1.15-4 2.1-5.15 5.15-1.15-3.05-2.1-4-5.15-5.15 3.05-1.15 4-2.15 5.15-5.2Z"/>
								<path fill="currentColor" opacity=".88" d="M24.2 14.4c.7 1.85 1.3 2.45 3.15 3.15-1.85.7-2.45 1.3-3.15 3.15-.7-1.85-1.3-2.45-3.15-3.15 1.85-.7 2.45-1.3 3.15-3.15Z"/>
								<path fill="currentColor" opacity=".74" d="M9.1 18.6c.55 1.45 1 1.9 2.45 2.45-1.45.55-1.9 1-2.45 2.45-.55-1.45-1-1.9-2.45-2.45 1.45-.55 1.9-1 2.45-2.45Z"/>
								<path fill="currentColor" opacity=".62" d="M11.4 7.2c.3.8.55 1.05 1.35 1.35-.8.3-1.05.55-1.35 1.35-.3-.8-.55-1.05-1.35-1.35.8-.3 1.05-.55 1.35-1.35Z"/>
							</svg>
						</span>
						<div class="asdevs-ai-admin__identity">
							<h2 class="asdevs-ai-admin__title"><?php esc_html_e( 'AI Assistant', 'asdevs-ai-assistant' ); ?></h2>
							<p class="asdevs-ai-admin__eyebrow"><?php esc_html_e( 'Settings', 'asdevs-ai-assistant' ); ?></p>
						</div>
					</div>
				</header>

				<div class="asdevs-ai-admin__stack">
					<div id="asdevs-ai-admin-toast-host" class="asdevs-ai-admin__toast-host" aria-live="polite">
						<?php if ( $updated ) : ?>
							<?php $this->render_toast( $ready ); ?>
						<?php endif; ?>
					</div>

					<section class="asdevs-ai-admin__card asdevs-ai-admin__connector">
						<div class="asdevs-ai-admin__card-head asdevs-ai-admin__card-head--simple">
							<h3 class="asdevs-ai-admin__card-title"><?php esc_html_e( 'AI connector', 'asdevs-ai-assistant' ); ?></h3>
							<p class="asdevs-ai-admin__card-desc">
								<?php esc_html_e( 'Choose which WordPress connector the assistant uses. API keys live in Connectors.', 'asdevs-ai-assistant' ); ?>
							</p>
						</div>

						<?php if ( array() === $providers ) : ?>
							<div class="asdevs-ai-admin__empty">
								<p>
									<?php esc_html_e( 'No AI connectors are active. Add one under Settings → Connectors, then come back.', 'asdevs-ai-assistant' ); ?>
								</p>
								<a class="asdevs-ai-admin__btn asdevs-ai-admin__btn--primary" href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>">
									<?php esc_html_e( 'Open Connectors', 'asdevs-ai-assistant' ); ?>
								</a>
							</div>
						<?php else : ?>
							<div
								class="asdevs-ai-admin__providers"
								role="radiogroup"
								aria-label="<?php esc_attr_e( 'AI connector', 'asdevs-ai-assistant' ); ?>"
								data-asdevs-providers
								data-current="<?php echo esc_attr( $current ); ?>"
							>
								<div class="asdevs-ai-admin__providers-overlay" hidden>
									<svg class="asdevs-ai-admin__dots" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="36" height="36" aria-hidden="true" focusable="false">
										<circle fill="currentColor" stroke="currentColor" stroke-width="15" r="15" cx="40" cy="100">
											<animate attributeName="opacity" calcMode="spline" dur="2s" values="1;0;1" keySplines=".5 0 .5 1;.5 0 .5 1" repeatCount="indefinite" begin="-0.4s"/>
										</circle>
										<circle fill="currentColor" stroke="currentColor" stroke-width="15" r="15" cx="100" cy="100">
											<animate attributeName="opacity" calcMode="spline" dur="2s" values="1;0;1" keySplines=".5 0 .5 1;.5 0 .5 1" repeatCount="indefinite" begin="-0.2s"/>
										</circle>
										<circle fill="currentColor" stroke="currentColor" stroke-width="15" r="15" cx="160" cy="100">
											<animate attributeName="opacity" calcMode="spline" dur="2s" values="1;0;1" keySplines=".5 0 .5 1;.5 0 .5 1" repeatCount="indefinite" begin="0s"/>
										</circle>
									</svg>
									<span class="screen-reader-text"><?php esc_html_e( 'Working…', 'asdevs-ai-assistant' ); ?></span>
								</div>

								<?php foreach ( $providers as $provider ) : ?>
									<?php
									$configured = $provider->is_configured();
									$id         = 'asdevs-ai-provider-' . $provider->id();
									$status     = $configured
										? esc_html__( 'Ready', 'asdevs-ai-assistant' )
										: esc_html__( 'Needs key', 'asdevs-ai-assistant' );
									?>
									<div class="asdevs-ai-admin__provider-row">
										<label class="asdevs-ai-admin__provider<?php echo $configured ? '' : ' asdevs-ai-admin__provider--needs-key'; ?>" for="<?php echo esc_attr( $id ); ?>">
											<input
												type="radio"
												name="provider"
												id="<?php echo esc_attr( $id ); ?>"
												value="<?php echo esc_attr( $provider->id() ); ?>"
												<?php checked( $current, $provider->id() ); ?>
											/>
											<span class="asdevs-ai-admin__provider-face">
												<span class="asdevs-ai-admin__provider-radio" aria-hidden="true"></span>
												<span class="asdevs-ai-admin__provider-name"><?php echo esc_html( $provider->label() ); ?></span>
												<span class="asdevs-ai-admin__provider-status <?php echo $configured ? 'is-ready' : 'is-pending'; ?>">
													<span class="asdevs-ai-admin__provider-status-dot" aria-hidden="true"></span>
													<?php echo $status; ?>
												</span>
											</span>
										</label>
										<button
											type="button"
											class="asdevs-ai-admin__btn asdevs-ai-admin__btn--ghost asdevs-ai-admin__test"
											data-test-provider="<?php echo esc_attr( $provider->id() ); ?>"
											<?php disabled( ! $configured ); ?>
										>
											<?php esc_html_e( 'Test connection', 'asdevs-ai-assistant' ); ?>
										</button>
									</div>
								<?php endforeach; ?>
							</div>

							<div class="asdevs-ai-admin__actions">
								<a class="asdevs-ai-admin__text-link" href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>">
									<?php esc_html_e( 'Manage keys in Connectors', 'asdevs-ai-assistant' ); ?>
								</a>
							</div>
						<?php endif; ?>
					</section>

					<?php $this->render_capabilities( $caps ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a status toast after save (or for the initial ?updated= redirect).
	 *
	 * @param bool $ready Whether the selected connector is ready to use.
	 */
	private function render_toast( bool $ready ): void {
		?>
		<div
			class="asdevs-ai-admin__toast <?php echo $ready ? 'asdevs-ai-admin__toast--success' : 'asdevs-ai-admin__toast--warning'; ?>"
			role="status"
		>
			<span class="asdevs-ai-admin__toast-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none">
					<?php if ( $ready ) : ?>
						<path d="M20 7 10.5 16.5 5 11" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
					<?php else : ?>
						<path d="M12 8v5.25M12 16.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						<path d="M10.29 4.86 2.82 17.5A1.8 1.8 0 0 0 4.36 20.2h15.28a1.8 1.8 0 0 0 1.54-2.7L13.71 4.86a1.8 1.8 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
					<?php endif; ?>
				</svg>
			</span>
			<div class="asdevs-ai-admin__toast-body">
				<p class="asdevs-ai-admin__toast-title">
					<?php
					echo $ready
						? esc_html__( 'Settings saved', 'asdevs-ai-assistant' )
						: esc_html__( 'Saved — one step left', 'asdevs-ai-assistant' );
					?>
				</p>
				<p class="asdevs-ai-admin__toast-text">
					<?php
					if ( $ready ) {
						esc_html_e( 'The assistant is ready. Open it from the button on any admin screen.', 'asdevs-ai-assistant' );
					} else {
						echo wp_kses(
							sprintf(
								/* translators: 1: opening anchor tag, 2: closing anchor tag */
								__( 'Add an API key in %1$sConnectors%2$s to finish setup.', 'asdevs-ai-assistant' ),
								'<a class="asdevs-ai-admin__toast-link" href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">',
								'</a>'
							),
							array(
								'a' => array(
									'class' => true,
									'href'  => true,
								),
							)
						);
					}
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the same capability list the assistant discovers via /capabilities.
	 *
	 * @param array<int, array<string, mixed>> $caps Capability collections.
	 */
	private function render_capabilities( array $caps ): void {
		$grouped = array();

		foreach ( $caps as $cap ) {
			if ( ! is_array( $cap ) ) {
				continue;
			}

			$namespace = (string) ( $cap['namespace'] ?? '' );
			if ( '' === $namespace ) {
				$namespace = __( 'Other', 'asdevs-ai-assistant' );
			}

			$grouped[ $namespace ][] = $cap;
		}

		$count = count( $caps );
		?>
		<section class="asdevs-ai-admin__card asdevs-ai-admin__caps">
			<div class="asdevs-ai-admin__card-head asdevs-ai-admin__card-head--simple">
				<h3 class="asdevs-ai-admin__card-title"><?php esc_html_e( 'What the assistant can reach', 'asdevs-ai-assistant' ); ?></h3>
				<p class="asdevs-ai-admin__card-desc">
					<?php
					esc_html_e( 'Live REST routes available to your account — the same map the assistant discovers before it acts.', 'asdevs-ai-assistant' );
					echo ' ';
					echo esc_html(
						sprintf(
							/* translators: %d: number of REST capability collections */
							_n( '(%d capability)', '(%d capabilities)', $count, 'asdevs-ai-assistant' ),
							$count
						)
					);
					?>
				</p>
			</div>

			<?php if ( array() === $grouped ) : ?>
				<p class="asdevs-ai-admin__caps-empty">
					<?php esc_html_e( 'No reachable REST capabilities were found for your account.', 'asdevs-ai-assistant' ); ?>
				</p>
			<?php else : ?>
				<div class="asdevs-ai-admin__caps-body">
					<?php foreach ( $grouped as $namespace => $items ) : ?>
						<div class="asdevs-ai-admin__caps-group">
							<h4 class="asdevs-ai-admin__caps-ns"><?php echo esc_html( (string) $namespace ); ?></h4>
							<ul class="asdevs-ai-admin__caps-list">
								<?php foreach ( $items as $cap ) : ?>
									<?php
									$label   = (string) ( $cap['label'] ?? '' );
									$base    = (string) ( $cap['base'] ?? '' );
									$methods = array_values( array_filter( (array) ( $cap['methods'] ?? array() ), 'is_string' ) );
									$methods = array_map( 'strtoupper', $methods );
									sort( $methods );
									?>
									<li class="asdevs-ai-admin__caps-item">
										<div class="asdevs-ai-admin__caps-face">
											<div class="asdevs-ai-admin__caps-copy">
												<?php if ( '' !== $label ) : ?>
													<span class="asdevs-ai-admin__caps-label"><?php echo esc_html( $label ); ?></span>
												<?php endif; ?>
												<?php if ( '' !== $base ) : ?>
													<code class="asdevs-ai-admin__caps-route"><?php echo esc_html( $base ); ?></code>
												<?php endif; ?>
											</div>
											<?php if ( array() !== $methods ) : ?>
												<span class="asdevs-ai-admin__caps-methods" aria-label="<?php esc_attr_e( 'HTTP methods', 'asdevs-ai-assistant' ); ?>">
													<?php foreach ( $methods as $method ) : ?>
														<?php
														$slug = strtolower( sanitize_html_class( $method ) );
														?>
														<span class="asdevs-ai-admin__caps-method asdevs-ai-admin__caps-method--<?php echo esc_attr( $slug ); ?>">
															<?php echo esc_html( $method ); ?>
														</span>
													<?php endforeach; ?>
												</span>
											<?php endif; ?>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * SVG menu icon as a data URI (chat bubble, matches the screen mark).
	 */
	private function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="black"><path d="M10 2c.72 1.9 1.31 2.53 3.22 3.25-1.91.72-2.5 1.35-3.22 3.25-.72-1.9-1.31-2.53-3.22-3.25C8.69 4.53 9.28 3.9 10 2z"/><path d="M15.1 9c.44 1.16.81 1.53 1.97 1.97-1.16.44-1.53.81-1.97 1.97-.44-1.16-.81-1.53-1.97-1.97 1.16-.44 1.53-.81 1.97-1.97z" opacity=".7"/><path d="M5.7 11.6c.34.9.62 1.19 1.53 1.53-.91.34-1.19.62-1.53 1.53-.34-.91-.62-1.19-1.53-1.53.91-.34 1.19-.62 1.53-1.53z" opacity=".45"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for WP admin menu SVG icons.
	}
}
