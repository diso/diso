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

sub xrds_simple {
	my $app = shift;
	$app->{requires_login} = 0;
	my $logger = MT::Log->get_logger();
	
	# load registry xrds_services
	my $xrds_services = $app->registry('xrds_services') || {};
	
	my @services;
	
	foreach my $service_name (keys %{$xrds_services}) {
		my $service_def = $xrds_services->{$service_name};
		if ($service_def->{local_id_handler}) {
			#$logger->debug(Dumper($service_def->{local_id_handler}));
			my $local_id_handler = $app->handler_to_coderef($service_def->{local_id_handler})
			        or return $app->error('local_id_handler call failed: ' . $!);
			    
			$service_def->{local_id} = $local_id_handler->();
		}
		$logger->debug(Dumper($service_def));
		push @services, $service_def;
	}
	
	#$logger->debug(Dumper(\@services));
	
    my $plugin = $app->component('xrds_simple');
    
	# now serve the file
	$logger->debug($app->request_method);
    if ( 'GET' eq $app->request_method ) {
        $app->response_content_type('application/xrds+xml');
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