<?php
/**
 * Db class for presentdays table 
 */
class Application_Model_DbTable_PresentDays extends Zend_Db_Table_Abstract
{
    protected $conferenceDayId, $staffId, $earlyStart, $lateEnd;
    protected $_name = 'presentdays';


    public function __construct($conferenceDayId = null, $staffId = null, $earlyStart = null, $lateEnd = null, $config = array()) {
        parent::__construct($config);
        $this->conferenceDayId = $conferenceDayId;
        $this->staffId = $staffId;
        $this->earlyStart = $earlyStart;
        $this->lateEnd = $lateEnd;
    }
    
    
    
    /*
     * GETTERS & SETTERS
     */
    public function getConferenceDayId() {
        return $this->conferenceDayId;
    }
    
    public function getEarlyStart() {
        return $this->earlyStart;
    }
    
    public function getLateEnd() {
        return $this->lateEnd;
    }
    
    public function getStaffId() {
        return $this->staffId;
    }
    
    public function setEarlyStart($earlystart) {
        $this->earlyStart = $earlystart;
    }
    
    public function setLateEnd($lateEnd) {
        $this->lateEnd = $lateEnd;
    }
    
    
    
    /**
     * Function deletes present day object from database
     * @throws Exception Er150 Null in non optional field
     */
    public function deletePresentDay() {
        if ($this->conferenceDayId == null || $this->staffId == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        
        $this->delete('conferenceDayId = ' . $this->_db->quote($this->conferenceDayId, 'INT') . ' AND staffId = ' . $this->_db->quote($this->staffId, 'INT'));
    }
    
    
    /**
     * Functions saves/updates presentDays object to database if all required field have a value
     * @throws Exception Er150 Null in non optional field
     */
    public function save() {
        if ($this->conferenceDayId == null || $this->staffId == null || $this->earlyStart == null || $this->lateEnd == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        
        $select = $this->select();
        $select->from('presentdays', 'count(*) AS amount')
                ->where('conferenceDayId = ? AND staffId = ?')
                ->bind(array($this->conferenceDayId, $this->staffId));
        $result = $this->fetchRow($select);
        
        if ($result['amount'] == 0) {
            $this->insert(array('conferenceDayId' => $this->conferenceDayId, 'staffId' => $this->staffId, 'earlyStart' => $this->earlyStart, 'lateEnd' =>$this->lateEnd));
        }
        else {
            $this->update(array('conferenceDayId' => $this->conferenceDayId, 'staffId' => $this->staffId, 'earlyStart' => $this->earlyStart, 'lateEnd' =>$this->lateEnd), 'conferenceDayId = ' . $this->_db->quote($this->conferenceDayId, 'INT') . ' AND staffId = ' . $this->_db->quote($this->staffId, 'INT'));
        }
    }
    
    

    /**
     * Function deletes all data from presentdays table
     */
    public static function deleteAll() {
        $presentDay = new Application_Model_DbTable_PresentDays();
        $presentDay->delete('');
    }
    
    
    /**
     * Function deletes all presentdays based on a specified conference
     * @param int $conferenceId conferenceId to identify conference
     */
    public static function deleteConference($conferenceId) {
        $conferenceId = (int) $conferenceId;        
        $presentdays = new Application_Model_DbTable_PresentDays();
        
        $days = Application_Model_DbTable_ConferenceDays::getConferenceDaysOfConference($conferenceId);
        foreach ($days as $day) {
            $presentdays->delete('conferenceDayId = ' . $presentdays->_db->quote($day->getConferenceDayId(), 'INT'));
        }
    }
    
    
    /**
     * Functions sets all obligated present days for staffmembers for a specified conference
     * @param type $conferenceId conferenceId to identify conference
     */
    public static function setObligatedPresence($conferenceId) {
        $conferenceId = (int) $conferenceId;        
        $presentDay = new Application_Model_DbTable_PresentDays();
        
        //loop all conference days
        $select1 = $presentDay->select();
        $select1->from('conferencedays' , array('conferenceDayId', 'primary', 'type'))
                ->where('conference = ?')
                ->bind(array($conferenceId))
                ->setIntegrityCheck(false);
        $result1 = $presentDay->fetchAll($select1);
        
        foreach ($result1 as $res1) {
            //check if conferenceDay is obligated
            if (!$res1['primary']) {
                continue;
            }
            
            if ($res1['type'] == 'Type1') { //titular
                //loop all titulars
                $select2 = $presentDay->select();
                $select2->from('groups', array('titularId'))
                        ->joinInner('participants', 'groups.groupId = participants.groupId', array())
                        ->where('participants.conferenceId = ?')
                        ->bind(array($conferenceId))
                        ->distinct()
                        ->setIntegrityCheck(false);
                $result2 = $presentDay->fetchAll($select2);
                foreach ($result2 as $res2) {
                    $select21 = $presentDay->select();
                    $select21->from('presentdays', 'count(*) AS amount')
                            ->where('conferenceDayId = ? AND staffId = ?')
                            ->bind(array($res1['conferenceDayId'], $res2['titularId']));
                    $result21 = $presentDay->fetchRow($select21);
                    
                    if ($result21['amount'] == 0) {
                        $presentDay->insert(array('conferenceDayId' => $res1['conferenceDayId'], 'staffId' => $res2['titularId']));
                    }
                }
                
                //loop all responsibles
                $select3 = $presentDay->select();
                $select3->from('participants', array())
                        ->joinInner('groups', 'participants.groupId = groups.groupId', array())
                        ->joinInner('responsibles', 'groups.yearId = responsibles.yearId', array('staffId'))
                        ->where('participants.conferenceId = ?')
                        ->bind(array($conferenceId))
                        ->distinct()
                        ->setIntegrityCheck(false);
                $result3 = $presentDay->fetchAll($select3);
                foreach ($result3 as $res3) {
                    $select31 = $presentDay->select();
                    $select31->from('presentdays', 'count(*) AS amount')
                            ->where('conferenceDayId = ? AND staffId = ?')
                            ->bind(array($res1['conferenceDayId'], $res3['staffId']));
                    $result31 = $presentDay->fetchRow($select31);
                    
                    if ($result31['amount'] == 0) {
                        $presentDay->insert(array('conferenceDayId' => $res1['conferenceDayId'], 'staffId' => $res3['staffId']));
                    }
                }
            }
            else { //teacher
                //loop all teachers
                $select4 = $presentDay->select();
                $select4->from('participants', array())
                        ->joinInner('groups_have_courses', 'participants.groupId = groups_have_courses.groupId', array())
                        ->joinInner('courses', 'groups_have_courses.courseId = courses.courseId', array('teacherId'))
                        ->where('participants.conferenceId = ?')
                        ->bind(array($conferenceId))
                        ->distinct()
                        ->setIntegrityCheck(false);
                $result4 = $presentDay->fetchAll($select4);
                foreach ($result4 as $res4) {
                    $select41 = $presentDay->select();
                    $select41->from('presentdays', 'count(*) AS amount')
                            ->where('conferenceDayId = ? AND staffId = ?')
                            ->bind(array($res1['conferenceDayId'], $res4['teacherId']));
                    $result41 = $presentDay->fetchRow($select41);
                    
                    if ($result41['amount'] == 0) {
                        $presentDay->insert(array('conferenceDayId' => $res1['conferenceDayId'], 'staffId' => $res4['teacherId']));
                    }
                }
            }
        }
    }
}