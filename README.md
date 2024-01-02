# PHP GraphQL Client

[![Latest Version](https://img.shields.io/github/release/rabelos-coder/php-graphql-client.svg?style=flat-square)](https://github.com/rabelos-coder/php-graphql-client/releases)
[![Software License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/rabelos-coder/php-graphql-client.svg?style=flat-square)](https://packagist.org/packages/rabelos-coder/php-graphql-client)

PHP Client for [GraphQL](http://graphql.org/)

## Main features

- Client with file attachment support
- Easy query/mutation execution
- Simple array results for mutation and queries
- Powerful object results for mutation and queries

## Installation

Via composer:

```
composer require rabelos-coder/php-graphql-client
```

## Documentation

### Instantiate a client

You can instantiate a simple client.

Simple Client:

```php
<?php
$client = new \RabelosCoder\GraphQL('https://your-domain/graphql');
```

### Using the GraphQL Client

You can use the client to execute queries and mutations and get the results.

```php
<?php

/**
 * Query Example
 */
$query = <<<'GQL'
query GetFooBar($idFoo: String, $idBar: String) {
  foo(id: $idFoo) {
    id_foo
    bar (id: $idBar) {
      id_bar
    }
  }
}
GQL;

$variables = [
    'idFoo' => 'foo',
    'idBar' => 'bar',
];

/** @var \RabelosCoder\GraphQL\Client $client */
$consumer = $client->query($query, $variables);

try {
    // returns response array
    $response = $consumer->send();

    return $response;
} catch (\Exception $e) {
    // Returns exception message
}

/**
 * Mutation Example
 */
$mutation = <<<'GQL'
mutation ($foo: ObjectInput!){
  CreateObjectMutation (object: $foo) {
    status
  }
}
GQL;

$variables = [
    'foo' => [
        'id_foo' => 'foo',
        'bar' => [
            'id_bar' => 'bar'
        ]
    ]
];

/** @var \RabelosCoder\GraphQL\Client $client */
$consumer = $client->query($mutation, $variables);

try {
    // returns response array
    $response = $consumer->send();

    return $response;
} catch (\Exception $e) {
    // Returns exception message
}

/**
 * Mutation With Single File Upload Example
 */
$mutation = <<<'GQL'
mutation ($file: Upload!){
  CreateObjectMutation (object: $file) {
    fileName
    filePath
  }
}
GQL;

$files = $_FILES[0];
$uploaded =  [
    'fileName' => $file['name'],
    'mimeType' => $file['type'],
    'filePath' => $file['tmp_name'],
];


$variables = [
    'file' => null,
];

/** @var \RabelosCoder\GraphQL\Client $client */
$consumer = $client->identifier('file')
            ->attachment($uploaded)
            ->query($mutation, $variables);

try {
    // returns response array
    $response = $consumer->send();

    return $response;
} catch (\Exception $e) {
    // Returns exception message
}

/**
 * Mutation With Multiple File Upload Example
 */
$mutation = <<<'GQL'
mutation ($files: [Upload!]!){
  CreateObjectMutation (object: $files) {
    fileName
    filePath
  }
}
GQL;

$files = $_FILES;
$uploaded = [];

foreach ($files as $file) {
    $uploaded[] = [
        'fileName' => $file['name'],
        'mimeType' => $file['type'],
        'filePath' => $file['tmp_name'],
    ];
}

$variables = [
    'files' => array_map(fn() => null, array_keys($uploaded)),
];

/** @var \RabelosCoder\GraphQL\Client $client */
$consumer = $client->identifier('files')
            ->attachments($uploaded)
            ->query($mutation, $variables);

try {
    // returns response array
    $response = $consumer->send();

    return $response;
} catch (\Exception $e) {
    // Returns exception message
}

```

In the previous examples, the client is used to execute queries and mutations. The response object is used to
get the results in array format.

## License

The MIT license. Please see [LICENSE](LICENSE) for more information.

[PSR-2]: http://www.php-fig.org/psr/psr-2/
[PSR-4]: http://www.php-fig.org/psr/psr-4/
