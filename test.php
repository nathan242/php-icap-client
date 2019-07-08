<?php
    require_once __DIR__.'/vendor/autoload.php';

    use IcapClient\IcapClient;

    $icap = new IcapClient('127.0.0.1', 13440);

    print_r($icap->options('example'));
    echo "\n\n\n";

    print_r(
        $icap->respmod(
            'example',
            [
                'res-hdr' => "HTTP/1.1 200 OK\r\nServer: Test/0.0.1\r\nContent-Type: text/html\r\n\r\n",
                'res-body' => 'This is a test.'
            ]
        )
    );
    echo "\n\n\n";

    print_r(
        $icap->reqmod(
            'example',
            [
                'req-hdr' => "POST /test HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n",
                'req-body' => 'This is another test.'
            ]
        )
    );

