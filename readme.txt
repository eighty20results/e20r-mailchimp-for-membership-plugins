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

= Download, Install and Activate! =
1. Upload the `e20r-mailchimp-for-membership-plugins` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. The settings page is at Settings --> E20R MailChimp in the WordPress admin dashboard.

= Objects fetched per API call (number) =
By default, the MailChimp.com servers limit the number of objects it will let an API call fetch. The default limit is 15 objects. An object is a mailing list, a merge field, interests in an interest group, etc. This setting can be used to increase or decrease that number for this plugin.

= Membership Plugin to user =
Select the member plugin you have installed and activated on the site. This ensures the plugin loads the correct compatibility module.

= User selectable for checkout/profile (opt-in) =
Let a new or existing user select which additional list(s) they wish to also subscribe to.

= Require Double Opt-in? =
Select Yes or No to require/not require MailChimp's double opt-in.


= Unsubscribe on Level Change? =
By default, this setting will change the Interests for the user in the "Membership Level" group to "Cancelled" if/when a user has cancelled all of their active membership levels (if applicable). You may also set it to "Clear Interest Groups (old membership levels)" which will clear the Membership Levels Group interests for any previous membership level the user had been included.

= Add new members to =
The MailChimp list you wish to add any member to (segmented by Interests and/or Merge Tags).

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/eighty20results.com/e20r-mailchimp-for-membership-plugins/issues

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at https://eighty20results.com/ for more documentation and our support forums.

== Screenshots ==

1. General settings for all members/subscribers list, opt-in rules, and unsubscribe rules.
2. Membership-level specific Groups/Interests and Merge Tag settings.

== Changelog ==
=== v1.0 ===
* Initial release