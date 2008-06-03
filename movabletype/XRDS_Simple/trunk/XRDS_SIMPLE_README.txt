# XRDS Simple for Movable Type

XRDS Simple for Movable Type implements [XRDS Simple](http://xrds-simple.net/core/1.0/) service discovery. New web services like [OpenID](http://openid.net) and [OAuth](http://oauth.net) are encouraging the use of XRDS (eXtensible Resource Descriptor Sequence) (and specifically the new, simplified version) for service discovery.

XRDS Simple for Movable Type is a faceless application that enables plugins to register services to be advertised in the discovery document.

## Registering A Plugin's Services

Sample configuration YAML:

    xrds_services:
        openid2:
            type: http://specs.openid.net/auth/2.0/server
            # media_type:
            uri: http://endpoint.example.net
            namespace_id: openid
            namespace_uri: http://openid.net/xmlns/1.0
            priority: 10
            # local_id: http://redmonk.net
            local_id_handler: sub { "http://example.com" }

### xrds_services

A plugin registers its services with XRDS Simple by including an <code>xrds_services</code> section in <code>config.yaml</code>. In that section are any number of service definitions, each listed under a unique identifier.

Within the service definition are a number of items which generally follow the XRDS Simple spec[1]. Of note are the <code>namespace\_id</code>, <code>namespace\_uri</code>, and <code>local\_id\_handler</code>.

Each service can register a namespace to be included in the xml declaration of the discovery document. The <code>namespace\_id</code> and <code>namespace\_uri</code> parameters are used to construct an <code>xsmlns:</code> attribute, like:

    xmlns:openid="http://openid.net/xmlns/1.0"

XRDS also supports the concept of a local id - an identifier for this site or user on the service the discovery document points to. The <code>local\_id</code> parameter will be added to the generated document in the service definition. Sometimes the <code>local\_id</code> will need to be set programatically. In that case, pass in a coderef as <code>local\_id\_handler</code>.

    local_id_handler: sub { "http://example.com" }

