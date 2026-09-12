<?php
/**
 * Admin screens.
 *
 * @package SearchMiner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menu pages, settings, dashboard widget, data erase.
 */
final class WPSM_Admin {

	/**
	 * Bootstrap.
	 */
	public static function init() {
		add_menu_page(
			__( 'SearchMiner', 'searchminer' ),
			__( 'SearchMiner', 'searchminer' ),
			WPSM_CAP,
			'searchminer',
			array( __CLASS__, 'render_overview' ),
			'dashicons-chart-bar',
			75
		);
		add_submenu_page(
			'searchminer',
			__( 'Overview', 'searchminer' ),
			__( 'Overview', 'searchminer' ),
			WPSM_CAP,
			'searchminer',
			array( __CLASS__, 'render_overview' )
		);
		add_submenu_page(
			'searchminer',
			__( 'Settings', 'searchminer' ),
			__( 'Settings', 'searchminer' ),
			WPSM_CAP,
			'searchminer-settings',
			array( __CLASS__, 'render_settings' )
		);

		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_wpsm_erase', array( __CLASS__, 'handle_erase' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Register the dashboard widget. Runs on wp_dashboard_setup, when
	 * dashboard.php (and wp_add_dashboard_widget) is guaranteed to be loaded.
	 */
	public static function add_dashboard_widget() {
		if ( ! current_user_can( WPSM_CAP ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'wpsm_widget',
			__( 'SearchMiner — searches that found nothing', 'searchminer' ),
			array( __CLASS__, 'render_widget' )
		);
	}

	/**
	 * Our minimal admin CSS, only on our own pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'searchminer' ) ) {
			return;
		}
		wp_enqueue_style(
			'wpsm-admin',
			plugins_url( 'assets/css/admin.css', WPSM_FILE ),
			array(),
			WPSM_VERSION
		);
	}

	/**
	 * Register settings with sanitization callback.
	 */
	public static function register_settings() {
		register_setting(
			'wpsm_settings_group',
			WPSM_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WPSM_Settings', 'sanitize' ),
				'default'           => WPSM_Settings::defaults(),
			)
		);
	}

	/**
	 * Overview page: cards, chart, top tables.
	 */
	public static function render_overview() {
		$days = isset( $_GET['range'] ) ? absint( $_GET['range'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter.
		$days = max( 1, min( 365, $days ) );

		$top_all     = WPSM_Repository::top( 'hits', $days, 10 );
		$top_zero    = WPSM_Repository::top_zero( $days, 10 );
		$top_noclick = WPSM_Repository::top( 'noclick', $days, 10 );
		$series      = WPSM_Repository::daily_series( $days );

		$total = 0;
		$zero  = 0;
		foreach ( $series as $row ) {
			$total += (int) $row['searches'];
			$zero  += (int) $row['zero_searches'];
		}
		$pct = ( $total > 0 ) ? round( 100 * $zero / $total, 1 ) : 0.0;
		?>
		<div class="wrap wpsm-wrap">
			<h1><?php esc_html_e( 'SearchMiner', 'searchminer' ); ?></h1>

			<p class="wpsm-nav">
				<?php foreach ( array( 7, 30, 90 ) as $r ) : ?>
					<a class="<?php echo $days === $r ? 'wpsm-on' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=searchminer&range=' . $r ) ); ?>">
						<?php
						/* translators: %d: number of days. */
						echo esc_html( sprintf( __( 'Last %d days', 'searchminer' ), $r ) );
						?>
					</a>
				<?php endforeach; ?>
			</p>

			<div class="wpsm-cards">
				<div class="wpsm-card">
					<div class="wpsm-num"><?php echo esc_html( number_format_i18n( $total ) ); ?></div>
					<div class="wpsm-label">
						<?php
						/* translators: %d: number of days. */
						echo esc_html( sprintf( __( 'Searches in last %d days', 'searchminer' ), $days ) );
						?>
					</div>
				</div>
				<div class="wpsm-card">
					<div class="wpsm-num"><?php echo esc_html( number_format_i18n( $zero ) ); ?></div>
					<div class="wpsm-label"><?php esc_html_e( 'Found nothing', 'searchminer' ); ?></div>
				</div>
				<div class="wpsm-card">
					<div class="wpsm-num"><?php echo esc_html( $pct ); ?>%</div>
					<div class="wpsm-label"><?php esc_html_e( 'Zero-result rate', 'searchminer' ); ?></div>
				</div>
				<?php
				/**
				 * Fires right after the overview cards, so add-ons (e.g. Pro)
				 * can inject their own cards without touching this template.
				 */
				do_action( 'wpsm_overview_after_cards' );
				?>
			</div>

			<h2><?php esc_html_e( 'Searches per day', 'searchminer' ); ?></h2>
			<div class="wpsm-chart">
				<?php self::render_bars( $series ); ?>
			</div>

			<h2><?php esc_html_e( 'Searched and found nothing', 'searchminer' ); ?></h2>
			<?php self::render_table( $top_zero, 'zero_hits' ); ?>

			<h2><?php esc_html_e( 'Searched, got results, did not click', 'searchminer' ); ?></h2>
			<?php self::render_table( $top_noclick, 'no_click_count' ); ?>

			<h2><?php esc_html_e( 'Most popular searches', 'searchminer' ); ?></h2>
			<?php self::render_table( $top_all, 'hits' ); ?>
		</div>
		<?php
	}

	/**
	 * Pure-CSS bar chart (no JS dependency).
	 *
	 * @param array[] $series Daily rows.
	 */
	private static function render_bars( $series ) {
		if ( empty( $series ) ) {
			echo '<p>' . esc_html__( 'No searches recorded yet. Once visitors use your search, data will appear here.', 'searchminer' ) . '</p>';
			return;
		}
		$max = 1;
		foreach ( $series as $row ) {
			$max = max( $max, (int) $row['searches'] );
		}
		echo '<div class="wpsm-bars">';
		foreach ( $series as $row ) {
			$h     = (int) $row['searches'];
			$z     = (int) $row['zero_searches'];
			$hp    = (int) round( 100 * $h / $max );
			$zp    = ( $h > 0 ) ? (int) round( 100 * $z / $max ) : 0;
			/* translators: 1: date, 2: searches, 3: zero-result searches. */
			$tip = sprintf( __( '%1$s: %2$d searches, %3$d found nothing', 'searchminer' ), $row['day'], $h, $z );
			printf(
				'<div class="wpsm-bar" title="%1$s"><span class="wpsm-bar-hit" style="height:%2$d%%"></span><span class="wpsm-bar-zero" style="height:%3$d%%"></span></div>',
				esc_attr( $tip ),
				(int) $hp,
				(int) $zp
			);
		}
		echo '</div>';
	}

	/**
	 * One top table.
	 *
	 * @param array[] $rows  Rows.
	 * @param string  $metric Primary metric column to highlight.
	 */
	private static function render_table( $rows, $metric ) {
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Nothing here yet.', 'searchminer' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped wpsm-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Query', 'searchminer' ); ?></th>
					<th><?php esc_html_e( 'Times searched', 'searchminer' ); ?></th>
					<th><?php esc_html_e( 'Found nothing', 'searchminer' ); ?></th>
					<th><?php esc_html_e( 'Clicked a result', 'searchminer' ); ?></th>
					<th><?php esc_html_e( 'No click', 'searchminer' ); ?></th>
					<th><?php esc_html_e( 'WooCommerce', 'searchminer' ); ?></th>
					<th><?php esc_html_e( 'Last seen', 'searchminer' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr class="<?php echo ( (int) $row[ $metric ] > 0 ) ? 'wpsm-hot' : ''; ?>">
					<td><?php echo esc_html( $row['query_text'] ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['hits'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['zero_hits'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['click_count'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['no_click_count'] ) ); ?></td>
					<td><?php echo ( (int) $row['is_woo'] ) ? esc_html__( 'Yes', 'searchminer' ) : ''; ?></td>
					<td><?php echo esc_html( $row['last_seen'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Settings screen.
	 */
	public static function render_settings() {
		if ( ! current_user_can( WPSM_CAP ) ) {
			return;
		}
		$s = WPSM_Settings::all();
		if ( isset( $_GET['wpsm_erased'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only confirmation banner.
			echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__( 'All recorded searches have been erased.', 'searchminer' ) . '</p></div>';
		}
		?>
		<div class="wrap wpsm-wrap">
			<h1><?php esc_html_e( 'SearchMiner — Settings', 'searchminer' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'wpsm_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Capture searches', 'searchminer' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( WPSM_Settings::OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?> />
							<?php esc_html_e( 'Record visitor searches', 'searchminer' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Capture for', 'searchminer' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( WPSM_Settings::OPTION ); ?>[capture_mode]" value="all" <?php checked( $s['capture_mode'], 'all' ); ?> />
							<?php esc_html_e( 'Everyone (bots are always filtered out)', 'searchminer' ); ?></label><br />
							<label><input type="radio" name="<?php echo esc_attr( WPSM_Settings::OPTION ); ?>[capture_mode]" value="guests" <?php checked( $s['capture_mode'], 'guests' ); ?> />
							<?php esc_html_e( 'Logged-out visitors only', 'searchminer' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Track result clicks', 'searchminer' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( WPSM_Settings::OPTION ); ?>[track_clicks]" value="1" <?php checked( $s['track_clicks'], 1 ); ?> />
							<?php esc_html_e( 'Detect searches where nothing was clicked (adds a tiny script to search result pages only)', 'searchminer' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Keep data for', 'searchminer' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( WPSM_Settings::OPTION ); ?>[retention]">
								<?php foreach ( array( 30, 60, 90, 180, 365 ) as $d ) : ?>
									<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $s['retention'], $d ); ?>>
										<?php
										/* translators: %d: number of days. */
										echo esc_html( sprintf( _n( '%d day', '%d days', $d, 'searchminer' ), $d ) );
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Older rows are deleted automatically. Short retention is better for privacy.', 'searchminer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpsm-exclude-ips"><?php esc_html_e( 'Excluded IP addresses', 'searchminer' ); ?></label></th>
						<td>
							<textarea id="wpsm-exclude-ips" name="<?php echo esc_attr( WPSM_Settings::OPTION ); ?>[exclude_ips]" rows="4" class="large-text code"><?php echo esc_textarea( $s['exclude_ips'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One per line. Use trailing .* for a prefix, e.g. 192.168.1.*. These searches are not recorded.', 'searchminer' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Stored data', 'searchminer' ); ?></h2>
			<p><?php esc_html_e( 'SearchMiner stores search phrases only. It never stores IP addresses or user accounts.', 'searchminer' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					onsubmit="return confirm('<?php echo esc_js( __( 'Delete ALL recorded searches? This cannot be undone.', 'searchminer' ) ); ?>');">
				<input type="hidden" name="action" value="wpsm_erase" />
				<?php wp_nonce_field( 'wpsm_erase' ); ?>
				<?php submit_button( __( 'Erase all recorded searches', 'searchminer' ), 'delete', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Erase handler (admin-post).
	 */
	public static function handle_erase() {
		if ( ! current_user_can( WPSM_CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'searchminer' ) );
		}
		check_admin_referer( 'wpsm_erase' );

		WPSM_Repository::erase_all();

		wp_safe_redirect( add_query_arg( array( 'page' => 'searchminer-settings', 'wpsm_erased' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Dashboard widget content.
	 */
	public static function render_widget() {
		$rows = WPSM_Repository::top_zero( 30, 5 );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No zero-result searches in the last 30 days.', 'searchminer' ) . '</p>';
			return;
		}
		echo '<ul class="wpsm-widget-list">';
		foreach ( $rows as $row ) {
			printf(
				'<li>%1$s — %2$s</li>',
				esc_html( $row['query_text'] ),
				/* translators: %d: number of times. */
				esc_html( sprintf( _n( '%d time', '%d times', (int) $row['zero_hits'], 'searchminer' ), (int) $row['zero_hits'] ) )
			);
		}
		echo '</ul>';
		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=searchminer' ) ),
			esc_html__( 'View full report', 'searchminer' )
		);
	}
}
