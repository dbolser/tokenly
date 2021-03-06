<?php
class Slick_App_Page_View_Controller extends Slick_App_ModControl
{
	public $args;
	public $data;
	
    function __construct()
    {
        parent::__construct();
        $this->model = new Slick_App_Page_View_Model;
        
        
    }
    
    public function init()
    {
		$output = parent::init();
		if($this->itemId != null){
			$get = $this->model->get('page_index', $this->itemId, array(), 'itemId');
			if(!$get){
				http_response_code(400);
				$output['view'] = '404';
				return $output;
			}
			
			$getPage = $this->model->getPageData($this->itemId);
			if($getPage){
				$output = array_merge($getPage, $output);
				$output['view'] = 'page';
				if($this->data['user']){
					Slick_App_LTBcoin_POP_Model::recordFirstView($this->data['user']['userId'], $this->data['module']['moduleId'], $this->itemId);
				}
			}
			else{
				http_response_code(400);
				$output['view'] = '404';
			}
			
		}
		else{
			http_response_code(400);
			$output['view'] = '404';
		}
		
		
		return $output;
		
		
	}
	
	
}
