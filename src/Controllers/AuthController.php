<?php

namespace Ordent\RamenAuth\Controllers;

use Illuminate\Http\Request;
use Ordent\RamenRest\Controllers\RestController;
use Ordent\RamenAuth\Controllers\AuthControllerTrait;
use Ordent\RamenRest\Processor\RestProcessor;

class AuthController extends RestController
{
    use AuthControllerTrait;
    public function __construct(RestProcessor $processor)
    {
        $this->model = config('ramenauth.model');
        $this->uri = config('ramenauth.uri');
        $class = new \ReflectionClass($this->model);
        if (!$class->isAbstract()) {
            $this->model = new $this->model();
        }
        parent::__construct($processor, $this->model);
    }
}
