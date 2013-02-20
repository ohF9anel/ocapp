<?php

class Application_Model_StaffRights
{
    protected $rightId, $staffId, $name, $firstname, $accessebilityLevel;
    
    
    public function __construct($rightId, $staffId, $name, $firstname, $accessebilityLevel) {
        $this->rightId = (int) $rightId;
        $this->staffId = (int) $staffId;
        $this->name = $name;
        $this->firstname = $firstname;
        $this->accessebilityLevel = (int) $accessebilityLevel;
    }
    
    public function getStaffId() {
        return $this->staffId;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getFirstname() {
        return $this->firstname;
    }
    
    public function getRightId() {
        return $this->rightId;
    }
    
    public function getAccessebilityLevel() {
        return $this->accessebilityLevel;
    }

    public function setAccessebilityLevel($accessebilityLevel) {
        $accessebilityLevel = (int) $accessebilityLevel;
        $this->accessebilityLevel = $accessebilityLevel;
    }
    
    public function save() {
        $right = new Application_Model_DbTable_Rights($this->rightId, $this->staffId, $this->accessebilityLevel);
        $right->save();
    }
}

