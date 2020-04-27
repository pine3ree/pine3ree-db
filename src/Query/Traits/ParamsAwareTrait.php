<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Query\Traits;

/**
 * Trait WhereAwareTrait
 */
trait ParamsAwareTrait
{
    private $params = [];
    private $params_types = [];

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParamsTypes(): array
    {
        return $this->params_types;
    }
}
