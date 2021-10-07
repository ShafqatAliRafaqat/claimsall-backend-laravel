<?php

namespace App\Http\Libraries;

use App\Http\Libraries\PaginationBuilder;
use Illuminate\Pagination\LengthAwarePaginator;

class ResponseBuilder {

    protected $statusCode,
        $message,
        $data,
        $objNotation,    
        $pagination,
        $authorization;

    public function __construct($statusCode = 200, $message = 'Ok', $data = [], $authorization = '') {
        $this->statusCode = $statusCode;
        $this->message = $message;
        $this->data = $data;
        $this->pagination = new \stdClass();
        $this->authorization = $authorization;
        $this->objNotation=true;
    }

    public function getStatusCode() {
        return $this->statusCode;
    }
    
    public function getObjNotation() {
        return $this->objNotation;
    }

    public function getMessage() {
        return $this->message;
    }

    public function getData() {
        if (empty($this->data)) {
            $this->data = ($this->objNotation===true) ? new \stdClass() : [];
        }
        return $this->data;
    }

    public function getPagination() {
        return $this->pagination;
    }

    public function getAuthorization() {
        return $this->authorization;
    }

    public function setStatusCode($statusCode) {
        $this->statusCode = $statusCode;
        if ($statusCode != 200) {
            $this->setMessage(__('Something went wrong'));
        }
        return $this;
    }

    public function setObjectNotation($objNotation) {
        $this->objNotation= $objNotation;
        return $this;
    }
    public function setMessage($message) {
        $this->message = $message;
        return $this;
    }

    public function setData($data) {
        $this->data = $data;
        return $this;
    }

    public function setPagination($pagination) {
        $this->pagination = $pagination;
        return $this;
    }

    public function setAuthorization($authorization) {
        $this->authorization = $authorization;
        return $this;
    }

    public function build() {
        $resData = $this->getData();
        if ($this->getData() instanceof LengthAwarePaginator) {
            //dd('sdsd');
            $dataWithPagination = PaginationBuilder::build($this->getData());
            $this->setPagination($dataWithPagination['pagination']);
            $this->setData($dataWithPagination['data']);
        } else if (!empty($this->getPagination()) > 0) {
            
//            if(is_array($resData) && !empty($resData['per_page']) && !empty($resData['current_page'])){
            if(is_array($resData) && !empty($resData['per_page'])){
                $dataWithPagination = PaginationBuilder::build($this->getData());

                $this->setPagination($dataWithPagination['pagination']);
                
                $this->setData($dataWithPagination['data']);

            }
            else
                $this->setPagination(PaginationBuilder::manipulatePaginationData([]));
        }
        $response = response([
            'status_code' => $this->getStatusCode(),
            'message' => $this->getMessage(),
            'data' => $this->getData(),
            'authorization' => $this->getAuthorization(),
            'pagination' => $this->getPagination()
        ], $this->getStatusCode());
        if (strlen($this->getAuthorization()) > 0) {
            $response->withHeaders([
                'Authorization' => $this->getAuthorization()
            ]);
        }
        return $response;
    }

    public function error($message = '', $statusCode = 422, $objNotation=true) {
        $this->setStatusCode($statusCode);
        if (strlen($message) > 0) {
            $this->setMessage($message);
        }
        return $this->setObjectNotation($objNotation)->build();
    }

    public function success($message, $data = [], $objNotation=true) {
        return $this->setMessage($message)->setData($data)->setObjectNotation($objNotation)->build();
    }

}
