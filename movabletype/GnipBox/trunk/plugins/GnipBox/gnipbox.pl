
package MT::Plugin::GnipBox;

use strict;
use base qw( MT::Plugin );
our $VERSION = '0.1'; 
my $plugin = MT::Plugin::GnipBox->new({
   id          => 'GnipBox',
   key         => 'gnipbox',
   name        => 'GnipBox',
   description => "Your Inbox, powered by Gnip",
   version     => $VERSION,
   author_name => "Steve Ivy",
   author_link => "http://redmonk.net",
   plugin_link => "http://redmonk.net",
});
MT->add_plugin($plugin);

sub init_registry {
    my $plugin = shift;
    $plugin->registry({
        widgets => {
            inbox => {
                label    => 'Inbox',
                plugin   => $plugin,
                template => 'inbox.mtml',
                set => 'main',
                singular => 1,
                handler => &GnipBox::GnipBox::widget
            },
        },
    });
}
