<?php

class Application_Model_TeacherAppointments
{
    protected $start, $end, $day, $dayId, $room, $roomId, $present, $obligated;
    protected $appointments;
    protected $availableRooms;

    
    public function __construct($start, $end, $day, $dayId, $room, $roomId,$present , $obligated, $appointments, $availableRooms = null) {
        $this->start = $start;
        $this->end = $end;
        $this->day = $day;
        $this->dayId = $dayId;
        $this->room = $room;
        $this->roomId = $roomId;
        $this->present = $present;
        $this->obligated = $obligated;
        $this->appointments = $appointments;
        $this->availableRooms = $availableRooms;
    }
    
    public function getStart() {
        return $this->start;
    }
    
    public function getEnd() {
        return $this->end;
    }
    
    public function getDay() {
        return $this->day;
    }
    
    public function getDayId() {
        return $this->dayId;
    }
    
    public function getRoom() {
        return $this->room;
    }
    
    public function getRoomId() {
        return $this->roomId;
    }
    
    public function isPresent() {
        return $this->present;
    }
    
    public function isObligated() {
        return $this->obligated;
    }
    
    public function getAppointments() {
        return $this->appointments;
    }
    
    public function getAvailableRooms() {
        return $this->availableRooms;
    }
    
    public function setAvailableRooms($availableRooms) {
        $this->availableRooms = $availableRooms;
    }
}

