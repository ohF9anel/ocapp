<?php

class Application_Model_TeacherData
{
    protected $firstname, $name, $staffId, $function, $courseId, $responsible, $toSee, $remark;
    protected $appointmentDay, $appointmentSlot;
    protected $availableSlots;
    
    public function __construct($firstname, $name, $staffId, $function, $courseId = null, $appointmentDay = null, $appointmentSlot = null, $responsible = false, $toSee = false, $remark = null) {
        $this->firstname = (string) $firstname;
        $this->name = (string) $name;
        $this->staffId = (int) $staffId;
        $this->function = (string) $function;
        $this->courseId = $courseId;
        $this->responsible = $responsible;
        $this->toSee = $toSee;
        $this->remark = $remark;
        $this->appointmentDay = $appointmentDay;
        $this->appointmentSlot = date('H:i', strtotime($appointmentSlot));
        $this->availableSlots = array();
    }
    
    public function getFirstname() {
        return $this->firstname;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getStaffId() {
        return $this->staffId;
    }
    
    public function getCourseId() {
        return $this->courseId;
    }
    
    public function getFunction() {
        return $this->function;
    }
    
    public function getToSee() {
        return $this->toSee;
    }
    
    public function getRemark() {
        return $this->remark;
    }
    
    public function getConferenceDays() {
        return $this->conferenceDays;
    }
    
    public function getAppointmentDay() {
        return $this->appointmentDay;
    }
    
    public function getAppointmentSlot() {
        return $this->appointmentSlot;
    }
    
    public function getAvailableSlots() {
        return $this->availableSlots;
    }
    
    public function setToSee($toSee) {
        $this->toSee = $toSee;
    }
    
    public function setAvailableSlots($availableSlots) {
        $this->availableSlots = $availableSlots;
    }
    
    public function setAppointmentDay($conferenceDayId) {
        $this->appointmentDay = (int) $conferenceDayId;
    }
    
    public function setAppointmentSlot($timeslot) {
        $this->appointmentSlot = $timeslot;
    }
    
    public function isResponsible() {
        return $this->responsible;
    }
    
    public function hasAppointment() {
        if ($this->appointmentDay != null) {
            return true;
        }
        
        return false;
    }
    

    public function isAvailable($conferenceDayId, $timeslot) {
        $conferenceDayId = (int) $conferenceDayId;
        $timeslot = date('H:i:s', strtotime($timeslot));
        
        if(array_key_exists($conferenceDayId, $this->availableSlots)) {
            if(in_array($timeslot, $this->availableSlots[$conferenceDayId])) {
                return true;
            }
        }
        
        return false;
    }
}