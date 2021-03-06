<?php
class Slick_App_Dashboard_Stats_Controller extends Slick_App_ModControl
{
    public $data = array();
    public $args = array();
    
    function __construct()
    {
        parent::__construct();
        
        $this->model = new Slick_App_Dashboard_Stats_Model;
        
        
    }
    
    public function init()
    {
		$output = parent::init();
		$output['view'] = 'stats';
		$output['template'] = 'admin';
		$output['stats'] = $this->model->getStats();
        
        return $output;
    }


}

?>
