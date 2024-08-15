<?php

namespace dashboard\MyWidgets\Lizenz;
use JobRouter\Api\Dashboard\v1\Widget;

class Lizenz extends Widget{
	
	
    public function getTitle(){
        return 'Ablaufdatum JR-Lizenz';
    }
	
	public function getDimensions() {

        return [
            'minHeight' => 1,
            'minWidth' => 1,
            'maxHeight' => 1,
            'maxWidth' => 1,
        ];
    }

    public function getData(){
        return [
			  'lizenz' => $this->getLizenz()
        ];
    }
	
	public function getLizenz(){
        $path = __DIR__ . '/../../../license/jr_license.xml';;

        $xml = simplexml_load_file($path);
        
        $rudContent = (string) $xml->rud;
        return json_encode($rudContent);
    }
}
		
