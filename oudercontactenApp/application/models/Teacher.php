<?php

class Application_Model_Teacher
{
    protected $name, $pupil, $course;
    protected $appointments;
    
    public function __construct($teacherId, $pupilId) {
        $teacherId = (int) $teacherId;
        $pupilId = (int) $pupilId;
        
        $staff = Application_Model_DbTable_Staff::getStaffById($teacherId);
        $pupil = Application_Model_DbTable_Pupils::getPupilById($pupilId);
        $this->name = $staff->getFirstname() . ' ' . $staff->getName();
        $this->pupil = $pupil->getFirstname() . ' ' . $pupil->getName();
    }
}

