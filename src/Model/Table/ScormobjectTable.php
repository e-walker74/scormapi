<?php
namespace App\Model\Table;

use App\Model\Entity\Scormobject;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Core\Configure;
use Cake\Utility\Security;
use Cake\ORM\TableRegistry;
/**
 * Scormobject Model
 *
 */
class ScormobjectTable extends Table
{

    const SCORM12=1;
    const SCORM2004=2;
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table('scormobject');
        $this->displayField('id');
        $this->primaryKey('id');

    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->add('id', 'valid', ['rule' => 'numeric'])
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('package_name', 'create')
            ->notEmpty('package_name');

        $validator
            ->add('package_type', 'valid', ['rule' => 'numeric'])
            ->requirePresence('package_type', 'create')
            ->notEmpty('package_type');

        $validator
            ->requirePresence('course_struct', 'create')
            ->notEmpty('course_struct');

        $validator
            ->requirePresence('location', 'create')
            ->notEmpty('location');

        return $validator;
    }

    /**
     * @param \SimpleXMLElement $manifestFile
     */
    protected function getSCORMType($manifest)
    {
        $version = (string)$manifest->metadata->schemaversion;
        if (strlen($version)==0) {
            $version = (string)$manifest->organizations->organization->metadata->schemaversion;
        }
        if (strlen($version)==0) {
            $version = (string)$manifest->attributes()->version;
        }

        if (strpos($version,'2004')!==false) return self::SCORM2004;
        if ((strpos($version,'1.2')!==false)||(strpos($version,'1.1')!==false)||(strpos($version,'1.0')!==false)) return self::SCORM12;
        return null;

    }
    /*protected static function parseSCORM2004($manifest)
    {
        $object = new \stdClass();
        $object->title= (string)$manifest->organizations->organization->title;
        $object->items = [];

        $resources = [];
        foreach ($manifest->resources->resource as $item)
        {
            $resoureId= (string)$item->attributes()->identifier;
            $href= (string)$item->attributes()->href;
            $resources[$resoureId] = $href;

        }


        foreach ($manifest->organizations->organization->item as $item)
        {
            $itemData = new \stdClass();
            $itemData->title = (string)$item->title;
            $resourceId = (string)$item->attributes()->identifierref;
            if (isset($resources[$resourceId]))
            {
                $itemData->href =  $resources[$resourceId];
            }

            if (isset($item->item))
            {
                $itemData = self::parseChilds($itemData,$item,$resources);
            }
            $object->items[] = $itemData;

        }


        return json_encode($object);
    }*/

    protected function getItemObjectives($objectives)
    {
        $objArray= [];
        if (isset($objectives->primaryObjective))
        {
            $objective = new \stdClass();
            $objective->id = (string)$objectives->primaryObjective->attributes()->objectiveID;
            $objArray[] = $objective;
        }
        if (isset($objectives->objective))
        {
            foreach ($objectives->objective as $obj)
            {

                $objective = new \stdClass();
                $objective->id = (string)$obj->attributes()->objectiveID;

                $objArray[] = $objective;
            }
        }
        return $objArray;

    }
    protected function parseChilds($parent,$xmlNode,$resources)
    {
        $parent->items=[];
        foreach ($xmlNode->item as $item)
        {
            $itemData = new \stdClass();
            $itemData->title = (string)$item->title;
            $itemData->resource = (string)$item->attributes()->identifierref;
            $itemData->parameters = (string)$item->attributes()->parameters;
            $itemData->identifier= (string)$item->attributes()->identifier;
            $resourceId = (string)$item->attributes()->identifierref;
            if (isset($resources[$resourceId]))
            {
                $itemData->href =  $resources[$resourceId];
            }
            if (isset($item->item))
            {
                $itemData = $this->parseChilds($itemData,$item,$resources);
            }
            $parent->items[] = $itemData;
        }
        return $parent;
    }
    protected function parseSCORM12($manifest)
    {
        $object = new \stdClass();
        $object->title= (string)$manifest->organizations->organization->title;

        $object->items = [];

        $resources = [];
        foreach ($manifest->resources->resource as $item)
        {
            $resoureId= (string)$item->attributes()->identifier;
            $href= (string)$item->attributes()->href;
            $resources[$resoureId] = $href;

        }


        foreach ($manifest->organizations->organization->item as $item)
        {
            $itemData = new \stdClass();
            $itemData->title = (string)$item->title;
            $itemData->identifier= (string)$item->attributes()->identifier;
            $itemData->parameters = (string)$item->attributes()->parameters;
            $resourceId = (string)$item->attributes()->identifierref;

            $namespaces = $item->getNameSpaces(true);
            //Now we don't have the URL hard-coded
            $imsss = $item->children($namespaces['imsss']);
            if (!empty($imsss))
            {
                if (isset($imsss->sequencing->objectives))
                $itemData->objectives = $this->getItemObjectives($imsss->sequencing->objectives);

            }


            if (isset($resources[$resourceId]))
            {
                $itemData->href =  $resources[$resourceId];
            }

            if (isset($item->item))
            {
                $itemData = $this->parseChilds($itemData,$item,$resources);
            }
            $object->items[] = $itemData;

        }


        return $object;


    }
    public function createSCORMPackage($fileName,$companyId)
    {
       $file = ['name'=>$fileName,'tmp_name'=>$fileName];
       return $this->handlePackageUpload($file,$companyId);
    }
    public function handlePackageUpload($file,$companyId='')
    {
        $location = Configure::read('PackageLocation');
        srand(time());
        $salt = rand();
        $packageDir = Security::hash($file['name'].$salt,'md5');
        $localPath = $location['local'].'/'.$packageDir;
        $folder = new Folder($localPath,true,511);
        $zip = new \ZipArchive();
        $res = $zip->open($file['tmp_name']);
        if ($res === TRUE)
        {
            $zip->extractTo($localPath);
            $zip->close();

            $manifest = new File($localPath.'/imsmanifest.xml');
            if (!$manifest->exists())
            {
                $folder->delete();
            }
            else
            {

                $content = $manifest->read();
                $xmlManifest = new \SimpleXMLElement($content);
                $type = $this->getSCORMType($xmlManifest);
                $data = $this->parseSCORM12($xmlManifest);

                $entity = ['package_name'=>$data->title,'package_type'=>$type,'course_struct'=>json_encode($data),'location'=>$packageDir];
                if (!empty($companyId)){
                   $entity['company_id'] = $companyId;
                }
                $rec = $this->newEntity($entity);
                $res = $this->save($rec);
               /* switch ($type)
                {
                    case self::SCORM12:
                        $data=self::parseSCORM12($xmlManifest);
                        break;
                    case self::SCORM2004:
                        $data = self::parseSCORM2004($xmlManifest);
                        break;
                }*/
            }


        }
        unlink($file['tmp_name']);
        return $res->id;


    }

    /**
     * @param $userId
     * @param $page
     * @param $limit
     * @param $orderBy
     * @return \stdClass
     */
    public function getPackagesList($userId, $current, $limit, $orderBy)
    {
        $data= new \stdClass();
        $data->current = $current;


        $data->rows=[];

        $records = $this->find('all',[
            'limit'=>$limit,
            'offset'=>$current-1,
            'fields'=>['id','package_name','package_type'],
            'order'=> $orderBy

        ])->all();

        foreach ($records as $record)
        {
            $typeMap = [self::SCORM12=>"SCORM 1.2",self::SCORM2004=>"SCORM 2004"];
            $objRec = new \stdClass();
            $objRec->package_name = $record->package_name;
            $objRec->package_type = !empty($record->package_type) ? $typeMap[$record->package_type] : "";
            $objRec->id = $record->id;
            $data->rows[] = $objRec;


        }
        $data->rowCount = count($data->rows);
        $data->total = $this->find('all')->count();
        return $data;
    }

    public function getAllPackages()
    {
        $rows=[];

        $records = $this->find('all')->all();
        foreach ($records as $record)
        {
            
            $objRec = new \stdClass();
            $objRec->name = $record->package_name;
            
            $objRec->id = $record->id;
            $rows[] = $objRec;


        }
        return $rows;

        
    }


    protected function getLogRecord($logData,$courseId)
    {
        foreach ($logData as $record)
        {
            if ($record->course_id == $courseId)
                return $record;
        }
       return null;
    }
    public function extractCourses($courseStruct,$logData)
    {
        $courses=[];
        foreach($courseStruct as $course)
        {
            if (isset($course->href))
            {
                $data = new \stdClass();
                $data->id =$course->identifier;
                $data->title =$course->title;

                if (!empty($logData))
                {
                    $log = $this->getLogRecord($logData,$course->identifier);
                    if (!empty($log))
                    {
                        $data->logId = $log->id;
                        $data->status = $log->completion;
                        $data->grade = $log->grade;

                    }
                    else
                    {
                        $data->logId = "";
                        $data->status = 'not attempted';
                        $data->grade = '';

                    }



                }
                $courses[] = $data;
            }
            if (isset($course->items))
            {
                $courses=array_merge($courses,$this->extractCourses($course->items,$logData));
            }

        }

        return $courses;

    }
    public function getLearnerProgress($userId, $packageId,$current,$limit)
    {
        $package = $this->get($packageId);
        $tabLogs = TableRegistry::get('CourseLogs');
        $logData = $tabLogs->find('all',[
            'fields'=>['id','completion','grade','course_id'],
            'conditions' =>
                ['user_id = '=>$userId,'package_id = '=>$packageId]

        ])->all();
        $data = json_decode($package->course_struct);

        $courses = $this->extractCourses($data->items,$logData);




        $result =  new \stdClass();
        $result->current = $current;
        $result->total = count($courses);
        $courses = array_slice($courses,$current-1,$limit);
        $result->rowCount = count($courses);


        $result->rows = $courses;
        return $result;


    }
    public function getStartedCourses($userId,$companyId)
    {
       $rows=[];


        $tabLogs = TableRegistry::get('CourseLogs');
        $tabUsers = TableRegistry::get('Users');
        $user = $tabUsers->find('all',['field'=>['id'],'conditions'=>['user_code = ' => $userId,'company_id = ' => $companyId ]])->first();
        if (!$user) {
           throw new \Exception("users record with id = $userId is not found");
           return null;
        } 


       // $startedQuery = $tabLogs->find('all',['fields' =>['package_id'],'conditions' =>['user_id = ' =>$user->id,'completion !=' =>'completed' ]])->select(['package_id']);
        


	$records = $this->find('all',['conditions' =>["exists (SELECT package_id FROM course_logs WHERE user_id = $user->id AND completion != 'completed' AND package_id = Scormobject.id )"]])->all();
        foreach ($records as $record)
        {
            
            $objRec = new \stdClass();
            $objRec->name = $record->package_name;
            
            $objRec->id = $record->id;
            $rows[] = $objRec;


        }
        return $rows;
    }
    public function getCompletedCourses($userId,$companyId)
    {
      $rows=[];


        $tabLogs = TableRegistry::get('CourseLogs');
        $tabUsers = TableRegistry::get('Users');
        $user = $tabUsers->find('all',['field'=>['id'],'conditions'=>['user_code = ' =>$userId ]])->first();
        if (!$user) {
           throw new \Exception("users record with id = $userId is not found");
           return null;
        } 


       // $startedQuery = $tabLogs->find('all',['fields' =>['package_id'],'conditions' =>['user_id = ' =>$user->id,'completion !=' =>'completed' ]])->select(['package_id']);
        


	$records = $this->find('all',['conditions' =>["exists (SELECT package_id FROM course_logs WHERE user_id = $user->id AND package_id = Scormobject.id ) 


AND not exists (SELECT package_id FROM course_logs WHERE user_id = $user->id AND package_id = Scormobject.id AND completion!='completed' )"]])->all();
        foreach ($records as $record)
        {
            
            $objRec = new \stdClass();
            $objRec->name = $record->package_name;
            
            $objRec->id = $record->id;
            $rows[] = $objRec;


        }
        return $rows;

    }
    public function getCompanyCourses($companyId)
    {
         $rows=[];

	$records = $this->find('all',['conditions' =>["company_id = "=>$companyId]])->all();
       

      foreach ($records as $record)
        {
            
            $objRec = new \stdClass();
            $objRec->name = $record->package_name;
            $objRec->company_id = $record->company_id;
            
            $objRec->id = $record->id;
            $rows[] = $objRec;


        }
        return $rows;

    }
    public function getCourseMetric($userId,$courseId,$companyId,$metric)
    {
       $tabUsers = TableRegistry::get('Users');
        $user = $tabUsers->find('all',['field'=>['id'],'conditions'=>['user_code = ' =>$userId ]])->first();
        if (!$user) {
           throw new \Exception("users record with id = $userId is not found");
           return null;
        }
       
        $course  = $this->find('all',array(
        'conditions' => array('Scormobject.id =' => $courseId)))->first();
        if (!$course) {
           throw new \Exception("courses record with id = $courseId is not found");
           return null;
        }


        $tabLogs = TableRegistry::get('CourseLogs');
        $log = $tabLogs->find('all',['conditions'=>['CourseLogs.package_id =' =>$courseId,'CourseLogs.user_id ='=>$user->id]])->first();
        
        if (strlen($log->cmi_data)>0){
           $logData = json_decode($log->cmi_data);
           if (isset($logData->$metric)) {
              return $logData->$metric;
           }
             
        }
        return null; 
         
        
        
    }  
}
