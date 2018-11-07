<?php

namespace Zer0\HTTP\Responses;

use Zer0\HTTP\HTTP;

/**
 * Class Base
 * @package Zer0\HTTP\Responses
 */
abstract class Base
{
    /**
     * @var mixed
     */
    protected $scope;

    /**
     * @param HTTP $http
     * @return mixed
     */
    abstract public function render(HTTP $http);

    /**
     * @param $scope
     */
    public function setScope($scope)
    {
        $this->scope = $scope;
    }

    /**
     * @return mixed
     */
    public function getScope()
    {
        return $this->scope;
    }
}
