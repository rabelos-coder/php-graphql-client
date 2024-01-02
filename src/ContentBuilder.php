<?php

namespace RabelosCoder\GraphQL;

use Exception;

/**
 * Class ContentBuilder
 *
 * @package GraphQL
 */
class ContentBuilder
{
    /**
     * @var string
     */
    protected string $eol;

    /**
     * @var string
     */
    protected string $boundary;

    /**
     * @var string
     */
    protected string $delimiter;

    /**
     * @var string
     */
    protected string $files;

    /**
     * @var string
     */
    protected string $body;

    public function __construct()
    {
        $this->eol          = "\r\n";
        $this->files        = '';
        $this->body         = '';
        $this->boundary     = uniqid();
        $this->delimiter    = '-------WebKitFormBoundary' . $this->boundary;
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    public function addFiles(array $files, string $prefix = null): self
    {
        if (is_array($files) && count($files)) {
            foreach ($files as $index => $file) {
                $name = $file['fileName'];
                $mimeType = $file['mimeType'];
                $contents = file_get_contents($file['filePath']);
                $this->files .= "--" . $this->delimiter . $this->eol
                . 'Content-Disposition: form-data; name="' . ($prefix ? $prefix : $index) . '"; filename="' . $name . '"' . $this->eol
                . 'Content-Type: ' . $mimeType . $this->eol
                . 'Content-Transfer-Encoding: binary' . $this->eol;
                $this->files .= $this->eol;
                $this->files .= $contents . $this->eol;
            }
        }
        return $this;
    }

    public function addFields(array $fields): self
    {
        if (is_array($fields) && count($fields)) {
            foreach ($fields as $name => $content) {
                $this->body .= "--" . $this->delimiter . $this->eol
                    . 'Content-Disposition: form-data; name="' . $name . "\"" . $this->eol . $this->eol
                    . $content . $this->eol;
            }
        }
        return $this;
    }

    public function build(): string
    {
        if (!$this->body) {
            throw new Exception('Fields not set.');
        }
        // dd($this->body);
        $this->body .= $this->files;
        $this->body .= "--" . $this->delimiter . "--";
        return $this->body;
    }
}
