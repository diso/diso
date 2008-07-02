=head1 NAME

GnipSubscriber - Performs Gnip subscriber related functions.

=head1 DESCRIPTION

This class provides convenience methods for accessing the Gnip servers and
performing subscriber related functions.

=head2 FUNCTIONS

The following functions are exported by default

=begin html

<HR>

=end html

=cut
package GnipSubscriber;
use strict;
use GnipHelper;

#constructor
sub new 
{
    my ($class, $username, $password) = @_;
    my $self = {
        _helper => new GnipHelper($username, $password),
    };
    bless $self, $class;
    return $self;
}

=head3 C<create_collection($collection_xml)>

Creates a new collection on the Gnip server, based on passed in collection xml

=head4 Parameters

=over 4 

=item * C<$collection_xml> (string) - The xml for collection

=back

Returns a string representing the server response.

=begin html

<HR>

=end html

=cut
sub create_collection
{
   my ($self, $collection_xml) = @_;

   my $url = $self->{_helper}->GNIP_BASE_URL . '/collections.xml';

   return $self->{_helper}->doHttpPost($url, $collection_xml);
}

=head3 C<delete_collection($name)>

Deletes an existing collection on the Gnip server, based on the
name of the collection.

=head4 Parameters

=over 4 

=item * C<$name> (string) - The name of the collection to delete

=back

Returns a string representing the server response.

=begin html

<HR>

=end html

=cut
sub delete_collection
{
   my ($self, $name) = @_;

   my $url = $self->{_helper}->GNIP_BASE_URL . "/collections/" . $name 
      . ".xml";

   return $self->{_helper}->doHttpDelete($url);
}


=head3 C<find_collection($name)>

Finds an existing collection on the Gnip server, based on the
name of the collection.

=head4 Parameters

=over 4

=item * C<$name> (string) - The name of the collection to find

=back

Returns a string representing the server response.

=begin html

<HR>

=end html

=cut
sub find_collection
{
   my ($self, $name) = @_;

   my $url = $self->{_helper}->GNIP_BASE_URL . "/collections/" . $name
      . ".xml";

   return $url . "\n";

   return $self->{_helper}->doHttpGet($url);
}


=head3 C<get($publisher, $date_and_time)>

Gets all of the data for a specific publisher, based on the
date_and_time parameter. If date_and_time is not passed in, 
the current time will be used. Note that all times need to be in UTC.

=head4 Parameters

=over 4 

=item * C<$publisher> (string) - The publisher of the data 
   collection

=item * C<$date_and_time> (long) - The time for which data should be retrieved

=back

Returns a string representing the server response.

=begin html

<HR>

=end html

=cut
sub get
{
   my ($self, $publisher, $date_and_time) = @_;

   if (undef  == $date_and_time) 
   {
      $date_and_time = time();
   }

   my $correctedTime = $self->{_helper}->syncWithGnipClock($date_and_time);
   my $roundedTime = $self->{_helper}->
      roundTimeToNearestFiveMinutes($correctedTime);
   my $timeString = $self->{_helper}->timeToString($roundedTime);

   my $url = $self->{_helper}->GNIP_BASE_URL . "/publishers/" . $publisher .
            "/activity/" . $timeString . ".xml";

   return $self->{_helper}->doHttpGet($url);
}

=head3 C<get_collection($name, $publisher, $date_and_time)>

Gets all of the data for a specific collection, based on the
date_and_time parameter. If date_and_time is not passed in, 
the current time will be used. Note that all times need to be in UTC.

=head4 Parameters

=over 4 

=item * C<$name> (string) - The name of the collection to get


=item * C<$date_and_time> (long) - The time for which data should be retrieved

=back

Returns a string representing the server response.

=begin html

<HR>

=end html

=cut
sub get_collection
{
   my ($self, $name, $date_and_time) = @_;

   if (undef  == $date_and_time) 
   {
      $date_and_time = time();
   }

   my $correctedTime = $self->{_helper}->syncWithGnipClock($date_and_time);
   my $roundedTime = $self->{_helper}->
      roundTimeToNearestFiveMinutes($correctedTime);
   my $timeString = $self->{_helper}->timeToString($roundedTime);

   my $url = $self->{_helper}->GNIP_BASE_URL . "/collections/" . $name .
            "/activity/" . $timeString . ".xml";

   return $self->{_helper}->doHttpGet($url);
}

=head3 C<update_collection($name, $publisher, $user_ids)>

Updates an existing collection on the Gnip server, based on
        passed collection name and collection xml.

=head4 Parameters

=over 4 

=item * C<$name> (string) - The name of the collection to update

=item * C<$collection_xml> (string) - The collection xml document

=back

Returns a string representing the server response.

=begin html

<HR>

=end html

=cut
sub update_collection
{
   my ($self, $name, $collection_xml) = @_;

   my $url = $self->{_helper}->GNIP_BASE_URL . "/collections/" 
      . $name . ".xml";

   return $self->{_helper}->doHttpPut($url, $collection_xml);
}

1;

