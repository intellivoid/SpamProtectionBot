<?php


    namespace CoffeeHouse\Bots;

    use CoffeeHouse\Abstracts\ForeignSessionSearchMethod;
    use CoffeeHouse\Classes\Hashing;
    use CoffeeHouse\Classes\Utilities;
    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Exceptions\BotSessionException;
    use CoffeeHouse\Exceptions\DatabaseException;
    use CoffeeHouse\Exceptions\ForeignSessionNotFoundException;
    use CoffeeHouse\Exceptions\InvalidMessageException;
    use CoffeeHouse\Exceptions\InvalidSearchMethodException;
    use CoffeeHouse\Objects\BotThought;
    use CoffeeHouse\Objects\ForeignSession;
    use Exception;

    /**
     * Class _Cleverbot
     * @package CoffeeHouse\Bots
     */
    class Cleverbot
    {

        /**
         * @var string
         */
        private $baseUrl;

        /**
         * @var string
         */
        private $serviceUrl;

        /**
         * @var CoffeeHouse
         */
        private $coffeeHouse;

        /**
         * @var ForeignSession
         */
        private $Session;

        /**
         * Cleverbot constructor.
         * @param CoffeeHouse $coffeeHouse
         * @throws Exception
         */
        public function __construct(CoffeeHouse $coffeeHouse)
        {
            $this->baseUrl = 'http://cleverbot.com';
            $this->serviceUrl = 'https://www.cleverbot.com/webservicemin?uc=UseOfficialCleverbotAPI';
            $this->coffeeHouse = $coffeeHouse;
        }

        /**
         * @param string $language
         * @throws DatabaseException
         * @throws ForeignSessionNotFoundException
         * @throws InvalidSearchMethodException
         * @throws BotSessionException
         */
        public function newSession($language = 'en')
        {
            $this->Session = $this->coffeeHouse->getForeignSessionsManager()->createSession($language);

            $this->Session->Variables = array(
                'stimulus'              => '',
                'cb_settings_language'  => $language,
                'cb_settings_scripting' => 'no',
                'islearning'            => '1',
                'icognoid'              => 'wsf'
            );

            $this->Session->Headers = array(
                'Accept-Language'       => $language . ';q=1.0'
            );

            $this->Session->Cookies = array();

            // Get the initial cookies
            $Response = Utilities::request(
                $this->baseUrl,
                $this->Session->Cookies,
                null,
                $this->Session->Headers
            );

            $this->Session->Cookies = $Response->cookies;
            $this->Session->Language = $language;

            $this->coffeeHouse->getForeignSessionsManager()->updateSession($this->Session);
            $this->coffeeHouse->getDeepAnalytics()->tally('coffeehouse', 'lydia_sessions', 0);
        }

        /**
         * Loads an existing session
         *
         * @param string $session_id
         * @throws DatabaseException
         * @throws ForeignSessionNotFoundException
         * @throws InvalidSearchMethodException
         * @noinspection PhpUnused
         */
        public function loadSession(string $session_id)
        {
            $this->Session = $this->coffeeHouse->getForeignSessionsManager()->getSession(
                ForeignSessionSearchMethod::bySessionId, $session_id
            );
        }

        /**
         * @param string $input
         * @return BotThought
         * @throws BotSessionException
         * @throws DatabaseException
         */
        public function think(string $input): string
        {
            $this->Session->Variables['stimulus'] = $input;

            // Debug this (Creates icognoid value)
            $data = http_build_query($this->Session->Variables);
            $this->Session->Variables['icognocheck'] = Hashing::icognocheckCode($data);

            $Response = Utilities::request(
                $this->serviceUrl,
                $this->Session->Cookies,
                $this->Session->Variables,
                $this->Session->Headers
            );
            $ResponseValues = explode("\r", $Response->response);

            // Parses the values
            $this->Session->Variables['sessionid'] = Utilities::stringAtIndex($ResponseValues, 1);
            $this->Session->Variables['logurl'] = Utilities::stringAtIndex($ResponseValues, 2);
            $this->Session->Variables['vText8'] = Utilities::stringAtIndex($ResponseValues, 3);
            $this->Session->Variables['vText7'] = Utilities::stringAtIndex($ResponseValues, 4);
            $this->Session->Variables['vText6'] = Utilities::stringAtIndex($ResponseValues, 5);
            $this->Session->Variables['vText5'] = Utilities::stringAtIndex($ResponseValues, 6);
            $this->Session->Variables['vText4'] = Utilities::stringAtIndex($ResponseValues, 7);
            $this->Session->Variables['vText3'] = Utilities::stringAtIndex($ResponseValues, 8);
            $this->Session->Variables['vText2'] = Utilities::stringAtIndex($ResponseValues, 9);
            $this->Session->Variables['prevref'] = Utilities::stringAtIndex($ResponseValues, 10);

            $Text = Utilities::stringAtIndex($ResponseValues, 0);

            if(!is_null($Text))
            {
                $Text = preg_replace_callback(
                    '/\|([01234567890ABCDEF]{4})/',
                    function($matches)
                    {
                        return iconv(
                            'UCS-4LE', 'UTF-8',
                            pack('V', hexdec($matches[0]))
                        );
                    }, $Text);

                $Text = Utilities::replaceThirdPartyMessages($Text);
            }
            else
            {
                $Text = 'COFFEE_HOUSE ERROR';
            }

            $this->Session->Messages += 1;
            $this->coffeeHouse->getForeignSessionsManager()->updateSession($this->Session);

            try
            {
                $this->coffeeHouse->getChatDialogsManager()->recordDialog(
                    $this->Session->SessionID, $this->Session->Messages, $input, $Text
                );
            }
            catch(InvalidMessageException $invalidMessageException)
            {
                // Ignore this exception
            }

            $this->coffeeHouse->getDeepAnalytics()->tally('coffeehouse', 'lydia_messages', 0);

            return $Text;
        }

        /**
         * @return ForeignSession
         */
        public function getSession(): ForeignSession
        {
            return $this->Session;
        }
    }