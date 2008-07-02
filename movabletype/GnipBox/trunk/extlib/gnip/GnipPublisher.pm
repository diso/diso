=head1 NAME

GnipPublisher - Performs Gnip publisher related functions.

=head1 DESCRIPTION

This class provides convenience methods for accessing the Gnip servers and
    performing publisher related functions.

=head2 FUNCTIONS

The following functions are exported by default

=begin html

<HR>

=end html

=cut
package GnipPublisher;
use strict;
use GnipHelper;

=head3 C<new($username, $password, $publisher)>

Initializes a GnipPublisher object

=head4 Parameters

=over 4 

=item * C<$username> (string) - The Gnip account username

=item * C<$password> (string) - The Gnip account password

=item * C<$publisher> (string) - The name of the publisher

=back

=begin html

<HR>

=end html

=cut
sub new 
{
    my ($class, $username, $password, $publisher) = @_;
    my $self = {
        _helper => new GnipHelper($username, $password),
        _publisher  => $publisher
    };
    bless $self, $class;
    return $self;
}

=head3 C<publish($activity)>

This method takes in a XML document with a list of activities and 
sends it to the Gnip server.

=head4 Parameters

=over 4 

=item * C<activity> (string) - XML document formatted to Gnip schema

=back

Returns a string representing the server response.

=begin html

<HR>

=end html

=cut
sub publish
{
   my ($self, $activity) = @_;

   my $url = $self->{_helper}->GNIP_BASE_URL . "/publishers/" 
      . $self->{_publisher} . "/activity.xml";

   return $self->{_helper}->doHttpPost($url, $activity);

}

1;
