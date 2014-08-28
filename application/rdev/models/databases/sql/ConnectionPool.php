<?php
/**
 * Copyright (C) 2014 David Young
 *
 * Defines a database connection pool
 * This can handle multiple server setups or simple single server setups
 */
namespace RDev\Models\Databases\SQL;

abstract class ConnectionPool
{
    /** @var array Maps driver names to their fully-qualified class names */
    private static $drivers = [
        "pdo_mysql" => "RDev\\Models\\Databases\\SQL\\PDO\\MySQL\\Driver",
        "pdo_pgsql" => "RDev\\Models\\Databases\\SQL\\PDO\\PostgreSQL\\Driver",
    ];
    /** @var array The servers in this pool */
    protected $servers = [
        "master" => null,
        "custom" => []
    ];
    /** @var IDriver The driver to use for connections made by this pool */
    protected $driver = null;
    /** @var array The list of connection options */
    protected $connectionOptions = [];
    /** @var array The list of driver options */
    protected $driverOptions = [];
    /** @var Configs\ConnectionPoolReader The config reader to use to initialize the connection pool from */
    protected $configReader = null;
    /** @var ServerFactory The factory to use to create servers from configs */
    protected $serverFactory = null;
    /** @var IConnection|null The connection to use for read queries */
    protected $readConnection = null;
    /** @var IConnection|null The connection to use for write queries */
    protected $writeConnection = null;

    /**
     * @param array|string $config The configuration to use to setup the connection pool
     *      If it's a string, then it must point to a valid JSON file containing the config
     *          This JSON file should be decodable into the same format defined below for a keyed array
     *      If it's an array, then it must have the following format
     *      This must contain the following keys:
     *          "driver" => name of the driver listed in self::$drivers OR
     *              The fully-qualified name of a custom driver class OR
     *              An object that implements IDriver
     *          "servers" => [
     *              "master" => master server (see ServerFactory for examples of a server configuration)
     *          ]
     *      The following are optional:
     *          "driverOptions" => settings to use to setup a driver connection,
     *          "connectionOptions" => the driver-specific connection settings
     * @throws \RuntimeException Thrown if the configuration was invalid
     * @see ServerFactory
     */
    public function __construct($config)
    {
        $this->configReader = new Configs\ConnectionPoolReader();
        $this->serverFactory = new ServerFactory();
        $configArray = $this->configReader->load("RDev\\Models\\Databases\\SQL\\Configs\\ConnectionPoolConfig", $config)
            ->toArray();
        $this->setDriver($configArray["driver"]);
        $this->setServers($configArray["servers"]);
        $this->driverOptions = isset($configArray["driverOptions"]) ? $configArray["driverOptions"] : [];
        $this->connectionOptions = isset($configArray["connectionOptions"]) ? $configArray["connectionOptions"] : [];
    }

    /**
     * Gets the list of pre-defined driver names available in this class
     *
     * @return array The list of driver names
     */
    public static function getDriverNames()
    {
        return array_keys(self::$drivers);
    }

    /**
     * @return IDriver
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @return Server|null
     */
    public function getMaster()
    {
        return $this->servers["master"]["server"];
    }

    /**
     * Gets the connection used for read queries
     *
     * @param Server $preferredServer The preferred server to use
     * @return IConnection The connection to use for reads
     * @throws \RuntimeException Thrown if the connection pool wasn't configured correctly
     */
    public function getReadConnection(Server $preferredServer = null)
    {
        if($preferredServer !== null)
        {
            $this->addServer("custom", $preferredServer);
            $this->setReadConnection($preferredServer);
        }
        elseif($this->readConnection == null)
        {
            $this->setReadConnection();
        }

        return $this->readConnection;
    }

    /**
     * Gets the connection used for write queries
     *
     * @param Server $preferredServer The preferred server to use
     * @return IConnection The connection to use for writes
     * @throws \RuntimeException Thrown if the connection pool wasn't configured correctly
     */
    public function getWriteConnection(Server $preferredServer = null)
    {
        if($preferredServer != null)
        {
            $this->addServer("custom", $preferredServer);
            $this->setWriteConnection($preferredServer);
        }
        elseif($this->writeConnection == null)
        {
            $this->setWriteConnection();
        }

        return $this->writeConnection;
    }

    /**
     * @param Server $master
     */
    public function setMaster(Server $master)
    {
        $this->addServer("master", $master);
    }

    /**
     * Sets the connection to use for read queries
     *
     * @param Server $preferredServer The preferred server to connect to
     * @throws \RuntimeException Thrown if the connection pool wasn't configured correctly
     */
    abstract protected function setReadConnection(Server $preferredServer = null);

    /**
     * Sets the connection to use for write queries
     *
     * @param Server $preferredServer The preferred server to connect to
     * @throws \RuntimeException Thrown if the connection pool wasn't configured correctly
     */
    abstract protected function setWriteConnection(Server $preferredServer = null);

    /**
     * Adds a server to our list of servers
     *
     * @param string $type The type of server we're trying to add, eg "master", "custom"
     * @param Server $server The server to add
     */
    protected function addServer($type, Server $server)
    {
        switch($type)
        {
            case "master":
                $this->servers["master"] = ["server" => $server, "connection" => null];
                break;
            default:
                $serverHashId = spl_object_hash($server);

                if(!isset($this->servers[$type][$serverHashId]))
                {
                    $this->servers[$type][$serverHashId] = ["server" => $server, "connection" => null];
                }

                break;
        }
    }

    /**
     * Creates a database connection
     *
     * @param Server $server The server to connect to
     * @return IConnection The database connection
     */
    protected function connectToServer(Server $server)
    {
        return $this->driver->connect($server, $this->connectionOptions, $this->driverOptions);
    }

    /**
     * Converts the input config to a keyed array
     *
     * @param array|string $config Either the already-formed array or a string pointing to the location of a config file
     * @return array The converted config array
     * @throws \RuntimeException Thrown if the config is not a string or an array or if the config file doesn't exist
     */
    protected function convertConfigToArray($config)
    {
        if(is_array($config))
        {
            return $config;
        }

        // We'll assume from here that the config parameter is really the path to the config file
        if(!is_string($config))
        {
            throw new \RuntimeException("ConnectionPoolConfig is neither a string nor an array");
        }

        if(!file_exists($config))
        {
            throw new \RuntimeException("Invalid config path: " . $config);
        }

        $configPathInfo = pathinfo($config);

        switch($configPathInfo["extension"])
        {
            case "json":
                $decodedJSON = json_decode(file_get_contents($config), true);

                if($decodedJSON === null)
                {
                    throw new \RuntimeException("Invalid JSON config file");
                }

                return $decodedJSON;
            default:
                throw new \RuntimeException("Invalid config file extension: " . $configPathInfo["extension"]);
        }
    }

    /**
     * Gets a connection to the input server
     *
     * @param string $type The type of server we're trying to connect to, eg "master", "custom"
     * @param Server $server The server we want to connect to
     * @return IConnection The connection to the server
     * @throws \RuntimeException Thrown if the connection pool wasn't configured correctly
     */
    protected function getConnection($type, Server $server)
    {
        switch($type)
        {
            case "master":
                if($this->servers["master"]["server"] == null)
                {
                    throw new \RuntimeException("No master specified");
                }

                if($this->servers["master"]["connection"] == null)
                {
                    $this->servers["master"]["connection"] = $this->connectToServer($server);
                }

                return $this->servers["master"]["connection"];
            default:
                $serverHashId = spl_object_hash($server);

                if(!isset($this->servers[$type][$serverHashId])
                    || $this->servers[$type][$serverHashId]["server"] == null
                )
                {
                    throw new \RuntimeException("Server of type '" . $type . "' not added to connection pool");
                }

                if($this->servers[$type][$serverHashId]["connection"] == null)
                {
                    $this->servers[$type][$serverHashId]["connection"] = $this->connectToServer($server);
                }

                return $this->servers[$type][$serverHashId]["connection"];
        }
    }

    /**
     * Sets the driver to use in this pool
     *
     * @param string|IDriver $driver Indicates the driver to use, which can be any of the following:
     *      The name of the driver per this class' constants
     *      The fully-qualified name of the driver class
     *      The driver object that implements IDriver
     * @throws \RuntimeException Thrown if the custom driver couldn't be found
     */
    protected function setDriver($driver)
    {
        if($driver instanceof IDriver)
        {
            $this->driver = $driver;
        }
        elseif(isset(self::$drivers[$driver]))
        {
            $this->driver = new self::$drivers[$driver]();
        }
        else
        {
            // We assume this is a custom driver class
            if(!class_exists($driver))
            {
                throw new \RuntimeException("Invalid custom driver: " . $driver);
            }

            $this->driver = new $driver();
        }
    }

    /**
     * Sets the server configuration
     *
     * @param array $config The configuration array to use to setup the list of servers used by this pool
     * @throws \RuntimeException Thrown if the config isn't valid
     */
    protected function setServers(array $config)
    {
        $this->setMaster($this->serverFactory->createFromConfig($config["master"]));
    }
} 