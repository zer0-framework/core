<?php

namespace Zer0\HTTP\Responses;

use Zer0\HTTP\HTTP;

/**
 * Class JSON
 * @package Zer0\HTTP\Responses
 */
class JSON extends Base
{
    /**
     * @var mixed
     */
    protected $scope;

    /**
     * JSON constructor.
     * @param $scope
     */
    public function __construct($scope)
    {
        $this->scope = $scope;
    }

    /**
     * Base constructor.
     * @param HTTP $http
     */
    public function render(HTTP $http)
    {
        $http->header('Content-Type: application/json');
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($http->config->pretty_json) {
            $flags |= JSON_PRETTY_PRINT;
        }
        echo json_encode($this->scope, $flags);
    }
}
