# Friends Plugin for Movable Type
# Author: Steve Ivy
# Copyright 2008 Six Apart, Ltd.
# License: Artistic, licensed under the same terms as Perl itself

package MT::Plugin::Friends;

use strict;
use base qw( MT::Plugin );
our $VERSION = '0.3';
use constant DEBUG => 1;

my $plugin = MT::Plugin::Friends->new({
	id          => 'Friends',
	key         => 'friends',
	name        => 'Friends (DiSo Project)',
	description => "From the DiSo project, provides a blogroll-like means of managing \
			   		and displaying a friends list and defining the (XFN-based) relationships \
		   			to their sites.",
	version     => $VERSION,
	schema_version => 0.5,
	author_name => "Steve Ivy",
	author_link => "htt:p//diso-project.org",
	plugin_link => "http://diso-project.org/nolinkyet",
});

MT->add_plugin($plugin);
