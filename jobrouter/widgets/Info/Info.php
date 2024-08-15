<?php

namespace dashboard\MyWidgets\Info;
use JobRouter\Api\Dashboard\v1\Widget;

class Info extends Widget{
	
	
    public function getTitle(){
        return 'Anzahl Einträge';
    }
	
	public function getDimensions() {

        return [
            'minHeight' => 1,
            'minWidth' => 1,
            'maxHeight' => 10,
            'maxWidth' => 10,
        ];
    }

    public function getData(){
        return [
			  'info' => $this->getInfo()
        ];
    }
	
	public function getInfo(){
        $JobDB = $this->getJobDB();
        $temp = "SELECT COUNT(*) AS count FROM jrincidents";
        $result = $JobDB->query($temp);
        

        while($row = $JobDB->fetchRow($result)){
            $info = $row["count"];
        }	

	    return json_encode($info);
    }
}
		
