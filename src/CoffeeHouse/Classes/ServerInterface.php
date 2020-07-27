<?php


    namespace CoffeeHouse\Classes;


    use CoffeeHouse\Abstracts\ServerInterfaceModule;
    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Exceptions\InvalidServerInterfaceModuleException;
    use CoffeeHouse\Exceptions\ServerInterfaceException;
    use CoffeeHouse\Objects\ServerInterfaceConnection;

    /**
     * Class ServerInterface
     * @package CoffeeHouse\Classes
     */
    class ServerInterface
    {
        /**
         * @var CoffeeHouse
         */
        private $coffeehouse;

        /**
         * ServerInterface constructor.
         * @param CoffeeHouse $coffeeHouse
         */
        public function __construct(CoffeeHouse $coffeeHouse)
        {
            $this->coffeehouse = $coffeeHouse;
        }

        /**
         * @param string|ServerInterfaceModule $module
         * @param string $path
         * @param array $parameters
         * @return string
         * @throws InvalidServerInterfaceModuleException
         * @throws ServerInterfaceException
         */
        public function sendRequest(string $module, string $path, array $parameters): string
        {
            $InterfaceConnection = $this->resolveInterfaceConnection($module);

            $CurlClient = curl_init();
            curl_setopt($CurlClient, CURLOPT_URL, $InterfaceConnection->generateAddress(false) . $path);
            curl_setopt($CurlClient, CURLOPT_POST, 1);
            curl_setopt($CurlClient, CURLOPT_POSTFIELDS, http_build_query($parameters));
            curl_setopt($CurlClient, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($CurlClient, CURLOPT_FAILONERROR, true);

            $response = curl_exec($CurlClient);

            if (curl_errno($CurlClient))
            {
                $error_response = curl_error($CurlClient);
                curl_close($CurlClient);

                throw new ServerInterfaceException(
                    $error_response, $InterfaceConnection->generateAddress(false) . $path, $parameters);
            }

            curl_close($CurlClient);
            return $response;
        }

        /**
         * Resolves the interface connection
         *
         * @param string $module
         * @return ServerInterfaceConnection
         * @throws InvalidServerInterfaceModuleException
         */
        public function resolveInterfaceConnection(string $module): ServerInterfaceConnection
        {
            $ServerInterfaceConnection = new ServerInterfaceConnection();
            $ServerInterfaceConnection->Host = $this->coffeehouse->getServerConfiguration()["Host"];
            $ServerInterfaceConnection->Module = $module;

            switch($module)
            {
                case ServerInterfaceModule::SpamPrediction:
                    $ServerInterfaceConnection->Port = $this->coffeehouse->getServerConfiguration()["SpamPredictionPort"];
                    break;

                case ServerInterfaceModule::LanguagePrediction:
                    $ServerInterfaceConnection->Port = $this->coffeehouse->getServerConfiguration()["LanguagePredictionPort"];
                    break;

                default:
                    throw new InvalidServerInterfaceModuleException();
            }

            return $ServerInterfaceConnection;
        }
    }