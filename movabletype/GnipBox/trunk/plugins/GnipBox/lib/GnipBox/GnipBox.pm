package GnipBox::GnipBox;

use strict;
use base qw( MT::App );

use Data::Dumper qw(Dumper);

sub widget {
    my $app = shift;
    my ( $tmpl, $param ) = @_;
    
    push @{ $param->{activity_loop} ||= [] },
        {
            at => '2008-06-08T10:12:07Z', 
            uid => 'factoryjoe',
            type => 'tweet',
            guid => 'http://twitter.com/factoryjoe/statuses/848163720'
        },
        {
            at => '2008-06-08T10:12:07Z', 
            uid => 'factoryjoe',
            type => 'tweet',
            guid => 'http://twitter.com/factoryjoe/statuses/848163720'
        },
        {
            at => '2008-06-08T10:12:07Z', 
            uid => 'factoryjoe',
            type => 'tweet',
            guid => 'http://twitter.com/factoryjoe/statuses/848163720'
        };
}