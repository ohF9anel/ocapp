<?php
/**
 * Db class for years table 
 */
class Application_Model_DbTable_Years extends Zend_Db_Table_Abstract
{
    protected $_name = 'years';


    
    /**
     * Function returns year from database, based on its id
     * @param int $yearId yearId to identify year
     * @return int year
     * @throws Exception Er100 Id not found
     */
    public static function getYearById($yearId) {
        $yearId = (int) $yearId;
        
        $year = new Application_Model_DbTable_Years();
        $selection = $year->select();
        $selection->from('years', 'year')
                ->where('yearId = ?')
                ->bind(array($yearId));
        $result = $year->_fetch($select);
        
        if (sizeof($result) != 1) {
            throw new Exception('Er100 Id not found');
        }
        
        return (int) $result[0]['year'];
    }
}