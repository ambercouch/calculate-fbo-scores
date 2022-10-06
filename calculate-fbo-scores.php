<?php
/*
Plugin Name: Calculate Fantasy Bake Off Scores
Plugin URI: http://www.creo.co.uk
Description: Calculate scores for each user
Version: 1.0
Author: Creo Interactive
Author URI: http://www.creo.co.uk
Text Domain: fantasy-bake-off-scores
*/

class FBO_Calculate_Scores {

	protected static $_instance = null;

	public function __construct() {
		load_plugin_textdomain( 'fantasy-bake-off-scores', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );

		add_action( 'wp_ajax_fbo_calculate_scores', array( $this, 'ajax_calculate_scores' ) );
		add_action( 'wp_ajax_nopriv_fbo_calculate_scores', array( $this, 'ajax_calculate_scores' ) );
	}

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function load_scripts( $hook_suffix ) {

		if( $hook_suffix == 'toplevel_page_calculate-fbo-scores' ) {
			$assets_path = untrailingslashit( plugins_url( '/', __FILE__ ) )  . '/assets/';
			wp_enqueue_script( 'calculate-fbo-scores', $assets_path . 'js/main.js', array( 'jquery' ), null, true );
			$data = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'calculate-fbo-scores-nonce' )
			);
			wp_localize_script( 'calculate-fbo-scores', 'calculate_fbo_scores', $data );

			wp_enqueue_style( 'calculate-fbo-scores', $assets_path . 'css/main.css' );
		}
	}


	private function count_users() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM fbo_users";

		return $wpdb->get_var($sql);
	}

	private function get_users( $offset, $limit ) {
		global $wpdb;

		$sql = "SELECT user_id, winners
		FROM fbo_users
		LIMIT %d, %d";

		return $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				$offset,
				$limit
			)
		);
	}

	private function get_weeks() {

		$args = array(
			'posts_per_page' => -1,
			'post_type' => 'week',
			'post_status' => 'publish',
			'fields' => 'ids'
		);

		return get_posts( $args );
	}

	private function calculate_scores( $offset, $limit ) {

		$users = $this->get_users( $offset, $limit );
		$weeks = $this->get_weeks();
		$points = Roots\Sage\Weeks\category_points();

		if( $users ) {
			foreach ( $users as $user ) {

				$total_score = 0;

				foreach ( $weeks as $week_id ) {

					$weekly_score = 0;

					if ( get_field( 'last_week', $week_id ) && $user->winners ) {

						$winners = unserialize( $user->winners );
						if (is_array($winners))
            {
                // One of the user's overall winner choices won the series
                if (in_array(get_field('best_baker', $week_id), $winners))
                {
                    $weekly_score += $points['overall_winner'];
                }

                // One of the user's overall winner choices was a runnerup in the final
                if (in_array(get_field('runnerup_1', $week_id), $winners))
                {
                    $weekly_score += $points['overall_runnerup'];
                }
                if (in_array(get_field('runnerup_2', $week_id), $winners))
                {
                    $weekly_score += $points['overall_runnerup'];
                }
            }
					}

					$week_data = Roots\Sage\Users\get_week_categories_data( $user->user_id, $week_id, true );
					$weekly_score += $week_data->best_baker_points;
					$weekly_score += $week_data->technical_points;
					$weekly_score += $week_data->eliminated_points;
					$weekly_score += $week_data->bonus_points;

					// No nominations were made so reduce the score by 2
					// week_id - 434 was the first week of the 2016 show when the site went down, the client didnt want anyone to have -2 points for not making a nomination
					// if( $week_id != 434 && $week_data->best_baker_result && !$week_data->best_baker && ( ( $week_data->technical_result && !$week_data->technical ) || get_field( 'last_week', $week_id ) ) && ( ( $week_data->eliminated_result && !$week_data->eliminated ) || get_field( 'last_week', $week_id ) ) && !$week_data->bonus ) {
					// 	$weekly_score -= 2;
					// }

					if( !Roots\Sage\Users\has_week_nominations( $user->user_id, $week_id ) )
						Roots\Sage\Users\set_week_nominations( $user->user_id, $week_id );

					Roots\Sage\Users\set_week_score( $user->user_id, $week_id, $weekly_score );

					$total_score += $weekly_score;
				}

				Roots\Sage\Users\set_overall_score( $user->user_id, $total_score );
			}
		}
	}

	public function ajax_calculate_scores() {
		check_ajax_referer( 'calculate-fbo-scores-nonce', 'nonce' );

		if( $_POST['first_request'] )
			update_option( 'fbo_calculating_scores', 1 );

		try {
			$this->calculate_scores( $_POST['rows_offset'], $_POST['rows_limit'] );

			if( $_POST['last_request'] )
				update_option( 'fbo_calculating_scores', 0 );

		} catch( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
			die();
		}

		wp_send_json_success();
		die();
	}


	public function admin_menu() {
		add_menu_page(
			__( 'Calculate Scores', 'fantasy-bake-off-scores' ),
			__( 'Calculate Scores', 'fantasy-bake-off-scores' ),
			'manage_options',
			'calculate-fbo-scores',
			array( $this, 'admin_page' ),
			'dashicons-hammer',
			2
		);
	}

	public function admin_page() {

		if( isset( $_GET['override_score_error'] ) && $_GET['override_score_error'] )
			update_option( 'fbo_calculating_scores', 0 );

		?>

		<div class="wrap">

			<?php if( isset( $_GET['success'] ) || isset( $_GET['error'] ) ) : ?>
				<div class="message-holder">
					<div class="text-<?php echo isset( $_GET['success'] ) ? 'success' : 'danger'; ?>">
						<?php echo isset( $_GET['success'] ) ? __( 'Score calcuations were made successfully', 'fantasy-bake-off-scores' ) : $_GET['error']; ?>
					</div>
				</div>
			<?php endif; ?>

			<h1><?php _e( 'Calculate Scores', 'fantasy-bake-off-scores' ); ?></h1>
			<p></p>
			<p><?php _e( 'Clicking the below button will cause the system to automatically go through each user and calculate their weekly and overall scores.', 'fantasy-bake-off-scores' ); ?></p>
			<p><?php _e( 'Users will not be able to see any scores or make any changes to any nominations when this process is running.', 'fantasy-bake-off-scores' ); ?></p>
			<div id="calculate-fbo-scores" class="button button-primary" data-active="0" data-page-url="<?php echo admin_url('admin.php?page=calculate-fbo-scores'); ?>"><?php _e( 'Calculate', 'fantasy-bake-off-scores' ); ?></div>

			<div id="progress-holder" class="hidden" data-total-users="<?php echo $this->count_users(); ?>">
				<p>
					<span class="spinner is-active"></span>
					<span class="progress"><?php _e( 'Calculation progress:', 'fantasy-bake-off-scores' ); ?> <strong><span>0</span></strong>%</span>
				</p>
				<div class="text-danger"><?php _e( 'DO NOT CLOSE THIS PAGE WHEN THE CALCULATION IS RUNNING.', 'fantasy-bake-off-scores' ); ?></div>
			</div>

			<?php if ( get_option( 'fbo_calculating_scores', 0 ) ) : ?>
				<div id="locked-scores-holder">
					<h2 class="text-danger"><?php _e( 'Scores are currently locked', 'fantasy-bake-off-scores' ); ?></h2>
					<p></p>
					<p><?php _e( 'Scores are currently locked and are not visible to the user on the website, this is due to the calculation process going wrong. <span class="text-danger">Please re-run the calculation process.</span>', 'fantasy-bake-off-scores' ); ?></p>
					<p><?php _e( 'Alternatively you can use the below button to override the system and make the scores visible to users however the scores will be wrong.', 'fantasy-bake-off-scores' ); ?></p>
					<a href="<?php echo add_query_arg( 'override_score_error', 1, admin_url('admin.php?page=calculate-fbo-scores') ); ?>" class="button button-secondary"><?php _e( 'Make scores visible', 'fantasy-bake-off-scores' ); ?></a>
				</div>
			<?php endif; ?>
		</div>

		<?php
	}

}

function FBO_Calculate_Scores() {
	return FBO_Calculate_Scores::instance();
}
$GLOBALS['fbo_calculate_scores'] = FBO_Calculate_Scores();
