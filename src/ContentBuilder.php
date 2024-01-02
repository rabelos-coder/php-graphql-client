<?php

namespace RabelosCoder\GraphQL;

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
    protected string $boundary;

    /**
     * @var array
     */
    protected array $fields;

    /**
     * @var array
     */
    protected array $files;

    /**
     * @var string
     */
    protected string $delimiter = '-------------';

    public function __construct(
        string $boundary,
        array $fields = [],
        array $files = []
    ) {
        $this->boundary = $boundary;
        $this->fields = $fields;
        $this->files = $files;
    }

    public function build(): string
    {
        $data = '';
        $eol = "\r\n";
        $this->delimiter = '-------------' . $this->boundary;
        if (is_array($this->fields) && count($this->fields)) {
            foreach ($this->fields as $name => $content) {
                $data .= "--" . $this->delimiter . $eol
                    . 'Content-Disposition: form-data; name="' . $name . "\"" . $eol . $eol
                    . $content . $eol;
            }
        }
        if (is_array($this->files) && count($this->files)) {
            foreach ($this->files as $index => $file) {
                $name = $file['fileName'];
                $mimeType = $file['mimeType'];
                $contents = file_get_contents($file['filePath']);
                $data .= "--" . $this->delimiter . $eol
                    . 'Content-Disposition: form-data; name="' . $index . '"; filename="' . $name . '"' . $eol
                    . 'Content-Type: ' . $mimeType . $eol
                    . 'Content-Transfer-Encoding: binary' . $eol;
                $data .= $eol;
                $data .= $contents . $eol;
            }
        }
        $data .= "--" . $this->delimiter . "--" . $eol;
        return $data;
    }
}
