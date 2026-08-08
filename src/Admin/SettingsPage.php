<?php
/**
 * The AI connector preference screen.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Admin;

use ASDevs\AIAssistant\Ai\ProviderRegistry;
use ASDevs\AIAssistant\Ai\Settings;

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
	 * Constructor.
	 *
	 * @param Settings         $settings  Settings.
	 * @param ProviderRegistry $providers Providers.
	 */
	public function __construct( Settings $settings, ProviderRegistry $providers ) {
		$this->settings  = $settings;
		$this->providers = $providers;
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
	 * Load screen styles only on this page.
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

					<span class="asdevs-ai-admin__status <?php echo $ready ? 'asdevs-ai-admin__status--ready' : 'asdevs-ai-admin__status--needs-setup'; ?>">
						<span class="asdevs-ai-admin__status-dot" aria-hidden="true"></span>
						<?php
						echo $ready
							? esc_html__( 'Ready', 'asdevs-ai-assistant' )
							: esc_html__( 'Needs setup', 'asdevs-ai-assistant' );
						?>
					</span>
				</header>

				<div class="asdevs-ai-admin__stack">
					<?php if ( $updated ) : ?>
						<div class="asdevs-ai-admin__alert <?php echo $ready ? 'asdevs-ai-admin__alert--success' : 'asdevs-ai-admin__alert--warning'; ?>" role="status">
							<svg class="asdevs-ai-admin__alert-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
								<?php if ( $ready ) : ?>
									<path d="M20 7 10.5 16.5 5 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<?php else : ?>
									<path d="M12 9v4.5M12 17h.01M10.3 4.8 2.8 18a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4.8a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
								<?php endif; ?>
							</svg>
							<p>
								<?php
								echo $ready
									? esc_html__( 'Saved. The assistant is ready — open it from any admin screen.', 'asdevs-ai-assistant' )
									: esc_html__( 'Saved, but the selected connector still needs a key under Settings → Connectors.', 'asdevs-ai-assistant' );
								?>
							</p>
						</div>
					<?php endif; ?>

					<section class="asdevs-ai-admin__card">
						<div class="asdevs-ai-admin__card-head">
							<div>
								<h3 class="asdevs-ai-admin__card-title"><?php esc_html_e( 'Preferred connector', 'asdevs-ai-assistant' ); ?></h3>
								<p class="asdevs-ai-admin__card-desc">
									<?php esc_html_e( 'Models and API keys are managed by WordPress for the connector you pick.', 'asdevs-ai-assistant' ); ?>
								</p>
							</div>
							<a class="asdevs-ai-admin__btn asdevs-ai-admin__btn--ghost" href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>">
								<?php esc_html_e( 'Open Connectors', 'asdevs-ai-assistant' ); ?>
							</a>
						</div>

						<?php if ( array() === $providers ) : ?>
							<div class="asdevs-ai-admin__empty">
								<p>
									<?php esc_html_e( 'No AI provider plugins are active yet. Install Anthropic, OpenAI, or Google from Settings → Connectors, then return here.', 'asdevs-ai-assistant' ); ?>
								</p>
								<a class="asdevs-ai-admin__btn asdevs-ai-admin__btn--primary" href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>">
									<?php esc_html_e( 'Open Connectors', 'asdevs-ai-assistant' ); ?>
								</a>
							</div>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
								<?php wp_nonce_field( self::ACTION ); ?>

								<div class="asdevs-ai-admin__providers" role="radiogroup" aria-label="<?php esc_attr_e( 'WordPress connector', 'asdevs-ai-assistant' ); ?>">
									<?php foreach ( $providers as $provider ) : ?>
										<?php
										$configured = $provider->is_configured();
										$id         = 'asdevs-ai-provider-' . $provider->id();
										?>
										<label class="asdevs-ai-admin__provider" for="<?php echo esc_attr( $id ); ?>">
											<input
												type="radio"
												name="provider"
												id="<?php echo esc_attr( $id ); ?>"
												value="<?php echo esc_attr( $provider->id() ); ?>"
												<?php checked( $current, $provider->id() ); ?>
											/>
											<span class="asdevs-ai-admin__provider-face">
												<span class="asdevs-ai-admin__provider-copy">
													<span class="asdevs-ai-admin__provider-name"><?php echo esc_html( $provider->label() ); ?></span>
													<span class="asdevs-ai-admin__provider-meta">
														<?php
														echo $configured
															? esc_html__( 'Connected in WordPress', 'asdevs-ai-assistant' )
															: esc_html__( 'Needs an API key in Connectors', 'asdevs-ai-assistant' );
														?>
													</span>
												</span>
												<span class="asdevs-ai-admin__chip <?php echo $configured ? 'asdevs-ai-admin__chip--ok' : 'asdevs-ai-admin__chip--off'; ?>">
													<?php
													echo $configured
														? esc_html__( 'Configured', 'asdevs-ai-assistant' )
														: esc_html__( 'Not configured', 'asdevs-ai-assistant' );
													?>
												</span>
											</span>
										</label>
									<?php endforeach; ?>
								</div>

								<div class="asdevs-ai-admin__actions">
									<button type="submit" class="asdevs-ai-admin__btn asdevs-ai-admin__btn--primary">
										<?php esc_html_e( 'Save changes', 'asdevs-ai-assistant' ); ?>
									</button>
								</div>
								<p class="asdevs-ai-admin__hint">
									<?php esc_html_e( 'After saving, open the assistant from the floating button on any admin screen.', 'asdevs-ai-assistant' ); ?>
								</p>
							</form>
						<?php endif; ?>
					</section>

					<section class="asdevs-ai-admin__card asdevs-ai-admin__privacy">
						<h3 class="asdevs-ai-admin__card-title"><?php esc_html_e( 'What leaves your site', 'asdevs-ai-assistant' ); ?></h3>
						<p class="asdevs-ai-admin__privacy-body">
							<?php esc_html_e( 'When you write to the assistant, your message, the recent conversation, and the site information needed to answer it are sent to the AI provider behind the WordPress connector you selected. Nothing is sent to us. Conversations are stored on this site, for your account only, and you can delete them at any time from the assistant.', 'asdevs-ai-assistant' ); ?>
						</p>
					</section>
				</div>
			</div>
		</div>
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
