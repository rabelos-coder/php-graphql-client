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
     * @var ContentBuilder
     */
    protected ContentBuilder $builder;

    /**
     * @var array
     */
    protected $httpHeaders;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array|null
     */
    protected $file;

    /**
     * @var array|null
     */
    protected $files;

    /**
     * @var int
     */
    protected $mapIndex = 0;

    /**
     * @var string
     */
    protected $fieldsMap;

    /**
     * @var string
     */
    protected $fileIdentField;

    /**
     * @var string
     */
    protected $fileIdentFields;

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

        $this->file             = null;
        $this->files            = null;
        $this->fileIdentField   = '';
        $this->fileIdentFields  = '';
        $this->options          = $options;
        $this->endpointUrl      = $endpointUrl;
        $this->httpClient       = new GuzzleClient($options);
        $this->httpHeaders      = $httpHeaders;
        $this->builder          = new ContentBuilder();
    }

    public function fileField(string $field): self
    {
        if (!is_string($field)) {
            throw new Exception('File field identifier must me a string.');
        }
        $this->fileIdentField = "variables." . $field;
        return $this;
    }

    public function filesField(string $field): self
    {
        if (!is_string($field)) {
            throw new Exception('File field identifier must me a string.');
        }
        $this->fileIdentFields = "variables." . $field;
        return $this;
    }

    private function validadeFileArguments($file, $field = null)
    {
        if (empty($field) || !is_string($field)) {
            throw new Exception('File field identifier not set.');
        }
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
    }

    public function attachment($file): self
    {
        $map = "";
        if (is_array($file)) {
            $this->validadeFileArguments($file, $this->fileIdentField);
            $map .= '"' . preg_replace('/\W+/m', '_', $this->fileIdentField) . '":["' . $this->fileIdentField . '"],';
            $this->fieldsMap .= $map;
            $this->file = $file;
        } else {
            $this->file = null;
        }
        return $this;
    }

    public function attachments(array $files = []): self
    {
        $map = "";
        if (is_array($files) && count($files)) {
            foreach($files as $file) {
                $this->validadeFileArguments($file, $this->fileIdentFields);
                $map .= '"' . $this->mapIndex . '":["' . $this->fileIdentFields . '.' . $this->mapIndex . '"],';
                $this->mapIndex++;
            }
            $this->fieldsMap .= $map;
            $this->files = $files;
        } else {
            $this->files = null;
        }
        return $this;
    }

    public function query(string $query, array $variables = [])
    {
        if (is_array($this->file) || is_array($this->files)) {
            if (is_array($this->file)) {
                $this->builder->addFiles([$this->file], preg_replace('/\W+/m', '_', $this->fileIdentField));
            }
            if (is_array($this->files)) {
                $this->builder->addFiles($this->files);
            }
            $fields = [
                'operations' => json_encode(['query' => $query, 'variables' => $variables]),
                'map' => '{' . substr($this->fieldsMap, 0, strlen($this->fieldsMap) - 1) . '}',
            ];
            $this->builder->addFields($fields);
            $this->body = $this->builder->build();
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

    public function getBody()
    {
        return $this->body;
    }

    public function send()
    {
        if (is_string($this->body)) {
            $this->httpHeaders = array_merge($this->httpHeaders, [
                "Content-Type" => 'multipart/form-data; boundary=' . $this->builder->getDelimiter(),
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
