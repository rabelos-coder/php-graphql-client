<?php

namespace RabelosCoder\GraphQL;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

/**
 * Class Client
 *
 * @package GraphQL
 */
class Client
{
    /**
     * @var string
     */
    protected $endpointUrl;

    /**
     * @var GuzzleClient
     */
    protected GuzzleClient $httpClient;

    /**
     * @var array
     */
    protected $httpHeaders;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $files;

    /**
     * @var string
     */
    protected $fieldsMap;

    /**
     * @var string
     */
    protected $fieldIdentifier;

    /**
     * @var string
     */
    protected $boundary;

    /**
     * @var string|array
     */
    protected $body;

    /**
     * Client constructor.
     *
     * @param string $endpointUrl
     * @param array  $options
     * @param array  $headers
     */
    public function __construct(
        string $endpointUrl,
        array $options = [],
        array $headers = [],
    ) {
        $httpHeaders = array_merge(
            $headers,
            [
                'Apollo-Require-Preflight' => 'true',
            ]
        );
        unset($options['headers']);

        $this->files            = [];
        $this->fieldIdentifier  = '';
        $this->options          = $options;
        $this->endpointUrl      = $endpointUrl;
        $this->httpClient       = new GuzzleClient($options);
        $this->httpHeaders      = $httpHeaders;
    }

    public function identifier(string $field): self
    {
        if (!is_string($field)) {
            throw new Exception('File field identifier must me a string.');
        }
        $this->fieldIdentifier = "variables." . $field;
        return $this;
    }

    public function attachments(array $files = []): self
    {
        if (empty($this->fieldIdentifier) || !is_string($this->fieldIdentifier)) {
            throw new Exception('File field identifier not set.');
        }
        $map = "";
        if (is_array($files) && count($files)) {
            foreach($files as $index => $file) {
                if (!isset($file['fileName']) || (isset($file['fileName']) &&
                (empty($file['fileName']) || !is_string($file['fileName'])))) {
                    throw new Exception('File name must be a string and it required.');
                }
                if (!isset($file['mimeType']) || (isset($file['mimeType']) &&
                (empty($file['mimeType']) || !is_string($file['mimeType'])))) {
                    throw new Exception('Mime type must be a string and it required.');
                }
                if (!isset($file['filePath']) || (isset($file['filePath']) &&
                (empty($file['filePath']) || !is_string($file['filePath'])))) {
                    throw new Exception('File path must be a string and it required.');
                }
                if (!file_exists($file['filePath'])) {
                    throw new Exception('File not found.');
                }
                $map .= '"' . $index . '": ["' . $this->fieldIdentifier . '.' . $index . '"], ';
            }
            $this->fieldsMap = '{' . substr($map, 0, strlen($map) - 2) . '}';
            $this->files = $files;
        } else {
            $this->files = [];
        }
        return $this;
    }

    public function query(string $query, array $variables = [])
    {
        if ((is_array($variables) && count($variables)) && count($this->files)) {
            $fields = [
                'operations' => json_encode(['query' => $query, 'variables' => $variables]),
                'map' => $this->fieldsMap,
            ];
            $this->boundary = uniqid();
            $builder = new ContentBuilder($this->boundary, $fields, $this->files);
            $this->body = $builder->build();
        } else {
            $this->body = [
                'json' => [
                    'query' => $query,
                    'variables' => $variables ?? [],
                ],
            ];
        }
        return $this;
    }

    public function send()
    {
        if (is_string($this->body)) {
            $delimiter = '-------------' . $this->boundary;
            $this->httpHeaders = array_merge($this->httpHeaders, [
                "Content-Type" => 'multipart/form-data; boundary=' . $delimiter,
            ]);
            $request = new Request('POST', $this->endpointUrl, $this->httpHeaders, $this->body);
            try {
                $response = $this->httpClient->send($request);
                $responseData = json_decode($response->getBody()->getContents(), true);
                return $responseData;
            } catch (RequestException $e) {
                throw new Exception($e->getMessage(), $e->getCode(), $e);
            }
        } elseif (is_array($this->body)) {
            try {
                $this->httpHeaders = array_merge($this->httpHeaders, [
                    'Content-Type' => 'application/json',
                ]);
                $response = $this->httpClient->post($this->endpointUrl, array_merge($this->body, $this->httpHeaders, $this->options));
                $responseData = json_decode($response->getBody()->getContents(), true);
                return $responseData;
            } catch (RequestException $e) {
                throw new Exception($e->getMessage(), $e->getCode(), $e);
            }
        }
        throw new Exception('Imcompatible body while trying to send request.');
    }
}
