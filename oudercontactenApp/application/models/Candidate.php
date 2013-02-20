<?php

class Application_Model_Candidate
{
    protected $pupilId, $firstname, $name, $parentId, $salutation, $courseId, $course;
    
    public function __construct($pupilId, $firstname, $name, $parentId, $salutation, $courseId = null, $course = null) {
        $this->pupilId = $pupilId;
        $this->firstname = $firstname;
        $this->name = $name;
        $this->parentId = $parentId;
        $this->salutation = $salutation;
        $this->courseId = $courseId;
        $this->course = $course;
    }

    public function getPupilId() {
        return $this->pupilId;
    }
    
    public function getFirstname() {
        return $this->firstname;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getParentId() {
        return $this->parentId;
    }
    
    public function getSalutation() {
        return $this->salutation;
    }
    
    public function getCourseId() {
        return $this->courseId;
    }
    
    public function getCourse() {
        return $this->course;
    }
}

