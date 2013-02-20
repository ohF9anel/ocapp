<?php
/**
 * Db class for responsibles table 
 */
class Application_Model_DbTable_Responsibles extends Zend_Db_Table_Abstract
{
    protected $staffId, $yearId, $function, $remark;
    protected $_name = 'responsibles';


    
    public function __construct($staffId = null, $yearId = null, $function = null, $remark = null, $config = array()) {
        parent::__construct($config);
        $this->staffId = $staffId;
        $this->yearId = $yearId;
        $this->function = $function;
        $this->remark = $remark;
    }
    
    
    
    /*
     * GETTERS & SETTERS
     */
    public function getFunction() {
        return $this->function;
    }
    
    public function getRemark() {
        return $this->remark;
    }
    
    public function getStaffId() {
        return $this->staffId;
    }
    
    
    
    /**
     * Function returns full name of responsible
     * @return string full name of responsible
     */
    public function getStaff() {
        return Application_Model_DbTable_Staff::getFullNameById($this->staffId);
    }
    
    
    /**
     * Function returns year object for this responsible
     * @return \Application_Model_DbTable_Years 
     */
    public function getYear() {
        return Application_Model_DbTable_Years::getYearById($this->yearId);
    }
    
    

    /**
     * Function counts the amount of records in this table
     * @return int amount of records
     */
    public static function countEntries() {
        $responsible = new Application_Model_DbTable_Responsibles();
        $select = $responsible->select();
        $select->from('responsibles', 'count(*) As amount')
                ->setIntegrityCheck(false);
        $result = $responsible->fetchRow($select);
        
        return $result['amount'];
    }
    
    
    /**
     * Function deletes all data from responsibles table
     */
    public static function deleteAll() {
        $responsible = new Application_Model_DbTable_Responsibles();
        $responsible->delete('');
    }
    
    
    /**
     * Function returns array of all responsibles based on year
     * @param int $yearId yearId to identify year
     * @return array of \Application_Model_DbTable_Responsibles 
     */
    public static function getResponsiblesByYear($yearId) {
        $yearId = (int) $yearId;
        
        $responsible = new Application_Model_DbTable_Responsibles();
        $select = $responsible->select();
        $select->from('responsibles', '*')
                ->where('yearId = ?')
                ->bind(array($yearId));
        $result = $responsible->_fetch($select);
        
        $responsibles = array();
        foreach ($result as $row) {
            $responsibles[] = new Application_Model_DbTable_Responsibles($row['staffId'], $row['yearId'], $row['function'], $row['remark']);
        }
        return $responsibles;
    }
    
    
    /**
     * Function checks if a staffmemeber is a responsible of a year for which a conference is organized
     * @param int $staffId
     * @param int $conferenceId
     * @return boolean 
     */
    public static function isResponsibleOnConference($staffId, $conferenceId) {
        $staffId = (int) $staffId;
        $conferenceId = (int) $conferenceId;
        $responsible = new Application_Model_DbTable_Responsibles();
        
        $select = $responsible->select();
        $select->from('participants', 'count(*) AS amount')
                ->joinInner('groups', 'participants.groupId = groups.groupId', array())
                ->joinInner('responsibles', 'groups.yearId = responsibles.yearId', array())
                ->where('participants.conferenceId = ? AND responsibles.staffId = ?')
                ->bind(array($conferenceId, $staffId))
                ->setIntegrityCheck(false);
        $result = $responsible->fetchRow($select);
        
        if ($result['amount'] == 0) {
            return false;
        }
        
        return true;
    }
    
    
    /**
     * Function updates responsibles table based on a data array
     * @param array $data array of arrays with following structure array(('responsible', 'yearId', 'function', 'remark')
     * @throws Exception Er151 Update failed
     */
    public static function updateResponsibles($data) {
        $responsibles = new Application_Model_DbTable_Responsibles();
        
        $succes = true;
        $information = '';
        $responsibles->_db->beginTransaction();
        try {
            foreach($data as $responsible) {
                $information = $responsible['responsible'];
                
                //determine staffId based on Google-account
                $select1 = $responsibles->select();
                $select1->from('staff', 'staffId')
                        ->where('openId = ?')
                        ->bind(array($responsible['responsible']))
                        ->setIntegrityCheck(false);
                $result1 = $responsibles->fetchRow($select1);
                $responsibleId = $result1['staffId'];
                
                //determine if update or insert has to be executed
                $select2 = $responsibles->select();
                $select2->from('responsibles', 'count(*) AS amount')
                        ->where('staffId = ? AND yearId = ? AND function = ?')
                        ->bind(array($responsibleId, $responsible['yearId'], $responsible['function']))
                        ->setIntegrityCheck(false);
                $result2 = $responsibles->fetchRow($select2);
                if ($result2['amount'] == 0) {      //insert
                    $responsibles->insert(array('staffId' => $responsibleId, 'yearId' => $responsible['yearId'], 'function' => $responsible['function'], 'remark' => $responsible['remark']));
                }
                else {
                    $responsibles->update(array('staffId' => $responsibleId, 'yearId' => $responsible['yearId'], 'function' => $responsible['function'], 'remark' => $responsible['remark']), 'staffId = ' . $responsibles->_db->quote($responsibleId, 'INT') . ' AND yearId = ' . $responsibles->_db->quote($responsible['yearId'], 'INT') . ' AND function = ' . $responsibles->_db->quote($responsible['function'], 'VARCHAR'));
                }
            }
        }
        catch (Exception $e) {
            $succes = false;
            print_r($e);
        }
        
        if ($succes) {
            $responsibles->_db->commit();
        }
        else {
            $responsibles->_db->rollBack();
            throw new Exception('update of ' . $information . ' failed', 151);
        }
    }
}