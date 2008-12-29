=== Extended Profile ===
Contributors: singpolyma, steveivy, wnorris
Tags: profile, microformats, hcard
equires at least: 2.6
Tested up to: 2.7
Stable tag: 0.6

Extend the WordPress profile to include additional attributes, and output as hCard.


== Description ==

This plugin extends the standard WordPress profile to inclue additional
attributes such as photo, organization, address, phone number, and others.  A
user's entire profile can then be output as an [hCard][] on any page using the
`profile` shortcode, or with the provided widget.

[hCard]: http://microformats.org/wiki/hcard


== Installation ==

This plugin follows the [standard WordPress installation method][]:

1. Upload the `openid` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Configure the extended profile attributes on the normal WordPress profile page

[standard WordPress installation method]: http://codex.wordpress.org/Managing_Plugins#Installing_Plugins


== Frequently Asked Questions ==

= How to I include my profile on a page? =

Use the `profile` shortcode with the username or ID of the user whose profile you'd like to include.

	[profile]admin[/profile]

You may include a profile directly from your PHP templates by calling:

	extended_profile('admin');

