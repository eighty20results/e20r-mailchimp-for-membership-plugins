=== E20R MailChimp Integration for Revenue Tools ===
Contributors: eighty20results
Tags: mailchimp, paid memberships pro, pmpro, membership plugin, email marketing, woocommerce, distribution list support, merge tags, interests, mailchimp groups
Requires at least: 4.5
Requires PHP: 5.4
Tested up to: 4.9.3
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WordPress based Users, Members or customers to MailChimp list with support for Groups (Interests) and Merge Tags.

== Description ==

Specify the subscripiton list(s) for your site's WordPress Users and Member plugin users. If a supported membership plugin is installed, you can specify additional list settings by either the membership level or the product category purchased.

The plugin has a setting to require/not require MailChimp's double opt-in, as well as a setting to change interests and merge tags for members on level change. This allows you to automatically resegment your users based on membership level or custom merge tag data. This plugin does _not_ unsubscribe users from MailChimp lists _unless_ it's been explicitly configured to do so.

We do not recommend using multiple lists for different membership levels. Instead, this plugin uses a single list for all members with segmnentation managed through Groups/ Interests, and merge tag values.

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

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/eighty20results/e20r-mailchimp-for-membership-plugins/issues

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at https://eighty20results.com/ for more documentation and our support forums.

== Screenshots ==

1. General settings for all members/subscribers list, opt-in rules, and unsubscribe rules.
2. Membership-level specific Groups/Interests and Merge Tag settings.

== Changelog ==

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