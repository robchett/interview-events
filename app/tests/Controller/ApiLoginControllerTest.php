<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiLoginControllerTest extends WebTestCase
{
    public function testRegister(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/register');

        $response = $client->getResponse()->getContent();

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');
        self::assertJson($response);

        $body = json_decode($response, true);
        self::assertIsArray($body);
        self::assertArrayHasKey('username', $body);
        self::assertIsString('username', $body['username']);
        self::assertArrayHasKey('password', $body);
        self::assertIsString('username', $body['password']);
    }
}
