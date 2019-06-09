<?php
    require 'IcapClient.php';

    $icap = new IcapClient('127.0.0.1', 13440);

    print_r($icap->options('example'));
    echo "\n\n\n";

    print_r($icap->respmod('example', 'Hello World!'));
    echo "\n\n\n";

    print_r($icap->reqmod('example', 'Hello World!'));

