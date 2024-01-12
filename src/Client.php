<?php

namespace RabelosCoder\GraphQL;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
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
     * @var Request
     */
    protected Request $request;

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
        try {
            $map = "";
            $file = json_decode(json_encode($file), true);
            if (is_array($file)) {
                $this->validadeFileArguments($file, $this->fileIdentField);
                $map .= '"' . preg_replace('/\W+/m', '_', $this->fileIdentField) . '":["' . $this->fileIdentField . '"],';
                $this->fieldsMap .= $map;
                $this->file = $file;
            } else {
                $this->file = null;
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return $this;
    }

    public function attachments(array $files = []): self
    {
        try {
            $map = "";
            $files = json_decode(json_encode($files), true);
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
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return $this;
    }

    public function query(string $query, $variables = [])
    {
        try {
            $variables = json_decode(json_encode($variables));
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
                $this->httpHeaders = array_merge($this->httpHeaders, [
                    'Accept' => 'application/json',
                    "Content-Type" => 'multipart/form-data; boundary=' . $this->builder->getDelimiter(),
                ]);
                $this->setRequest($this->body);
            } else {
                $this->body = [
                    'query' => $query,
                    'variables' => $variables ?? [],
                ];
                $this->httpHeaders = array_merge($this->httpHeaders, [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);
                $this->setRequest(json_encode($this->body));
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return $this;
    }

    public function getBody()
    {
        return $this->body;
    }

    private function setRequest($body)
    {
        $this->request = new Request('POST', $this->endpointUrl, $this->httpHeaders, $body);
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function send()
    {
        if (is_string($this->body)) {
            try {
                $response = $this->httpClient->send($this->request);
                $responseData = json_decode($response->getBody()->getContents());
                return $responseData;
            } catch (RequestException $e) {
                throw new RequestException($e->getMessage(), $this->request, null, $e);
            }
        } elseif (is_array($this->body)) {
            try {
                $response = $this->httpClient->send($this->request);
                $responseData = json_decode($response->getBody()->getContents());
                return $responseData;
            } catch (RequestException $e) {
                throw new RequestException($e->getMessage(), $this->request, null, $e);
            }
        }
        throw new Exception('Imcompatible body while trying to send request.');
    }
}
