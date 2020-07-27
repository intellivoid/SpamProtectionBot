<?php /** @noinspection PhpUnused */


    namespace CoffeeHouse\Objects\Results;

    use function array_diff;

    /**
     * Class LanguagePredictionResults
     * @package CoffeeHouse\Objects\Results
     */
    class LanguagePredictionResults
    {
        /**
         * @var LanguagePrediction[]|null
         */
        public $DLTC_Results;

        /**
         * @var LanguagePrediction[]|null
         */
        public $CLD_Results;

        /**
         * @var LanguagePrediction[]|null
         */
        public $LD_Results;

        /**
         * Combines all results into a singular working result array
         *
         * @return LanguagePredictions[]
         * @noinspection DuplicatedCode
         */
        public function combineResults(): array
        {
            $WorkingResults = [];

            // Combine the results
            if($this->DLTC_Results !== null)
            {
                foreach($this->DLTC_Results as $DLTC_Result)
                {
                    /** @noinspection DuplicatedCode */
                    if(isset($WorkingResults[$DLTC_Result->Language]) == false)
                    {
                        $WorkingResults[$DLTC_Result->Language] = new LanguagePredictions();
                        $WorkingResults[$DLTC_Result->Language]->Language = $DLTC_Result->Language;
                    }

                    $WorkingResults[$DLTC_Result->Language]->Probabilities[] = $DLTC_Result->Probability;
                    $WorkingResults[$DLTC_Result->Language]->updateProbability();
                }
            }

            if($this->CLD_Results !== null)
            {
                foreach($this->CLD_Results as $CLD_Result)
                {
                    /** @noinspection DuplicatedCode */
                    if(isset($WorkingResults[$CLD_Result->Language]) == false)
                    {
                        $WorkingResults[$CLD_Result->Language] = new LanguagePredictions();
                        $WorkingResults[$CLD_Result->Language]->Language = $CLD_Result->Language;
                    }

                    $WorkingResults[$CLD_Result->Language]->Probabilities[] = $CLD_Result->Probability;
                    $WorkingResults[$CLD_Result->Language]->updateProbability();
                }
            }

            if($this->LD_Results !== null)
            {
                foreach($this->LD_Results as $LD_Result)
                {
                    /** @noinspection DuplicatedCode */
                    if(isset($WorkingResults[$LD_Result->Language]) == false)
                    {
                        $WorkingResults[$LD_Result->Language] = new LanguagePredictions();
                        $WorkingResults[$LD_Result->Language]->Language = $LD_Result->Language;
                    }

                    $WorkingResults[$LD_Result->Language]->Probabilities[] = $LD_Result->Probability;
                    $WorkingResults[$LD_Result->Language]->updateProbability();
                }
            }

            // Combine "ZH" predictions into zh-cn and zh-tw
            if(isset($WorkingResults["zh"]))
            {
                if(isset($WorkingResults["zh-cn"]))
                {
                    foreach($WorkingResults["zh"]->Probabilities as $probability)
                    {
                        $WorkingResults["zh-cn"]->Probabilities[] = $probability;
                    }
                }

                if(isset($WorkingResults["zh-tw"]))
                {
                    foreach($WorkingResults["zh"]->Probabilities as $probability)
                    {
                        $WorkingResults["zh-tw"]->Probabilities[] = $probability;
                    }
                }

                unset($WorkingResults["zh"]);
            }

            // Sort the results
            $SortedResults = array();
            for ($i = 0; $i < count($WorkingResults); $i++)
            {
                $LargestProbability = null;
                $CurrentSelection = null;

                foreach($WorkingResults as $prediction)
                {
                    if(isset($SortedResults[$prediction->Language]) == false)
                    {
                        if($prediction->Probability > $LargestProbability)
                        {
                            $LargestProbability = $prediction->Probability;
                            $CurrentSelection = $prediction;
                        }
                    }
                }

                if($CurrentSelection !== null)
                {
                    $SortedResults[$CurrentSelection->Language] = $CurrentSelection;
                }
            }

            // Organize the results into an array
            $Results = array();
            foreach($SortedResults as $prediction)
            {
                $Results[] = $prediction;
            }

            return $Results;
        }

        /**
         * Returns the array that represents this object
         *
         * @param bool $bytes
         * @return array
         */
        public function toArray(bool $bytes=false): array
        {
            $DLTC_Results = null;
            $CLD_Results = null;
            $LD_Results = null;

            if($this->DLTC_Results !== null)
            {
                $DLTC_Results = array();

                foreach($this->DLTC_Results as $result)
                {
                    $DLTC_Results[] = $result->toArray($bytes);
                }
            }

            if($this->CLD_Results !== null)
            {
                $CLD_Results = array();

                foreach($this->CLD_Results as $result)
                {
                    $CLD_Results[] = $result->toArray($bytes);
                }
            }

            if($this->LD_Results !== null)
            {
                $LD_Results = array();

                foreach($this->LD_Results as $result)
                {
                    $LD_Results[] = $result->toArray($bytes);
                }
            }

            return array(
                "dltc_results" => $DLTC_Results,
                "cld_results" => $CLD_Results,
                "ld_results" => $LD_Results
            );
        }

        /**
         * Constructs object from array
         *
         * @param array $data
         * @param bool $bytes
         * @return LanguagePredictionResults
         */
        public static function fromArray(array $data, bool $bytes=false): LanguagePredictionResults
        {
            $LanguagePredictionResultsObject = new LanguagePredictionResults();

            if(isset($data["dltc_results"]))
            {
                $LanguagePredictionResultsObject->DLTC_Results = array();

                foreach($data["dltc_results"] as $datum)
                {
                    $LanguagePredictionResultsObject->DLTC_Results[] = LanguagePrediction::fromArray($datum, $bytes);
                }
            }

            if(isset($data["dltc"]))
            {
                $LanguagePredictionResultsObject->DLTC_Results = array();

                foreach($data["dltc"] as $datum)
                {
                    $LanguagePredictionResultsObject->DLTC_Results[] = LanguagePrediction::fromArray($datum, $bytes);
                }
            }

            if(isset($data["cld_results"]))
            {
                $LanguagePredictionResultsObject->CLD_Results = array();

                foreach($data["cld_results"] as $datum)
                {
                    $LanguagePredictionResultsObject->CLD_Results[] = LanguagePrediction::fromArray($datum, $bytes);
                }
            }

            if(isset($data["cld"]))
            {
                $LanguagePredictionResultsObject->CLD_Results = array();

                foreach($data["cld"] as $datum)
                {
                    $LanguagePredictionResultsObject->CLD_Results[] = LanguagePrediction::fromArray($datum, $bytes);
                }
            }

            if(isset($data["ld_results"]))
            {
                $LanguagePredictionResultsObject->LD_Results = array();

                foreach($data["ld_results"] as $datum)
                {
                    $LanguagePredictionResultsObject->LD_Results[] = LanguagePrediction::fromArray($datum, $bytes);
                }
            }

            if(isset($data["ld"]))
            {
                $LanguagePredictionResultsObject->LD_Results = array();

                foreach($data["ld"] as $datum)
                {
                    $LanguagePredictionResultsObject->LD_Results[] = LanguagePrediction::fromArray($datum, $bytes);
                }
            }

            return $LanguagePredictionResultsObject;
        }
    }