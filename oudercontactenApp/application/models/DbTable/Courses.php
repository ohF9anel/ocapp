<?php
/**
 * Db class for courses table 
 */
class Application_Model_DbTable_Courses extends Zend_Db_Table_Abstract
{
    protected $courseId, $teacherId, $course, $remark;
    protected $_name = 'courses';


    
    public function __construct($courseId = null, $teacherId = null, $course = null, $remark = null, $config = array()) {
        parent::__construct($config);
        $this->courseId = $courseId;
        $this->teacherId = $teacherId;
        $this->course = $course;
        $this->remark = $remark;
    }
    
    
    
    /*
     * GETTERS & SETTERS
     */
    public function getCourse() {
        return $this->course;
    }
    
    public function getCourseId() {
        return $this->courseId;
    }
    
    public function getRemark() {
        return $this->remark;
    }
    
    public function getTeacherId() {
        return $this->teacherId;
    }

    
    
    /**
     * Function returns full name of teacher
     * @return string full name of teacher
     */
    public function getTeacher() {
        return Application_Model_DbTable_Staff::getFullNameById($this->teacherId);
    }
    
    
    /**
     * Function return staff object of teacher
     * @return \Application_Model_DbTable_Staff 
     */
    public function getTeacherAsStaff() {
        return Application_Model_DbTable_Staff::getStaffById($this->teacherId);
    }
    
    
    
    /**
     * Function counts the amount of records in this table
     * @return int amount of records
     */
    public static function countEntries() {
        $course = new Application_Model_DbTable_Courses();
        $select = $course->select();
        $select->from('courses', 'count(*) As amount')
                ->setIntegrityCheck(false);
        $result = $course->fetchRow($select);
        
        return $result['amount'];
    }
    
    
    /**
     * Function deletes all data from courses table 
     */
    public static function deleteAll() {
        $course = new Application_Model_DbTable_Courses();
        $course->delete('');
    }
    
    
    /**
     * Function returns course object, based on it's id
     * @param type $courseId courseId to identify course
     * @return \Application_Model_DbTable_Courses
     * @throws Exception 
     */
    public static function getCourseById($courseId) {
        $courseId = (int) $courseId;
        
        $course = new Application_Model_DbTable_Courses();
        $select = $course->select();
        $select->from('courses', '*')
                ->where('courseId = ?')
                ->bind(array($courseId))
                ->setIntegrityCheck(false);
        $result = $course->_fetch($select);
        
        //check if group exists
        if (sizeof($result) < 1) {
            throw new Exception('Er104 Unexisting course');
        }
        
        $course->courseId = $result[0]['courseId'];
        $course->teacherId = $result[0]['teacherId'];
        $course->course = $result[0]['course'];
        $course->remark = $result[0]['remark'];
        return $course;
    }
    
    
    /**
     * Function checks if a staffmemeber is a teacher of a course for which a conference is organized
     * @param int $staffId staffId to identify staffmember
     * @param int $conferenceId conferenceId to identify conference
     * @return boolean whether or not teacher of course is participating in conference
     */
    public static function isTeacherOnConference($staffId, $conferenceId) {
        $staffId = (int) $staffId;
        $conferenceId = (int) $conferenceId;
        $course = new Application_Model_DbTable_Courses();
        
        $select = $course->select();
        $select->from('participants', 'count(*) AS amount')
                ->joinInner('groups_have_courses', 'participants.groupId = groups_have_courses.groupId', array())
                ->joinInner('courses', 'groups_have_courses.courseId = courses.courseId', array())
                ->where('courses.teacherId = ? AND participants.conferenceId = ?')
                ->bind(array($staffId, $conferenceId))
                ->setIntegrityCheck(false);
        $result = $course->fetchRow($select);
        
        if ($result['amount'] == 0) {
            return false;
        }
        
        return true;
    }
    
    
    /**
     * Function updates courses table based on a data array
     * @param array $data array of arrays with following structure array(('teacher', 'course', 'uuid'))
     * @throws Exception Er151 Update failed
     */
    public static function updateCourses($data) {
        $courses = new Application_Model_DbTable_Courses();
        
        $succes = true;
        $information = '';
        $courses->_db->beginTransaction();
        try {
            foreach($data as $course) {
                $information = $course['uuid'];
                
                //determine staffId, based on google-account
                $select1 = $courses->select();
                $select1->from('staff', 'staffId')
                        ->where('openId = ?')
                        ->bind(array($course['teacher']))
                        ->setIntegritycheck(false);
                $result1 = $courses->fetchRow($select1);
                $teacherId = $result1['staffId'];
                
                //determine if update or insert has to be executed
                $select2 = $courses->select();
                $select2->from('courses', 'count(*) As amount')
                        ->where('uuid = ?')
                        ->bind(array($course['uuid']))
                        ->setIntegrityCheck(false);
                $result2 = $courses->fetchRow($select2);
                if ($result2['amount'] == 0) {      //insert
                    $courses->insert(array('teacherId' => $teacherId, 'course' => $course['course'], 'uuid' => $course['uuid'], 'remark' => $course['remark']));
                }
                else {      //update
                    $courses->update(array('teacherId' => $teacherId, 'course' => $course['course'], 'remark' => $course['remark']), 'uuid = ' . $courses->_db->quote($course['uuid'], 'VARCHAR'));
                }
            }            
        }
        catch (Exception $e) {
            $succes = false;
        }
        
        if ($succes) {
            $courses->_db->commit();
        }
        else {
            $courses->_db->rollBack();
            throw new Exception('update of ' . $information . ' failed', 151);
        }
    }
}