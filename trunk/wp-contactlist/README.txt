#WP Microformatted Blogroll

##Intro

The WP Microformatted Blogroll plugin outputs a list of links on your blog marked up with [XOXO](http://microformats.org/wiki/xoxo), [hCard](http://microformats.org/wiki/hCard) and [XFN](http://gmpg.org/xfn/). If the "Lookup users with OpenID" option is set, the plugin will check to see if any of your blogroll links match registered blog users (link URL matches the user's url). If any of those users registered via [OpenID](http://openid.net), the user's name in the blogroll will be linked to their OpenID URI.

To install:

1. Upload this file into your wp-content/plugins directory.
2. Activate the WP Microformatted Blogroll plugin in your WordPress admin panel.

You must have the [WP-OpenID plugin](http://willnorris.com/projects/wp-openid) installed and active for OpenID integration to work.

For the user matching to work, the code assumes:

* Link Name -> person’s name (First-space-Last)
* Link Description -> blog name
* Link URI -> blog link, must match the "url" entered in the user's account. First the whole URI (minus http:// and any trailing /) is checked, if there's no match, it will remove any trailing path and try matching against just the domain.

## In-Page Blogroll

The WP Microformatted Blogroll plugin can generate the blogroll in a static page:

1. Create a new static page.
2. Add <!--xfnpage--> to the page content where you want the blogroll to appear.

## Microformatted Blogroll Widget

Add the "Microformatted Blogroll" widget to your sidebar ("Presentation > Widgets" - works in Wordpress 2.3, or 2.0+ with [Automattic's Widgets plugin](http://automattic.com/code/widgets/)).

## Blogroll Template Tag

Use the <code>xfn_blogroll()</code> template tag in your template.

<code>&lt;?php xfn\_blogroll(); ?>

