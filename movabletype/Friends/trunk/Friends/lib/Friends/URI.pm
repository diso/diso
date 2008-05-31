package Friends::URI;

use strict;

use base qw( MT::Object );

__PACKAGE__->install_properties(
    {
        datasource => 'diso_friend_uri',

        column_defs => {
            'id'            => 'integer not null auto_increment',
            'friend_id'     => 'integer not null',
            'author_id'	    => 'integer not null',
            'uri'           => 'text not null',
            'source_uri'    => 'text',
            'description'   => 'text',
            'target'        => 'string(255)',
            'feed_uri'      => 'text',
            'notes'         => 'text',
            'rating'        => 'integer',
            'is_subscribed' => 'integer',
            'last_updated'  => 'timestamp',
        },
        class_type  => 'uri',
        primary_key => 'id',
        audit       => 1,
    }
);

sub friend {
    my $uri = shift;
    my $friend_class = MT->model('friend');
    $friend = Friends::Friend->load( $uri->friend_id );
	return $friend;
}

sub class_label { MT->translate('URL'); }
sub class_label_plural { MT->translate('URLs'); }
1;
