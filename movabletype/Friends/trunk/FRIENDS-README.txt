# Friends (Blogroll) Plugin for Movable Type

By Steve Ivy <steveivy@gmail.com> 

This is a basic blogroll plugin for Movable Type 4.1+. I realize that there are several other existing blogroll plugins: at the time of this writing none were released under a Free software license that allowed derivative works, and I wanted something to learn on, so this was it.

Since I'm interested in building code that follows the DiSo model, this plugin is the basis for future work in MT.

## What does it do?

Allows you to build and maintain a list of Friend links, initially for the purpose of displaying a blogroll on your site. Adding, editing, listing, deleting Friends works, as well as bulk show/hide/delete. There are several template tags available as well.

## Installation

As usual. Nothing extra to go anywhere but drop in the plugin, and enable it. To start using it, navigate to your profile, there you will find a "Friends" link in the navigation. Have at it!

## Sample Widget

This widget outputs a basic microformatted blogroll, marked up with hCard and XFN

<div class="widget-blogroll widget">
    <h3 class="widget-header">People I Know</h3>
    <div class="widget-content">
        <ul class="blogroll xoxo">
        <mt:friends>
            <li class="vcard"><$mt:friendname$>
                <ul>
                    <mt:friendlinks>
                        <li><a class="fn url" title="<$mt:friendlinknotes$>" rel="<$MTFriendRel$>" href="<$mt:friendlinkuri$>"><$mt:friendlinklabel$></a></li>
                    </mt:friendlinks>
                </ul>
            </li>
        </mt:friends>
        <ul>
    </div>
</div>

## Next Steps

* Finish SGAPI import
* Start in on something to "insert link into entry"
* hCard retrieval and subscription
    * store a "source" with the contact
    * store an "update timestamp" with the contact
    * create_date

--Steve
http://redmonk.net // http://diso-project.org