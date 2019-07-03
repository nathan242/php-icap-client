# php-icap-client

PHP ICAP client.

Allows you to send requests to Internet Content Adaptation Protocol (ICAP) servers from PHP.

### Usage

Instantiate the class with the ICAP server address and port:

```php
$icap = new IcapClient('127.0.0.1', 13440);
```

Send an OPTIONS request to the "example" service:

```php
$icap->options('example');
```

Send a REQMOD and RESPMOD request to the "example" service:

```php
$icap->reqmod(
    'example',
    [
        'req-hdr' => "POST /test HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n",
        'req-body' => 'This is another test.'
    ]
);

$icap->respmod(
    'example',
    [
        'res-hdr' => "HTTP/1.1 200 OK\r\nServer: Test/0.0.1\r\nContent-Type: text/html\r\n\r\n",
        'res-body' => 'This is a test.'
    ]
);
```

Successful requests will return an array describing the response:

```
Array
(
    [protocol] => Array
        (
            [icap] => ICAP/1.0
            [code] => 200
            [message] => OK
        )

    [headers] => Array
        (
            [Date] => Sun, 09 Jun 2019 15:43:08 GMT
            [ISTag] => N26W1Q5ZURSA16S3HKKZWE0L2O68PEVG
            [Server] => ICAP/1.3 Python/2.7.13
        )

    [body] => c
Hello World!
0


)
```

