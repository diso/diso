package Friends::Friend;

use strict;
our $VERSION = '0.5';
use base qw( MT::Object MT::Scorable MT::Taggable );

use constant DEBUG => 1;

__PACKAGE__->install_properties(
    {
        datasource => 'diso_friend',

        column_defs => {
            'id'            => 'integer not null auto_increment',
            'author_id'     => 'integer not null',
            'name'          => 'string(255) not null',
            'rel'           => 'string(255)',
            'image'         => 'text',
            'notes'         => 'text',
            'visible'       => 'integer',
            'is_subscribed' => 'integer',
        },
        class_type  => 'friend',
        primary_key => 'id',
        audit       => 1,
    }
);

sub uris {
	my $self = shift;
    require Friends::URI;
	my @uris = Friends::URI->load( { friend_id => $self->id });
    return \@uris;
}

sub class_label { MT->translate('Friend'); }
sub class_label_plural { MT->translate('Friends'); }

1;