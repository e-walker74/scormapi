<?php
namespace App\Model\Table;

use App\Model\Entity\CourseLog;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\TableRegistry;

/**
 * CourseLogs Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Users
 * @property \Cake\ORM\Association\BelongsTo $Scormobject
 *
 */
class CourseLogsTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table('course_logs');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('Scormobject', [
            'foreignKey' => 'package_id',
            'joinType' => 'INNER'
        ]);

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
            ->requirePresence('cmi_data', 'create')
            ->notEmpty('cmi_data');

        $validator
            ->requirePresence('completion', 'create')
            ->notEmpty('completion');

        $validator
            ->requirePresence('grade', 'create')
            ->notEmpty('grade');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'));
        $rules->add($rules->existsIn(['package_id'], 'Scormobject'));

        return $rules;
    }
    public function saveCMIData($userId,$packageId,$courseId,$cmiData)
    {


        $cmi = json_decode($cmiData);

        $logData = $this->find('all',['conditions' =>
            ['user_id = '=>$userId,'package_id = '=>$packageId,'course_id = '=>$courseId]])->first();

        if ($logData == NULL)
        {
            $logData =$this->newEntity();
            $logData->user_id = $userId;
            $logData->package_id = $packageId;
            $logData->course_id = $courseId;
        }


                // update status
        $status = $cmi->completion_status;
        $outcome = $cmi->success_status;
        $logData->completion = $status;
        if (empty($logData->grade))
            $logData->grade ='0';
        if ($logData->grade<$cmi->score_raw)
        {
            $logData->grade =$cmi->score_raw;
        }
        $logData->cmi_data = $cmiData;

        $this->save($logData);
        return 'store complete - ' . strlen($cmiData) . ' bytes received';




    }

    /**
     * @param $userId
     * @param $userName
     * @param $packageId
     * @param $courseId
     * @return mixed|\stdClass
     */
    public function loadCMIData($userId, $userName, $packageId, $courseId)
    {
        $logData = $this->find('all',['conditions' =>
            ['user_id = '=>$userId,'package_id = '=>$packageId,'course_id = '=>$courseId]])->first();

        $data = new \stdClass();
        if ($logData != NULL)
        {
            $data = json_decode($logData->cmi_data);

        }
        $data->learner_id = $userId;
        $data->learner_name = $userName;

        if (!isset($data->objectives))
        {
            $tabScorm = TableRegistry::get('Scormobject');
            $package = $tabScorm->get($packageId);
            $courses = json_decode($package->course_struct);
            $item = $this->findItem($courses->items,'identifier',$courseId);
            if ($item&&isset($item->objectives))
            {

                $data->objectives = $item->objectives;
            }

        }

        return $data;
    }

    /**
     * @param $items
     * @param $field
     * @param $value
     * @return \stdClass
     */
    protected function findItem($items, $field, $value)
    {
        foreach($items as $item)
        {
            if (isset($item->$field)&&($item->$field==$value))
            {
                return $item;
            }
            if (isset($item->items))
            {
                $res=  $this->findItem($item->items,$field, $value);
                if ($res)
                {
                    return $res;
                }

            }
        }
        return null;
    }

}
