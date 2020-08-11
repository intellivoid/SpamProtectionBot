<?php


    namespace acm;

    use acm\Exceptions\LocalConfigurationException;
    use acm\Exceptions\NoConfigurationFoundException;
    use acm\Objects\Schema;
    use Exception;

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Abstracts' . DIRECTORY_SEPARATOR . 'ExceptionCodes.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'LocalConfigurationException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . 'NoConfigurationFoundException.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'Objects' . DIRECTORY_SEPARATOR . 'Schema.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'CommandLine.php');

    /**
     * Class acm
     * @package acm
     */
    class acm
    {
        /**
         * The working directory for configuration files
         *
         * @var array
         */
        public static $WorkingDirectory = array(
            'linux' => "/etc/acm",
            'windows' => "C:\\acm"
        );

        /**
         * @var string
         */
        private $BaseDirectory;

        /**
         * @var string
         */
        private $Vendor;

        /**
         * @var array
         */
        public $MasterConfiguration;

        /**
         * @var string
         */
        private $VendorSafe;

        /**
         * The file location of the master configuration file
         *
         * @var string
         */
        private $MasterConfigurationLocation;

        /**
         * Indicates if the master configuration has been loaded or not
         *
         * @var bool
         */
        private $MasterConfigurationLoaded;

        /**
         * Gets the working directory appropriate to the system
         *
         * @return string
         */
        public static function getWorkingDirectory(): string
        {
            if (strncasecmp(PHP_OS, 'WIN', 3) == 0)
            {
                return acm::$WorkingDirectory['windows'];
            }
            else
            {
                return acm::$WorkingDirectory['linux'];
            }
        }

        /**
         * Public Constructor
         *
         * acm constructor.
         * @param string $base_directory
         * @param string $vendor\
         */
        public function __construct(string $base_directory, string $vendor)
        {
            // Declare the fields
            $this->BaseDirectory = $base_directory;
            $this->Vendor = $vendor;

            $this->VendorSafe = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $vendor);
            $this->VendorSafe = mb_ereg_replace("([\.]{2,})", '', $this->VendorSafe);
            $this->VendorSafe = str_ireplace(' ', '_', $this->VendorSafe);
            $this->VendorSafe = strtolower($this->VendorSafe);

            $this->MasterConfigurationLocation = acm::getWorkingDirectory() . DIRECTORY_SEPARATOR . $this->VendorSafe . '.json';
            $this->reloadMasterConfiguration(); // Loads / Creates the Master Configuration
        }

        /**
         * Updates the master configuration from memory to disk
         *
         * Returns false if the master configuration was not found / cannot be written to
         *
         * @return bool
         */
        public function updateMasterConfiguration(): bool
        {
            if(file_exists($this->MasterConfigurationLocation))
            {
                if(is_writeable($this->MasterConfigurationLocation) == false)
                {
                    return false;
                }
            }

            if($this->MasterConfigurationLoaded == false)
            {
                if($this->reloadMasterConfiguration() == false)
                {
                    return false;
                }
            }

            file_put_contents(
                $this->MasterConfigurationLocation,
                json_encode($this->MasterConfiguration, JSON_PRETTY_PRINT)
            );
            return true;
        }

        /**
         * Reloads master configuration from disk
         *
         * Returns false if the master configuration was not found
         *
         * @return bool
         */
        public function reloadMasterConfiguration(): bool
        {
            if(file_exists($this->MasterConfigurationLocation) == false)
            {
                if(file_exists(acm::getWorkingDirectory()) == false)
                {
                    $this->MasterConfigurationLoaded = false;
                    return false;
                }

                // If the working directory is not writable
                //if(is_writable(dirname(acm::getWorkingDirectory())) == false)
                //{
                //    return false;
                //}

                $this->MasterConfiguration = array(
                    'file_type' => 'acm_json',
                    'file_version' => '1.0.0.0',
                    'configurations' => array(),
                    'schemas' => array()
                );

                // Generate the master configuration file
                file_put_contents(
                    $this->MasterConfigurationLocation,
                    json_encode($this->MasterConfiguration, JSON_PRETTY_PRINT)
                );
            }

            $ConfigurationContents = file_get_contents($this->MasterConfigurationLocation);
            $this->MasterConfiguration = json_decode($ConfigurationContents, true);
            $this->MasterConfigurationLoaded = true;
            return true;
        }

        /**
         * Defines a configuration schema structure
         *
         * @param string $name
         * @param Schema $schema
         * @return bool
         */
        public function defineSchema(string $name, Schema $schema): bool
        {
            if($this->MasterConfigurationLoaded == false)
            {
                return false;
            }

            if(isset($this->MasterConfiguration['schemas'][$name]) == true)
            {
                if($this->MasterConfiguration['schemas'][$name] === $schema->toArray())
                {
                    return false;
                }
            }

            $this->MasterConfiguration['schemas'][$name] = $schema->toArray();
            $this->updateMasterConfiguration();

            return true;
        }

        /**
         * Deletes a schema structure if it exists
         *
         * @param string $name
         * @return bool
         */
        public function deleteSchema(string $name): bool
        {
            if($this->MasterConfigurationLoaded == false)
            {
                return false;
            }

            if(isset($this->MasterConfiguration['schemas']) == false)
            {
                return false;
            }

            if(isset($this->MasterConfiguration['schemas'][$name]) == false)
            {
                return false;
            }

            unset($this->MasterConfiguration['schemas'][$name]);
            return true;
        }

        /**
         * @param string $name
         * @return mixed
         * @throws Exception
         */
        public function getConfiguration(string $name)
        {
            // If the master configuration is not loaded, try to read from the local configuration file instead
            if($this->MasterConfigurationLoaded == false)
            {
                return $this->getLocalConfiguration($name);
            }

            // Check if the configuration exists
            if(isset($this->MasterConfiguration['configurations'][$name]) == false)
            {
                try
                {
                    $LocalConfiguration = $this->getLocalConfiguration($name);
                }
                catch(Exception $exception)
                {
                    $LocalConfiguration = null;
                }

                if(isset($this->MasterConfiguration['schemas'][$name]))
                {
                    foreach($this->MasterConfiguration['schemas'][$name] as $schemaKey => $defaultValue)
                    {
                        $this->MasterConfiguration['configurations'][$name][$schemaKey] = $defaultValue;
                    }
                }

                // Overwrite default values if possible
                if($LocalConfiguration !== null)
                {
                    foreach($LocalConfiguration as $configKey => $configValue)
                    {
                        $this->MasterConfiguration['configurations'][$name][$configKey] = $configValue;
                    }
                }

                $this->updateMasterConfiguration();
            }

            if(isset($this->MasterConfiguration['configurations'][$name]) == false)
            {
                throw new NoConfigurationFoundException();
            }

            return $this->MasterConfiguration['configurations'][$name];
        }

        /**
         * Gets the local configuration if possible
         *
         * @param string $name
         * @return mixed
         * @throws Exception
         */
        public function getLocalConfiguration(string $name)
        {
            $LocalConfiguration = $this->BaseDirectory . DIRECTORY_SEPARATOR . 'configuration.ini';

            if(file_exists($LocalConfiguration) == false)
            {
                throw new NoConfigurationFoundException();
            }
            $ParsedConfiguration = parse_ini_file($LocalConfiguration, true);

            if(isset($ParsedConfiguration[$name]) == false)
            {
                throw new LocalConfigurationException();
            }

            return $ParsedConfiguration[$name];
        }

        /**
         * Processes any command line commands
         */
        public function processCommandLine()
        {
            $CommandLine = new CommandLine($this);
            $CommandLine->processCommandLine();
        }


        /**
         * @return string
         */
        public function getBaseDirectory(): string
        {
            return $this->BaseDirectory;
        }

        /**
         * @return bool
         */
        public function isMasterConfigurationLoaded(): bool
        {
            return $this->MasterConfigurationLoaded;
        }

        /**
         * @return string
         */
        public function getMasterConfigurationLocation(): string
        {
            return $this->MasterConfigurationLocation;
        }

    }