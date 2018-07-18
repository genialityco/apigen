<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Storage;
use App\evaLib\Services\GoogleFiles;

/**
 * @resource Files
 *
 * Files handing mostly used to upload new files
 */
class FilesController extends Controller
{


/**
 * Uploads files send though HTTP multipart/form-data
 * 
 * Uploads provided file though HTTPFile  multipart/form-data; and returns full file URL.
 * default field_name(key) for the file is file but it could be changed using 
 * additional parameter field_name to reference file using another field_name
 * HTTPFile could be just one file on multiple files, 
 * for one file this function returns  a string with the url
 * for multiple files It returns an array of URLS.
 * 
 * Request example
 * POST /eviusapilaravel/public/api/files/upload/image HTTP/1.1
 * Host: localhost
 * Cache-Control: no-cache
 * Postman-Token: 2f16a68e-f8fd-4b1b-a0d6-635c5ba7e981
 * Content-Type: multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW
 * 
 *
 * @param Request $request
 * @param string $field_name 
 * @param GoogleFiles $gfService 
 * @return string of file uploaded url  or  array of urls for multiple files
 */
    public function upload(Request $request,string $field_name = null, GoogleFiles $gfService)
    {   //@debug post $entityBody = file_get_contents('php://input');
        $imgurls = [];
        
        //valor por defecto de campo que contiene el archivo
        $field_name = ($field_name)?$field_name:"file";
        
        //No viene ningun archivo
        if (!$request->hasFile($field_name)){
            $statusCode = "400";
            $message = "No file found in field '".$field_name."' to be uploded";
            return response()->json(['error' => $message], $statusCode);
        }
        $files = $request->file($field_name);

        //convertimos un solo elemento a array
        $files = is_array($files)?$files:[$files];

        foreach ($files as $file) {
            $imgurls[] = $gfService->storeFile($file);
        }
        
        //devolvemos una cadena o un arreglo segun sea el caso
        return (count($imgurls)>1)?$imgurls:reset($imgurls);
        
    }    
}