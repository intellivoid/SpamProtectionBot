<?php


    namespace acm;

    /**
     * Class CommandLine
     * @package acm
     */
    class CommandLine
    {
        /**
         * @var acm
         */
        private $acm;

        /**
         * CommandLine constructor.
         * @param acm $acm
         */
        public function __construct(acm $acm)
        {
            $this->acm = $acm;
        }

        /**
         * Processes the command-line command
         */
        public function processCommandLine()
        {
            if(PHP_SAPI !== 'cli')
            {
                return;
            }

            if(isset($_SERVER['argv'][1]) == false)
            {
                return;
            }

            switch($_SERVER['argv'][1])
            {
                case "build-mc":
                    $this->buildMasterConfiguration();
                    exit(0);

                case 'status':
                    $this->status();
                    exit(0);

                case 'help':
                    print(" acm Usage Commands\n");
                    print("     build-mc    = Builds the master configuration file\n");
                    print("     status      = Displays how configuration is managed in this setup\n");
                    exit(0);
            }

        }

        public function status()
        {
            print("Master Configuration Location: " . $this->acm->getMasterConfigurationLocation() . "\n");
            if($this->acm->isMasterConfigurationLoaded() == false)
            {
                print("Master Configuration Loaded: False\n");
            }
            else
            {
                print("Master Configuration Loaded: True\n");
            }

            print("Working Directory: " . acm::getWorkingDirectory() . "\n");
            if(file_exists(acm::getWorkingDirectory()) == false)
            {
                print("Working Directory Exists: False\n");
            }
            else
            {
                print("Working Directory Exists: True\n");
            }

            $LocalConfiguration = $this->acm->getBaseDirectory() . DIRECTORY_SEPARATOR . 'configuration.ini';;
            print("Local Configuration: " . $LocalConfiguration . "\n");
            if(file_exists($LocalConfiguration) == false)
            {
                print("Local Configuration Exists: False\n");
            }
            else
            {
                print("Local Configuration Exists: True\n");
            }

            if($this->acm->isMasterConfigurationLoaded() == false)
            {
                print("Warning: The master configuration was not loaded, acm will attempt to use the local configuration instead\n");
            }

            if(file_exists($LocalConfiguration) == false)
            {
                print("Warning: The local configuration is not found, if the master configuration is not found either then the application may throw errors\n");
            }
        }

        /**
         * Builds the master configuration
         */
        public function buildMasterConfiguration()
        {
            if($this->acm->isMasterConfigurationLoaded() == false)
            {
                print("Cannot build master configuration because it isn't loaded.\n");
                print("Check '" . acm::getWorkingDirectory() . "' for errors\n");
                exit(1);
            }

            $LocalConfiguration = $this->acm->getBaseDirectory() . DIRECTORY_SEPARATOR . 'configuration.ini';
            print("Checking if '$LocalConfiguration' exists\n");
            $ParsedConfiguration = null;
            if(file_exists($LocalConfiguration) == true)
            {
                print("Parsing '$LocalConfiguration'\n");
                $ParsedConfiguration = parse_ini_file($LocalConfiguration, true);
            }
            else
            {
                print("Skipping local configuration since it doesn't exist\n");
            }

            print("Building configuration from schema\n");
            foreach($this->acm->MasterConfiguration['schemas'] as $schemaName => $schemaValue)
            {
                print("Parsing $schemaName\n");
                foreach($this->acm->MasterConfiguration['schemas'][$schemaName] as $schemaKey => $defaultValue)
                {
                    print("Creating $schemaKey=>'$defaultValue' from $schemaName\n");
                    $this->acm->MasterConfiguration['configurations'][$schemaName][$schemaKey] = $defaultValue;
                }
            }

            // Overwrite default values if possible
            if($ParsedConfiguration !== null)
            {
                print("Building configuration from Local configuration\n");
                foreach($ParsedConfiguration as $configurationName => $configurationValue)
                {
                    print("Parsing $configurationName\n");
                    foreach($ParsedConfiguration[$configurationName] as $configKey => $configValue)
                    {
                        print("Creating $configKey=>'$configValue' from $configurationName\n");
                        $this->acm->MasterConfiguration['configurations'][$configurationName][$configKey] = $configValue;
                    }
                }

            }

            print("Updating Master Configuration ...\n");
            $this->acm->updateMasterConfiguration();
            print("Done");
        }
    }