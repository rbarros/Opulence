<?php
/**
 * Copyright (C) 2014 David Young
 *
 * Mocks the Memcached class for use in testing
 */
namespace RDev\Tests\Models\Databases\NoSQL\Memcached\Mocks;
use RDev\Models\Databases\NoSQL\Memcached as MemcachedNamespace;
use RDev\Models\Databases\NoSQL\Memcached\Configs;

// To get around having to install Memcached just to run tests, include a mock Memcached class
if(!class_exists("Memcached"))
{
    require_once(__DIR__ . "/Memcached.php");
}

class RDevMemcached extends MemcachedNamespace\RDevMemcached
{
    /**
     * {@inheritdoc}
     */
    public function __construct($config)
    {
        if(is_array($config))
        {
            $config = new Configs\ServerConfig($config);
        }

        $this->typeMapper = new MemcachedNamespace\TypeMapper();

        /** @var Server $server */
        foreach($config["servers"] as $server)
        {
            $this->addServer($server->getHost(), $server->getPort(), $server->getWeight());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addServer($host, $port, $weight = 0)
    {
        $server = new Server();
        $server->setHost($host);
        $server->setPort($port);
        $server->setWeight($weight);
        $this->servers[] = $server;
    }
}