<?php
/**
 * Db class for appointments table
 */
class Application_Model_DbTable_Appointments extends Zend_Db_Table_Abstract
{
    protected $appointmentId, $staffId, $pupilId, $parentId, $courseId, $appointment, $conferenceDayId, $conferenceId, $selfPlanned, $confirmed, $temporary;
    protected $_name = 'appointments';
    protected static $allocatedMinutes = 2;


    public function __construct($appointmentId = null, $staffId = null, $pupilId = null, $parentId = null, $courseId = null, $appointment = null, $conferenceDayId = null, $conferenceId = null, $selfPlanned = false, $confirmed = false, $temporary = null, $config = array()) {
        parent::__construct($config);
        $this->appointmentId = $appointmentId;
        $this->staffId = $staffId;
        $this->pupilId = $pupilId;
        $this->parentId = $parentId;
        $this->courseId = $courseId;
        $this->appointment = $appointment;
        $this->conferenceDayId = $conferenceDayId;
        $this->conferenceId = $conferenceId;
        $this->selfPlanned = $selfPlanned;
        $this->confirmed = $confirmed;
        $this->temporary = $temporary;
    }
    
    
    /*
     * GETTERS & SETTERS
     */
    public function getAppointment() {
        return $this->appointment;
    }
    
    public function getAppointmentId() {
        return $this->appointmentId;
    }
    
    public function getConferenceDay() {
        return Application_Model_DbTable_ConferenceDays::getDateByConferenceDay($this->conferenceDayId);
    }
    
    public function getConferenceDayId() {
        return $this->conferenceDayId;
    }
    
    public function getConferenceId() {
        return $this->conferenceId();
    }
    
    public function getCourseId() {
        return $this->courseId;
    }
    
    public function getIsConfirmed() {
        return $this->confirmed;
    }
    
    public function getIsSelfPlanned() {
        return $this->selfPlanned;
    }
    
    public function getParentId() {
        return $this->parentId;
    }
    
    public function getPupilId() {
        return $this->pupilId;
    }
    
    public function getStaff() {
        return Application_Model_DbTable_Staff::getStaffById($this->staffId);
    }
    
    public function getStaffId() {
        return $this->staffId;
    }    
    
    public function getTemporary() {
        return $this->temporary;
    }
    
    
    
    /**
     * Function deletes appointment based on appointmentId and staffId
     * @throws Exception Er150 Null in non optional field
     */
    public function deleteAppointment() {
        if ($this->appointmentId == null || $this->staffId == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        
        $this->delete('appointmentId = ' . $this->_db->quote($this->appointmentId, 'INT') . ' AND staffId = ' . $this->_db->quote($this->staffId, 'INT'));
    }
    
    
    /**
     * Function saves appointment object to database if all required fields have a value
     * @return boolean whether or not the object is saved
     * @throws Exception Er133 Duplicate appointment
     * @throws Exception Er134 Duplicate appointment for parent
     * @throws Exception Er150 Null in non optional field
     */
    public function save() {
        //check if parent or teacher has not already another appointment
        if ($this->parentId != null) {
            $parentSelect = $this->select();
            $parentSelect->from('appointments', 'count(*) AS amount')
                    ->where('parentId = ? AND appointment = ? AND conferenceDayId = ?')
                    ->bind(array($this->parentId, $this->appointment, $this->conferenceDayId));
            $parentResult = $this->fetchRow($parentSelect);
            if ($parentResult['amount'] != 0) {
                throw new Exception('Er134 Duplicate appointment for parent', 134);
            }
        }
        
        $staffSelect = $this->select();
        $staffSelect->from('appointments', 'count(*) AS amount')
                ->where('staffId = ? AND appointment = ? AND conferenceDayId = ?')
                ->bind(array($this->staffId, $this->appointment, $this->conferenceDayId));
        $staffResult = $this->fetchRow($staffSelect);
        if ($staffResult['amount'] != 0) {
            throw new Exception('Er133 Duplicate appointment', 133);
        }

        //check if appointment needs to be updated (id exists) or needs to be added (id doesn't exists)
        if ($this->appointmentId != null) {
            $select = $this->select();
            $select->from('appointments', 'count(*)')
                    ->where('appointmentId = ?')
                    ->bind(array($this->appointmentId));
            $result = $this->fetchRow($select);
            
            if ($result['count(*)'] == 0) {
                return false;
            }
            
            $this->update(array('staffId' => $this->staffId, 'pupilId' => $this->pupilId, 'parentId' => $this->parentId, 'courseId' => $this->courseId, 'appointment' => $this->appointment, 'conferenceDayId' => $this->conferenceDayId, 'conferenceId' => $this->conferenceId), 'appointmentId = ' . $this->_db->quote($this->appointmentId));
        }        
        else if ($this->staffId == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        else {
            $select = $this->select();
            $select->from('appointments', 'count(*) AS amount');
            if ($this->courseId == null) {
                $select->where('staffId = ? AND pupilId = ? AND parentId = ? AND conferenceId = ?')
                    ->bind(array($this->staffId, $this->pupilId, $this->parentId, $this->conferenceId));
            } else {
                $select->where('staffId = ? AND pupilId = ? AND parentId = ? AND courseId = ? AND conferenceId = ?')
                    ->bind(array($this->staffId, $this->pupilId, $this->parentId, $this->courseId, $this->conferenceId));
            }
            
            //check for existing appointments and delete them
            $result = $this->fetchRow($select);
            if ($result['amount'] != 0) {
                if ($this->courseId == null) {
                    $this->delete('staffId = ' . $this->_db->quote($this->staffId, 'INT') . ' AND pupilId = '. $this->_db->quote($this->pupilId, 'INT') . ' AND parentId = ' . $this->_db->quote($this->parentId, 'INT') . ' AND conferenceId = ' . $this->_db->quote($this->conferenceId, 'INT'));
                } else {
                    $this->delete('staffId = ' . $this->_db->quote($this->staffId, 'INT') . ' AND pupilId = '. $this->_db->quote($this->pupilId, 'INT') . ' AND parentId = ' . $this->_db->quote($this->parentId, 'INT') . ' AND courseId = ' . $this->_db->quote($this->courseId, 'INT') . ' AND conferenceId = ' . $this->_db->quote($this->conferenceId, 'INT'));
                }
            }
            $this->insert(array('staffId' => $this->staffId, 'pupilId' => $this->pupilId, 'parentId' => $this->parentId, 'courseId' => $this->courseId, 'appointment' => $this->appointment, 'conferenceDayId' => $this->conferenceDayId, 'conferenceId' => $this->conferenceId));
        }
        
        return true;
    }
    
    

    /**
     * Function allocates a teacher appointment to a parent based on some params
     * @param int $courseId courseId to identify course
     * @param int $pupilId pupilId to identify pupil
     * @param int $parentId parentId to identify parent
     * @param sting $slot time of appointment
     * @param int $dayId dayId to identify conferenceDay
     * @return boolean whether or not the appointment was aves succesfull
     * @throws Exception Er107 Unexisting Childrelation 
     */
    public static function allocateTeacherAppointment($courseId, $pupilId, $parentId, $slot, $dayId) {
        //set database & erase temporary row which are out of date
        $appointment = new Application_Model_DbTable_Appointments();
        $appointment->delete(sprintf('temporary < "%s"', date('Y-m-d H:i:s', time())));

        $courseId = ($courseId != null ? (int) $courseId : null);
        $pupilId = ($pupilId != null ? (int) $pupilId : null);
        $parentId = ($parentId != null ? (int) $parentId : null);
        $slot = ($slot != null ? date('H:i', strtotime($slot)) : null);
        $dayId = ($dayId != null ? (int) $dayId : null);
        $success = false;
        $conferenceId = Application_Model_DbTable_ConferenceDays::getConferenceDayById($dayId)->getConference();
        
        //check if child belongs to parent
        $select = $appointment->select();
        $select->from('childrelations', 'count(*) AS amount')
                ->where('childId = ? AND parentId = ?')
                ->bind(array($pupilId, $parentId))
                ->setIntegrityCheck(false);
        $result = $appointment->fetchRow($select);
        if ($result['amount'] == 0) {
            throw new Exception('Er107 Unexisting Childrelation', 107);
        }
        
        if ($courseId != null && $dayId != null && $pupilId != null && $parentId != null) {
            //get staffId of teacher of this course
            $select1 = $appointment->select();
            $select1->from('courses', array('teacherId'))
                    ->where('courseId = ?')
                    ->bind(array($courseId))
                    ->setIntegrityCheck(false);
            $result1 = $appointment->fetchRow($select1);
            $staffId = $result1['teacherId'];
            
            $select = $appointment->select();
            $select->from('presentdays', array('earlyStart', 'lateEnd', 'presentdays.conferenceDayId as conferenceDayId'))
                    ->joinInner('conferencedays', 'presentdays.conferenceDayId', array('startTime', 'endTime'))
                    ->where('staffId = ? AND type = "Type2" AND presentdays.conferenceDayId = ?')
                    ->bind(array($staffId, $dayId))
                    ->setIntegrityCheck(false);
            $result = $appointment->fetchRow($select);

            //preform check on inserted conferenceDayId
            if (sizeof($result) == 0) {
                return false;
            }
            
            $start = date('H:i', strtotime(strtotime($result['earlyStart']) < strtotime($result['startTime']) && $result['earlyStart'] != null ? $result['earlyStart'] : $result['startTime']));
            $end = date('H:i', strtotime(strtotime($result['lateEnd']) < strtotime($result['endTime']) && $result['lateEnd'] != null ? $result['lateEnd'] : $result['endTime']));
            
            //preform checks on other inserted values
            if ($start <= $slot && $end >= $slot) {
                $appointment->_db->beginTransaction();
                $appointment->_db->query('LOCK TABLES appointments WRITE');
                
                //check if teacher is available
                $select2 = $appointment->select();
                $select2->from('appointments', array('count(*) as amount'))
                        ->where('staffId = ? AND conferenceDayId = ? AND appointment = ? AND pupilId != ? AND parentId != ?')
                        ->bind(array($staffId, $dayId, $slot, $pupilId, $parentId));
                $result2 = $appointment->fetchRow($select2);
                $available = $result2['amount'];
                
                //check if temporary appointment has to be replaced by new one
                $select3 = $appointment->select();
                $select3->from('appointments', array('count(*) as amount'))
                        ->where('staffId = ? AND conferenceId = ? AND pupilId = ? AND parentId = ? AND courseId = ? AND temporary IS NOT NULL')
                        ->bind(array($staffId, $conferenceId, $pupilId, $parentId, $courseId));
                $result3 = $appointment->fetchRow($select3);
                $replace = $result3['amount'];
                
                if ($available == 0 && $replace == 1) {     //replace existing temporary appointment
                    $appointment->update(array(
                        'courseId' => $courseId,
                        'staffId' => $staffId,
                        'pupilId' => $pupilId,
                        'parentId' => $parentId,
                        'appointment' => $slot,
                        'conferenceDayId' => $dayId,
                        'conferenceId' => $conferenceId,
                        'temporary' => date('Y-m-d H:i:s', time() + (Application_Model_DbTable_Appointments::$allocatedMinutes * 60))
                    ), 'staffId = ' . $appointment->_db->quote($staffId, 'INT') . ' AND conferenceId = ' . $appointment->_db->quote($conferenceId, 'INT') . ' AND pupilId = ' . $appointment->_db->quote($pupilId, 'INT') . ' AND parentId = ' . $appointment->_db->quote($parentId, 'INT') . ' AND courseId = ' . $appointment->_db->quote($courseId, 'INT') . ' AND temporary IS NOT NULL');
                    $success = true;
                }
                else if ($available == 0) {     //add new temporary appointment
                    $appointment->insert(array(
                        'courseID' => $courseId,
                        'staffId' => $staffId,
                        'pupilId' => $pupilId,
                        'parentId' => $parentId,
                        'appointment' => $slot,
                        'conferenceDayId' => $dayId,
                        'conferenceId' => $conferenceId,
                        'temporary' => date('Y-m-d H:i:s', time() + (Application_Model_DbTable_Appointments::$allocatedMinutes * 60))
                ));
                    $success = true;
                }
                
                $appointment->_db->query('UNLOCK TABLES');
                $appointment->_db->commit();
            }
            
            return $success;
        }
    }
    
    
    /**
     * Function allocates a titular appointment to a parent based on some params
     * @param int $staffId staffId to identify staffmember
     * @param int $pupilId pupilId to identify pupil
     * @param int $parentId parentId to identify parent
     * @param sting $slot time of appointment
     * @param int $dayId dayId to identify conferenceDay
     * @return boolean whether or not the appointment was aves succesfull
     * @throws Exception Er107 Unexisting Childrelation
     */
    public static function allocateTitularAppointment($staffId, $pupilId, $parentId, $slot, $dayId) {
        //set database & erase temporary row which are out of date
        $appointment = new Application_Model_DbTable_Appointments();
        $appointment->delete(sprintf('temporary < "%s"', date('Y-m-d H:i:s', time())));

        $staffId = ($staffId != null ? (int) $staffId : null);
        $pupilId = ($pupilId != null ? (int) $pupilId : null);
        $parentId = ($parentId != null ? (int) $parentId : null);
        $slot = ($slot != null ? date('H:i', strtotime($slot)) : null);
        $dayId = ($dayId != null ? (int) $dayId : null);
        $success = false;
        $conferenceId = Application_Model_DbTable_ConferenceDays::getConferenceDayById($dayId)->getConference();
        
        //check if child belongs to parent
        $select = $appointment->select();
        $select->from('childrelations', 'count(*) AS amount')
                ->where('childId = ? AND parentId = ?')
                ->bind(array($pupilId, $parentId))
                ->setIntegrityCheck(false);
        $result = $appointment->fetchRow($select);
        if ($result['amount'] == 0) {
            throw new Exception('Er107 Unexisting Childrelation', 107);
        }

        if ($staffId != null && $dayId != null && $pupilId != null && $parentId != null) {
            $select = $appointment->select();
            $select->from('presentdays', array('earlyStart', 'lateEnd', 'presentdays.conferenceDayId as conferenceDayId'))
                    ->joinInner('conferencedays', 'presentdays.conferenceDayId', array('startTime', 'endTime'))
                    ->where('staffId = ? AND type = "Type1" AND presentdays.conferenceDayId = ?')
                    ->bind(array($staffId, $dayId))
                    ->setIntegrityCheck(false);
            $result = $appointment->fetchRow($select);

            //preform check on inserted conferenceDayId
            if (sizeof($result) == 0) {
                return false;
            }
            
            $start = date('H:i', strtotime(strtotime($result['earlyStart']) < strtotime($result['startTime']) && $result['earlyStart'] != null ? $result['earlyStart'] : $result['startTime']));
            $end = date('H:i', strtotime(strtotime($result['lateEnd']) < strtotime($result['endTime']) && $result['lateEnd'] != null ? $result['lateEnd'] : $result['endTime']));
            
            //preform checks on other inserted values
            //if ($start <= $slot && $end >= $slot) {
                $appointment->_db->beginTransaction();
                $appointment->_db->query('LOCK TABLES appointments WRITE');
                
                //check if teacher is available
                $select1 = $appointment->select();
                $select1->from('appointments', array('count(*) as amount'))
                        ->where('staffId = ? AND conferenceDayId = ? AND appointment = ? AND pupilId != ? AND parentId != ?')
                        ->bind(array($staffId, $dayId, $slot, $pupilId, $parentId));
                $result1 = $appointment->fetchRow($select1);
                $available = $result1['amount'];
                
                //check if temporary appointment has to be replaced by new one
                $select2 = $appointment->select();
                $select2->from('appointments', array('count(*) as amount'))
                        ->where('staffId = ? AND conferenceId = ? AND pupilId = ? AND parentId = ? AND temporary IS NOT NULL')
                        ->bind(array($staffId, $conferenceId, $pupilId, $parentId));
                $result2 = $appointment->fetchRow($select2);
                $replace = $result2['amount'];
                
                if ($available == 0 && $replace == 1) {     //replace existing temporary appointment
                    $appointment->update(array(
                        'staffId' => $staffId,
                        'pupilId' => $pupilId,
                        'parentId' => $parentId,
                        'appointment' => $slot,
                        'conferenceDayId' => $dayId,
                        'conferenceId' => $conferenceId,
                        'temporary' => date('Y-m-d H:i:s', time() + (Application_Model_DbTable_Appointments::$allocatedMinutes * 60))
                    ), 'staffId = ' . $appointment->_db->quote($staffId, 'INT') . ' AND conferenceId = ' . $appointment->_db->quote($conferenceId, 'INT') . ' AND pupilId = ' . $appointment->_db->quote($pupilId, 'INT') . ' AND parentId = ' . $appointment->_db->quote($parentId, 'INT') . ' AND temporary IS NOT NULL');
                    $success = true;
                }
                else if ($available == 0) {     //edd new temporary appointment
                    $appointment->insert(array(
                        'staffId' => $staffId,
                        'pupilId' => $pupilId,
                        'parentId' => $parentId,
                        'appointment' => $slot,
                        'conferenceDayId' => $dayId,
                        'conferenceId' => $conferenceId,
                        'temporary' => date('Y-m-d H:i:s', time() + (Application_Model_DbTable_Appointments::$allocatedMinutes * 60))
                ));
                    $success = true;
                }
                
                $appointment->_db->query('UNLOCK TABLES');
                $appointment->_db->commit();
            //}
            
            return $success;
        }
    }
    
    
        /**
     * Function deletes all temporary appointments of which the leasetime is exceeded
     */
    public static function clearTemporaryAppointments() {
        $appointment = new Application_Model_DbTable_Appointments();
        $appointment->delete('temporary < "' . date('Y-m-d H:i:s', time()) . '"');
    }
    
    
    /**
     * Function deletes all temporary appointment of specified parent
     * @param int $parentId parentId to identify parent
     */
    public static function clearTemporaryAppointmentsByParentId($parentId) {
        $parentId = (int) $parentId;
        
        $appointment = new Application_Model_DbTable_Appointments();
        $appointment->delete('temporary IS NOT NULL AND parentId = ' . $appointment->_db->quote($parentId, 'INT'));
    }
    
    
    /**
     * Function deletes all data from appointments table
     */
    public static function deleteAll() {
        $appointment = new Application_Model_DbTable_Appointments();
        /* this function expects something */
        $appointment->delete('');
    }
    
    
    /**
     * Function deletes appointments of a staffmember on a specified conferenceDay
     * @param int $conferenceDayId conferenceDayId toidentify conferenceDay
     * @param int $staffId staffId to identify staffmember
     */
    public static function deleteAppointments($conferenceDayId, $staffId) {
        $conferenceDayId = (int) $conferenceDayId;
        $staffId = (int) $staffId;
        
        $appointment = new Application_Model_DbTable_Appointments();
        $appointment->delete('conferenceDayId = ' . $appointment->_db->quote($conferenceDayId, 'INT') . ' AND staffId = ' . $appointment->_db->quote($staffId, 'INT'));
    }
    
    
    /**
     * Function deletes all appointments of specified conference
     * @param int $conferenceId conferenceId to identify conference
     */
    public static function deleteConference($conferenceId) {
        $conferenceId = (int) $conferenceId;
        $appointment = new Application_Model_DbTable_Appointments();
        
        $appointment->delete('conferenceId = ' . $appointment->_db->quote($conferenceId, 'INT'));
    }
    
    
    /**
     * Function extends leasetime of temporary appointments of specified parent
     * Function called by JavaScript
     * @param int $parentId parentId to identify parent
     */
    public static function extendLeaseTemporaryAppointments($parentId) {
        $parentId = (int) $parentId;
        
        $appointment = new Application_Model_DbTable_Appointments();
        $appointment->update(array('temporary' => date('Y-m-d H:i:s', time() + (Application_Model_DbTable_Appointments::$allocatedMinutes * 60))), 'parentId = ' . $appointment->_db->quote($parentId, 'INT') . ' AND temporary IS NOT NULL');
    }
    
    
    /**
     * Function returns appointment object from database, based on courseId and a few other params 
     * @param int $pupilId pupilId to identify pupil
     * @param int $parentId parentId to identify parent
     * @param int $courseId courseId to identify course
     * @return null|\Application_Model_DbTable_Appointments 
     */
    public static function getAppointmentByCourseId($pupilId, $parentId, $courseId) {
        $pupilId = (int) $pupilId;
        $parentId = (int) $parentId;
        $courseId = (int) $courseId;
        
        
        $appointment = new Application_Model_DbTable_Appointments();
        $select = $appointment->select();
        $select->from('appointments', '*')
                ->where('courseId = ? AND pupilId = ? AND parentId = ? AND temporary IS NULL')
                ->bind(array($courseId, $pupilId, $parentId));
        $result = $appointment->fetchRow($select);
        
        if ($result == null) {
            return null;
        }
        
        $appointment = new Application_Model_DbTable_Appointments($result['appointmentId'], $result['staffId'], $result['pupilId'], $result['parentId'], $result['courseId'], $result['appointment'], $result['conferenceDayId'], $result['conferenceId'], $result['selfPlanned']);
        return $appointment;
    }
    
    
    /**
     * Function returns appointment object from database, based on appointmentId
     * @param int $appointmentId appointmentId to identify appointment
     * @return \Application_Model_DbTable_Appointments 
     */
    public static function getAppointmentById($appointmentId) {
        $appointmentId = (int) $appointmentId;
        
        $appointment = new Application_Model_DbTable_Appointments();
        $select = $appointment->select();
        $select->from('appointments', '*')
                ->where('appointmentId = ?')
                ->bind(array($appointmentId));
        $result = $appointment->fetchRow($select);
        
        return new Application_Model_DbTable_Appointments($result['appointmentId'], $result['staffId'], $result['pupilId'], $result['parentId'], $result['courseId'], $result['appointment'], $result['conferenceDayId'], $result['conferenceId']);
    }
    
    
    /**
     * Function returns appointment object from database, based on staffId and a few other params
     * @param int $pupilId pupilId to identify pupil
     * @param int $parentId parentId to identify parent
     * @param int $staffId staffId to identify staffmember
     * @param int $conferenceId conferenceId to identify confernce
     * @return null|\Application_Model_DbTable_Appointments 
     */
    public static function getAppointmentByStaffId($pupilId, $parentId, $staffId, $conferenceId) {
        $pupilId = (int) $pupilId;
        $parentId = (int) $parentId;
        $staffId = (int) $staffId;
        $conferenceId = (int) $conferenceId;
        
        $appointment = new Application_Model_DbTable_Appointments();
        $select = $appointment->select();
        $select->from('appointments', '*')
                ->where('staffId = ? AND pupilId = ? AND parentId = ? AND conferenceId = ? AND temporary IS NULL')
                ->bind(array($staffId, $pupilId, $parentId, $conferenceId));
        $result = $appointment->fetchRow($select);
        
        if ($result == null) {
            return null;
        }
        
        $appointment = new Application_Model_DbTable_Appointments($result['appointmentId'], $result['staffId'], $result['pupilId'], $result['parentId'], $result['courseId'], $result['appointment'], $result['conferenceDayId'], $result['conferenceId'], $result['selfPlanned']);
        return $appointment;
    }
    
    
    /**
     * Function returns appointment object from database, based on staffId and a few other params
     * @param int $pupilId pupilId to identify pupil
     * @param int $parentId parentId to identify parent
     * @param int $staffId staffId to identify staffmember
     * @param int $conferenceId conferenceId to identify confernce
     * @param string $type conference type (Type1 or Type2)
     * @return null|\Application_Model_DbTable_Appointments 
     */
    public static function getAppointmentByStaffIdOnType($pupilId, $parentId, $staffId, $conferenceId, $type) {
        $pupilId = (int) $pupilId;
        $parentId = (int) $parentId;
        $staffId = (int) $staffId;
        $conferenceId = (int) $conferenceId;
        $type = (strtolower($type) == 'type2' ? 'Type2' : 'Type1');
        
        $appointment = new Application_Model_DbTable_Appointments();
        $select = $appointment->select();
        $select->from('conferences', array())
                ->joinInner('conferencedays', 'conferences.conferenceId = conferencedays.conference', array())
                ->joinInner('appointments', 'conferencedays.conferenceDayId = appointments.conferenceDayId', '*')
                ->where('appointments.staffId = ? AND appointments.pupilId = ? AND appointments.parentId = ? AND conferences.conferenceId = ? AND conferencedays.type = ? AND appointments.temporary IS NULL')
                ->bind(array($staffId, $pupilId, $parentId, $conferenceId, $type));
        $result = $appointment->fetchRow($select);
        echo $select;
        if ($result == null) {
            return null;
        }
        
        $appointment = new Application_Model_DbTable_Appointments($result['appointmentId'], $result['staffId'], $result['pupilId'], $result['parentId'], $result['courseId'], $result['appointment'], $result['conferenceDayId'], $result['conferenceId'], $result['selfPlanned']);
        return $appointment;
    }
    
    
    /**
     * Function saves all temporary created appointments for a specified parent and pupil
     * @param int $pupilId pupilId to identify pupil
     * @param int $parentId parentId to identify
     * @param int $conferenceId conferenceId to identify conference
     */
    public static function saveAllTemporary($pupilId, $parentId, $conferenceId) {
        $pupilId = (int) $pupilId;
        $parentId = (int) $parentId;
        $conferenceId = (int) $conferenceId;
        
        $app = new Application_Model_DbTable_Appointments();
        $select = $app->select();
        $select->from('appointments', '*')
                ->where('pupilId = ? AND parentId = ? AND conferenceId = ? AND temporary IS NULL')
                ->bind(array($pupilId, $parentId, $conferenceId));
        $appointments = $app->fetchAll($select);
        
        $select = $app->select();
        $select->from('appointments', '*')
                ->where('pupilId = ? AND parentId = ? AND conferenceId = ? AND temporary IS NOT NULL')
                ->bind(array($pupilId, $parentId, $conferenceId));
        $newAppointments = $app->fetchAll($select);
        
        foreach ($newAppointments as $newAppointment) {
            foreach ($appointments as $appointment) {
                if ($appointment['parentId'] == $newAppointment['parentId'] && $appointment['pupilId'] == $newAppointment['pupilId'] && $appointment['staffId'] == $newAppointment['staffId'] && $appointment['courseId'] == $newAppointment['courseId']) {
                    $app->delete('appointmentId = ' . $app->_db->quote($appointment['appointmentId'], 'INT'));
                }
            }
            
            $app->update(array('temporary' => null), 'appointmentId = ' . $app->_db->quote($newAppointment['appointmentId'], 'INT'));
        }
    }
}

