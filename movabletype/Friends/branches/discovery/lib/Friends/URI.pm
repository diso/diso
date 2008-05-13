package Friends::URI;

use strict;
our $VERSION = '0.5';

use base qw( MT::Object );

use constant DEBUG => 1;

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
    $uri->cache_property(
        'friend',
        sub {
            return undef unless $uri->friend_id;
            my $req          = MT::Request->instance();
            my $friend_cache = $req->stash('friend_cache');
            my $friend       = $friend_cache->{ $uri->friend_id };
            unless ($friend) {
                require Friends::Friend;
                $friend = Friends::Friend->load( $uri->friend_id );
                $friend_cache->{ $uri->friend_id } = $friend;
                $req->stash( 'friend_cache', $friend_cache );
            }
            $friend;
        }
    );
}

sub class_label { MT->translate('URL'); }
sub class_label_plural { MT->translate('URLs'); }
1;
