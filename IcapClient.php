<?php
    class IcapClient {
        /** @var string $host Address of ICAP server */
        private $host;
        /** @var int $port Port number */
        private $port;
        /** @var socket $socket Socket object */
        private $socket;

        /** @var string $userAgent User agent string */
        public $userAgent = 'PHP-ICAP-CLIENT/0.0.1';

        /**
         * Constructor
         *
         * @param string $host IP address of ICAP server
         * @param int $port Port number
         */
        public function __construct($host, $port) {
            $this->host = $host;
            $this->port = $port;
        }

        /**
         * Connect to ICAP server
         *
         * @return boolean True if successful
         */
        private function connect() {
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

            if (!socket_connect($this->socket, $this->host, $this->port)) {
                return false;
            }

            return true;
        }

        /**
         * Close connection to ICAP server
         */
        private function disconnect() {
            socket_shutdown($this->socket);
            socket_close($this->socket);
        }

        /**
         * Get last error code from socket object
         *
         * @return int Socket error code
         */
        public function getLastSocketError() {
            return socket_last_error($this->socket);
        }

        /**
         * Generate request string
         *
         * @param string $method ICAP method
         * @param string $service ICAP service
         * @param string|boolean $body ICAP request body or false
         * @param array $headers Array of headers
         * @return string Request string
         */
        public function getRequest($method, $service, $body = false, $headers = []) {
            if (!array_key_exists('Host', $headers)) {
                $headers['Host'] = $this->host;
            }

            if (!array_key_exists('User-Agent', $headers)) {
                $headers['User-Agent'] = $this->userAgent;
            }

            if (!array_key_exists('Connection', $headers)) {
                $headers['Connection'] = 'close';
            }

            $request = "{$method} icap://{$this->host}/{$service} ICAP/1.0\r\n";
            foreach ($headers as $header => $value) {
                $request .= "{$header}: {$value}\r\n";
            }

            $request .= "\r\n";

            if (false !== $body) {
                $request .= dechex(strlen($body))."\r\n";
                $request .= $body;
                $request .= "\r\n0\r\n\r\n";
            }

            return $request;
        }

        /**
         * Send OPTIONS request
         *
         * @param string $service ICAP service
         * @return array Response array
         * @throws RuntimeException
         */
        public function options($service) {
            $request = $this->getRequest('OPTIONS', $service);
            $response = $this->send($request);

            return $this->parseResponse($response);
        }

        /**
         * Send RESPMOD request
         *
         * @param string $service ICAP service
         * @param string|boolean $body Body content or false
         * @return array Response array
         * @throws RuntimeException
         */
        public function respmod($service, $body = false) {
            $headers = [];
            if (false !== $body) {
                $headers['Encapsulated'] = 'res-body=0';
            }

            $request = $this->getRequest('RESPMOD', $service, $body, $headers);
            $response = $this->send($request);

            return $this->parseResponse($response);
        }

        /**
         * Send REQMOD request
         *
         * @param string $service ICAP service
         * @param string|boolean $body Body content or false
         * @return array Response array
         * @throws RuntimeException
         */
        public function reqmod($service, $body = false) {
            $headers = [];
            if (false !== $body) {
                $headers['Encapsulated'] = 'req-body=0';
            }

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
        public function send($request) {
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
        private function parseResponse($response) {
            $responseArray = [
                'protocol' => [],
                'headers' => []
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
                $responseArray['body'] = $body[1];
            }

            return $responseArray;
        }
    }

