<?php


    namespace CoffeeHouse\NaturalLanguageProcessing;


    use CoffeeHouse\Abstracts\GeneralizedClassificationSearchMethod;
    use CoffeeHouse\Abstracts\ServerInterfaceModule;
    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Exceptions\DatabaseException;
    use CoffeeHouse\Exceptions\GeneralizedClassificationNotFoundException;
    use CoffeeHouse\Exceptions\InvalidInputException;
    use CoffeeHouse\Exceptions\InvalidSearchMethodException;
    use CoffeeHouse\Exceptions\InvalidServerInterfaceModuleException;
    use CoffeeHouse\Exceptions\ServerInterfaceException;
    use CoffeeHouse\Exceptions\SpamPredictionCacheNotFoundException;
    use CoffeeHouse\Objects\GeneralizedClassification;
    use CoffeeHouse\Objects\Results\SpamPredictionResults;

    /**
     * Class SpamPrediction
     * @package CoffeeHouse\NaturalLanguageProcessing
     */
    class SpamPrediction
    {
        /**
         * @var CoffeeHouse
         */
        private $coffeeHouse;

        /**
         * SpamPrediction constructor.
         * @param CoffeeHouse $coffeeHouse
         */
        public function __construct(CoffeeHouse $coffeeHouse)
        {
            $this->coffeeHouse = $coffeeHouse;
        }

        /**
         * Predicts if the given input is spam or not
         *
         * @param string $input
         * @param bool $generalize
         * @param string $generalized_id
         * @param bool $cache
         * @return SpamPredictionResults
         * @throws DatabaseException
         * @throws GeneralizedClassificationNotFoundException
         * @throws InvalidSearchMethodException
         * @throws InvalidServerInterfaceModuleException
         * @throws ServerInterfaceException
         * @throws InvalidInputException
         * @noinspection DuplicatedCode
         */
        public function predict(string $input, bool $generalize=false, string $generalized_id="None", bool $cache=true): SpamPredictionResults
        {
            if(strlen($input) == 0)
            {
                throw new InvalidInputException();
            }

            $SpamPredictionCache = null;
            $PredictionGeneralization = null;

            if($generalize)
            {
                if($generalized_id == "None")
                {
                    $PredictionGeneralization = $this->createGeneralized();
                }
                else
                {
                    $PredictionGeneralization = $this->getGeneralized($generalized_id);
                }
            }

            if($cache)
            {
                try
                {
                    $SpamPredictionCache = $this->coffeeHouse->getSpamPredictionCacheManager()->getCache($input);
                }
                catch (SpamPredictionCacheNotFoundException $e)
                {
                    unset($e);
                    $SpamPredictionCache = null;
                }
                catch(DatabaseException $e)
                {
                    throw $e;
                }

                if($SpamPredictionCache !== null)
                {
                    if(((int)time() - $SpamPredictionCache->LastUpdated) < 86400)
                    {
                        $PredictionResults = new SpamPredictionResults();
                        $PredictionResults->SpamPrediction = $SpamPredictionCache->SpamCalculation;
                        $PredictionResults->HamPrediction = $SpamPredictionCache->HamCalculation;

                        if($generalize)
                        {
                            /** @var GeneralizedClassification $PredictionGeneralization */
                            $PredictionGeneralization['spam_generalized']->addValue($PredictionResults->SpamPrediction);
                            $PredictionGeneralization['ham_generalized']->addValue($PredictionResults->HamPrediction);

                            $PredictionResults->GeneralizedSpam = $PredictionGeneralization['spam_generalized']->Results;
                            $PredictionResults->GeneralizedHam = $PredictionGeneralization['ham_generalized']->Results;
                            $PredictionResults->GeneralizedID = $PredictionGeneralization['generalized_id'];

                            $this->updateGeneralized($PredictionGeneralization);
                        }

                        return $PredictionResults;
                    }
                }
            }

            $Results = $this->coffeeHouse->getServerInterface()->sendRequest(
                ServerInterfaceModule::SpamPrediction, "/", array("input" => $input)
            );

            $PredictionResults = SpamPredictionResults::fromArray(json_decode($Results, true)['results']);

            if($cache)
            {
                if($SpamPredictionCache == null)
                {
                    $this->coffeeHouse->getSpamPredictionCacheManager()->registerCache($input, $PredictionResults);
                }
                else
                {
                    if(((int)time() - $SpamPredictionCache->LastUpdated) > 86400)
                    {
                        $this->coffeeHouse->getSpamPredictionCacheManager()->updateCache($SpamPredictionCache, $PredictionResults);
                    }
                }
            }

            if($generalize)
            {
                /** @var GeneralizedClassification $PredictionGeneralization */
                $PredictionGeneralization['spam_generalized']->addValue($PredictionResults->SpamPrediction);
                $PredictionGeneralization['ham_generalized']->addValue($PredictionResults->HamPrediction);

                $PredictionResults->GeneralizedSpam = $PredictionGeneralization['spam_generalized']->Results;
                $PredictionResults->GeneralizedHam = $PredictionGeneralization['ham_generalized']->Results;
                $PredictionResults->GeneralizedID = $PredictionGeneralization['generalized_id'];

                $this->updateGeneralized($PredictionGeneralization);
            }

            return $PredictionResults;
        }

        /**
         * Returns the generalized results as an array
         *
         * @param string $generalized_id
         * @return array
         * @throws DatabaseException
         * @throws GeneralizedClassificationNotFoundException
         * @throws InvalidSearchMethodException
         */
        public function getGeneralized(string $generalized_id): array
        {
            $generalized_ids = explode(':', $generalized_id);

            if(count($generalized_ids) !== 2)
            {
                throw new GeneralizedClassificationNotFoundException();
            }

            return array(
                'spam_generalized' => $this->coffeeHouse->getGeneralizedClassificationManager()->get(
                    GeneralizedClassificationSearchMethod::byPublicID, $generalized_ids[0]
                ),
                'ham_generalized' => $this->coffeeHouse->getGeneralizedClassificationManager()->get(
                    GeneralizedClassificationSearchMethod::byPublicID, $generalized_ids[1]
                ),
                'generalized_id' => $generalized_id
            );
        }

        /**
         * Creates a generalized structure
         *
         * @return array
         * @throws DatabaseException
         * @throws GeneralizedClassificationNotFoundException
         * @throws InvalidSearchMethodException
         */
        public function createGeneralized(): array
        {
            $spam_generalized = $this->coffeeHouse->getGeneralizedClassificationManager()->create(50);
            $ham_generalized = $this->coffeeHouse->getGeneralizedClassificationManager()->create(50);

            return array(
                'spam_generalized' => $spam_generalized,
                'ham_generalized' => $ham_generalized,
                'generalized_id' => $spam_generalized->PublicID . ":" . $ham_generalized->PublicID
            );
        }

        /**
         * Updates an existing generalized structure
         *
         * @param array $generalization_array
         * @return bool
         * @throws DatabaseException
         * @throws GeneralizedClassificationNotFoundException
         * @throws InvalidSearchMethodException
         */
        public function updateGeneralized(array $generalization_array): bool
        {
            $this->coffeeHouse->getGeneralizedClassificationManager()->update(
                $generalization_array['spam_generalized']
            );

            $this->coffeeHouse->getGeneralizedClassificationManager()->update(
                $generalization_array['ham_generalized']
            );

            return true;
        }
    }