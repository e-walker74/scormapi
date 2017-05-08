<?php

namespace App\Model\Table;

use h4cc\Multipart\ParserSelector;
use Cake\Core\Exception\Exception;

class UploadHelper
{
   const ZIP_PART = 1;
   const TEXT_PART = 2;
   const UNKNOWN_PART = -1;
   private $files;
   private $jsonData;
   public function __construct($contentType,$data)
   {

     
     $this->parseContent($contentType,$data);

   }
   public function parseContent($contentType,$data)
   {
       $this->jsonData = null;
       $this->files = [];
       $content=str_replace('"','',$contentType);

       $parseSelector = new ParserSelector();
       $parser = $parseSelector->getParserForContentType($content);
       $multipart = $parser->parse($data);
       if (is_array($multipart)&&count($multipart)>0) {
          foreach ($multipart as $part) {

             if (isset($part['headers'])) {
                $type = $this->partType($part['headers']);
                
                switch ($type) {
                   case self::TEXT_PART: 
                      $this->setJsonData($part['body']);
                      break;
                   case self::ZIP_PART:
                      $this->uploadFile($part['body']);
                      break;   
                }
             }
             
          }
       } else {
         throw new Cake\Core\Exception\Exception('Cannot parse request data');
       }

   }
   protected function setJsonData($data)
   {
     
$this->jsonData = json_decode($data);
   }
   protected function uploadFile($data)
   {
      $tmpfname = tempnam(sys_get_temp_dir(), "scorm_");

      $handle = fopen($tmpfname, "wb");
      fwrite($handle, $data);
      fclose($handle);
      $this->files[] = $tmpfname;
   }
   public function __get($jsonField)
   {
     if (!empty($this->jsonData)&&isset($this->jsonData->$jsonField)) {
       return $this->jsonData->$jsonField;
    }
    return null;
  
   }
   private function partType($headers)
   {

     if (isset($headers['content-type'])) {
        foreach ($headers['content-type'] as $type){
           if (strpos($type,'application/zip')!==false) {
              return self::ZIP_PART;
           }
           if (strpos($type,'application/json')!==false) {
              return self::TEXT_PART;
           }
           if (strpos($type,'text')!==false) {
              return self::TEXT_PART;
           }
           if (strpos($type,'multipart/mixed')!==false) {
              return self::TEXT_PART;
           }      
        }
  
     }
     $isBinary = false;
     if (isset($headers['content-transfer-encoding'])) {
        foreach ($headers['content-type'] as $type){
           if (strpos($type,'binary')) {
              $isBinary = true;
              break;
           }     
        }
     }
     if ($isBinary){
        if (isset($headers['content-disposition'])) {
           foreach ($headers['content-disposition'] as $disposition){
              if (strpos('.zip',$disposition)!==false) {
                 return self::ZIP_PART;
              }     
           }
        }
     }
     
     return self::UNKNOWN_PART;
  }
  public function getFiles()
  {
     return $this->files;
  }     
}