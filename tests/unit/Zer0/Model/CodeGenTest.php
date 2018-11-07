<?php

namespace Zer0\Model;

use Zer0\TestCase;

/**
 * Class CodeGenTest
 * @package Zer0\Model
 */
class CodeGenTest extends TestCase
{

    /**
     *
     */
    public function setUp()
    {
        $this->markTestSkipped();
    }

    /**
     * @test
     * @covers \Zer0\Model\CodeGen::transformSqlToPhp()
     */
    public function testCodeGen()
    {
        parent::assertEquals(
            CodeGen::transformSqlToPhp(
                'SELECT * FROM User INNER JOIN User~online WHERE id IN (?)',
                ['addNamespaces' => false]
            ),
            "User::where('id IN (?)', [null])\n->innerJoin('User~online')",
            'SELECT ... INNER JOIN ... index ... WHERE'
        );

        parent::assertEquals(
            CodeGen::transformSqlToPhp(
            'SELECT Item.*, Category.name AS categoryName FROM Item '
            . 'LEFT JOIN Category ON Item.category = Category.id'
            . ' WHERE Item.id IN (?) AND Item.deleted = 0',
            ['addNamespaces' => true]
        ),
            "\\Model\\Item::where('Item.id IN (?) AND Item.deleted = 0', [null])\n"
            . "->fields(['Item.*'])\n"
            . "->leftJoin('Category', 'Item.category = Category.id', ['categoryName' => 'Category.name'])",
            'SELECT ... LEFT JOIN ... WHERE'
        );

        $query = 'SELECT a,b,c FROM User WHERE a = 1 AND b = 2';
        parent::assertEquals(
            "\\Model\\User::where('a = 1 AND b = 2')\n->fields(['a', 'b', 'c'])",
            CodeGen::transformSqlToPhp($query, ['addNamespaces' => true]),
            $query . ', addNamespaces = true'
        );

        $query = 'SELECT a,b,c FROM User WHERE a = 1 AND b = 2';
        parent::assertEquals(
            "User::where('a = 1 AND b = 2')\n->fields(['a', 'b', 'c'])",
            CodeGen::transformSqlToPhp($query, ['addNamespaces' => false]),
            $query . ', addNamespaces = false'
        );

        $query = 'SELECT * FROM User WHERE id = "123456"';
        parent::assertEquals(
            "User::where('id = :id', ['id' => '123456'])",
            CodeGen::transformSqlToPhp($query, ['addNamespaces' => false]),
            $query . ', addNamespaces = false'
        );
    }
}
