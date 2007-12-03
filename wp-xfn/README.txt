## Intro

Simply outputs a page of links on your blog marked up with XOXO, hCard and XFN. If the "Lookup users with OpenID" option is set, the plugin will check to see if any of your blogroll links match registered blog users (link URL matches the user's url). If any of those users registered via [OpenID](http://openid.net)  (using [WP-OpenID](http://willnorris.com/projects/wp-openid)), the user's name in the blogroll will be linked to their OpenID URI.

To install:

	1. Upload this file into your wp-content/plugins directory.
	2. Activate the WP Microformatted Blogroll plugin in your WordPress admin panel.

## Page
	1. Create a new static page.
	2. &lt;!--xfnpage-->

## Widget

Add "Microformatted Blogroll" to your sidebar.

## Template Tag

<code>&lt;?php xfn\_blogroll(); ?>

