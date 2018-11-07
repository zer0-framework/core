<?php

namespace Tests\Zer0\Model\Expressions\Conditions;

use Zer0\TestCase;

/**
 * Class GenericTest
 * @package Tests\Zer0\Model\Expressions\Conditions
 */
class GenericTest extends TestCase
{
    /**
     * @return array
     */
    public function getProviderForMongoNotation()
    {
        return [
            [
                'one = ?',
                [1],
                ['one' => 1],
            ],
            [
                'one = ?',
                [25],
                ['one' => 25],
            ],
            [
                'one = ?',
                ['test'],
                ['one' => 'test'],
            ],
            // Bigger
            [
                'one > ?',
                [1],
                ['one' => ['$gt' => 1]],
            ],
            [
                'one > ?',
                [25],
                ['one' => ['$gt' => 25]],
            ],
            [
                'one > ?',
                [-1],
                ['one' => ['$gt' => -1]],
            ],
            // Bigger or Equals
            [
                'one >= ?',
                [1],
                ['one' => ['$gte' => 1]],
            ],
            [
                'one >= ?',
                [25],
                ['one' => ['$gte' => 25]],
            ],
            [
                'one >= ?',
                [-1],
                ['one' => ['$gte' => -1]],
            ],
            // Lower
            [
                'one < ?',
                [1],
                ['one' => ['$lt' => 1]],
            ],
            [
                'one < ?',
                [25],
                ['one' => ['$lt' => 25]],
            ],
            [
                'one < ?',
                [-1],
                ['one' => ['$lt' => -1]],
            ],
            // Lower or Equals
            [
                'one <= ?',
                [1],
                ['one' => ['$lte' => 1]],
            ],
            [
                'one <= ?',
                [25],
                ['one' => ['$lte' => 25]],
            ],
            [
                'one <= ?',
                [-1],
                ['one' => ['$lte' => -1]],
            ],
            // Not Equals
            [
                'one != ?',
                [1],
                ['one' => ['$ne' => 1]],
            ],
            [
                'one != ?',
                [25],
                ['one' => ['$ne' => 25]],
            ],
            [
                'one != ?',
                [-1],
                ['one' => ['$ne' => -1]],
            ],
            // IN
            [
                'one IN (?)',
                [1],
                ['one' => ['$in' => [1]]]
            ],
            [
                'one IN (?)',
                [[1, 2, 3, 4, 5]],
                ['one' => ['$in' => [1, 2, 3, 4, 5]]]
            ],
            [
                'one = ? AND two = ?',
                [1, 2],
                [
                    'one' => 1,
                    'two' => 2
                ]
            ],
            [
                'one = ? OR two = ?',
                [1, 2],
                [
                    '$or' => [
                        [
                            'one' => 1
                        ],
                        [
                            'two' => 2
                        ]
                    ]
                ]
            ],
            [
                '(one = ? OR two = ?) AND (three = ? OR four = ?)',
                [1, 2, 3, 4],
                [
                    '$and' => [
                        [
                            '$or' => [
                                ['one' => 1],
                                ['two' => 2],
                            ]
                        ],
                        [
                            '$or' => [
                                ['three' => 3],
                                ['four' => 4],
                            ]
                        ],
                    ]
                ]
            ]
        ];
    }

    /**
     * @dataProvider getProviderForMongoNotation
     *
     * @param $condition
     * @param array $data
     * @param array $expectedNotation
     */
    public function testSimpleMongoNotation($condition, array $data, array $expectedNotation)
    {
        $condition = new \Zer0\Model\Expressions\Conditions\Generic($condition, $data, null);
        parent::assertSame($expectedNotation, $condition->mongoNotation);
    }

    /**
     * @return array
     */
    public function getProviderForCheckCondition()
    {
        return [
            // Equals
            [
                'one = 1',
                [
                    'one' => 1
                ],
                true
            ],
            [
                'one = 1',
                [
                    'one' => 2
                ],
                false
            ],
            // Not Equals
            [
                'one != 1',
                [
                    'one' => 2
                ],
                true
            ],
            [
                'one != 0',
                [
                    'one' => 1
                ],
                true
            ],
            [
                'one != 1',
                [
                    'one' => 1
                ],
                false
            ],
            // Greater
            [
                'one > 25',
                [
                    'one' => 26
                ],
                true
            ],
            [
                'one > 25',
                [
                    'one' => 25
                ],
                false
            ],
            [
                'one > 25',
                [
                    'one' => 2
                ],
                false
            ],
            // Greater or equals
            [
                'one >= 25',
                [
                    'one' => 26
                ],
                true
            ],
            [
                'one >= 25',
                [
                    'one' => 25
                ],
                true
            ],
            [
                'one >= 25',
                [
                    'one' => 2
                ],
                false
            ],
            // Lower
            [
                'one < 25',
                [
                    'one' => 24
                ],
                true
            ],
            [
                'one < 25',
                [
                    'one' => 25
                ],
                false
            ],
            [
                'one < 25',
                [
                    'one' => 26
                ],
                false
            ],
            // Lower or equals
            [
                'one <= 25',
                [
                    'one' => 24
                ],
                true
            ],
            [
                'one <= 25',
                [
                    'one' => 25
                ],
                true
            ],
            [
                'one <= 25',
                [
                    'one' => 26
                ],
                false
            ],
            // IN
            [
                'one IN (2, 4, 8)',
                [
                    'one' => 2
                ],
                true
            ],
            [
                'one IN (2, 4, 8)',
                [
                    'one' => 4
                ],
                true
            ],
            [
                'one IN (2, 4, 8)',
                [
                    'one' => 8
                ],
                true
            ],
            [
                'one IN (2, 4, 8)',
                [
                    'one' => 0
                ],
                false
            ],
            [
                'one IN (2, 4, 8)',
                [
                    'one' => null
                ],
                false
            ],
            // OR
            [
                'one != 1 OR two != 2',
                [
                    'one' => 1,
                    'two' => 2,
                ],
                false
            ],
            [
                'one != 1 OR two != 2',
                [
                    'one' => 1,
                    'two' => 3,
                ],
                true
            ],
            [
                'one != 1 OR two != 2',
                [
                    'one' => 3,
                    'two' => 2,
                ],
                true
            ],
            // And
            /*[
                'one != 1 AND one != 2',
                [
                    'one' => 1
                ],
                false
            ],
            [
                'one != 1 AND one != 2',
                [
                    'one' => 2
                ],
                false
            ],
            [
                'one != 2 AND one != 1',
                [
                    'one' => 2
                ],
                false
            ],
            [
                'one != 1 AND one != 2',
                [
                    'one' => 3
                ],
                true
            ],*/
        ];
    }

    /**
     * @dataProvider getProviderForCheckCondition
     *
     * @param string $where
     * @param array $data
     * @param bool $expected
     */
    public function testCheckCondition($where, array $data, $expected)
    {
        $condition = new \Zer0\Model\Expressions\Conditions\Generic($where, null, null);
        parent::assertEquals($expected, $condition->checkCondition($data));
    }
}
