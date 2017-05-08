<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use App\Model\Table\UsersTable;
use App\Model\Table\ScormObjectTable;
use App\Model\Table\CallsTable;
use App\Model\Table\UploadHelper;
use Cake\Routing\Router;
use Cake\Datasource\ConnectionManager;
use h4cc\Multipart\ParserSelector;
/**
 * Player Controller
 *
 * @property \App\Model\Table\CallsTable $Calls
 * @property \App\Model\Table\UsersTable $Users
 */
class PlayerController extends AppController
{


     public function beforeFilter()
     {
	$this->Auth->allow(['get','play','deleteCourse','uploadCourse','courseStatus','listAllCourses','coursesStarted','coursesCompleted','listStudentCourses','courseMetric', 'listCompanyCourses']);
     }    
   public function get()
    {
        $this->autoRender=false;
        $this->response->type('json');
        $reqParams = $this->request->input('json_decode');
        

        $response = new \StdClass(); 
        if (isset($reqParams->user)&&isset($reqParams->pass)&&isset($reqParams->course)){
           $tabUser  = TableRegistry::get('Users');
        
           $user = $tabUser->find('all',array(
        'conditions' => array('Users.username =' => $reqParams->user)))->first();

           if ($user) {
              $tabCourse  =  TableRegistry::get('Scormobject');

              $course  = $tabCourse->find('all',array(
        'conditions' => array('Scormobject.id =' => $reqParams->course)))->first();

              if($course) {
                $tabCall = TableRegistry::get('Calls');
		  
		  $call = $tabCall->newEntity();

	         $call->user_id = $user->id;
                $call->course_id = $course->id;
                $call->token = md5(microtime());


                $tabCall->save($call);
                $response->action ='success';
		  $response->url = Router::url('/player/play/'.$call->token, true);

              } else {
                 $this->response->statusCode('400');
                 $response->error = 'bad course_id';
              }


           } else {
               $this->response->statusCode('400');
               $response->error = 'bad user/password';   
           }
 
        } else {
           $response->error = 'bad user/password or course_id';
        }
        echo json_encode($response); 
        

        
    }
    public function play($token) 
   { 
      $this->autoRender=false; 
       $response = new \StdClass();
       if (isset($token)&&(!empty($token))) {
 $this->viewBuilder()->layout('scorm2');
           $tabCall = TableRegistry::get('Calls');
           $call =  $tabCall->find('all',array(
          'conditions' => array('Calls.token =' => $token)))->first();
          
	   if($call) {
              $this->autoRender=true; 
		$this->viewBuilder()->layout('player2');
              $userId = $call->user_id;
		$packageId = $call->course_id;
		
		$tabUser  = TableRegistry::get('Users');
              $user = $tabUser->find('all',array(
        	'conditions' => array('Users.id =' => $userId)))->first();

		$this->Auth->setUser($user->toArray());
        
              $tabScorm= TableRegistry::get('Scormobject'); 

		$course = $tabScorm->get($packageId);

        	$courseStruct= json_decode($course->course_struct);
       	 $this->set('courseStruct',$courseStruct);
        	$courseLocation = $course->location;
       	 $this->set('courseLocation',$courseLocation);
        	$this->set('courseType',$course->package_type);
              
              $this->set('packageId',$packageId);
              
           } else {
              $this->response->statusCode('404');
		$response->error = "The request token is not found"; 
           	 echo json_encode($response); 
           } 
          
           
       } else {
            
            $this->response->statusCode('400');
            $response->error = "Empty request"; 
            echo json_encode($response); 
       }
   }
   public function deleteCourse()
   {
        $this->autoRender=false;
        $reqParams = $this->request->input('json_decode');
        

        $response = new \StdClass(); 
        
        if (isset($reqParams->course_id)&&isset($reqParams->company_id)) {
		$tabScorm = TableRegistry::get('Scormobject'); 
		$course = $tabScorm->find('all',['conditions'=>['Scormobject.id ='=>$reqParams->course_id]])->first();
 
              if ($course->id) {
           
		  $conn = $tabScorm->connection();	
                 try {
                     $logTable = TableRegistry::get('CourseLogs');
                     $logTable->deleteAll(['course_id'=>$reqParams->course_id]);

			$callsTable = TableRegistry::get('Calls');
                     $callsTable->deleteAll(['course_id'=>$reqParams->course_id]);
			$tabScorm->delete($course);

			$conn->commit();
			$response->success = true; 
   
                 } catch ( \Exception $ex ) {
                       $response->error = $ex->getMessage();
                       $conn->rollback();
                 }
              }
               if (!isset($response->success)) {
                 $this->response->statusCode('404');
                 $response->error = 'cannot find course with id = '.$reqParams->course_id." on company ".$reqParams->company_id; 
               }
        } else {
            $this->response->statusCode('400');
            $response->error = 'the course_id or company_id is not set';  
        } 
      
	echo json_encode($response); 
 
   }
   public function uploadCourse() 
   {
	$this->autoRender=false;
       $this->response->type('json');
       $data = $this->request->input();

       $content = $this->request->env('CONTENT_TYPE');
       
       


       $response = new \StdClass();
       $response->course_ids = [];

       $tabScorm  = TableRegistry::get('Scormobject');

       
       if (strpos($content,'multipart/mixed')!==false) {
       
   $tabScorm  = TableRegistry::get('Scormobject');
          $conn = $tabScorm->connection();
          $conn->begin();
          try{
            $uploadData = new UploadHelper($content,$data);
            $companyId = $uploadData->company_id;

            if (($companyId == null)||(!is_numeric($companyId))||($companyId <= 0)) {
            unset($response->course_ids);
            $this->response->statusCode('400');
            $response->error[] = 'The company_id has invalid value or not set';
           } else {
            $files = $uploadData->getFiles();
     
            foreach ($files as $file) {
              
              $result = $tabScorm->createSCORMPackage($file,$companyId);
              if ($result>0) {
                $response->course_ids[]  = $result;
              } else {
                unset($response->course_ids);
                $this->response->statusCode('500');
                $response->error[] = "The file(s) appeared to be not SCORM package(s)";
                $conn->rollback();  
		  break;
              }
            }
           }  
          }catch (\Exception $ex) {
             $this->response->statusCode('500');
             $response->error = $ex->getMessage();
             unset($response->course_ids);
             $conn->rollback();
          }

          if (!isset($response->error)) {
             $response->success = "true";
	      $conn->commit();
          }
          echo json_encode($response);
          return;   
       } else {

           $this->response->statusCode('400');
           $response->error[] = 'only multipart/mixed content type is allowed';
           unset($response->course_ids);
           echo json_encode($response);
           return;  
   
       }
 
       if (isset($_FILES)&&(is_array($_FILES))&&(count($_FILES)>0)) {
          $tabScorm  = TableRegistry::get('Scormobject');
	   $conn = $tabScorm->connection();
          $conn->begin();

         foreach ($_FILES as $file) {
            
       
            $result = $tabScorm->handlePackageUpload($file);
            
            if ($result>0) {
             $response->course_ids[]  = $result;
            } else {
              unset($response->course_ids);
              $this->response->statusCode('500');
              $response->error[] = "cannot upload ".$file['name'];
              $conn->rollback();  
		break;
            }

          }
          if (!isset($response->error)) {
             $response->success = "true";
	      $conn->commit();
          } 
           
       } else {
          $this->response->statusCode('400');
          unset($response->course_ids);
          $response->error = "no files on inputs";  
       }
        
       echo json_encode($response); 

   }
   public function courseStatus($companyId,$user,$courseId) 
   {
	$this->autoRender=false;
       $this->response->type('json');
       
       $response = new \StdClass();
       

       $tabScorm  = TableRegistry::get('Scormobject');
       if (!empty($user)&&!empty($companyId)&&!empty($courseId)) {
          $tabLog = TableRegistry::get('CourseLogs');
          $tabUsers = TableRegistry::get('Users');
          $userRec = $tabUsers->find('all',['field'=>['id'],'conditions'=>['user_code = ' => $user,'company_id = ' => $companyId ]])->first();
          if (!$userRec) {
            $this->response->statusCode('404');
            $response->error = "users record with id = $user is not found";
            echo json_encode($response);
            return; 
          }
          
           $course  = $tabScorm->find('all',array(
        'conditions' => array('Scormobject.id =' => $courseId)))->first();
        if (!$course) {
            $this->response->statusCode('404');
            $response->error = "courses record with id = $courseId is not found";
            echo json_encode($response);
            return;
           
        }




          $log = $tabLog
		->find('all',['conditions'=>['CourseLogs.package_id =' =>$courseId,'Users.user_code ='=>$user]])->contain(['Users'])->first();
		/*->contain(['Users' => function ($q)
					 {
                                      return $q
                                             ->select(['username'])
                                             ->where(['Users.username=' =>$reqParams->user]) 
                                    }])->first();*/
         if ($log != null) {
            $response->status = $log->completion;
         } else {
            $response->status = 'not started';
         }
 
       }else {
         $this->response->statusCode('400');
         $response->error = 'bad user,company_id or course_id'; 
       }
       echo json_encode($response);        


    
   }
   public function listAllCourses()
   {
       $this->autoRender=false;
       $this->response->type('json');
       $tabCourses = TableRegistry::get('Scormobject');
       $response = $tabCourses->getAllPackages();
       if ($response == null) {
         $response = new \StdClass();
         $this->response->statusCode('404');
         $response->error = 'Cannot list courses';
       }
       
       echo json_encode($response); 
   }
   public function coursesStarted($companyId,$user)
   {
     $this->autoRender=false;
       $reqParams = $this->request->input('json_decode');
 
       $tabCourses = TableRegistry::get('Scormobject');
        if (!empty($user)&&!empty($companyId)) {
            try {
              $response = $tabCourses->getStartedCourses($user,$companyId);
              if (!$response) {
                $response = new \StdClass();
                $this->response->statusCode('404');
                $response->error = 'Cannot get course list of specified user and company';
              }
             } catch (\Exception $e) {
                $response = new \StdClass();
                $this->response->statusCode('404');
                $response->error = $e->getMessage();
                  
             }
            
        }else {
          $response = new \StdClass();
          $this->response->statusCode('400');
          $response->error = 'Bad user or company_id';
       }
       echo json_encode($response);
       
   }

   public function coursesCompleted($companyId,$user)
   {
     $this->autoRender=false;
     $this->response->type('json');
      
 
       $tabCourses = TableRegistry::get('Scormobject');
        if (!empty($user)&&!empty($companyId)) {
          try {
            $response = $tabCourses->getCompletedCourses($user,$company_id);
            if (!$response) {
              $response = new \StdClass();
              $this->response->statusCode('404');
              $response->error = 'Cannot get course list of specified user and company';

           }
        } catch (\Exception $e) {
                $response = new \StdClass();
                $this->response->statusCode('404');
                $response->error = $e->getMessage();
                  
             }   
        }else {
          $response = new \StdClass();
          $this->response->statusCode('400');
          $response->error = 'Bad user or company_id';
       }
       echo json_encode($response);
       
   }
   public function listStudentCourses($companyId,$user)
   {
       $this->autoRender=false;
       $this->response->type('json');
       
       $tabCourses = TableRegistry::get('Scormobject');
       $response = new \StdClass();
       if (!empty($user)&&!empty($companyId)) {
        try {
	    $response->completed = $tabCourses->getCompletedCourses($user,$companyId);
           $response->current = $tabCourses->getStartedCourses($user,$companyId);
        } catch (\Exception $e) {
                $response = new \StdClass();
                $this->response->statusCode('404');
                $response->error = $e->getMessage();
                  
             }

       } else {
          $response = new \StdClass();
          $this->response->statusCode('400'); 
          $response->error = 'Bad user or company_id';
       }
       echo json_encode($response);
 
   }
   public function courseMetric($companyId,$user,$courseId,$metric) 
  {
       $this->autoRender=false;
       $this->response->type('json');
       
       $tabCourses = TableRegistry::get('Scormobject');
       $response = new \StdClass();
       if (!empty($user)&&!empty($companyId)&&!empty($courseId)&&!empty($metric)) {
         try {
           
           $response->success = true;
         
           $response->$metric = $tabCourses->getCourseMetric($user,$courseId,$companyId,$metric);

       } catch (\Exception $e) {
                $response = new \StdClass();
                $this->response->statusCode('404');
                $response->error = $e->getMessage();
                  
             }   
       } else {
          $response = new \StdClass();
          $this->response->statusCode('400');
          $response->error = 'Bad user,course_id, metric or company_id';
       }
       echo json_encode($response);

  }
  public function listCompanyCourses($companyId)
  {
       $this->autoRender=false;
       $this->response->type('json');
       
       $tabCourses = TableRegistry::get('Scormobject');
       $response = new \StdClass();
       if (isset($companyId)) {
        
	    $response = $tabCourses->getCompanyCourses($companyId);
           

       } else {
          $response = new \StdClass();
          $this->response->statusCode('400');
          $response->error = 'Bad company_id';
       }
       echo json_encode($response);   
  }
  
}


