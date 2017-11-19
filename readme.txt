=== E20R MailChimp Integration for Membership Plugins ===
Contributors: eighty20results
Tags: mailchimp, paid memberships pro, pmpro, membership plugin, email marketing, distribution list support, merge tags, interests, mailchimp groups
Requires at least: 4.5
Requires PHP: 5.4
Tested up to: 4.8.1
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WordPress Users and Members with MailChimp lists. Including support for Groups and Merge Tags.

== Description ==

Specify the subscripiton list(s) for your site's WordPress Users and Member plugin users. If a supported membership plugin is installed, you can specify additional list settings by membership level.

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
NOTE: This option is only available once you've selected the WooCommerce compatibility module and saved the settings.

The setting is used to select the email address to use with the MailChimp list(s). It's entirely possible for a user to be logged in to your system with one email address, but order in the WooCommerce shop on behalf of different billing address/user. This setting lets you specify whether to use the email address of the logged in user, or that of the billing address. The default is the Billing address email.

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
=== v1.0 ===
* Initial release