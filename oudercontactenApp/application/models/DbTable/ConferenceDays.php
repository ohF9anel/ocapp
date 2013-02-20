<?php
/**
 * Db class for conferencedays table
 */
class Application_Model_DbTable_ConferenceDays extends Zend_Db_Table_Abstract
{
    protected $conferenceDayId, $conference, $date, $startTime, $endTime, $type, $primary;
    protected $_name = 'conferencedays';


    
    public function __construct($conferenceDayId = null, $conference = null, $date = null, $startTime = null, $endTime = null, $type = null, $primary = false, $config = array()) {
        parent::__construct($config);
        $this->conferenceDayId = $conferenceDayId;
        $this->conference = $conference;
        $this->date = $date;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->type = $type;
        $this->primary = $primary;
    }
    
    
    
    /*
     * GETTERS & SETTERS
     */
    public function getConference() {
        return $this->conference;
    }
    
    public function getConferenceDayId() {
        return $this->conferenceDayId;
    }    
    
    public function getDate() {
        return $this->date;
    }
    
    public function getEndTime() {
        return $this->endTime;
    }
    
    public function getIsPrimary() {
        if ($this->primary == 0) {
            return false;
        }
        else {
            return true;
        }
    }
    
    public function getStartTime() {
        return $this->startTime;
    }
    
    public function getType() {
        return $this->type;
    }
    
    public function setConference($conferenceId) {
        $conferenceId = (int) $conferenceId;
        $this->conference = $conferenceId;
    }
    
    public function setConferenceDayId($conferenceDayId) {
        $conferenceDayId = (int) $conferenceDayId;
        $this->conferenceDayId = $conferenceDayId;
    }
    
    public function setDate($date) {
        $date = (string) $date;
        $this->date = $date;
    }
    
    public function setEndTime($endTime) {
        $endTime = (string) $endTime;
        $this->endTime = $endTime;
    }
    
    public function setIsPrimary($primary) {
        $primary = (bool) $primary;
        $this->primary = $primary;
    }
    
    public function setStartTime($startTime) {
        $startTime = (string) $startTime;
        $this->startTime = $startTime;
    }
    
    public function setType($type) {
        $type = (string) $type;
        $this->type = $type;
    }
    
    
    
    /**
     * Function saves/updates conferenceDay object to database if all required field have a value
     * @return int id of inserted/updated conferenceDay
     * @throws Exception Er105 Unexisting Conference
     */
    public function save() {
        if ($this->conference == null || $this->date == null || $this->startTime == null || $this->endTime == null || $this->type == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        else if ($this->conferenceDayId != null) {
            $select = $this->select();
            $select->from('conferencedays', 'count(*) AS amount')
                    ->where('conferenceDayId = ?')
                    ->bind(array($this->conferenceDayId));
            $result = $this->fetchRow($select);
            
            if ($result['amount'] == 0) {
                throw new Exception('Er105 Unexisting Conference');
            }
            
            $this->update(array('conference' => $this->conference, 'date' => Date('Y-m-d', strtotime($this->date)), 'startTime' => $this->startTime, 'endTime' =>$this->endTime, 'type' => $this->type, 'primary' => ($this->primary ? true : false)), 'conferenceDayId = ' . $this->_db->quote($this->conferenceDayId, 'INT'));
            return $this->conferenceDayId;
        }
        else {
            return $this->insert(array('conference' => $this->conference, 'date' => Date('Y-m-d', strtotime($this->date)), 'startTime' => $this->startTime, 'endTime' =>$this->endTime, 'type' => $this->type, 'primary' => ($this->primary ? true : false)));
        }
    }
    
    
    
    /**
     * Function deletes all data from conferencedays table
     */
    public static function deleteAll() {
        $conferenceDay = new Application_Model_DbTable_ConferenceDays();
        $conferenceDay->delete('');
    }
    
    
    /**
     * Function deletes all conferencedays from database, based on a specified conference
     * @param int $conferenceId conferenceId to indentify conference
     */
    public static function deleteConference($conferenceId) {
        $conferenceId = (int) $conferenceId;
        $conferenceDay = new Application_Model_DbTable_ConferenceDays();
        
        $conferenceDay->delete('conference = ' . $conferenceDay->_db->quote($conferenceId, 'INT'));
    }
    
    
    /**
     * Function returns conferenceDay object from database, based on its id
     * @param int $conferenceDayId conferenceDayId to identify conferenceDay
     * @return \Application_Model_DbTable_ConferenceDays
     * @throws Exception Er106 Unexisting ConferenceDays
     */
    public static function getConferenceDayById($conferenceDayId) {
        $conferenceDayId = (int) $conferenceDayId;
        
        $conferenceDay = new Application_Model_DbTable_ConferenceDays();
        $select = $conferenceDay->select();
        $select->from('conferencedays', '*')
                ->where('conferenceDayId = ?')
                ->bind(array($conferenceDayId))
                ->setIntegrityCheck(false);
        $result = $conferenceDay->fetchRow($select);
        
        if (sizeof($result) != 1) {
            throw new Exception('Er106 Unexisting ConferenceDays');
        }
        
        $conferenceDay = new Application_Model_DbTable_ConferenceDays($result['conferenceDayId'], $result['conference'], $result['date'], $result['startTime'], $result['endTime'], $result['type'], $result['primary']);
        return $conferenceDay;
    }
    
    
    /**
     * Function returns an array of all conferenceDays of a specified conference
     * @param int $conferenceId conferenceId to identify conference
     * @return array of conferenceDay objects
     * @throws Exception Er105 Unexisting Conference
     */
    public static function getConferenceDaysOfConference($conferenceId) {
        $conferenceId = (int) $conferenceId;
        
        $conferenceDay = new Application_Model_DbTable_ConferenceDays();
        $select = $conferenceDay->select();
        $select->from('conferencedays', '*')
                ->where('conference = ?')
                ->bind(array($conferenceId));
        $result = $conferenceDay->fetchAll($select);
        
        //check if conference exists
        if (sizeof($result) == 0) {
            throw new Exception('Er105 Unexisting Conference');
        }
        
        $conferenceDays = array();
        foreach ($result as $row) {
            $conferenceDays[] = new Application_Model_DbTable_ConferenceDays($row['conferenceDayId'], $row['conference'], $row['date'], $row['startTime'], $row['endTime'], $row['type'], $row['primary']);
        }
        return $conferenceDays;
    }
    
    
    
    /**
     * Function returns an array of all available conferenceDays of a specified staffmember
     * @param int $conferenceId conferenceId to identify conference
     * @param int $staffId staffId to identify staffmember
     * @return array of conferenceDay objects
     * @throws Exception Er130 No Conference days available
     */
    public static function getConferenceDaysOfTeacher($conferenceId, $staffId) {
        $conferenceId = (int) $conferenceId;
        $staffId = (int) $staffId;
        
        $conferenceDay = new Application_Model_DbTable_ConferenceDays();
        $select = $conferenceDay->select();
        $select->from('conferencedays', '*')
                ->joinInner('presentdays', 'conferencedays.conferenceDayId = presentdays.conferenceDayId', array('earlyStart', 'lateEnd'))
                ->where('staffId = ? AND conference = ?')
                ->bind(array($staffId, $conferenceId))
                ->setIntegrityCheck(false);
        $result = $conferenceDay->fetchAll($select);
        
        //check if conference days are available
        if (sizeof($result) == 0) {
            throw new Exception('Er130 No Conference days available');
        }
        
        $conferenceDays = array();
        foreach ($result as $row) {
            $day = new Application_Model_DbTable_ConferenceDays($row['conferenceDayId'], $row['conference'], $row['date'], $row['startTime'], $row['endTime'], $row['type'], $row['primary']);
            if ($row['earlyStart'] != null) {
                $day->startTime = $row['earlyStart'];
            }
            if ($row['lateEnd'] != null) {
                $day->endTime = $row['lateEnd'];
            }
            $conferenceDays[] = $day;
        }
        return $conferenceDays;
    }
    
    
    /**
     * Function returns date of specified conferenceDay
     * @param int $conferenceDayId conferenceDayId to identify conferenceDay
     * @return string date (d-m-Y)
     * @throws Exception Er106 Unexisting ConferenceDays
     */
    public static function getDateByConferenceDay($conferenceDayId) {
        $conferenceDayId = (int) $conferenceDayId;
        
        $conferenceDay = new Application_Model_DbTable_ConferenceDays();
        $select = $conferenceDay->select();
        $select->from('conferencedays', 'date')
                ->where('conferenceDayId = ?')
                ->bind(array($conferenceDayId))
                ->setIntegrityCheck(false);
        $result = $conferenceDay->fetchAll($select);
        
        if (sizeof($result) != 1) {
            throw new Exception('Er106 Unexisting ConferenceDays');
        }
        
        return date('d-m-Y', strtotime($result[0]['date']));
    }
}

