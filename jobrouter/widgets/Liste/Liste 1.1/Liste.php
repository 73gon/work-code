<?php

namespace dashboard\MyWidgets\Liste;
use JobRouter\Api\Dashboard\v1\Widget;

class Liste extends Widget{
	
	
    public function getTitle(){
        return 'Liste';
    }

    public function getCategory(){
        return 'administration';
    }
	
	public function getDimensions() {

        return [
            'minHeight' => 3,
            'minWidth' => 4,
            'maxHeight' => 10,
            'maxWidth' => 10,
        ];
    }

    public function getData(){
        return [
			  'columns' => $this->getColumns()
        ];
    }
	
	public function getColumns(){
		$columns = [
            "processname",
            "indate",
            "incident",
            "description",
			"workflowid",
			"status", 
			"steplabel"
        ];
		return json_encode($columns);
	}
}