<?php

namespace Core\Model;

use Model\User;
use Zer0\Model\Result\ResultList;
use Zer0\TestCase;

/**
 * Class IteratorTest
 * @package Core\Model
 */
class IteratorTest extends TestCase
{
    /**
     *
     */
    public function setUp()
    {
        $this->markTestSkipped();
    }

    /**
     * @return array
     */
    public function getEntitiesDataProvider()
    {
        return [
            // Empty entities in Iterator
            [
                [],
                0
            ],
            // 1 Entity
            [
                [
                    [
                        'id' => 1,
                        'username' => 'name',
                    ]
                ],
                1
            ],
            // 2 Entity
            [
                [
                    [
                        'id' => 1,
                        'username' => 'name',
                    ],
                    [
                        'id' => 2,
                        'username' => 'name',
                    ]
                ],
                2
            ],
            // 3 Entity
            [
                [
                    [
                        'id' => 1,
                        'username' => 'name',
                    ],
                    [
                        'id' => 2,
                        'username' => 'name',
                    ],
                    [
                        'id' => 3,
                        'username' => 'name',
                    ]
                ],
                3
            ]
        ];
    }

    /**
     * @dataProvider getEntitiesDataProvider
     *
     * @param array $elements
     * @param int $expectedCount
     *
     * @return \Zer0\Model\Iterator
     */
    public function testIteratorSuccess(array $elements, $expectedCount)
    {
        $iterator = new \Zer0\Model\Iterator(
            new \Model\User(),
            new ResultList($elements)
        );

        $resuls = [];

        foreach ($iterator as $key => $entity) {
            parent::assertInstanceOf(User::class, $entity);
            $resuls[$key] = $entity;
        }

        parent::assertSame($expectedCount, count($resuls));
        parent::assertCount($expectedCount, $iterator);

        /**
         * Assert that on 2nd iterate, it's not going to hydrate a new entity
         * and use old one from 1st iteration
         */
        foreach ($iterator as $key => $entity) {
            parent::assertSame($resuls[$key], $entity);
        }

        return $iterator;
    }

    /**
     * @dataProvider getEntitiesDataProvider
     *
     * @param array $elements
     * @param int $expectedCount
     */
    public function testIteratorModelToArray(array $elements, $expectedCount)
    {
        /**
         * Iterator will hydrate array to object
         * and after it we are going to hydrate from object to array
         */
        $iterator = $this->testIteratorSuccess($elements, $expectedCount);
        $iterator->asArray(true);

        $total = 0;

        foreach ($iterator as $entity) {
            parent::assertInternalType('array', $entity);
            $total++;
        }

        parent::assertSame($expectedCount, $total);
        parent::assertCount($expectedCount, $iterator);
    }

    /**
     * @dataProvider getEntitiesDataProvider
     *
     * @param array $elements
     * @param int $expectedCount
     *
     * @return \Zer0\Model\Iterator
     */
    public function testIteratorAsArraySuccess(array $elements, $expectedCount)
    {
        $iterator = new \Zer0\Model\Iterator(
            new \Model\User(),
            new ResultList($elements)
        );
        $iterator->asArray(true);

        $total = 0;

        foreach ($iterator as $entity) {
            parent::assertInternalType('array', $entity);
            $total++;
        }

        parent::assertSame($expectedCount, $total);
        parent::assertCount($expectedCount, $iterator);

        return $iterator;
    }

    /**
     * @dataProvider getEntitiesDataProvider
     *
     * @param array $elements
     * @param int $expectedCount
     */
    public function testIteratorArrayToModel(array $elements, $expectedCount)
    {
        $iterator = $this->testIteratorAsArraySuccess($elements, $expectedCount);
        $iterator->asArray(false);

        $total = 0;

        foreach ($iterator as $entity) {
            parent::assertInstanceOf(User::class, $entity);
            $total++;
        }

        parent::assertSame($expectedCount, $total);
        parent::assertCount($expectedCount, $iterator);
    }
}
