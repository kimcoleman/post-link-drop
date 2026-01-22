<?php
/**
 * Scheduled jobs and cron handling.
 *
 * @package Post_Link_Drop
 */

namespace Post_Link_Drop;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Jobs class.
 */
class Jobs {

	/**
	 * Cron hook name.
	 */
	const CRON_HOOK = 'post_link_drop_import_cron';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_import' ) );
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
		add_action( 'update_option_post_link_drop_import_enabled', array( $this, 'on_import_setting_change' ), 10, 2 );
		add_action( 'update_option_post_link_drop_import_interval', array( $this, 'on_interval_change' ), 10, 2 );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['pld_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes', 'post-link-drop' ),
		);

		$schedules['pld_30min'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 minutes', 'post-link-drop' ),
		);

		return $schedules;
	}

	/**
	 * Schedule the import cron job.
	 */
	public function schedule_import() {
		$import_enabled = get_option( 'post_link_drop_import_enabled', false );

		if ( ! $import_enabled ) {
			$this->unschedule_import();
			return;
		}

		// Check if already scheduled.
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		$interval = get_option( 'post_link_drop_import_interval', 'hourly' );

		// Validate interval.
		$valid_intervals = array( 'pld_15min', 'pld_30min', 'hourly', 'twicedaily', 'daily' );
		if ( ! in_array( $interval, $valid_intervals, true ) ) {
			$interval = 'hourly';
		}

		wp_schedule_event( time(), $interval, self::CRON_HOOK );
	}

	/**
	 * Unschedule the import cron job.
	 */
	public function unschedule_import() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}

		// Clear all instances.
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Run the scheduled import.
	 */
	public function run_scheduled_import() {
		// Check if import is still enabled.
		$import_enabled = get_option( 'post_link_drop_import_enabled', false );

		if ( ! $import_enabled ) {
			return;
		}

		// Check if credentials are set.
		$access_token = get_option( 'post_link_drop_access_token', '' );
		$user_id      = get_option( 'post_link_drop_ig_user_id', '' );

		if ( empty( $access_token ) || empty( $user_id ) ) {
			return;
		}

		// Run the import.
		$result = post_link_drop()->importer->run_import();

		// Log result.
		if ( is_wp_error( $result ) ) {
			update_option( 'post_link_drop_last_error', $result->get_error_message() );
		}
	}

	/**
	 * Handle import enabled setting change.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $new_value New value.
	 */
	public function on_import_setting_change( $old_value, $new_value ) {
		if ( $new_value ) {
			$this->unschedule_import();
			$this->schedule_import();
		} else {
			$this->unschedule_import();
		}
	}

	/**
	 * Handle interval setting change.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $new_value New value.
	 */
	public function on_interval_change( $old_value, $new_value ) {
		if ( $old_value === $new_value ) {
			return;
		}

		// Reschedule with new interval.
		$this->unschedule_import();
		$this->schedule_import();
	}

	/**
	 * Get the next scheduled import time.
	 *
	 * @return int|false Timestamp or false.
	 */
	public function get_next_scheduled() {
		return wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Check if import is currently scheduled.
	 *
	 * @return bool True if scheduled.
	 */
	public function is_scheduled() {
		return (bool) wp_next_scheduled( self::CRON_HOOK );
	}
}
