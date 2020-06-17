<?php


    namespace CoffeeHouse\Managers;


    use CoffeeHouse\Classes\Hashing;
    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Exceptions\DatabaseException;
    use CoffeeHouse\Exceptions\SpamPredictionCacheNotFoundException;
    use CoffeeHouse\Objects\Cache\SpamPredictionCache;
    use CoffeeHouse\Objects\Results\SpamPredictionResults;
    use msqg\QueryBuilder;

    /**
     * Class SpamPredictionCacheManager
     * @package CoffeeHouse\Managers
     */
    class SpamPredictionCacheManager
    {
        /**
         * @var CoffeeHouse
         */
        private $coffeeHouse;

        /**
         * SpamPredictionCacheManager constructor.
         * @param CoffeeHouse $coffeeHouse
         */
        public function __construct(CoffeeHouse $coffeeHouse)
        {
            $this->coffeeHouse = $coffeeHouse;
        }

        /**
         * Registers a cache result into the database
         *
         * @param string $input
         * @param SpamPredictionResults $predictionResults
         * @return bool
         * @throws DatabaseException
         */
        public function registerCache(string $input, SpamPredictionResults $predictionResults): bool
        {
            $hash = $this->coffeeHouse->getDatabase()->real_escape_string(Hashing::input($input));
            $ham = (float)$predictionResults->HamPrediction;
            $spam = (float)$predictionResults->SpamPrediction;
            $created_timestamp = (int)time();
            $last_updated_timestamp = (int)time();

            $Query = QueryBuilder::insert_into('spam_prediction_cache', array(
                'hash' => $hash,
                'ham_calculation' => $ham,
                'spam_calculation' => $spam,
                'last_updated' => $last_updated_timestamp,
                'created' => $created_timestamp
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

        /**
         * Returns an existing cache record from the database
         *
         * @param string $input
         * @return SpamPredictionCache
         * @throws DatabaseException
         * @throws SpamPredictionCacheNotFoundException
         */
        public function getCache(string $input): SpamPredictionCache
        {
            $hash = $this->coffeeHouse->getDatabase()->real_escape_string(Hashing::input($input));

            $Query = QueryBuilder::select('spam_prediction_cache', [
                'id',
                'hash',
                'ham_calculation',
                'spam_calculation',
                'last_updated',
                'created'
            ], 'hash', $hash, null, null, 1);
            $QueryResults = $this->coffeeHouse->getDatabase()->query($Query);

            if($QueryResults)
            {
                $Row = $QueryResults->fetch_array(MYSQLI_ASSOC);
                $QueryResults->close();

                if ($Row == False)
                {
                    throw new SpamPredictionCacheNotFoundException();
                }
                else
                {
                    return(SpamPredictionCache::fromArray($Row));
                }
            }
            else
            {
                throw new DatabaseException($this->coffeeHouse->getDatabase()->error);
            }
        }

        /**
         * Updates an existing cache record in the database
         *
         * @param SpamPredictionCache $spamPredictionCache
         * @param SpamPredictionResults $spamPredictionResults
         * @return bool
         * @throws DatabaseException
         */
        public function updateCache(SpamPredictionCache $spamPredictionCache, SpamPredictionResults $spamPredictionResults): bool
        {
            $id = (int)$spamPredictionCache->ID;
            $ham = (float)$spamPredictionResults->HamPrediction;
            $spam = (float)$spamPredictionResults->SpamPrediction;
            $last_updated_timestamp = (int)time();

            $Query = QueryBuilder::update('spam_prediction_cache', array(
                'ham_calculation' => $ham,
                'spam_calculation' => $spam,
                'last_updated' => $last_updated_timestamp
            ), 'id', $id);
            $QueryResults = $this->coffeeHouse->getDatabase()->query($Query);

            if($QueryResults)
            {
                return(True);
            }
            else
            {
                throw new DatabaseException($this->coffeeHouse->getDatabase()->error);
            }
        }
    }