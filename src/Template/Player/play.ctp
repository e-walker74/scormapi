<?php
use  Cake\Core\Configure;
use App\Model\Table\ScormobjectTable;

/**
 * @param $items
 * @param $courseLocation
 * @return string
 */
function prepareTree($items, $courseLocation)
{
    static $webLocation;
    if (empty($webLocation))
    {
        $location = Configure::read('PackageLocation');
        $webLocation = $location['www'];

    }
    $str ='nodes: [';
    foreach ($items as $item)
    {
        $str.='{text: "'.$item->title.'"';
        if (!empty($item->href))
        {


            $str.=', cntloc:"'.$webLocation.$courseLocation.'/'.$item->href.$item->parameters.'"';
        }

        if (!empty($item->identifier))
        {


            $str.=', ident:"'.$item->identifier.'"';
        }


        if (isset($item->items))
        {
           $str.=", ".  prepareTree($item->items,$courseLocation);
        }
        $str.='}, ';
    }
    $str = rtrim($str,', ');
    return $str."]";
}



$this->start('css');

echo $this->Html->css('player.css');

echo $this->Html->css('bootstrap-treeview.min.css');


$this->end();

$this->start('script');

echo $this->Html->script('bootstrap-treeview.min.js');

echo $this->Html->script('sco_node_datamodel.js');
echo $this->Html->script('sco_node_debug.js');
echo $this->Html->script('flash_detect.js');

switch ($courseType)
{
    case ScormobjectTable::SCORM12:
        echo $this->Html->script('sco_node_api_1_2.js');
        break;
    case ScormobjectTable::SCORM2004:
        echo $this->Html->script('sco_node_api_2004.js');
        break;

}

$this->end();

$this->Html->scriptStart(['block' => true]);


echo 'var tree = [{
        text:"'.$courseStruct->title.'", '.prepareTree($courseStruct->items,$courseLocation)."}];";

$this->Html->scriptEnd();



?>
<script type="text/javascript">

    function setSCORMUrl(courseId) {
        if (cmi) {
            cmi.lms_commit_url = "<?php echo $this->Url->build([
                    "controller" => "Scorm",
                    "action" => "logResult",
                    $packageId]);?>" + "/" + courseId;
            cmi.lms_init_url  = "<?php echo $this->Url->build([
                    "controller" => "Scorm",
                    "action" => "initData",
                    $packageId]);?>" + "/" + courseId;


        }
    }
    $(document).ready(function() {
        $('#treecnt').treeview({
            data: tree,
            backColor: "white",
            color: "black",
            borderColor:"white",

            onNodeSelected: function (event, data) {
                if (typeof data.cntloc!='undefined') {

                    setSCORMUrl(data.ident);
                    $('#content').attr('src',data.cntloc);
                }
            }
        });

    });


</script>
<div id="framecontent">
    <div class="innertube">
     <div id="treecnt">

    </div>
</div>


<div id="maincontent">
    <div class="innertube">

       <iframe id="content" src="" />

    </div>
</div>

