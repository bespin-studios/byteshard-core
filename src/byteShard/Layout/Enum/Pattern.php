<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Layout\Enum;

/**
 * Class Pattern
 * @package byteShard\Enum\Layout
 */
enum Pattern: string
{
    /**
     * One Cell Layout<br>┌─┐<br>└─┘
     */
    case PATTERN_1C = '1C';
    /**
     * Two Cell Layout<br>┌─┐<br>├─┤<br>└─┘
     */
    case PATTERN_2E = '2E';
    /**
     * Two Cell Layout<br>┌─┬─┐<br>└─┴─┘
     */
    case PATTERN_2U = '2U';
    /**
     * Three Cell Layout<br>┌─┐<br>├─┤<br>├─┤<br>└─┘
     */
    case PATTERN_3E = '3E';
    /**
     * Three Cell Layout<br>┌─┬─┐<br>├─┤&nbsp;&nbsp;&nbsp;│<br>└─┴─┘
     */
    case PATTERN_3J = '3J';
    /**
     * Three Cell Layout<br>┌─┬─┐<br>│&nbsp;&nbsp;&nbsp;├─┤<br>└─┴─┘
     */
    case PATTERN_3L = '3L';
    /**
     * Three Cell Layout<br>┌───┐<br>├─┬─┤<br>└─┴─┘
     */
    case PATTERN_3T = '3T';
    /**
     * Three Cell Layout<br>┌─┬─┐<br>├─┴─┤<br>└───┘
     */
    case PATTERN_3U = '3U';
    /**
     * Three Cell Layout<br>┌─┬─┬─┐<br>└─┴─┴─┘
     */
    case PATTERN_3W = '3W';
    /**
     * Four Cell Layout<br>┌─┬─┬─┐<br>├─┤&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>└─┴─┴─┘
     */
    case PATTERN_4A = '4A';
    /**
     * Four Cell Layout<br>┌─┬─┐<br>│&nbsp;&nbsp;&nbsp;├─┤<br>│&nbsp;&nbsp;&nbsp;├─┤<br>└─┴─┘
     */
    case PATTERN_4C = '4C';
    /**
     * Four Cell Layout<br>┌─┐<br>├─┤<br>├─┤<br>├─┤<br>└─┘
     */
    case PATTERN_4E = '4E';
    /**
     * Four Cell Layout<br>┌─┬─┐<br>├─┴─┤<br>├───┤<br>└───┘
     */
    case PATTERN_4F = '4F';
    /**
     * Four Cell Layout<br>┌─┬─┐<br>├─┤&nbsp;&nbsp;&nbsp;│<br>├─┤&nbsp;&nbsp;&nbsp;│<br>└─┴─┘
     */
    case PATTERN_4G = '4G';
    /**
     * Four Cell Layout<br>┌─┬─┬─┐<br>│&nbsp;&nbsp;&nbsp;├─┤&nbsp;&nbsp;&nbsp;│<br>└─┴─┴─┘
     */
    case PATTERN_4H = '4H';
    /**
     * Four Cell Layout<br>┌───┐<br>├─┬─┤<br>├─┴─┤<br>└───┘
     */
    case PATTERN_4I = '4I';
    /**
     * Four Cell Layout<br>┌───┐<br>├───┤<br>├─┬─┤<br>└─┴─┘
     */
    case PATTERN_4J = '4J';
    /**
     * Four Cell Layout<br>┌─┬─┬─┐<br>│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;├─┤<br>└─┴─┴─┘
     */
    case PATTERN_4L = '4L';
    /**
     * Four Cell Layout<br>┌─────┐<br>├─┬─┬─┤<br>└─┴─┴─┘
     */
    case PATTERN_4T = '4T';
    /**
     * Four Cell Layout<br>┌─┬─┬─┐<br>├─┴─┴─┤<br>└─────┘
     */
    case PATTERN_4U = '4U';
    /**
     * Four Cell Layout<br>┌─┬─┬─┬─┐<br>└─┴─┴─┴─┘
     */
    case PATTERN_4W = '4W';
    /**
     * Five Cell Layout<br>┌─┬─┬─┐<br>│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;├─┤<br>│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;├─┤<br>└─┴─┴─┘
     */
    case PATTERN_5C = '5C';
    /**
     * Five Cell Layout<br>┌─┐<br>├─┤<br>├─┤<br>├─┤<br>├─┤<br>└─┘
     */
    case PATTERN_5E = '5E';
    /**
     * Five Cell Layout<br>┌─┬─┬─┐<br>├─┤&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>├─┤&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>└─┴─┴─┘
     */
    case PATTERN_5G = '5G';
    /**
     * Five Cell Layout<br>┌─┬─┬─┐<br>│&nbsp;&nbsp;&nbsp;├─┤&nbsp;&nbsp;&nbsp;│<br>│&nbsp;&nbsp;&nbsp;├─┤&nbsp;&nbsp;&nbsp;│<br>└─┴─┴─┘
     */
    case PATTERN_5H = '5H';
    /**
     * Five Cell Layout<br>┌─────┐<br>├─┬─┬─┤<br>├─┴─┴─┤<br>└─────┘
     */
    case PATTERN_5I = '5I';
    /**
     * Five Cell Layout<br>┌─┬─┐<br>├─┤&nbsp;&nbsp;&nbsp;│<br>│&nbsp;&nbsp;&nbsp;├─┤<br>├─┤&nbsp;&nbsp;&nbsp;│<br>└─┴─┘
     */
    case PATTERN_5K = '5K';
    /**
     * Five Cell Layout<br>┌─┬─┐<br>│&nbsp;&nbsp;&nbsp;├─┤<br>├─┤&nbsp;&nbsp;&nbsp;│<br>│&nbsp;&nbsp;&nbsp;├─┤<br>└─┴─┘
     */
    case PATTERN_5S = '5S';
    /**
     * Five Cell Layout<br>┌─┬─┬─┬─┐<br>├─┴─┴─┴─┤<br>└───────┘
     */
    case PATTERN_5U = '5U';
    /**
     * Five Cell Layout<br>┌─┬─┬─┬─┬─┐<br>└─┴─┴─┴─┴─┘
     */
    case PATTERN_5W = '5W';
    /**
     * Six Cell Layout<br>┌─┬─┐<br>├─┤&nbsp;&nbsp;&nbsp;│<br>├─┤&nbsp;&nbsp;&nbsp;│<br>├─┤&nbsp;&nbsp;&nbsp;│<br>├─┤&nbsp;&nbsp;&nbsp;│<br>└─┴─┘
     */
    case PATTERN_6A = '6A';
    /**
     * Six Cell Layout<br>┌─┬─┐<br>│&nbsp;&nbsp;&nbsp;├─┤<br>│&nbsp;&nbsp;&nbsp;├─┤<br>│&nbsp;&nbsp;&nbsp;├─┤<br>│&nbsp;&nbsp;&nbsp;├─┤<br>└─┴─┘
     */
    case PATTERN_6C = '6C';
    /**
     * Six Cell Layout<br>┌─┬─┬─┐<br>│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;├─┤<br>│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;├─┤<br>│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;├─┤<br>└─┴─┴─┘
     */
    case PATTERN_6E = '6E';
    /**
     * Six Cell Layout<br>┌─┬─┬─┐<br>│&nbsp;&nbsp;&nbsp;├─┤&nbsp;&nbsp;&nbsp;│<br>│&nbsp;&nbsp;&nbsp;├─┤&nbsp;&nbsp;&nbsp;│<br>│&nbsp;&nbsp;&nbsp;├─┤&nbsp;&nbsp;&nbsp;│<br>└─┴─┴─┘
     */
    case PATTERN_6H = '6H';
    /**
     * Six Cell Layout<br>┌───────┐<br>├─┬─┬─┬─┤<br>├─┴─┴─┴─┤<br>└───────┘
     */
    case PATTERN_6I = '6I';
    /**
     * Six Cell Layout<br>┌─┬─┬─┐<br>├─┤&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>├─┤&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>├─┤&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>└─┴─┴─┘
     */
    case PATTERN_6J = '6J';
    /**
     * Five Cell Layout<br>┌─┬─┬─┬─┬─┬─┐<br>└─┴─┴─┴─┴─┴─┘
     */
    case PATTERN_6W = '6W';
    /**
     * Seven Cell Layout<br>┌─┬─┬─┐<br>│&nbsp;&nbsp;&nbsp;├─┤&nbsp;&nbsp;&nbsp;│<br>│&nbsp;&nbsp;&nbsp;├─┤&nbsp;&nbsp;&nbsp;│<br>│&nbsp;&nbsp;&nbsp;├─┤&nbsp;&nbsp;&nbsp;│<br>│&nbsp;&nbsp;&nbsp;├─┤&nbsp;&nbsp;&nbsp;│<br>└─┴─┴─┘
     */
    case PATTERN_7H = '7H';
    /**
     * Seven Cell Layout<br>┌─────────┐<br>├─┬─┬─┬─┬─┤<br>├─┴─┴─┴─┴─┤<br>└─────────┘
     */
    case PATTERN_7I = '7I';

    public function numberOfCells(): int
    {
        return match ($this) {
            Pattern::PATTERN_1C                                                                                                                                                                                                                                        => 1,
            Pattern::PATTERN_2E, Pattern::PATTERN_2U                                                                                                                                                                                                                   => 2,
            Pattern::PATTERN_3E, Pattern::PATTERN_3J, Pattern::PATTERN_3L, Pattern::PATTERN_3T, Pattern::PATTERN_3U, Pattern::PATTERN_3W                                                                                                                               => 3,
            Pattern::PATTERN_4A, Pattern::PATTERN_4C, Pattern::PATTERN_4E, Pattern::PATTERN_4F, Pattern::PATTERN_4G, Pattern::PATTERN_4H, Pattern::PATTERN_4I, Pattern::PATTERN_4J, Pattern::PATTERN_4L, Pattern::PATTERN_4T, Pattern::PATTERN_4U, Pattern::PATTERN_4W => 4,
            Pattern::PATTERN_5C, Pattern::PATTERN_5E, Pattern::PATTERN_5G, Pattern::PATTERN_5H, Pattern::PATTERN_5I, Pattern::PATTERN_5K, Pattern::PATTERN_5S, Pattern::PATTERN_5U, Pattern::PATTERN_5W                                                                => 5,
            Pattern::PATTERN_6A, Pattern::PATTERN_6C, Pattern::PATTERN_6E, Pattern::PATTERN_6H, Pattern::PATTERN_6I, Pattern::PATTERN_6J, Pattern::PATTERN_6W                                                                                                          => 6,
            Pattern::PATTERN_7H, Pattern::PATTERN_7I                                                                                                                                                                                                                   => 7,
        };
    }
}

