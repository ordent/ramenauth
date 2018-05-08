<?php

namespace Ordent\RamenRest\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Ordent\RamenRest\Processor\RestProcessor;
use Ordent\RamenRest\Requests\RestRequestFactory;
use Ordent\RamenRest\Requests\RestRequest;
use Ordent\RamenRest\Response\RestResponse;
use Illuminate\Validation\ValidationException;
use ReflectionClass;

trait RestControllerTrait
{
    protected function setModel($model){
        if (!$this->model instanceof Model && is_string($this->model)) {
            $class = new ReflectionClass($this->model);
            if (!$class->isAbstract()) {
                $this->model = new $this->model();
                if (!is_null($this->model)) {
                    $this->processor->setModel($this->model);
                }
            }
        }

        if($model instanceof Model){
            $this->processor->setModel($this->model);            
        }
    }

    public function getCollection(Request $request)
    {
        // return collection
        return response()->successResponse($this->processor->getCollectionStandard($request));
    }

    public function getItem(Request $request, $id)
    {
        // return first id it found or not found http exception as a json
        return response()->successResponse($this->processor->getItemStandard($request, $id));
    }

    public function postItem(Request $request, $validate)
    {
        // validate the request first, rules fetched from model get rules method
        if($validate){
            try {
                $request = RestRequestFactory::createRequest($this->model, "store");
            } catch (ValidationException $e) {
                return response()->exceptionResponse($e);
            }
        }
        // return newly created item
        return response()->createdResponse($this->processor->postItemStandard($request));
    }
    public function putItem($id, Request $request, $validate)
    {
        if($validate){
            try {
                $request = RestRequestFactory::createRequest($this->model, "update");
            } catch (ValidationException $e) {
                return response()->exceptionResponse($e);
            }
        }

        return response()->successResponse($this->processor->putItemStandard($id, $request));
    }

    public function deleteItem($id, Request $request)
    {
        return response()->noContentResponse($this->processor->deleteItemStandard($id, $request));
    }
    
}
