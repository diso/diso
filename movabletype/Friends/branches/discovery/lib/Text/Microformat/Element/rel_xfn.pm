package Text::Microformat::Element::rel_xfn;
use warnings;
use strict;
use base 'Text::Microformat::Element';

__PACKAGE__->_init({
    criteria => {
        rel => qr/contact|acquaintance|friend|met|co\-worker|colleague|co-resident|neighbor|child|parent|sibling|spouse|kin|muse|crush|date|sweetheart|me/,
    },
});

sub MachineValue {
	my $self = shift;
	my $tag = defined $self->_element->local_name ? $self->_element->local_name : "";
	if ($tag eq 'a') {
		return $self->_element->attr('href');
	}
	else {
	    return undef;
	}
}

sub HumanValue {
	my $self = shift;
	my $tag = defined $self->_element->local_name ? $self->_element->local_name : "";
	if ($tag eq 'a') {
		return $self->_element->content;
	}
	else {
	    return undef;
	}
}

=head1 NAME

Text::Microformat::Element::rel_xfn - a rel-xfn element

=head1 SYNOPSIS

    To add rel-xfn to a Text::Microformat schema:

    package Text::Microformat::Element::hMyFormat
    __PACKAGE__->init(
        'my-format',
        schema => {
            tags => 'rel-xfn',
        }
    );
    
    To then retrieve tags from a Text::Microformat::Element::hMyFormat instance:
    
    foreach my $rel (@{$format->xfn}) {
        print $rel->MachineValue, "\n"; # print the href
        print $rel->HumanValue, "\n"; # print the tag word
    }

=head1 SEE ALSO

L<Text::Microformat>, L<http://microformats.org/wiki/xfn>

=head1 AUTHOR
Steve Ivy, C<< <steveivy at gmail.com> >>

=head1 COPYRIGHT & LICENSE

Copyright 2008 Steve Ivy, all rights reserved.

This program is free software; you can redistribute it and/or modify it
under the same terms as Perl itself.

=cut

1;