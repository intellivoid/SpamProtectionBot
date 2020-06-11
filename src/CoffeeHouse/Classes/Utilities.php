<?php


    namespace CoffeeHouse\Classes;


    use CoffeeHouse\Exceptions\BotSessionException;
    use CoffeeHouse\Objects\HttpResponse;
    
    /**
     * Class Utilities
     * @package CoffeeHouse\Classes
     */
    class Utilities
    {

        /**
         * Raw HTTP Request
         *
         * @param string $url
         * @param $cookies
         * @param $parameters
         * @param null $headers
         * @return HttpResponse
         * @throws BotSessionException
         */
        public static function request(string $url, &$cookies, $parameters, $headers = null): HttpResponse
        {
            $ContextParameters  = array();
            $ContextParameters['http'] = array();

            // Process if it's a POST request or not
            if($parameters)
            {
                $ContextParameters['http']['method'] = 'POST';
                $ContextParameters['http']['content'] = http_build_query($parameters);
                $ContextParameters['http']['header'] = "Content-type: application/x-www-form-urlencoded\r\n";
            }
            else
            {
                $ContextParameters['http']['method'] = 'GET';
            }

            // Process the cookies
            if(!is_null($cookies) && count($cookies) > 0)
            {
                $CookieHeader = "Cookie: ";
                foreach($cookies as $Name => $Value)
                {
                    // TODO: Double check if it's supposed to be "NAME=VALUE;"
                    $CookieHeader .= $Value . ";";
                }
                $CookieHeader .= "\r\n";

                if(isset($ContextParameters['http']['header']))
                {
                    $ContextParameters['http']['header'] .= $CookieHeader;
                }
                else
                {
                    $ContextParameters['http']['header'] = $CookieHeader;
                }
            }

            // Process custom headers
            if(!is_null($headers))
            {
                foreach($headers as $HeaderName => $HeaderValue)
                {
                    $HeaderRow = $HeaderName . ': ' . $HeaderValue . "\r\n";

                    if(isset($ContextParameters['http']['header']))
                    {
                        $ContextParameters['http']['header'] .= $HeaderRow;
                    }
                    else
                    {
                        $ContextParameters['http']['header'] = $HeaderRow;
                    }
                }
            }

            // Establish the request stream
            $Context = stream_context_create($ContextParameters);
            $BufferStream = fopen($url, 'rb', false, $Context);
            if(!$BufferStream)
            {
                throw new BotSessionException(error_get_last());
            }
            $Response = stream_get_contents($BufferStream);


            // Accept new cookies
            if(!is_null($cookies))
            {
                foreach($http_response_header as $header)
                {
                    if (preg_match('@Set-Cookie: (([^=]+)=[^;]+)@i', $header, $matches))
                    {
                        $cookies[$matches[2]] = $matches[1];
                    }
                }
            }

            // Close the stream
            fclose($BufferStream);
            return new HttpResponse($cookies, $Response);
        }

        /**
         * @param $strings
         * @param $index
         * @return mixed|string
         */
        public static function stringAtIndex($strings, $index)
        {
            if(count($strings) > $index)
            {
                return $strings[$index];
            }

            return '';
        }

        /**
         * Replaces third party input
         *
         * @param string $input
         * @return string
         */
        public static function replaceThirdPartyMessages(string $input): string
        {
            $input = str_ireplace('cleverbot', 'Lydia', $input);
            $input = str_ireplace('clever bot', 'Lydia', $input);
            $input = str_ireplace('rollo carpenter', 'Zi Xing', $input);
            $input = str_ireplace('jabberwacky', 'Lydia', $input);
            $input = str_ireplace('clever', 'smart', $input);
            $input = str_ireplace('existor', 'Intellivoid', $input);

            return $input;
        }
    }