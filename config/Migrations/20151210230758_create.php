<?php
use Migrations\AbstractMigration;

class Create extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $table = $this->table('scormobject');
        $table->addColumn('package_name', 'string', [
            'limit' => 255,

        ])
        ->addColumn('package_type','integer')
        ->addColumn('course_struct','text')
        ->addColumn('location','string',['limit'=>255]);

        $table->create();
    }
}
