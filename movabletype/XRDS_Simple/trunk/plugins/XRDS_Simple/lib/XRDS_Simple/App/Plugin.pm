package XRDS_Simple::App::Plugin;

use strict;
use Data::Dumper qw(Dumper);

sub init_request {
    my $app = shift;
    $app->SUPER::init_request(@_);
    $app->{requires_login} = 0;
    $app->{is_admin} = 0;
    $app;
}

sub init_app { 
    my $plugin = shift;
    my ($app) = @_;
# I am not sure I need to do this
#    MT->add_callback('MT::App::CMS::init_request', 1, $app, sub { 
#	$app->set_header( 'X-XRDS-Location' => $app->base . $app->mt_uri('mode' => 'xrds_simple') ); 
#    });
    MT->add_callback('MT::App::Comments::init_request', 1, $app, sub { 
	$app->set_header( 'X-XRDS-Location' => $app->base . $app->mt_uri('mode' => 'xrds_simple') ); 
    });
    1;
}

sub xrds_simple {
    my $app = shift;
    $app->{requires_login} = 0;
    #my $logger = MT::Log->get_logger();
    
    # load registry xrds_services
    my $xrds_services = $app->registry('xrds_services') || {};
    
    my @services;
    
    foreach my $service_name (keys %{$xrds_services}) {
	my $service_def = $xrds_services->{$service_name};
	# TODO: support coderefs for each parameter (expires, uri, mediatype, etc.)
	if ($service_def->{local_id_handler}) {
	    my $local_id_handler = $app->handler_to_coderef($service_def->{local_id_handler})
		or return $app->error('local_id_handler call failed: ' . $!);
	    $service_def->{local_id} = $local_id_handler->();
	}
	if ($service_def->{registered_services}) {
	    my @rservices;
	    foreach my $rservice (keys %{$service_def->{registered_services}}) {
		push @rservices, $service_def->{registered_services}->{$rservice};
	    }
	    $service_def->{registered_services} = \@rservices;
	}
#	MT->log(Dumper($service_def));
	push @services, $service_def;
    }
    
    my $plugin = $app->component('xrds_simple');
    
    # now serve the file
    # $logger->debug($app->request_method);
    if ( 'GET' eq $app->request_method ) {
        $app->response_content_type('application/xrds+xml');
#        $app->response_content_type('text/plain');
        if ( 'xrds_simple' eq $app->mode ) {
            my $param = {
                services => \@services,
            };
            return $plugin->load_tmpl( 'xrds_simple.tmpl', $param );
        }
    }
    elsif ( 'HEAD' eq $app->request_method ) {
	#$app->set_header( 'X-XRDS-Location' => $app->base . $app->mt_uri('mode' => $app->mode) );
	$app->response_content_type('application/xrds+xml');
	return q();
    }
    return $app->error($plugin->translate('Invalid request'));
}

sub openid_local_id {
    "steve";
}

1;
