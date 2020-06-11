<?php


    namespace CoffeeHouse\Classes;

    /**
     * Class StringDistance
     * @package CoffeeHouse\Classes
     */
    class StringDistance
    {

        /**
         * @param string $string_1
         * @param string $string_2
         * @param bool $linear_usage
         * @return mixed
         */
        public static function levenshtein(string $string_1, string $string_2, $linear_usage = false)
        {
            if($linear_usage == true)
            {
                $l1 = strlen($string_1);
                $l2 = strlen($string_2);
                $dis = range(0,$l2);
                for($x=1;$x<=$l1;$x++)
                {
                    $dis_new[0]=$x;
                    for($y=1;$y<=$l2;$y++)
                    {
                        $c = ($string_1[$x-1] == $string_2[$y-1])?0:1;
                        $dis_new[$y] = min($dis[$y]+1,$dis_new[$y-1]+1,$dis[$y-1]+$c);
                    }
                    $dis = $dis_new;
                }

                return $dis[$l2];
            }

            $m = strlen($string_1);
            $n = strlen($string_2);
            $d = [];

            for($i=0;$i<=$m;$i++) $d[$i][0] = $i;
            for($j=0;$j<=$n;$j++) $d[0][$j] = $j;

            for($i=1;$i<=$m;$i++)
            {
                for($j=1;$j<=$n;$j++)
                {
                    $c = ($string_1[$i-1] == $string_2[$j-1])?0:1;
                    $d[$i][$j] = min($d[$i-1][$j]+1,$d[$i][$j-1]+1,$d[$i-1][$j-1]+$c);
                }
            }
            return $d[$m][$n];
        }
    }