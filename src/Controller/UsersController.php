<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Event\Event;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 */
class UsersController extends AppController
{


    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->Auth->allow(['createUser']);
    }


    public function createUser()
    {
        $this->autoRender=false;
        $this->response->type('json');
        $reqParams = $this->request->input('json_decode');

        $response = new \StdClass(); 
        if (isset($reqParams->user)&&isset($reqParams->pass)&&isset($reqParams->company_id)){
          $userCode = $this->Users->createScormUser($reqParams->user,$reqParams->pass,$reqParams->company_id);
          if(!empty($userCode)) {
             $response->success = "user created";
             $response->id = $userCode;
              
          } else {
             $this->response->statusCode('409'); 
             $response->error = "Cannot create an user";  
          }
        } else {
             $this->response->statusCode('400');
             $response->error = "Bad username/password or comapny id";
        } 

        echo json_encode($response); 
    }
}
