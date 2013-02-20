<?php

class Application_Model_PupilData
{
    protected $firstname, $name, $pupilId, $group, $type;
    protected $teachers;
    
    public function __construct($firstname, $name, $pupilId, $group, $type) {
        $this->firstname = (string) $firstname;
        $this->name = (string) $name;
        $this->pupilId = (int) $pupilId;
        $this->group = (string) $group;
        $this->type = ($type != 'type1' && $type != 'type2' ? 'type1' : $type);
        $this->teachers = array();
    }
    
    public function getFirstname() {
        return $this->firstname;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getPupilId() {
        return $this->pupilId;
    }
    
    public function getGroup() {
        return $this->group;
    }
    
    public function getType() {
        return $this->type;
    }
    
    public function getTeachers() {
        return $this->teachers;
    }

    public function addTeacher($firstname, $name, $staffId, $function, $remark = null, $courseId = null, $appointmentDay = null, $appointmentSlot = null, $responsible = false, $toSee = false) {
        if ($this->getType() == 'type2' && $courseId == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        
        $teacher = new Application_Model_TeacherData((string) $firstname, (string) $name, (int) $staffId, (string) $function, (int) $courseId, $appointmentDay, $appointmentSlot, $responsible, $toSee, $remark);
        $this->teachers[] = $teacher;
    }
}