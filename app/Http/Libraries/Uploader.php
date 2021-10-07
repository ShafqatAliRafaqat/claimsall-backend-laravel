<?php

namespace App\Http\Libraries;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use File;

class Uploader {

    protected $maxFileSize, $inputName, $file, $fileType, $valid, $filePath, $uploaded, $message, $fileextension, $mimeType;
    public $fileName;

    function __construct($inputName = '', $maxFileSize = 26214400) {
        $this->maxFileSize = $maxFileSize; // 25 MB default size
        $this->inputName = $inputName;
        $this->valid = FALSE;
        $this->uploaded = FALSE;
        $this->message = '';
    }

    public function setInputName($inputName) {
        $this->inputName = $inputName;
        return $this;
    }

    public function setFile($file) {
        $this->file = $file;
    }

    public function isValidFile() {
        if (Input::hasFile($this->inputName) || $this->file) {
            $this->file = (!$this->file) ? Input::file($this->inputName) : $this->file;
            if ($this->file->isValid()) {
                if ($this->file->getSize() <= $this->maxFileSize) {
                    $fileName = explode('.', $this->file->getClientOriginalName());
                    $fileNameChange = str_replace(' ', '_', $fileName[0]);
                    $this->fileName = $fileNameChange;
                    $this->valid = TRUE;
                    $this->fileextension = $this->file->getClientMimeType();
                } else {
                    $this->message = 'File size too large';
                }
            } else {
                $this->message = $this->file->getErrorMessage();
            }
        }
        return $this->valid;
    }

    public function hasValidDimentions($minWidth, $minHeight) {
        if ($this->valid) {
            $validator = Validator::make([$this->inputName => 'image'], [$this->inputName => $this->file]);
            if ($validator->passes()) {
                $this->fileType = 'image';
                $imageData = getimagesize($this->file->getRealPath());
                if ($imageData[0] >= $minWidth && $imageData[1] >= $minHeight) {
                    return TRUE;
                } else {
                    $this->message = 'Image must be at least ' . $minWidth . 'x' . $minHeight;
                }
            } else {
                $this->message = $validator->messages()->first();
            }
        }
        return false;
    }

    public function upload($uploadPath, $fileName = 'file', $temp = false, $thumb = []) {
        if ($this->valid) {
            $appPrefix = strtolower(env('APP_NAME'));
            $fileName = (strlen($fileName)>=40)? substr($fileName, 20, 30) : $fileName;
            $fileName = "{$appPrefix}-{$fileName}_" . rand(99, 9999) . time() . '.' . $this->file->getClientOriginalExtension();
            $this->filePath = $uploadPath . '/'; // 'uploads/'.(($temp) ? 'temp':$uploadPath).'/';
            $this->file->move($this->filePath, $fileName);
            if (!empty($thumb)) {
                list($width, $height) = $thumb;
//                $img = \Intervention\Image\Facades\Image::make($temp.'/'.$fileName)->fit($width,$height);
                $img = \Intervention\Image\Facades\Image::make($this->filePath . $fileName)->fit($width, $height);
                $img->save($this->filePath . 's_' . $fileName);
            }
            $this->filePath = $this->filePath . $fileName;
            $this->mimeType = \File::mimeType(env('BASE_UPLOAD_PATH', '') . $this->filePath);
            $this->file = NULL;
            $this->valid = FALSE;
            $this->uploaded = TRUE;
        } else {
            $this->uploaded = FALSE;
        }
    }

    public function compress() {
        try{
            \Spatie\LaravelImageOptimizer\Facades\ImageOptimizer::setTimeout(10)->optimize($this->filePath);
        } catch (\Exception $e){
            logger('compression error: ========> '. $e->getMessage());
        }
         return true;
    }
    public function compression($fileName, $uploadPath) {
        $this->filePath = $uploadPath . DIRECTORY_SEPARATOR;
        $pathToImage = $this->filePath.$fileName;
        try{
            \Spatie\LaravelImageOptimizer\Facades\ImageOptimizer::setTimeout(10)->optimize($pathToImage);
        } catch (\Exception $e){
            logger('compression error: ========> '. $e->getMessage());
        }
        return true;
    }

    public function getUploadedPath($fullpath = true) {
        if ($fullpath !== true) {
            $temp = explode('/', $this->filePath);
            return end($temp);
        }
        return $this->filePath;
    }

    public function isUploaded() {
        return $this->uploaded;
    }

    public function getFileExtension() {

        return $this->fileextension;
    }

    public function getMessage() {
        return $this->message;
    }

    public function getMaxFileSize() {
        return $this->maxFileSize;
    }

    public function getMimeType() {
        return $this->mimeType;
    }

}
