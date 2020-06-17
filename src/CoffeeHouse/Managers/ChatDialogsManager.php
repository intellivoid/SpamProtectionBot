<?php


    namespace CoffeeHouse\Managers;


    use CoffeeHouse\Classes\Validation;
    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Exceptions\DatabaseException;
    use CoffeeHouse\Exceptions\InvalidMessageException;
    use msqg\QueryBuilder;

    /**
     * Class ChatDialogsManager
     * @package CoffeeHouse\Managers
     */
    class ChatDialogsManager
    {
        /**
         * @var CoffeeHouse
         */
        private $coffeeHouse;

        /**
         * ChatDialogsManager constructor.
         * @param CoffeeHouse $coffeeHouse
         */
        public function  __construct(CoffeeHouse $coffeeHouse)
        {
            $this->coffeeHouse = $coffeeHouse;
        }

        /**
         * Records a dialog conversation
         *
         * @param string $session_id
         * @param int $step
         * @param string $input
         * @param string $output
         * @return bool
         * @throws DatabaseException
         * @throws InvalidMessageException
         */
        public function recordDialog(string $session_id, int $step, string $input, string $output): bool
        {
            if(Validation::message($input) == false)
            {
                throw new InvalidMessageException();
            }

            if(Validation::message($output) == false)
            {
                throw new InvalidMessageException();
            }

            $session_id = $this->coffeeHouse->getDatabase()->real_escape_string($session_id);
            $step = (int)$step;
            $input = $this->coffeeHouse->getDatabase()->real_escape_string(base64_encode($input));
            $output = $this->coffeeHouse->getDatabase()->real_escape_string(base64_encode($output));
            $timestamp = (int)time();

            $Query = QueryBuilder::insert_into('chat_dialogs', array(
                'session_id' => $session_id,
                'step' => $step,
                'input' => $input,
                'output' => $output,
                'timestamp' => $timestamp
            ));
            $QueryResults = $this->coffeeHouse->getDatabase()->query($Query);

            if($QueryResults)
            {
                return true;
            }
            else
            {
                throw new DatabaseException($this->coffeeHouse->getDatabase()->error);
            }
        }
    }