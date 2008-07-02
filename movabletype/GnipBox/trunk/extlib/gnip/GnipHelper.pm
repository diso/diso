=head1 NAME

GnipHelper - Common functionality between all Gnip classes

=head1 DESCRIPTION

This module provides basic functionality help for all Gnip classes.

=head2 FUNCTIONS

The following functions are exported by default

=begin html

<HR>

=end html

=cut

package GnipHelper;
use strict;
use LWP::UserAgent;
use DateTime;
use DateTime::Format::Strptime;

use constant GNIP_BASE_ADDRESS => 'prod.gnipcentral.com';
use constant GNIP_BASE_URL => 'https://prod.gnipcentral.com';

=head3 C<new($username, $password)>

Initializes a GnipHelper object

=head4 Parameters

=over 4 

=item * C<$username> (string) - The Gnip account username

=item * C<$password> (string) - The Gnip account password

=back

=begin html

<HR>

=end html

=cut
sub new 
{
    my ($class, $username, $password) = @_;
    my $self = {
        _username => $username,
        _password  => $password
    };
    bless $self, $class;
    return $self;
}

=head3 C<doHttpGet($url)>

Does a HTTP GET request of the passed in url, and returns 
        the result from the server.

=head4 Parameters

=over 4

=item * C<$url> (string) - The URL to GET

=back

Returns a string representing the page retrieved.

=begin html

<HR>

=end html

=cut
sub doHttpGet
{
   my ($self, $url) = @_;

   my $agent = LWP::UserAgent->new;
   my $request = HTTP::Request->new(GET => $url);
   $request->authorization_basic($self->{_username}, $self->{_password});
   my $response = $agent->request($request);

   # Check the outcome of the response
   if ($response->is_success) {
      return $response->content;
   }
   else {
      return $response->status_line;
   }
}

=head3 C<doHttpPost($url, $data)>

Does a HTTP POST request of the passed in url and data, and returns 
        the result from the server.

=head4 Parameters

=over 4

=item * C<$url> (string) - The URL to GET

=item * C<$data> (string) - POST data url encoded with content-type as application/xml

=back

Returns a string representing the page retrieved.

=begin html

<HR>

=end html

=cut
sub doHttpPost 
{
   my ($self, $url, $data) = @_;

   my $agent = LWP::UserAgent->new;
   my $request = HTTP::Request->new(POST => $url);
   $request->authorization_basic($self->{_username}, $self->{_password});
   $request->content_type('application/xml');
   $request->content($data);

   my $response = $agent->request($request);

   # Check the outcome of the response
   if ($response->is_success) {
      return $response->content;
   }
   else {
      return $response->status_line;
   }
}


=head3 C<doHttpPut($url, $data)>

Does a HTTP PUT request of the passed in url and data, and returns
        the result from the server.

=head4 Parameters

=over 4

=item * C<$url> (string) - The Put Url

=item * C<$data> (string) - POST data url encoded with content-type as application/xml

=back

Returns a string representing the page retrieved.

=begin html

<HR>

=end html

=cut
sub doHttpPut
{
   my ($self, $url, $data) = @_;
   return doHttpPost($self, $url.';edit', $data)
}

=head3 C<doHttpDelete($url)>

Does a HTTP Delete request of the passed in url and returns
        the result from the server.

=head4 Parameters

=over 4

=item * C<$url> (string) - The Delete Url

=back

Returns a string representing response

=begin html

<HR>

=end html

=cut
sub doHttpDelete
{
   my ($self, $url) = @_;
   return doHttpPost($self, $url.';delete', ' ')
}

=head3 C<roundTimeToNearestFiveMinutes($theTime)>

Rounds the time passed in down to the previous 5 minute mark.

=head4 Parameters

=over 4

=item * C<$theTime> (long) - The time to round

=back

Returns a long containing the rounded time

=begin html

<HR>

=end html

=cut
sub roundTimeToNearestFiveMinutes 
{
   my ($self, $theTime) = @_;

   my $dateTime = DateTime->from_epoch(epoch => $theTime);

   my $min = $dateTime->minute();
   my $newMin = $min - ($min % 5);

   $dateTime->set(minute => $newMin);
   $dateTime->set(second => 0);

   return $dateTime->epoch();
}

=head3 C<syncWithGnipClock($theTime)>

This method gets the current time from the Gnip server,
gets the current local time and determines the difference 
between the two. It then adjusts the passed in time to 
account for the difference.

=head4 Parameters

=over 4

=item * C<$theTime> (long) - The time to adjust

=back

Returns a long containing the adjusted time

=begin html

<HR>

=end html

=cut
sub syncWithGnipClock 
{
   my ($self, $theTime) = @_;

   # Do HTTP HEAD request
   my $agent = LWP::UserAgent->new;
   my $request = HTTP::Request->new(HEAD => GNIP_BASE_URL);
   my $response = $agent->request($request);

   my $localTime = time();

   my $formatter = DateTime::Format::Strptime->new( 
      pattern => '%a, %d %b %Y %H:%M:%S %Z' );

   my $gnipTime = 
      ($formatter->parse_datetime($response->header('Date')))->epoch();

   my $timeDelta = $gnipTime - $localTime;

   return $theTime + $timeDelta;
}

=head3 C<timeToString($theTime)>

Converts the time passed in to a string of the form YYYYMMDDHHMM.

=head4 Parameters

=over 4

=item * C<$theTime> (long) - The time to convert

=back

Returns a string containing the converted time

=begin html

<HR>

=end html

=cut
sub timeToString
{
   my ($self, $theTime) = @_;

   my $formatter = DateTime::Format::Strptime->new( 
      pattern => '%Y%m%d%H%M' );

   return DateTime->from_epoch( epoch => $theTime, formatter => $formatter );
}

1;
