<?php
/*
 * Copyright (c) 2021. - Eighty / 20 Results by Wicked Strong Chicks.
 * ALL RIGHTS RESERVED
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace E20R\MailChimp;

use E20R\MailChimp\Licensing\Mailchimp_License;
use E20R\Utilities\Licensing\Licensing;
use E20R\MailChimp\Admin\Admin_Setup;
use E20R\MailChimp\Handlers\Member_Handler;
use E20R\Utilities\GDPR_Enablement;
use E20R\Utilities\Utilities;
use E20R\MailChimp\Admin\MC_Settings;
use E20R\MailChimp\Server\MailChimp_API;
use E20R\MailChimp\Server\Interest_Groups;
use E20R\MailChimp\Server\Merge_Fields;

if ( ! class_exists( 'E20R\MailChimp\Controller' ) ) {
	/**
	 * Class Controller
	 * @package E20R\MailChimp
	 */
	class Controller
	{

		/**
		 * Name of plugin directory (plugin slug)
		 */
		const plugin_slug = 'e20r-mailchimp-for-membership-plugins';

		/**
		 * @var null|\E20R\MailChimp\Controller
		 */
		private static $instance = null;

		/**
		 * @var string $plugin - Name of the plugin (for hooks)
		 */
		public static $plugin;

		/**
		 * Server license object
		 *
		 * @var null|Licensing
		 */
		private $licensing = null;

		/**
		 * Client license object
		 *
		 * @var null|Mailchimp_License $license_client
		 */
		private $license_client = null;

		/**
		 * Controller constructor.
		 */
		private function __construct()
		{
		}

		/**
		 * Return the name of this plugin
		 *
		 * @return string
		 */
		public function get_plugin_name()
		{
			return Controller::plugin_slug;
		}

		/**
		 * Returns an instance of the Controller class (or null)
		 *
		 * @return Controller|null
		 */
		public static function get_instance()
		{

			if (is_null(self::$instance)) {
				self::$instance = new self();
				self::$instance->licensing = new Licensing('E20R_MC');
				self::$instance->license_client = new Mailchimp_License();
				add_filter('e20r-licensing-text-domain', array(self::$instance, 'get_plugin_name'));
			}

			return self::$instance;
		}

		/**
		 * The plugins_loader hook handler for the E20R MailChimp for PMPro plugin
		 */
		public function plugins_loaded()
		{

			Utilities::get_instance()->log("Loading action handlers for member plugins");

			self::$plugin = plugin_basename(__FILE__);

			add_action('plugins_loaded', array(Admin_Setup::get_instance(), 'load_hooks'));
			add_action('plugins_loaded', array($this->licensing, 'load_hooks'), 11);
			add_action("plugins_loaded", array(Member_Handler::get_instance(), "load_plugin"), 12);
			add_action('plugins_loaded', array(GDPR_Enablement::get_instance(), 'load_hooks'), 98);
			add_action('plugins_loaded', array(MC_Settings::get_instance(), 'load_actions'), 99);

			add_action('init', array(User_Handler::get_instance(), 'load_actions'), 10);

			if (class_exists('E20R\MailChimp\Licensing\Mailchimp_License')) {
				add_action('init', array($this->license_client, 'load_hooks'), 99);
			}

			add_action('admin_enqueue_scripts', array($this, 'load_admin_styles'));
			add_action('wp_enqueue_scripts', array($this, 'load_frontend_styles'), 999);

		}

		/**
		 * Load CSS and Javascript to /wp-admin/
		 */
		public function load_admin_styles()
		{

			if (is_admin()) {
				wp_enqueue_style('e20r-mc-admin', E20R_MAILCHIMP_URL . 'css/e20r-mailchimp-for-membership-plugins-admin.css', array(), E20R_MAILCHIMP_VERSION);
			}
		}

		/**
		 * Are we currently on the login or registration page?
		 * (Includes support for Theme My Logins)
		 *
		 * @return bool
		 */
		public static function on_login_page()
		{

			global $post;

			$on_login_page = ($GLOBALS['pagenow'] === 'wp-login.php' && !empty($_REQUEST['action']) && $_REQUEST['action'] === 'register');
			$on_login_page = $on_login_page || (isset($post->post_content) && has_shortcode($post->post_content, 'theme-my-login'));

			return $on_login_page;
		}

		/**
		 * Load style(s) for frontend
		 *
		 * @since 2.2 - ENHANCEMENT: Account for PMPro styles
		 */
		public function load_frontend_styles()
		{

			if (wp_style_is('pmpro_frontend', 'enqueued')) {
				$deps = array('pmpro_frontend');
			} else {
				$deps = null;
			}

			wp_enqueue_style('e20r-mc', E20R_MAILCHIMP_URL . "css/e20r-mailchimp-for-membership-plugins.css", $deps, E20R_MAILCHIMP_VERSION);
		}


		/**
		 * Set Default options when activating plugin
		 */
		public function activation()
		{
			//get options
			$options = get_option("e20r_mc_settings", array());

			//defaults
			if (empty($options)) {

				$options = array(
					"api_key" => "",
					"double_opt_in" => 0,
					"unsubscribe" => 2,
					"members_list" => array(),
					"additional_lists" => array(),
					"level_merge_field" => "",
				);
				update_option("e20r_mc_settings", $options);

			} else if (!isset($options['unsubscribe'])) {

				$options['unsubscribe'] = 2;
				update_option("e20r_mc_settings", $options);
			}

			do_action('e20r-mailchimp-plugin-activation');
		}

		/**
		 * Unsubscribe a user from a specific list
		 *
		 * @param string $list_id - the List ID
		 * @param \WP_User $user - The WP_User object for the user
		 * @param array|null $merge_fields - The Merge Fields to use for the user/list ID
		 * @param array|null $interests - The Interests to use for the user/list ID
		 *
		 * @return bool
		 */
		public function unsubscribe($list_id, $user, $merge_fields = null, $interests = null)
		{

			//make sure user has an email address
			if (empty($user->user_email)) {
				return false;
			}

			$mc_api = MailChimp_API::get_instance();
			$utils = Utilities::get_instance();

			$unsub_setting = $mc_api->get_option('unsubscribe');

			if (!empty($mc_api)) {

				switch ($unsub_setting) {
					case 1:
					case 'all':

						$utils->log("Actually removing the user {$user->ID} from the {$list_id} list");

						return $mc_api->delete($list_id, $user, $merge_fields, $interests);
						break;

					case 2:

						// We're only updating the user/member's list settings
						$utils->log("Updating the member list settings (merge fields and Interests) for {$user->ID} on {$list_id}");

						return $mc_api->subscribe($list_id, $user, $merge_fields, $interests);
						break;

					default:
						return false;
				}


			} else {
				wp_die(__('Error during unsubscribe operation. Please report this error to the administrator', Controller::plugin_slug));
			}
		}

		/**
		 * Subscribe a user to a specific list
		 *
		 * @param string $list_id - the List ID
		 * @param \WP_User $user - The WP_User object for the user
		 * @param int $level_id - The membership level (ID) the user is being added/subscribed to
		 *
		 * @return bool
		 */
		public function subscribe($list_id, $user, $level_id)
		{

			$utils = Utilities::get_instance();
			$mc_api = MailChimp_API::get_instance();
			$ig_controller = Interest_Groups::get_instance();
			$mf_controller = Merge_Fields::get_instance();
			$level_ids = array();

			//make sure user has an email address
			if (empty($user->user_email)) {

				$utils->log("No user info??? " . print_r($user, true));

				return false;
			}

			$utils->log("Subscribe operation for {$user->user_email} for Level ID: {$level_id}");

			if (!is_null($level_id)) {
				$level_ids = array($level_id);
			}

			$merge_fields = $mf_controller->populate($list_id, $user, $level_ids);
			$interests = $ig_controller->populate($list_id, $user, $level_ids);

			$opt_in = $mc_api->get_option('double_opt_in');

			$email_type = apply_filters('e20r_mailchimp_default_mail_type', 'html');
			$has_consent = apply_filters('e20r-mailchimp-user-consent-provided', true, $user->ID);

			if (true === $has_consent) {
				$utils->log("Trying to subscribe {$user->ID} to list {$list_id} with double opt-in ({$opt_in}), GDPR consent ({$has_consent}) and type {$email_type}");

				return $mc_api->subscribe($list_id, $user, $merge_fields, $interests, $email_type, $opt_in);
			}

			do_action('e20r-mailchimp-process-consent', $has_consent, $user->ID);
		}

		/**
		 * Subscribe a user to any additional opt-in lists selected
		 *
		 * @param int $user_id
		 */
		public function subscribe_to_additional_lists($user_id, $level_id)
		{

			$utils = Utilities::get_instance();
			$additional_lists = $utils->get_variable('additional_lists', null);

			$has_consent = apply_filters('e20r-mailchimp-user-consent-provided', true, $user_id);

			if (!empty($additional_lists)) {
				update_user_meta($user_id, 'e20r_mc_additional_lists', $additional_lists);

				$list_user = get_userdata($user_id);

				foreach ($additional_lists as $list) {
					//subscribe them
					if (true === $has_consent) {
						$this->subscribe($list, $list_user, $level_id);
					}
				}
			}

			do_action('e20r-mailchimp-process-consent', $has_consent, $user_id);
		}
	}
}
