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
			  'columns' => $this->getColumns(),
		//	  'entries' => $this->getEntries(),
        ];
    }
	
	public function getColumns(){
		$columns = [
            "processname",
            "indate",
            "incident",
            "description",
			"workflowid"
        ];
		return json_encode($columns);
	}
	
/*	public function getEntries(){
		$JobDB = $this->getJobDB();
		$columns = json_decode($this->getColumns());
		$temp = "";
		$entries = array(
						array(),
						array(),
						);
		$count = 0;				
		for($i = 0; $i < count($columns); $i++){
			$temp = "SELECT " .$columns[$i] ." FROM JRINCIDENTS ORDER BY " .$columns[1]  ." DESC";
			$result = $JobDB->query($temp);
			while($row = $JobDB->fetchRow($result)){
				$entries[$i][$count]= $row;
				$count++;
			}
		}
		
	return json_encode($entries);		
	} */
}