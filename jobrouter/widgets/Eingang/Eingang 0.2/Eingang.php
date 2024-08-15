<?php

namespace dashboard\MyWidgets\Eingang;
use JobRouter\Api\Dashboard\v1\Widget;

class Eingang extends Widget{
	
	
    public function getTitle(){
        return 'Alle Eingänge';
    }

    public function getCategory(){
        return 'administration';
    }
	
	public function getDimensions() {

        return [
            'minHeight' => 3,
            'minWidth' => 2,
            'maxHeight' => 10,
            'maxWidth' => 4,
        ];
    }

    public function getData(){
        return [
			  'processBox' => $this->getInboxDetails(),
			  'JobFunctions' => $this->getRoles(),
			  'Links' => $this->getInboxLinks(),
			  'description' => $this->getInboxDescription(),
        ];
    }
	
	public function CountInboxes(){
		$JobDB = $this->getJobDB();
		$oldJobFunctions = $this->getCurrentJobFunctions();
		$temp = "";
		$index = 0;
		$currentJobFunctions = range(0, count($oldJobFunctions), 1);
		
		foreach($oldJobFunctions as $JobFunction){
			$temp = "SELECT * FROM jrincidents WHERE recipient = '" .$JobFunction ."' AND (status = '0' OR status = '1') ORDER BY  recipient, startdate DESC";
			$result = $JobDB->query($temp);
			
			$count = 0;
			while($row = $JobDB->fetchRow($result)){
				$count++;
			}	
			
			if($count > 0){
				$currentJobFunctions[$index] = $JobFunction;
				$index++;
			}
		}
		array_splice($currentJobFunctions, $index);
		return json_encode($currentJobFunctions);
	}
	
	public function getRoles(){
		$JobDB = $this->getJobDB();
		$temp = "";
		$index = 0;
		$currentJobFunctions = json_decode($this->CountInboxes());
		
		foreach($currentJobFunctions as $JobFunction){
			$temp = "SELECT DISTINCT processname, steplabel  FROM jrincidents WHERE recipient = '" .$JobFunction ."' AND (status = '0' OR status = '1') ORDER BY  recipient, startdate DESC";
			$result = $JobDB->query($temp);
			
			while($row = $JobDB->fetchRow($result)){
				$currentJobFunctions[$index] = $row['processname'] ." | " .$row['steplabel'];
				$index++;
			}	
		}
		array_splice($currentJobFunctions, $index);
		return json_encode($currentJobFunctions);
	}

	public function getInboxDetails(){
		$JobDB = $this->getJobDB();
		$roles = json_decode($this->getRoles());
		$details = array(
						array(),
						array(),
						);;
		$count = 0;		

 		for($i = 0; $i < count($roles); $i++){
			$where = explode(" | ", $roles[$i]);
			$temp = "SELECT * FROM jrincidents WHERE processname = '" .$where[0] ."' AND steplabel = '" .$where[1] ."' AND (status = '0' OR status = '1') ORDER BY  recipient, startdate DESC";
			$result = $JobDB->query($temp);
			while($row = $JobDB->fetchRow($result)){
				$details[$i][$count]= $row['incident'];
				$count++;
			}
		}
		return json_encode($details); 
	}
	
	public function getInboxDescription(){
		$JobDB = $this->getJobDB();
		$roles = json_decode($this->getRoles());
		$description = array(
						array(),
						array(),
						);
		$count = 0;				
 		for($i = 0; $i < count($roles); $i++){
			$where = explode(" | ", $roles[$i]);
			$temp = "SELECT * FROM jrincidents WHERE processname = '" .$where[0] ."' AND steplabel = '" .$where[1] ."' AND (status = '0' OR status = '1') ORDER BY  recipient, startdate DESC";
			$result = $JobDB->query($temp);
			while($row = $JobDB->fetchRow($result)){
				$description[$i][$count]= $row['summary'];
				$description[$i][$count+1]= $row['indate'];
				$count = $count + 2;
			}
		}
		return json_encode($description); 
	}
	
	public function getInboxLinks(){
		$JobDB = $this->getJobDB();
		$roles = json_decode($this->getRoles());
		$links = array(
						array(),
						array(),
						);;
		$count = 0;				
 		for($i = 0; $i < count($roles); $i++){
			$where = explode(" | ", $roles[$i]);
			$temp = "SELECT * FROM jrincidents WHERE processname = '" .$where[0] ."' AND steplabel = '" .$where[1] ."' AND (status = '0' OR status = '1') ORDER BY  recipient, startdate DESC";
			$result = $JobDB->query($temp);
			while($row = $JobDB->fetchRow($result)){
				$links[$i][$count]= $row['workflowid'];
				$count++;
			}
		}
		return json_encode($links); 
	}
}