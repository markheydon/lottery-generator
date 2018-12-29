<?php
/**
 * Helper class to generate numbers for the Lotto game.
 */

namespace MarkHeydon\LotteryGenerator;

/**
 * Helper class to generate numbers for the Lotto game.
 *
 * @package MarkHeydon\LotteryGenerator
 * @since 1.0.0
 */
class LottoGenerate
{
    /**
     * Generate 'random' Lotto numbers.
     *
     * @since 1.0.0
     *
     * @return array Array of lines containing generated numbers.
     */
    public static function generate(): array
    {
        // @todo: Download results periodically -- only updated weekly I think?
        // Currently using a lotto-draw-history.csv file but should download and/or utilize a database.
        $allDraws = LottoDownload::readLottoDrawHistory();

        // Build some generated lines of 'random' numbers and return
        $linesMethod1 = self::generateMostFrequentTogether($allDraws);
        $linesMethod2 = self::generateMostFrequent($allDraws);
        $linesMethod3 = self::generateFullIteration($allDraws);

        $lines = [
            'method1' => $linesMethod1,
            'method2' => $linesMethod2,
            'method3' => $linesMethod3,
        ];
        return $lines;
    }

    /**
     * Filter the specified draws array by the specified ball number (value).
     *
     * @since 1.0.0
     *
     * @param array $draws Array of draws.
     * @param int $ball Ball value number to filter by.
     * @return array Filtered array of draws.
     */
    private static function filterDrawsByBall(array $draws, int $ball): array
    {
        $filteredDraws = array_filter($draws, function ($draw) use ($ball) {
            $result = $draw['ball1'] == $ball || $draw['ball2'] == $ball || $draw['ball3'] == $ball ||
                $draw['ball4'] == $ball || $draw['ball5'] == $ball || $draw['ball6'] == $ball ||
                $draw['bonusBall'] == $ball;
            return $result;
        });
        return $filteredDraws;
    }

    /**
     * Filter the specified array by the specified machine name.
     *
     * @since 1.0.0
     *
     * @param array $draws Array of draws.
     * @param string $machine Machine name to filter by.
     * @return array Filtered array of draws.
     */
    private static function filterDrawsByMachine(array $draws, string $machine): array
    {
        $filteredDraws = array_filter($draws, function ($draw) use ($machine) {
            return $draw['machine'] === $machine;
        });
        return $filteredDraws;
    }

    /**
     * Filter the specified array by the specified ball set.
     *
     * @since 1.0.0
     *
     * @param array $draws Array of draws.
     * @param string $ballSet Ball set to filter by.
     * @return array Filtered array of draws.
     */
    private static function filterDrawsByBallSet(array $draws, string $ballSet): array
    {
        $filteredDraws = array_filter($draws, function ($draw) use ($ballSet) {
            return $draw['ballSet'] === $ballSet;
        });
        return $filteredDraws;
    }

    /**
     * Returns a list of machine names sorted by most frequent first.
     *
     * @since 1.0.0
     *
     * @param array $draws Array of draws.
     * @return array Array of machine names with most frequent first.
     */
    private static function getMachineNames(array $draws): array
    {
        $machineCount = self::getCount($draws, 'machine');
        arsort($machineCount);
        reset($machineCount);
        return array_keys($machineCount);
    }

    /**
     * Returns a list of ball sets sorted by most frequent first.
     *
     * @since 1.0.0
     *
     * @param array $draws Array of draws.
     * @return array Array of ball sets with most frequent first.
     */
    private static function getBallSets(array $draws): array
    {
        $ballSetCount = self::getCount($draws, 'ballSet');
        arsort($ballSetCount);
        reset($ballSetCount);
        return array_keys($ballSetCount);
    }

    /**
     * Returns an array of counters for the specified array element in the supplied draws array.
     *
     * @since 1.0.0
     *
     * @param array $draws Array of draws.
     * @param string $element Element name within the draws array.
     * @return array Array of elements with count of their occurrence in the draws array.
     */
    private static function getCount(array $draws, string $element): array
    {
        $count = [];
        foreach ($draws as $draw) {
            $machine = $draw[$element];
            if (!isset($count[$machine])) {
                $count[$machine] = 1;
            } else {
                $count[$machine]++;
            }
        }
        return $count;
    }

    /**
     * Returns array of balls that frequently occur for the specified draws array.
     *
     * @param array $draws The draws array to use.
     * @param bool $together Balls that occur together?
     * @return array Array of balls.
     */
    private static function getFrequentlyOccurringBalls(array $draws, bool $together): array
    {
        // Want 6 numbers in total
        $results = [];
        $freqBall = self::calculateFrequentBall($draws);
        $results[] = $freqBall;
        for ($n = 1; $n < 6; $n++) {
            if ($together) {
                $draws = self::filterDrawsByBall($draws, $freqBall);
            }
            $freqBall = self::calculateFrequentBall($draws, $results);
            $results[] = $freqBall;
        }

        // Sort the results and return
        asort($results);
        return $results;
    }

    /**
     * Calculate the most frequently occurring ball value from the specified draws array.
     *
     * Looks through all the draws and counts the number of times a ball value occurs
     * and return the highest count value.  Optionally excludes the specified ball values.
     *
     * @since 1.0.0
     *
     * @param array $draws The draws array.
     * @param array $except Optional array of ball values to ignore from the count.
     * @return int Ball value of the most frequently occurring or 0 if draws array is empty.
     */
    private static function calculateFrequentBall(array $draws, array $except = []): int
    {
        $ballCount = [];
        foreach ($draws as $draw) {
            for ($b = 1; $b <= 7; $b++) {
                if ($b === 7) {
                    $ballNumber = 'bonusBall';
                } else {
                    $ballNumber = 'ball' . $b;
                }
                $ballValue = $draw[$ballNumber];
                if (!in_array($ballValue, $except)) {
                    if (!isset($ballCount[$ballValue])) {
                        $ballCount[$ballValue] = 1;
                    } else {
                        $ballCount[$ballValue]++;
                    }
                }
            }
        }
        arsort($ballCount);
        reset($ballCount);
        return (int)key($ballCount) ?? 0;
    }

    /**
     * Generate lotto lines by iterating through most frequent machine, ball set and balls within that set.
     *
     * Will run through however many history draws there are available and generate as many lines as possible
     * depending on the site of the data.
     *
     * @since 1.0.0
     *
     * @param array $draws The draws array to use.
     * @return array Array of lines generated.
     */
    private static function generateFullIteration(array $draws): array
    {
        $lines = [];
        $machines = self::getMachineNames($draws);
        foreach ($machines as $machine) {
            // Loop through ball sets (for single machine).
            $machineDraws = self::filterDrawsByMachine($draws, $machine);
            $ballSets = self::getBallSets($machineDraws);
            foreach ($ballSets as $ballSet) {
                $filteredDraws = self::filterDrawsByBallSet($machineDraws, $ballSet);
                $lines[] = self::getFrequentlyOccurringBalls($filteredDraws, true);
            }
        }

        return $lines;
    }

    /**
     * Generate a lotto line by finding balls that occurs most frequently across all data together.
     *
     * I.e. looks for numbers that occur within the same lines together, not across the whole data set.
     *
     * @since 1.0.0
     *
     * @param array $draws The draws array to use.
     * @return array Array of lines generated.
     */
    private static function generateMostFrequentTogether(array $draws): array
    {
        // return as array to keep consistence with other generate method(s)
        $lines = [];
        $lines[] = self::getFrequentlyOccurringBalls($draws, true);
        return $lines;
    }

    /**
     * Generate a lotto line by finding balls that occurs most frequently across all data.
     *
     * @since 1.0.0
     *
     * @param array $draws The draws array to use.
     * @return array Array of lines generated.
     */
    private static function generateMostFrequent(array $draws): array
    {
        // return as array to keep consistence with other generate method(s)
        $lines = [];
        $lines[] = self::getFrequentlyOccurringBalls($draws, false);
        return $lines;
    }
}