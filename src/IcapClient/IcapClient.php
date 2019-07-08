<?php
    namespace IcapClient;

    class IcapClient
    {
        /** @var string $host Address of ICAP server */
        private $host;
        /** @var int $port Port number */
        private $port;
        /** @var socket $socket Socket object */
        private $socket;

        /** @var string $userAgent User agent string */
        public $userAgent = 'PHP-ICAP-CLIENT/0.5.0';

        /**
         * Constructor
         *
         * @param string $host IP address of ICAP server
         * @param int $port Port number
         */
        public function __construct($host, $port)
        {
            $this->host = $host;
            $this->port = $port;
        }

        /**
         * Connect to ICAP server
         *
         * @return boolean True if successful
         */
        private function connect()
        {
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

            if (!socket_connect($this->socket, $this->host, $this->port)) {
                return false;
            }

            return true;
        }

        /**
         * Close connection to ICAP server
         */
        private function disconnect()
        {
            socket_shutdown($this->socket);
            socket_close($this->socket);
        }

        /**
         * Get last error code from socket object
         *
         * @return int Socket error code
         */
        public function getLastSocketError()
        {
            return socket_last_error($this->socket);
        }

        /**
         * Generate request string
         *
         * @param string $method ICAP method
         * @param string $service ICAP service
         * @param array $body Request body data
         * @param array $headers Array of headers
         * @return string Request string
         */
        public function getRequest($method, $service, $body = [], $headers = [])
        {
            if (!array_key_exists('Host', $headers)) {
                $headers['Host'] = $this->host;
            }

            if (!array_key_exists('User-Agent', $headers)) {
                $headers['User-Agent'] = $this->userAgent;
            }

            if (!array_key_exists('Connection', $headers)) {
                $headers['Connection'] = 'close';
            }

            $bodyData = '';
            $hasBody = false;
            $encapsulated = [];
            foreach ($body as $type => $data) {
                switch ($type) {
                    case 'req-hdr':
                    case 'res-hdr':
                        $encapsulated[$type] = strlen($bodyData);
                        $bodyData .= $data;
                        break;

                    case 'req-body':
                    case 'res-body':
                        $encapsulated[$type] = strlen($bodyData);
                        $bodyData .= dechex(strlen($data))."\r\n";
                        $bodyData .= $data;
                        $bodyData .= "\r\n";
                        $hasBody = true;
                        break;
                }
            }

            if ($hasBody) {
                $bodyData .= "0\r\n\r\n";
            } elseif (count($encapsulated) > 0) {
                $encapsulated['null-body'] = strlen($bodyData);
            }

            if (count($encapsulated) > 0) {
                $headers['Encapsulated'] = '';
                foreach ($encapsulated as $section => $offset) {
                    $headers['Encapsulated'] .= $headers['Encapsulated'] === '' ? '' : ', ';
                    $headers['Encapsulated'] .= "{$section}={$offset}";
                }
            }

            $request = "{$method} icap://{$this->host}/{$service} ICAP/1.0\r\n";
            foreach ($headers as $header => $value) {
                $request .= "{$header}: {$value}\r\n";
            }

            $request .= "\r\n";
            $request .= $bodyData;

            return $request;
        }

        /**
         * Send OPTIONS request
         *
         * @param string $service ICAP service
         * @return array Response array
         * @throws RuntimeException
         */
        public function options($service)
        {
            $request = $this->getRequest('OPTIONS', $service);
            $response = $this->send($request);

            return $this->parseResponse($response);
        }

        /**
         * Send RESPMOD request
         *
         * @param string $service ICAP service
         * @param array $body Request body data
         * @return array Response array
         * @throws RuntimeException
         */
        public function respmod($service, $body = [], $headers = [])
        {
            $request = $this->getRequest('RESPMOD', $service, $body, $headers);
            $response = $this->send($request);

            return $this->parseResponse($response);
        }

        /**
         * Send REQMOD request
         *
         * @param string $service ICAP service
         * @param array $body Request body data
         * @return array Response array
         * @throws RuntimeException
         */
        public function reqmod($service, $body = [], $headers = [])
        {
            $request = $this->getRequest('REQMOD', $service, $body, $headers);
            $response = $this->send($request);

            return $this->parseResponse($response);
        }

        /**
         * Send request
         *
         * @param string $request Request string
         * @return string Response string
         * @throws RuntimeException
         */
        public function send($request)
        {
            if (!$this->connect()) {
                throw new RuntimeException("Cannot connect to icap://{$this->host}:{$this->port} (Socket error: ".$this->getLastSocketError().")");
            }

            socket_write($this->socket, $request);

            $response = '';
            while ($buffer = socket_read($this->socket, 2048)) {
                $response .= $buffer;
            }

            $this->disconnect();
            return $response;
        }

        /**
         * Parse response string
         *
         * @param string $response Response string
         * @return array Response array
         * @throws RuntimeException
         */
        private function parseResponse($response)
        {
            $responseArray = [
                'protocol' => [],
                'headers' => [],
                'body' => [],
                'rawBody' => ''
            ];

            foreach (preg_split('/\r?\n/', $response) as $line) {
                if ([] === $responseArray['protocol']) {
                    if (0 !== strpos($line, 'ICAP/')) {
                        throw new RuntimeException('Unknown ICAP response');
                    }

                    $parts = preg_split('/\ +/', $line, 3);

                    $responseArray['protocol'] = [
                        'icap' => isset($parts[0]) ? $parts[0] : '',
                        'code' => isset($parts[1]) ? $parts[1] : '',
                        'message' => isset($parts[2]) ? $parts[2] : '',
                    ];

                    continue;
                }

                if ('' === $line) {
                    break;
                }

                $parts = preg_split('/:\ /', $line, 2);
                if (isset($parts[0])) {
                    $responseArray['headers'][$parts[0]] = isset($parts[1]) ? $parts[1] : '';
                }
            }

            $body = preg_split('/\r?\n\r?\n/', $response, 2);
            if (isset($body[1])) {
                $responseArray['rawBody'] = $body[1];

                if (array_key_exists('Encapsulated', $responseArray['headers'])) {
                    $encapsulated = [];
                    $params = preg_split('/, /', $responseArray['headers']['Encapsulated']);

                    if (count($params) > 0) {
                        foreach ($params as $param) {
                            $parts = preg_split('/=/', $param);
                            if (count($parts) !== 2) {
                                continue;
                            }

                            $encapsulated[$parts[0]] = $parts[1];
                        }
                    }

                    foreach ($encapsulated as $section => $offset) {
                        $data = substr($body[1], $offset);
                        switch ($section) {
                            case 'req-hdr':
                            case 'res-hdr':
                                $responseArray['body'][$section] = preg_split('/\r?\n\r?\n/', $data, 2)[0];
                                break;

                            case 'req-body':
                            case 'res-body':
                                $parts = preg_split('/\r?\n/', $data, 2);
                                if (count($parts) === 2) {
                                    $responseArray['body'][$section] = substr($parts[1], 0, hexdec($parts[0]));
                                }
                                break;
                        }
                    }
                }
            }

            return $responseArray;
        }
    }

