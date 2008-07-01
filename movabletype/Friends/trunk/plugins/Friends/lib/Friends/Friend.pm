package Friends::Friend;

use strict;
use Data::Dumper qw(Dumper);
use base qw( MT::Object MT::Scorable MT::Taggable );

use constant DEBUG => 1;

__PACKAGE__->install_properties(
    {
        datasource => 'diso_friend',

        column_defs => {
            'id'        => 'integer not null auto_increment',
            'author_id' => 'integer not null',
            'name'      => 'string(255) not null',
            'rel'       => 'string(255)',
            'image'     => 'text',
            'notes'     => 'text',
            'visible'   => 'integer',
            'pending'   => 'integer',
        },
		defaults => {
			pending => 0,
		},
        class_type  => 'friend',
        primary_key => 'id',
        audit       => 1,
    }
);

sub links {
    my $self = shift;
    my $params = shift || {};
    my $link_class = MT->model('link');
    my @links = $link_class->load( { friend_id => $self->id, %{$params} } );
    MT->log(Dumper(\@links));
    return \@links;
}

sub class_label        { MT->translate('Friend'); }
sub class_label_plural { MT->translate('Friends'); }

1;
