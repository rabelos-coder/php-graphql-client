<?php

namespace RabelosCoder\GraphQL\Test;

use GuzzleHttp\Cookie\CookieJar;
use PHPUnit\Framework\TestCase;
use RabelosCoder\GraphQL\Client;

class ClientBuilderTest extends TestCase
{
    public function testBuild()
    {
        $client = new Client('http://foo.bar/qux');
        $this->assertInstanceOf(Client::class, $client);
    }

    public function testBuildWithGuzzleOptions()
    {
        $guzzleOptions = [
            'cookies' => new CookieJar(),
        ];

        $client = new Client('http://foo.bar/qux', $guzzleOptions);
        $this->assertInstanceOf(Client::class, $client);
    }
}
