<?php

use Bellows\Plugins\PlanetScale;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->setHttpCredentials([
        'service_token_id' => 'test-token-id',
        'service_token'    => 'test-token',
    ]);
});

it('can select from an existing database and branch', function () {
    Http::fake([
        'organizations?per_page=1' => [
            'data' => [
                [

                    'name' => 'test-org',
                ],
            ],
        ],
        'organizations/test-org/databases' => Http::response([
            'data' => [
                [
                    'name' => 'bellows-tester',
                ],
            ],
        ]),
        'organizations/test-org/databases/bellows-tester/branches' => Http::response([
            'data' => [
                [
                    'name' => 'main',
                ],
            ],
        ]),
        'organizations/test-org/databases/bellows-tester/branches/main/passwords' => Http::response([
            'connection_strings' => [
                'laravel' => <<<'ENV'
                DB_CONNECTION=mysql
                DB_HOST=aws.connect.psdb.cloud
                DB_PORT=3306
                DB_DATABASE=bellows-tester
                DB_USERNAME=test-username
                DB_PASSWORD=test-password
                MYSQL_ATTR_SSL_CA=/etc/ssl/cert.pem
                ENV
            ],
        ]),
    ]);

    $result = $this->plugin(PlanetScale::class)
        ->expectsQuestion('Database', 'bellows-tester')
        ->expectsQuestion('Branch', 'main')
        ->deploy();

    expect($result->getEnvironmentVariables())->toBe([
        'DB_CONNECTION'        => 'mysql',
        'DB_HOST'              => 'aws.connect.psdb.cloud',
        'DB_PORT'              => '3306',
        'DB_DATABASE'          => 'bellows-tester',
        'DB_USERNAME'          => 'test-username',
        'DB_PASSWORD'          => 'test-password',
        'MYSQL_ATTR_SSL_CA'    => '/etc/ssl/certs/ca-certificates.crt',
    ]);
});

it('can create a new database', function () {
    Http::fake([
        'organizations?per_page=1' => [
            'data' => [
                [

                    'name' => 'test-org',
                ],
            ],
        ],
        'organizations/test-org/databases' => Http::sequence([
            [
                'data' => [
                    [
                        'name' => 'bellows-tester',
                    ],
                ],
            ],
            [
                'name' => 'new-db',
            ],
        ]),
        'organizations/test-org/databases/new-db/branches' => Http::response([
            'data' => [
                [
                    'name' => 'main',
                ],
            ],
        ]),
        'organizations/test-org/databases/new-db/branches/main/passwords' => Http::response([
            'connection_strings' => [
                'laravel' => <<<'ENV'
                DB_CONNECTION=mysql
                DB_HOST=aws.connect.psdb.cloud
                DB_PORT=3306
                DB_DATABASE=new-db
                DB_USERNAME=test-username
                DB_PASSWORD=test-password
                MYSQL_ATTR_SSL_CA=/etc/ssl/cert.pem
                ENV
            ],
        ]),
    ]);

    $result = $this->plugin(PlanetScale::class)
        ->expectsQuestion('Database', 'Create new database')
        ->expectsQuestion('Database name', 'new-db')
        ->expectsConfirmation('Once you have added the scopes: Continue?', 'yes')
        ->expectsQuestion('Branch', 'main')
        ->deploy();

    expect($result->getEnvironmentVariables())->toBe([
        'DB_CONNECTION'        => 'mysql',
        'DB_HOST'              => 'aws.connect.psdb.cloud',
        'DB_PORT'              => '3306',
        'DB_DATABASE'          => 'new-db',
        'DB_USERNAME'          => 'test-username',
        'DB_PASSWORD'          => 'test-password',
        'MYSQL_ATTR_SSL_CA'    => '/etc/ssl/certs/ca-certificates.crt',
    ]);

    $this->assertRequestWasSent('POST', 'organizations/test-org/databases', [
        'name' => 'new-db',
    ]);
});

it('can create a new branch', function () {
    Http::fake([
        'organizations?per_page=1' => [
            'data' => [
                [

                    'name' => 'test-org',
                ],
            ],
        ],
        'organizations/test-org/databases' => Http::sequence([
            [
                'data' => [
                    [
                        'name' => 'bellows-tester',
                    ],
                ],
            ],
            [
                'name' => 'new-db',
            ],
        ]),
        'organizations/test-org/databases/bellows-tester/branches' => Http::sequence([
            [
                'data' => [
                    [
                        'name' => 'main',
                    ],
                ],
            ],
            [
                'name' => 'new-branch',
            ],
        ]),
        'organizations/test-org/databases/bellows-tester/branches/new-branch/passwords' => Http::response([
            'connection_strings' => [
                'laravel' => <<<'ENV'
                DB_CONNECTION=mysql
                DB_HOST=aws.connect.psdb.cloud
                DB_PORT=3306
                DB_DATABASE=bellows-tester
                DB_USERNAME=test-username
                DB_PASSWORD=test-password
                MYSQL_ATTR_SSL_CA=/etc/ssl/cert.pem
                ENV
            ],
        ]),
    ]);

    $result = $this->plugin(PlanetScale::class)
        ->expectsQuestion('Database', 'bellows-tester')
        ->expectsQuestion('Branch', 'Create new branch')
        ->expectsQuestion('Branch name', 'new-branch')
        ->expectsQuestion('Parent branch', 'main')
        ->deploy();

    expect($result->getEnvironmentVariables())->toBe([
        'DB_CONNECTION'        => 'mysql',
        'DB_HOST'              => 'aws.connect.psdb.cloud',
        'DB_PORT'              => '3306',
        'DB_DATABASE'          => 'bellows-tester',
        'DB_USERNAME'          => 'test-username',
        'DB_PASSWORD'          => 'test-password',
        'MYSQL_ATTR_SSL_CA'    => '/etc/ssl/certs/ca-certificates.crt',
    ]);

    $this->assertRequestWasSent(
        'POST',
        'organizations/test-org/databases/bellows-tester/branches',
        [
            'name'          => 'new-branch',
            'parent_branch' => 'main',
        ],
    );
});

it('will bark if it does not have the correct database scopes', function () {
    Http::fake([
        'organizations?per_page=1' => [
            'data' => [
                [

                    'name' => 'test-org',
                ],
            ],
        ],
        'organizations/test-org/databases' => Http::response([
            'code' => 'forbidden',
        ]),
    ]);

    $this->plugin(PlanetScale::class)
        ->expectsOutput("Bellows doesn't have permission to list databases.")
        ->deploy();
});

it('will bark if it does not have the correct create database scopes', function () {
    Http::fake([
        'organizations?per_page=1' => [
            'data' => [
                [

                    'name' => 'test-org',
                ],
            ],
        ],
        'organizations/test-org/databases' => Http::sequence([
            [
                'data' => [
                    [
                        'name' => 'bellows-tester',
                    ],
                ],
            ],
            [
                'code' => 'forbidden',
            ],
        ]),
    ]);

    $this->plugin(PlanetScale::class)
        ->expectsQuestion('Database', 'Create new database')
        ->expectsQuestion('Database name', 'new-db')
        ->expectsOutput("Bellows doesn't have permission to create a database.")
        ->deploy();
});

it('will bark if it does not have the correct list branches scopes', function () {
    Http::fake([
        'organizations?per_page=1' => [
            'data' => [
                [

                    'name' => 'test-org',
                ],
            ],
        ],
        'organizations/test-org/databases' => Http::response([
            'data' => [
                [
                    'name' => 'bellows-tester',
                ],
            ],
        ]),
        'organizations/test-org/databases/bellows-tester/branches' => Http::sequence([
            [
                'code' => 'forbidden',
            ],
        ]),
    ]);

    $this->plugin(PlanetScale::class)
        ->expectsQuestion('Database', 'bellows-tester')
        ->expectsOutput("Bellows doesn't have permission to list database branches.")
        ->deploy();
});

it('will bark if it does not have the correct create branch scopes', function () {
    Http::fake([
        'organizations?per_page=1' => [
            'data' => [
                [

                    'name' => 'test-org',
                ],
            ],
        ],
        'organizations/test-org/databases' => Http::response([
            'data' => [
                [
                    'name' => 'bellows-tester',
                ],
            ],
        ]),
        'organizations/test-org/databases/bellows-tester/branches' => Http::sequence([
            [
                'data' => [
                    [
                        'name' => 'main',
                    ],
                ],
            ],
            [
                'code' => 'forbidden',
            ],
        ]),
    ]);

    $this->plugin(PlanetScale::class)
        ->expectsQuestion('Database', 'bellows-tester')
        ->expectsQuestion('Branch', 'Create new branch')
        ->expectsQuestion('Branch name', 'new-branch')
        ->expectsQuestion('Parent branch', 'main')
        ->expectsOutput("Bellows doesn't have permission to create a branch.")
        ->deploy();
});

it('will bark if it does not have the correct create password scopes', function () {
    Http::fake([
        'organizations?per_page=1' => [
            'data' => [
                [

                    'name' => 'test-org',
                ],
            ],
        ],
        'organizations/test-org/databases' => Http::response([
            'data' => [
                [
                    'name' => 'bellows-tester',
                ],
            ],
        ]),
        'organizations/test-org/databases/bellows-tester/branches' => Http::response([
            'data' => [
                [
                    'name' => 'main',
                ],
            ],
        ]),
        'organizations/test-org/databases/bellows-tester/branches/main/passwords' => Http::sequence([
            [
                'code' => 'forbidden',
            ],
        ]),
    ]);

    $this->plugin(PlanetScale::class)
        ->expectsQuestion('Database', 'bellows-tester')
        ->expectsQuestion('Branch', 'main')
        ->expectsOutput("Bellows doesn't have permission to create a password.")
        ->deploy();
});
