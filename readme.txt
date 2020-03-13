=== E20R MailChimp Interest Groups for Paid Memberships Pro and WooCommerce ===
Contributors: eighty20results
Tags: mailchimp, paid memberships pro, pmpro, membership plugin, email marketing, woocommerce, distribution list support, merge tags, interests, mailchimp groups, mailchimp interest groups
Requires at least: 4.5
Requires PHP: 7.1
Tested up to: 5.4
Stable tag: 5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WordPress based Users, Members (Paid Memberships Pro) or customers (WooCommerce) to a MailChimp list with support for Groups (Interests) and Merge Tags.

== Description ==

Specify the subscription list(s) for your site's WordPress Users and Member plugin users. If a supported membership plugin is installed, you can specify additional list settings by either the membership level or the product category purchased.

The plugin has a setting to require/not require MailChimp's double opt-in, as well as a setting to change interests and merge tags for members on level change. This allows you to automatically re-segment your users based on membership level or custom merge tag data. This plugin does _not_ unsubscribe users from MailChimp lists _unless_ it's been explicitly configured to do so.

We do not recommend using multiple lists for different membership levels. Instead, this plugin uses a single list for all members with segmentation managed through Groups/ Interests, and merge tag values.

== Installation ==
This plugin works with and without a member plugin installed.

== Download, Install and Activate! ==
1. Upload the `e20r-mailchimp-for-membership-plugins` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. The settings page is at Settings --> E20R MailChimp in the WordPress admin dashboard.

= Settings =
== Mailchimp API Key ==
The API key for your account to allow access to your lists and objects on the MailChimp.com system. You can find your API after you log in to your account on mailchimp.com, click on the drop down for your username/account, and select the "Account" page. Find the "Extras" menu and select "API Keys". In the "Your API Keys" section you'll either find the existing API key, or you can "Create a Key". Copy the value in the "API key" field and paste it to your WordPress "MailChimp Integration Options and Settings" page.

== Objects fetched per API call (number) ==
By default, the MailChimp.com servers limit the number of objects it will let an API call fetch. The default limit is 15 objects. An object is a mailing list, a merge field, interests in an interest category, etc. This setting is used to increase or decrease the visible number of objects in the plugin.

== Membership Plugin to use ==
Select the member plugin you have installed and activated on the site. This ensures the plugin loads the correct compatibility module. See the listing below for the currently supported compatibility modules.

== Email address to use for subscription ==
This setting is only available once you've selected the WooCommerce compatibility module and saved the settings.

It is used to select the email address use when subscribing to the MailChimp list(s). It is possible for a user to be logged in to your system with one email address, but then order in the WooCommerce shop on behalf of different billing address/user. This setting lets you specify whether to use the email address of the logged in user, or that of the billing address. The default is the Billing address email.

Please note that if the email address specified as the Billing email doesn't link to a user account on your WordPress system, we will default to using the user account either created or logged in to when the order was paid for. It's (currently!) not possible to add anonymous orders to the MailChimp list(s).

= User selectable for checkout/profile (opt-in) =
The user can opt in to one or more additional list(s) during the checkout phase for the shop/plugin you have an active compatibility module for, if they choose. Select the lists to offer them by holding down the CTRL or Command (MacOS) key and clicking on the list name. To deselect one or more selected lists, hold down the CTRL or Command (MacOS) key and click the list to deselect.

= Require Double Opt-in? =
Select Yes or No to require/not require MailChimp's double opt-in (send the new member/user a confirmation email message before they're joined to the list).

= Unsubscribe on Level Change? =
By default, this setting will change the Interests for the user in the "Membership Level" or "Product" group to "Cancelled" if/when a user has cancelled all of their active membership levels / subscriptions/ products (if applicable). You may also set it to "Clear Interest Groups (old membership levels)" which will clear the Membership Levels Group interests for any previous membership or product group the user has purchased.

= Add new members to =
The MailChimp list you wish to add the new member / user to (segmented by Interests and/or Merge Tags).

== Filters & Hooks ==

1. e20r-mailchimp-member-interest-names - Array of interests defined for the "Membership names" group. Accepts one argument: string[]
1. e20r-mailchimp-interest-category-label - Group name for Membership Level interests. Accepts 1 argument: string $label_name
1. e20r-mailchimp-list-interest-category-type - Type of interest (default: checkbox). Accepts 2 arguments: string  $interest_form_input_type (checkbox, see MailChimp.com API documentation for options), string $list_id (the list it applies to).
1. e20r-mailchimp-assign-interest-to-user - Do we assign the interest to the user being processed (default: true). Accepts 5 arguments: boolean $enabled, WP_User $user, string $interest, string $list_id, int $level_id)
1. e20r-mailchimp-interests-to-assign-to-user - The interest IDs to assign to the user. Accepts 5 arguments: string[] $interest_ids, WP_User $user, string $list_id, bool $cancelling, int[] $level_ids
1. e20r-mailchimp-member-merge-field-defs - Definitions for Merge Tags/Fields to assign the user. Accepts 2 arguments: array() $merge_field_definitions, string $list_id
1. e20r-mailchimp-member-merge-field-values - Values for the defined Merge Tags/Fields being assigned for the user. Accepts 4 arguments: array $values, WP_User $user, string $list_id, int $level_id
1. e20r-mailchimp-merge-tag-settings - The merge field value/data array to submit to the MailChimp distribution list. Accepts 3 arguments: array $merge_fields, string $list_id, int $level_id
1. e20r-mailchimp-user-defined-merge-tag-fields - The merge tag field definitions. Accepts 3 arguments: array $merge_fields, string $list_id, int $level_id

More filters and hooks can be found in the sources for the plugin on [github.com](https://github.com/eighty20results.com/repositories/e20r-mailchimp-for-membership-plugins)

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. [Add issues](https://github.com/eighty20results/e20r-mailchimp-for-membership-plugins/issues)

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium [support](https://eighty20results.com/) for more documentation and to access our forums.

== Screenshots ==

1. General settings for all members/subscribers list, opt-in rules, and unsubscribe rules.
2. Membership-level specific Groups/Interests and Merge Tag settings.

== Changelog ==

== 5.0 ==

* ENHANCEMENT: Refactor settings & option handling
* ENHANCEMENT: Add E20R MailChimp to PMPro 'Memberships' wp-admin menu(s)
* ENHANCEMENT: Better logging w/E20R_LICENSING_DEBUG enabled
* ENHANCEMENT: Added 'e20r-license-save-btn-text' field to allow user(s) to configure Submit/Save button for license(s)
* ENHANCEMENT: Added support for verifying license manually (and refresh the cache) from the E20R License page
* BUG FIX: Didn't always include add-on specific default settings
* BUG FIX: Change on mailchimp.com and can no longer encode the JSON w/o triggering error
* BUG FIX: Don't use default 'Basic' as user for authentication against MailChimp API
* BUG FIX: Triggered an enormous number of requests against the license verification services. Now caching data a bit better (I hope)
* BUG FIX: PHP Notice while processing when the license is scheduled to expire/be renewed
* BUG FIX: Check License button is too big
* BUG FIX: Updated license SKU info
* BUG FIX: Didn't properly handle turning off checkbox options from 3rd party modules/plug-ins
* BUG FIX: Loading settings page shouldn't force uncached license check
* BUG FIX: Refactored load_list_data() method - shared between all modules so now in base class
* BUG FIX: Didn't process events properly in JS files

== 4.1.3 ==

* BUG FIX: Updated Licensing module 

== 4.1.2 ==

* BUG FIX: Updated Utilities module to fix PHP Notice messages 

== 4.1.1 ==

* BUG FIX: Add support for PMPro registration checks in WooCommerce plugin support module

== 4.0.1 ==

* ENHANCEMENT: Updated the Utilities module

== 4.0 ==

* ENHANCEMENT: Added docker based test environment
* ENHANCEMENT: Updated copyright notice
* ENHANCEMENT: Change license management tools
* BUG FIX: Didn't handle spl errors
* BUG FIX: Didn't load the correct update checker

== 3.1 ==

* ENHANCEMENT: Clear the list/user cache entry for the user in the MailChimp_API::delete(), subscribe() and remote_user_update() methods
* ENHANCEMENT: Cache list info for user (avoid remote requests when possible)
* ENHANCEMENT: WPCS applied to Member_Handler() class
* ENHANCEMENT: Add Membership_Plugin::get_last_for_user() abstract function
* ENHANCEMENT: Add filter to fetch most recent 'levels' (or category IDs) for a user based on their last order/checkout
* ENHANCEMENT: Set the cursor to 'waiting' after submitting AJAX requests
* ENHANCEMENT: Set the cursor back to normal if we receive an error from the AJAX call
* ENHANCEMENT: Added the appropriate label for the plugin on the tabbed Settings page
* BUG FIX: Empty list of membership IDs didn't trigger cancellation processing in Merge_Fields::populate()
* BUG FIX: Didn't always respect unsubscribe setting in Interest_Groups::populate()
* BUG FIX: Would use 'Level:' label for WooCommerce categories
* BUG FIX: Improved text for the Plus license banner
* BUG FIX: Didn't include PHPDoc block for default_timeout variable
* BUG FIX: Have to json_encode() the body before submitting the request in MailChimp_API::execute()
* BUG FIX: Refactored MailChimp_API::update_list_member() to use remote_user_update()
* BUG FIX: Always returned true in MailChimp_API::delete()
* BUG FIX: Don't log the REQUEST during user save operation
* BUG FIX: MailChimp_API::delete() didn't handle empty interests or merge fields when using unsubscribe option 2 (change interest groups)
* BUG FIX: Fix the PHPDoc block for Controller::unsubscribe()

== 3.0 ==

* ENHANCEMENT: Add button processing to re-enable the background update of members button
* ENHANCEMENT: Better articulation of purpose of the E20R MailChimp Plus (Support & Updates) license
* ENHANCEMENT: Better highlight the value of the E20R MailChimp Plus (Support & Updates) license
* ENHANCEMENT: Add 'build-for-svn.sh' script
* ENHANCEMENT: Support the 'build-for-svn.sh' script
* BUG FIX: Load testing functionality if E20R_MC_TESTING constant isn't FALSE
* BUG FIX: Add languages folder (.pot file) for translation
* BUG FIX: Didn't remove the One-Click updater in build-for-svn.sh script
* BUG FIX: Clarify renewal message text
* BUG FIX: Error isolation for E20R MailChimp admin features

== 2.11 ==

* BUG FIX: Handle both WP_Error and fail codes for HTTP status on return of request
* BUG FIX: Use MailChimp_API::execute() method for all requests
* BUG FIX: Don't send error messages to backend/front-end when running in background
* BUG FIX: Move HTML elements from translatable text
* BUG FIX: Skip unnecessary executions of wp_remote_retrieve_response_message()
* BUG FIX: Renamed e20r_mailchimp_cache_timeout_secs filter to e20r-mailchimp-cache-timeout-secs
* BUG FIX: Background task handler looping infinitely if server had unexpected process timeout values
* ENHANCEMENT: Make request timeout (default_timeout) a class member variable
* ENHANCEMENT: Add e20r-mailchimp-api-http-timeout filter (to set request timeout value)
* ENHANCEMENT: Add own MailChimp_API::execution() method to extend request timeout if needed
* ENHANCEMENT: Add MailChimp_API::process_error() method to return standard format for WP_Error and request statuses other than 2xx/3xx
* ENHANCEMENT: Add additional section if plugin isn't licensed

== 2.10 ==

* BUG FIX: Would trigger MailChimp update for renewal subscription payments
* BUG FIX: PHP Notice if unexpected setting name
* BUG FIX: Remove extra debug logging in MailChimp_API() class
* BUG FIX: Simplify debug logging output (less print_r() of arrays)
* BUG FIX: WooCommerce Customer import to MailChimp is in the Plus module
* BUG FIX: PMPro Member import to MailChimp is in the Plus module
* BUG FIX: Make WooCommmerce::get_category_ids() a public interface
* BUG FIX: Didn't save the MailChimp error messages when subscribe operation failed
* ENHANCEMENT: Use global to let us track MailChimp server error messages
* ENHANCEMENT: Track reason why MailChimp subscribe() operation failed
* ENHANCEMENT: Renamed $enabled to $enable for the interest(s) to enable

== 2.9.2 ==

* BUG FIX: WPCS update for MC_Settings() class
* BUG FIX: Updated Utilities module (didn't correctly identify the PMPro Subscription Delays add-on)
* BUG FIX: Didn't detect Debug version of Plus module when loaded

== 2.9.1 ==

* BUG FIX: Fatal error in Utilities module

== 2.9 ==

* ENHANCEMENT: Add CSS for cache reset button, edit on mailchimp button and field headers
* ENHANCEMENT: Add 'clear local cache' button handler - MC_Settings::clear_cache()
* ENHANCEMENT: Updated Merge_Fields class for WPCS
* ENHANCEMENT: Add support for locally caching all list info from MailChimp.com
* ENHANCEMENT: Shorten time to complete Mailchimp_API::connect()
* ENHANCEMENT: Use cached data if available in Mailchimp_API::load_lists()
* ENHANCEMENT: Add support for upstream list info in Cache
* ENHANCEMENT: Apply WPCS to Mailchimp_API() class
* ENHANCEMENT: WPCS in Interest_Groups() class
* ENHANCEMENT: Add support for Clear local cache button
* BUG FIX: AJAX timeout was too short for certain operations
* BUG FIX: Didn't load PMPro specific GDPR opt-in if WooCommerce was the selected plugin, but had PMPro installed as well.
* BUG FIX: Didn't always display GDPR opt-in on the PMPro checkout page
* BUG FIX: PHP Warning in Interest_Groups() class
* BUG FIX: Make sure the GDPR opt-in shows up on the PMPro checkout page and PMPro Signon Shortcode form
* BUG FIX: Save Merge Fields to local cache
* BUG FIX: Consistently use Utilities::add_message() for warnings/errors/notices
* BUG FIX: Don't force mailchimp.com lookup of mail lists, etc if a cached version exists already

== 2.8 ==

* BUG FIX: Wouldn't show the GDPR opt-in for this plugin on PMPro forms if WooCommerce was the preferred integration

== 2.7 ==

* BUG FIX: Would delete user from MailChimp list when updating user(s) profile (Mailchimp_API::delete() vs Controlller::unsubscribe()
* BUG FIX: Specified wrong table when looking for recent products bought by the customer in the WooCommerce store
* BUG FIX: Wouldn't always update the remote user record on mailchimp.com
* BUG FIX: Escape the regex Interest Category name (just in case)
* ENHANCEMENT: Refactor Mailchimp_API::get_cache() method
* ENHANCEMENT: Add Mailchimp_API::generate_cache_key() method
* ENHANCEMENT: Add PHPDoc blocks for more methods in Mailchimp_API class
* ENHANCEMENT: Added 'Mailchimp_API::delete()' method and deprecated Mailchimp_API::unsubscribe()
* ENHANCEMENT: Refactored PMPro::membership_level_ids_for_user() method
* ENHANCEMENT: Added PMPro::get_level_history_for_user() method
* ENHANCEMENT: Add member module specific method for level/category history
* ENHANCEMENT: Use 'Mailchimp_API::delete()' method when removing users from lists
* ENHANCEMENT: Removed stale (unused) code from Member_Handler() class
* ENHANCEMENT: Improved documentation for Member_Handler::get_levels() method
* ENHANCEMENT: Refactored WooCommerce::plugin_load() method
* ENHANCEMENT: Refactored WooCommerce::verify_custom_fields() method
* ENHANCEMENT: Refactored WooCommerce::get_most_recent_product_cats() method
* ENHANCEMENT: Refactored WooCommerce::init_default_groups() method
* ENHANCEMENT: Refactored WooCommerce::get_interest_cat_label() method
* ENHANCEMENT: Refactored WooCommerce::is_on_checkout_page() method
* ENHANCEMENT: Refactored WooCommerce::set_mf_values_for_member() method
* ENHANCEMENT: Refactored WooCommerce::list_members_for_update() method
* ENHANCEMENT: Refactored WooCommerce::get_level_definition() method
* ENHANCEMENT: Refactored WooCommerce::has_membership_plugin() method
* ENHANCEMENT: Refactored WooCommerce::membership_level_ids_for_user() method
* ENHANCEMENT: Added WooCommerce::get_level_history_for_user() method
* ENHANCEMENT: Reordered methods in class.woocommerce.php
* ENHANCEMENT: Add the GDPR opt-in to the PMPro Signup Shortcode form
* ENHANCEMENT: Move GDPR opt-in field to the 'pmpro_checkout_after_user_fields' action for Paid Memberships Pro

== 2.4 ==

* ENHANCEMENT: Filter added for Interests (by name): e20r-mailchimp-member-interest-names
* ENHANCEMENT: Renamed e20r_mailchimp_list_interest_category_type to e20r-mailchimp-list-interest-category-type
* ENHANCEMENT: Removed stale (unused) code

== 2.3 ==

* BUG FIX: Extra (GDPR) opt-in required the 'alternate list opt-in' to be configured (should not be required)
* ENHANCEMENT: Global add_custom_views() handler for any supported membership/commerce plugin
* ENHANCEMENT: Add custom view handler for PMPro (at bottom of checkout page)
* ENHANCEMENT: Add custom view handler for WooCommerce (on checkout page)

== 2.2 ==

* ENHANCEMENT: Removed extra and unused code for licensed features
* ENHANCEMENT: Added .pot file for translations
* ENHANCEMENT: Add support for loading and saving settings from a supported external module
* ENHANCEMENT: Loading GDPR settings and forms from Plus module
* ENHANCEMENT: Added formatting for GDPR opt-in form
* ENHANCEMENT: Add custom registration checks (for GDPR assistance)
* ENHANCEMENT: Generic registration checks (PMPro) handler documentation
* ENHANCEMENT: Generic registration checks (WooCommerce) handler
* ENHANCEMENT: More GDPR updates
* BUG FIX: Required argument for consent check filter not included

== 2.0 ==

* ENHANCEMENT: Added WooCommerce compatibility metadata
* ENHANCEMENT: Add latent support for GDPR features in WordPress core
* ENHANCEMENT: Refactored view_additional_lists() method
* ENHANCEMENT: Refactored clear_levels_cache() method
* ENHANCEMENT: Refactored session_vars() method
* ENHANCEMENT: Moved session_vars() method to PMPro specific class
* ENHANCEMENT: Moved clear_levels_cache() method to PMPro specific class
* ENHANCEMENT: Moved view_additional_lists() method to parent Membership_Plugin class
* ENHANCEMENT: Refactored view_additional_lists() to Membership_Plugin class
* ENHANCEMENT: Added GDPR related consent opt-in checkbox to Member_Handler_View::addl_list_choice() view
* ENHANCEMENT: Warn admin if user didn't consent to Data Policy
* ENHANCEMENT: Updated filter string for e20r-mailchimp-assign-interest-to-user and documented it
* ENHANCEMENT: Added documentation for e20r-mailchimp-interests-to-assign-to-user filter
* ENHANCEMENT: Refactored the Interest_Groups class
* ENHANCEMENT: Updated filter documentation for e20r-mailchimp-user-defined-merge-tag-fields
* ENHANCEMENT: Updated copyright notice
* ENHANCEMENT: Removed stale/old code
* ENHANCEMENT: Refactored code
* ENHANCEMENT: Expand autoloader
* BUG FIX: Didn't handle licensed vs non-licensed features correctly

== 1.4.1 ==

* BUG FIX: Syntax error in Utilities module

== 1.4 ==

* BUG FIX: Update Utilities module to get rid of empty warning messages (should figure out why they're getting added too!)

== 1.3 ==

* BUG FIX: Incorrect path name to the WP Plugins directory
* BUG FIX: Would attempt to process Interest Categories when none were present.
* BUG FIX: PHP Warning while loading the plugin
* ENHANCEMENT: Updated Utilities module for plugin

== 1.2 ==

* ENHANCEMENT: Update Utilities submodule
* ENHANCEMENT: Updated the basic framework for Export_MC_Users class
* ENHANCEMENT: Only load new license config and and Settings page in wp-admin
* ENHANCEMENT: Added WooCommerce handler for e20r-mailchimp-checkout-pages
* ENHANCEMENT: Added PMPro handler for e20r-mailchimp-checkout-pages
* ENHANCEMENT: Only need to load settings stuff if the user is logged in
* ENHANCEMENT: Only load Member Handler functionality on certain pages, in the back-end, or when the user is logged in
* ENHANCEMENT: Renamed e20r-mailchimp-checkout-pages filter to e20r-mailchimp-load-on-pages (more descriptive)
* ENHANCEMENT: Only load user profile functionality if we're in the back-end, or on the TML front-end profile page(s) and logged in
* ENHANCEMENT: Load the licensing stuff during init action
* ENHANCEMENT: Change the priority for the Member_Hander::load_plugin() method
* ENHANCEMENT: Add on_login_page() method for Controller() class (Includes TML support)
* BUG FIX: Too agressive when speeding up the plugin load

== 1.1 ==

* ENHANCEMENT: Set the license stub to e20r_mc
* ENHANCEMENT: Adding stub for Export_MC_User class
* ENHANCEMENT: Adding stub functions/partial classes for Signup form shorcode
* ENHANCEMENT: Update Utilities submodule to v2.0

=== v1.0 ===
* Initial release
