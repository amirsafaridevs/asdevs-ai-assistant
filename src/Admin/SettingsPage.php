<?php
/**
 * The AI service setup screen.
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
 * One screen, one job: connect an AI service once.
 *
 * There is no wizard, no onboarding and no welcome tour anywhere in this
 * plugin (section 24); this page exists only because a service is required.
 */
final class SettingsPage {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'asdevs_ai_assistant_save_settings';

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
	 * Add the menu entry.
	 */
	public function register_menu(): void {
		add_options_page(
			__( 'AI Assistant', 'asdevs-ai-assistant' ),
			__( 'AI Assistant', 'asdevs-ai-assistant' ),
			'manage_options',
			ASDEVS_AI_ASSISTANT_SLUG,
			array( $this, 'render' )
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
		$model    = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
		$key      = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';

		if ( ! isset( $this->providers->all()[ $provider ] ) ) {
			$provider = $this->settings->provider();
		}

		if ( isset( $_POST['forget_key'] ) ) {
			$this->settings->forget_key( $provider );
		}

		$this->settings->save( $provider, $model, $key );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => ASDEVS_AI_ASSISTANT_SLUG,
					'updated' => 'true',
				),
				admin_url( 'options-general.php' )
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

		$current  = $this->settings->provider();
		$model    = $this->settings->model();
		$ready    = $this->providers->is_ready();
		$has_key  = '' !== $this->settings->key_for( $current );
		$updated  = isset( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Assistant', 'asdevs-ai-assistant' ); ?></h1>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						echo $ready
							? esc_html__( 'Saved. The assistant is ready — open it from any admin screen.', 'asdevs-ai-assistant' )
							: esc_html__( 'Saved, but the assistant still needs a key before it can work.', 'asdevs-ai-assistant' );
						?>
					</p>
				</div>
			<?php endif; ?>

			<p>
				<?php esc_html_e( 'The assistant needs an AI service to work. Set it up once; you will not need to come back to this page.', 'asdevs-ai-assistant' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php wp_nonce_field( self::ACTION ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="asdevs-ai-provider"><?php esc_html_e( 'Service', 'asdevs-ai-assistant' ); ?></label>
						</th>
						<td>
							<select name="provider" id="asdevs-ai-provider">
								<?php foreach ( $this->providers->all() as $provider ) : ?>
									<option value="<?php echo esc_attr( $provider->id() ); ?>" <?php selected( $current, $provider->id() ); ?>>
										<?php echo esc_html( $provider->label() ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="asdevs-ai-model"><?php esc_html_e( 'Model', 'asdevs-ai-assistant' ); ?></label>
						</th>
						<td>
							<select name="model" id="asdevs-ai-model">
								<option value=""><?php esc_html_e( 'Recommended default', 'asdevs-ai-assistant' ); ?></option>
								<?php foreach ( $this->providers->all() as $provider ) : ?>
									<?php foreach ( $provider->models() as $id => $label ) : ?>
										<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $model, $id ); ?>>
											<?php echo esc_html( $provider->label() . ' — ' . $label ); ?>
										</option>
									<?php endforeach; ?>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="asdevs-ai-key"><?php esc_html_e( 'API key', 'asdevs-ai-assistant' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								name="api_key"
								id="asdevs-ai-key"
								class="regular-text"
								autocomplete="off"
								placeholder="<?php echo esc_attr( $has_key ? __( 'A key is saved. Leave blank to keep it.', 'asdevs-ai-assistant' ) : __( 'Paste the key here', 'asdevs-ai-assistant' ) ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'The key is stored on this site and is never sent to the browser or to us.', 'asdevs-ai-assistant' ); ?>
							</p>
							<?php if ( $has_key ) : ?>
								<p>
									<label>
										<input type="checkbox" name="forget_key" value="1" />
										<?php esc_html_e( 'Remove the saved key for this service', 'asdevs-ai-assistant' ); ?>
									</label>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save', 'asdevs-ai-assistant' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'What leaves your site', 'asdevs-ai-assistant' ); ?></h2>
			<p>
				<?php esc_html_e( 'When you write to the assistant, your message, the recent conversation, and the site information needed to answer it are sent to the AI service you selected above. Nothing is sent to us. Conversations are stored on this site, for your account only, and you can delete them at any time from the assistant.', 'asdevs-ai-assistant' ); ?>
			</p>
		</div>
		<?php
	}
}
