# php-icap-client

PHP ICAP client.

Allows you to send requests to Internet Content Adaptation Protocol (ICAP) servers from PHP.

This is a work in progress. Functionality is currently very limited.

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
$icap->reqmod('example', 'Hello World!');
$icap->respmod('example', 'Hello World!');
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

