<?php
/**
 * Class to represent a quiz.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2018 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.4.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer;


/**
 * Class for a single quiz.
 */
class Quiz
{
    const PASSED = 4;
    const WARNING = 2;
    const FAILED = 1;

    /** Base tag for caching.
     * @const string */
    const TAG = 'quiz';

    /** Quiz DB record ID.
     * @var integer */
    private $id = 0;

    /** Old DB record ID. Used when editing or duplicating a quiz.
     * @var integer */
    private $old_id = 0;

    /** Current user ID.
     * @var integer */
    private $uid = 0;

    /** Name for the quiz.
     * @var string */
    private $name = '';

    /** Text message shown at the start of a quiz.
     * @var string */
    private $introtext = '';

    /** Custom fields for data collection at the start of a quiz.
     * @var string */
    private $introfields = '';

    /** Levels to determine pass/fail scores.
     * @var string */
    private $levels;

    /** Quiz fields, an array of objects.
    * @var array */
    private $fields = array();

    /** Message to show the taker if the quiz is passed.
     * @var string */
    private $pass_msg = '';

    /** Message to show the taker if the quiz is failed.
     * @var string */
    private $fail_msg = '';

    /** Result object for a user submission.
    * @var object */
    private $Result;

    /** Database ID of a result record.
    * @var integer */
    private $res_id = 0;

    /** Reward record ID.
     * @var integer */
    private $reward_id = 0;

    /** Status for which a reward is granted.
     * 1 = failed (not used)
     * 2 = passed
     * 3 = either
     * @var integer */
    private $reward_status = 3;

    /** Flag to indicate that submission is allowed.
     * Turns off the submit button when previewing.
     * @var boolean */
    private $allow_submit = 1;

    /** Flag to indicate that this is a new quiz.
     * @var boolean */
    private $isNew = true;

    /** Scoring name=>value mappings.
     * @var array */
    public static $grades = array(
        1 => 'danger',
        2 => 'warning',
        4 => 'success',
    );


    /**
     * Constructor.
     * Create a quizzer object for the specified user ID, or the current
     * user if none specified.
     * If a key is requested, then just build the quizzer for that key (requires a $uid).
     *
     * @param   integer $id     Quiz ID, empty to create a new record
     */
    function __construct($id = '')
    {
        global $_USER, $_CONF_QUIZ, $_TABLES;

        $this->setUid($_USER['uid']);
        $this->Result = NULL;

        if (is_array($id)) {
            $this->setVars($id, true);
            $this->isNew = false;
        } elseif (!empty($id)) {
            $id = COM_sanitizeID($id);
            $this->setID($id);
            $this->isNew = false;
            if (!$this->Read($id)) {
                $this->setID(COM_makeSid());
                $this->isNew = true;
            }
        } else {
            $this->isNew = true;
            $def_group = (int)DB_getItem(
                $_TABLES['groups'],
                'grp_id',
                "grp_name='quizzer Admin'"
            );
            if ($def_group < 1) {
                $def_group = 1;     // default to Root
            }
            $this->setFillGid($_CONF_QUIZ['fill_gid'])
                ->setGid($def_group)
                ->setEnabled(1)
                ->setID(COM_makeSid())
                ->setIntrotext('')
                ->setPassMsg('')
                ->setFailMsg('')
                ->setIntroFields('')
                ->setName('')
                ->setOnetime(0)
                ->setNumQ(0)
                ->setLevels(0);
        }
    }


    /**
     * Get an instance of a quiz object.
     *
     * @param   string  $quiz_id     Quiz ID
     * @return  object      Quiz object
     */
    public static function getInstance($quiz_id)
    {
        $key = self::TAG . '_' . $quiz_id;
        $Obj = Cache::get($key);
        if ($Obj === NULL) {
            $Obj = new self($quiz_id);
            Cache::set($quiz_id, $Obj);
        }
        return $Obj;
    }


    /**
     * Set the current user ID.
     *
     * @param   integer $uid    User ID
     * @return  object  $this
     */
    private function setUid($uid)
    {
        $this->uid = (int)$uid;
        if ($this->uid < 1) {
            $this->uid = 1;    // Anonymous
        }
        return $this;
    }


    /**
     * Get the current user ID.
     *
     * @return  integer     User ID
     */
    public function getUid()
    {
        return (int)$this->uid;
    }


    /**
     * Set the quiz record ID.
     *
     * @param   string  $id     Record ID for quiz
     * @return  object  $this
     */
    private function setID($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * Get the quiz reord ID.
     *
     * @return  string  Record ID of quiz
     */
    public function getID()
    {
        return $this->id;
    }


    /**
     * Set the quiz name.
     *
     * @param   string  $name   Name of quiz
     * @return  object  $this
     */
    private function setName($name)
    {
        $this->name = $name;
        return $this;
    }


    /**
     * Get the quiz name.
     *
     * @return  string      Name of quiz
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Set the intro message text.
     *
     * @param   string  $text   Intro text
     * @return  object  $this
     */
    private function setIntrotext($text)
    {
        $this->introtext = $text;
        return $this;
    }


    /**
     * Get the intro message text.
     *
     * @return  string      Intro text
     */
    public function getIntrotext()
    {
        return $this->introtext;
    }


    /**
     * Set the intro custom fields
     *
     * @param   string  $text   Intro fields
     * @return  object  $this
     */
    private function setIntrofields($text)
    {
        $this->introfields = $text;
        return $this;
    }


    /**
     * Get the intro custom fields
     *
     * @return  string      Intro fields
     */
    public function getIntrofields()
    {
        return $this->introfields;
    }


    /**
     * Set the message shown when a quiz is passed.
     *
     * @param   string  $text   Message text
     * @return  object  $this
     */
    private function setPassMsg($text)
    {
        $this->pass_msg = $text;
        return $this;
    }


    /**
     * Get the message to show when a quiz is passed.
     *
     * @return  string      Message text
     */
    public function getPassMsg()
    {
        return $this->pass_msg;
    }


    /**
     * Set the message shown when a quiz is failed.
     *
     * @param   string  $text   Message text
     * @return  object  $this
     */
    private function setFailMsg($text)
    {
        $this->fail_msg = $text;
        return $this;
    }


    /**
     * Get the message to show when a quiz is failed.
     *
     * @return  string      Message text
     */
    public function getFailMsg()
    {
        return $this->fail_msg;
    }


    /**
     * Set the levels for passing or failing a quiz.
     *
     * @param   string  $text   Serialized levels
     * @return  object  $this
     */
    private function setLevels($text)
    {
        $this->levels = $text;
        return $this;
    }


    /**
     * Get the levels for passing or failing a quiz.
     *
     * @return  string      Serialized levels
     */
    public function getLevels()
    {
        return $this->levels;
    }


    /**
     * Set the ID of the group allowed to take the quiz.
     *
     * @param   integer $gid    Authorized Group ID
     * @return  object  $this
     */
    private function setFillGid($gid)
    {
        $this->fill_gid = (int)$gid;
        return $this;
    }


    /**
     * Get the authorized group ID for the quiz.
     *
     * @return  integer     Group ID
     */
    public function getFillGid()
    {
        return (int)$this->fill_gid;
    }


    /**
     * Set the ID of the owner group for this quiz.
     *
     * @param   integer $gid    Owner Group ID
     * @return  object  $this
     */
    private function setGid($gid)
    {
        $this->group_id = (int)$gid;
        return $this;
    }


    /**
     * Get the owner group ID for the quiz.
     *
     * @return  integer     Group ID
     */
    public function getGid()
    {
        return (int)$this->group_id;
    }


    /**
     * Set the ID of the group allowed to view quiz results.
     *
     * @param   integer $gid    Results viewer group ID
     * @return  object  $this
     */
    private function setResultsGid($gid)
    {
        $this->results_gid = (int)$gid;
        return $this;
    }


    /**
     * Get the results viewer group ID.
     *
     * @return  integer     Group ID
     */
    public function getResultsGid()
    {
        return (int)$this->results_gid;
    }


    /**
     * Set the flag to indicate if or how a quiz may be taken multiple times.
     *
     * @param   integer $flag   Ontime status flag value
     * @return  object  $this
     */
    private function setOnetime($flag)
    {
        $this->onetime = (int)$flag;
        return $this;
    }


    /**
     * Get the one-time status flag.
     *
     * @return  integer     Value of onetime flag
     */
    public function getOnetime()
    {
        return (int)$this->onetime;
    }


    /**
     * Set the number of questions to be presented to the quiz taker.
     *
     * @param   integer $numq   Number of questions
     * @return  object  $this
     */
    private function setNumQ($numq)
    {
        $this->num_q = (int)$numq;
        return $this;
    }


    /**
     * Get the number of questions to be shown in a quiz.
     *
     * @return  integer     Number of questions
     */
    public function getNumQ()
    {
        return (int)$this->num_q;
    }


    /**
     * Set the flag indicating whether this quiz is enabled (published).
     *
     * @param   integer $flag   Value of flag, 1 to enable, 0 to disable
     * @return  object  $this
     */
    private function setEnabled($flag)
    {
        $this->enabled = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Get the enabled flag for this quiz.
     *
     * @return  integer     Value of flag, 1 or zero
     */
    public function getEnabled()
    {
        return $this->enabled ? 1 : 0;
    }


    /**
     * Get the questions for the quiz.
     *
     * @return  array       Array of question objects
     */
    public function getQuestions()
    {
        return $this->questions;
    }


    /**
     * Set the reward record ID.
     *
     * @param   integer $id     Reward record ID
     * @return  object  $this
     */
    private function setRewardID($id)
    {
        $this->reward_id = (int)$id;
        return $this;
    }


    /**
     * Get the reward record ID.
     *
     * @return  integer     Reward ID, 0 if none
     */
    public function getRewardID()
    {
        return (int)$this->reward_id;
    }


    /**
     * Set the reward record minimum status.
     *
     * @param   integer $status     Minimu status
     * @return  object  $this
     */
    private function setRewardStatus($status)
    {
        $this->reward_status = (int)$status;
        return $this;
    }


    /**
     * Get the minimum result status (grade) required for a reward.
     *
     * @return  integer     Minimum grade
     */
    public function getRewardStatus()
    {
        return (int)$this->reward_status;
    }


    /**
     * Check if this is a new record.
     *
     * @return  integer     1 if new, 0 if not
     */
    public function isNew()
    {
        return $this->isNew ? 1 : 0;
    }


    /**
     * Read all quiz fields.
     *
     * @param  string  $id      Optional quiz ID.
     */
    public function Read($id = '')
    {
        global $_TABLES;

        $this->setID($id);

        // Clear out any existing items, in case we're reusing this instance.
        $this->fields = array();

        $sql = "SELECT * FROM {$_TABLES['quizzer_quizzes']}
                WHERE id = '" . $this->id . "'";
        //echo $sql;die;
        $res1 = DB_query($sql, 1);
        if (!$res1 || DB_numRows($res1) < 1) {
            return false;
        }

        $A = DB_fetchArray($res1, false);
        $this->setVars($A, true);
        return true;
    }


    /**
     * Set all values for this quiz into local variables.
     *
     * @param   array   $A          Array of values to use.
     * @param   boolean $fromdb     Indicate if $A is from the DB or a quiz.
     */
    function setVars($A, $fromdb=false)
    {
        if (!is_array($A))
            return false;

        $this->setID($A['id'])
            ->setName($A['name'])
            ->setIntroText($A['introtext'])
            ->setPassMsg($A['pass_msg'])
            ->setFailMsg($A['fail_msg'])
            ->setIntroFields($A['introfields'])
            ->setFillGid($A['fill_gid'])
            ->setOnetime($A['onetime'])
            ->setNumQ($A['num_q'])
            ->setLevels($A['levels'])
            ->setRewardID($A['reward_id'])
            ->setRewardStatus($A['reward_status']);

        if ($fromdb) {
            // Coming from the database
            $this->setEnabled($A['enabled']);
            $this->old_id = $A['id'];
        } else {
            // This is coming from the quiz edit form
            $this->setEnabled(isset($A['enabled']) ? 1 : 0);
            $this->old_id = $A['old_id'];
        }
    }


    /**
     * Create the edit quiz for all the quizzer variables.
     * Checks the type of edit being done to select the right template.
     *
     * @param   string  $type   Type of editing- 'edit' or 'registration'
     * @return  string          HTML for edit quiz
     */
    public function editQuiz($type = 'edit')
    {
        global $_CONF_QUIZ, $_USER, $LANG_QUIZ;

        if (isset($_POST['referrer'])) {
            $referrer = $_POST['referrer'];
        } elseif (isset($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
        } else {
            $referrer = '';
        }

        $T = new \Template(QUIZ_PI_PATH . '/templates/admin');
        $T->set_file(array(
            'editquiz' => 'editquiz.thtml',
            'tips'  => 'tooltipster.thtml',
        ) );

        $T->set_var(array(
            'id'    => $this->getID(),
            'old_id' => $this->old_id,
            'name'  => $this->getName(),
            'introtext' => $this->getIntrotext(),
            'pass_msg' => $this->getPassMsg(),
            'fail_msg' => $this->getFailMsg(),
            'introfields' => $this->getIntrofields(),
            'ena_chk' => $this->getEnabled() ? 'checked="checked"' : '',
            'email' => $this->email,
            'user_group_dropdown' => $this->_groupDropdown(),
            'doc_url'   => QUIZ_getDocURL('quiz_def.html'),
            'referrer'      => $referrer,
            'lang_confirm_delete' => $LANG_QUIZ['confirm_quiz_delete'],
            'one_chk_' . $this->getOnetime() => 'selected="selected"',
            'num_q'     => $this->getNumQ(),
            'levels'    => $this->getLevels(),
            'reward_options' => Reward::optionList($this->reward_id),
            'rs_' . $this->reward_status => 'checked="checked"',
        ) );
        if (!$this->isNew) {
            $T->set_var('candelete', 'true');
        }
        $T->parse('tooltipster_js', 'tips');
        $T->parse('output', 'editquiz');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Save all quizzer items to the database.
     * Calls each field's Save() method iff there is a corresponding
     * value set in the $vals array.
     *
     * @param   array   $vals   Values to save, from $_POST, normally
     * @return  string      HTML error list, or empty for success
     */
    public function SaveData($vals)
    {
        global $_TABLES;

        // Check that $vals is an array; should be from $_POST;
        if (!is_array($vals)) return false;

        // Check that the user has access to fill out this quiz
        if (!$this->hasAccess(QUIZ_ACCESS_FILL)) return false;

        if (isset($vals['res_id']) && !empty($vals['res_id'])) {
            $res_id = (int)$vals['res_id'];
            $newSubmission = false;
        } else {
            $newSubmission = true;
            $res_id = 0;
        }

        // Check whether the submission can be updated and, if so, whether
        // the res_id from the quiz is correct
        if ($this->getOnetime() == QUIZ_LIMIT_ONCE) {
            if ($res_id == 0) {
                // even if no result ID given, see if there is one
                $res_id = Result::FindResult($this->id, $this->uid);
            }
            if ($res_id > 0) return false;       // can't update the submission
        }   // else, multiple submissions are allowed

        // Validate the quiz fields
        $msg = '';
        $invalid_flds = '';
        foreach ($this->getQuestions() as $Q) {
            $msg = $Q->Validate($vals);
            if (!empty($msg)) {
                $invalid_flds .= "<li>$msg</li>\n";
            }
        }

        // All fields are valid, carry on with the onsubmit actions
        $onsubmit = $this->onsubmit;
        if ($onsubmit & QUIZ_ACTION_STORE) {
            // Save data to the database
            $this->Result = new Result($res_id);
            $this->Result->setInstance($this->instance_id);
            $this->Result->setModerate($this->moderate);
            $this->res_id = $this->Result->SaveData($this->id, $this->questions,
                    $vals, $this->uid);
        } else {
            $this->res_id = false;
        }
        return '';
    }


    /**
     * Save a quiz definition.
     * If creating a new quiz, or changing the Quiz ID of an existing one,
     * then the DB is checked to ensure that the ID is unique.
     *
     * @param   array   $A      Array of values (e.g. $_POST)
     * @return  string      Error message, empty on success
     */
    function SaveDef($A = '')
    {
        global $_TABLES, $LANG_QUIZ;

        if (is_array($A)) {
            $this->setVars($A, false);
        }

        $frm_name = $this->name;
        if (empty($frm_name)) {
            return $LANG_QUIZ['err_name_required'];
        }

        // If saving a new record or changing the ID of an existing one,
        // make sure the new quiz ID doesn't already exist.
        $changingID = false;
        if ($this->isNew || (!$this->isNew && $this->id != $this->old_id)) {
            if (!$this->isNew) $changingID = true;
            $x = DB_count($_TABLES['quizzer_quizzes'], 'id', $this->id);
            if ($x > 0) {
                $this->id = COM_makeSid();
            }
        }

        if (!$this->isNew && $this->old_id != '') {
            $sql1 = "UPDATE {$_TABLES['quizzer_quizzes']} ";
            $sql3 = " WHERE id = '{$this->old_id}'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['quizzer_quizzes']} ";
            $sql3 = '';
        }
        $sql2 = "SET id = '{$this->id}',
            name = '" . DB_escapeString($this->name) . "',
            introtext = '" . DB_escapeString($this->introtext) . "',
            introfields= '" . DB_escapeString($this->introfields) . "',
            pass_msg= '" . DB_escapeString($this->pass_msg) . "',
            fail_msg= '" . DB_escapeString($this->fail_msg) . "',
            enabled = '{$this->enabled}',
            fill_gid = '{$this->fill_gid}',
            onetime = '{$this->onetime}',
            num_q = {$this->num_q},
            reward_id = {$this->reward_id},
            reward_status = {$this->reward_status},
            levels = '" . DB_escapeString($this->levels) . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql, 1);

        if (!DB_error()) {
            // Now, if the ID was changed, update the field & results tables
            if (!$this->isNew && $changingID) {
                DB_query("UPDATE {$_TABLES['quizzer_results']}
                        SET quiz_id = '{$this->id}'
                        WHERE quiz_id = '{$this->old_id}'");
                DB_query("UPDATE {$_TABLES['quizzer_questions']}
                        SET quiz_id = '{$this->id}'
                        WHERE quiz_id = '{$this->old_id}'");
            }
            CTL_clearCache();       // so autotags pick up changes
            Cache::clear();         // Clear plugin cache
            $msg = '';              // no error message if successful
        } else {
            $msg = 5;
        }
        return $msg;
    }


    /**
     * Render a quiz question.
     * Set $mode to 'preview' to have the cancel button return to the admin
     * list.  Otherwise it might return and re-execute an action, like "copy".
     *
     * @param   integer $question   ID of question being rendered.
     * @return  string      HTML for the quiz
     */
    public function Render($question = 0)
    {
        global $LANG_QUIZ, $_CONF;

        $retval = '';
        $isAdmin = false;

        // Don't allow another submission if the user has already filled out
        // this quiz.
        if ($this->onetime) {
            $results = Result::findByUser($this->uid, $this->id);
            if (count($results) > 0) {
                COM_refresh($_CONF['site_url'] . '/index.php?plugin=quizzer&msg=7');
                exit;
            }
        }

        // Check that the current user has access to fill out this quiz.
        if (!$this->hasAccess(QUIZ_ACCESS_FILL)) {
            return $this->noAccessMsg();
        }

        $Result = Result::getCurrent($this->getID());
        if ($Result->getID() == 0) {
            $Result->Create($this->getID());
        }

        if ($question == 0) {
            if ($this->num_q == 0) {
                // If the number of questions is zero (forgot to fill in...)
                // then ask all questions.
                $this->num_q = Question::countQ($this->id);
            }
        }

        // If starting the quiz, and there are intro fields to fill out, then
        // display those. Otherwise start with the first question below.
        if (
            $question == 0 &&
            ($this->introtext != '' || $this->introfields != '') &&
            !$Result->introDone()
        ) {
            $T = new \Template(QUIZ_PI_PATH . '/templates');
            $T->set_file('intro', 'intro.thtml');
            $T->set_var(array(
                'introtext'     => $this->introtext,
                'quiz_name'     => $this->name,
                'quiz_id'       => $this->id,
            ) );
            if ($this->introfields != '') {
                $introfields = explode('|', $this->introfields);
                $T->set_block('intro', 'introFields', 'iField');
                foreach ($introfields as $fld) {
                    $T->set_var(array(
                        'if_prompt' => $fld,
                        'if_name'   => COM_sanitizeId($fld),
                    ) );
                    $T->parse('iField', 'introFields', true);
                }
            }
            $T->parse('output', 'intro');
            $retval .= $T->finish($T->get_var('output'));
        } else {
            // Just jump to the next (or first) quiz question.
            // Create a new result set if necessary.
            $Questions = $Result->getQuestions();
            if ($question == 0) {
                $question = $Result->getNextQuestion();
            }
            if (isset($Questions[$question])) {
                $retval = $Questions[$question]
                    ->setTotalQ(count($Questions))
                    ->Render();
            }
        }
        return $retval;
    }


    /**
     * Delete a quiz definition.
     * Deletes a quiz, removes the questions, and deletes user data.
     *
     * @uses    Result::Delete()
     * @param   integer $quiz_id     Optional quiz ID, current object if empty
     */
    public static function DeleteDef($quiz_id)
    {
        global $_TABLES;

        $quiz_id = COM_sanitizeID($quiz_id);
        // If still no valid ID, do nothing
        if ($quiz_id == '') return;

        DB_delete($_TABLES['quizzer_quizzes'], 'id', $quiz_id);
        //DB_delete($_TABLES['quizzer_frmXfld'], 'frm_id', $quiz_id);
        DB_delete($_TABLES['quizzer_questions'], 'quiz_id', $quiz_id);

        $sql = "SELECT id FROM {$_TABLES['quizzer_results']}
            WHERE quiz_id='$quiz_id'";
        $r = DB_query($sql, 1);
        if ($r) {
            while ($A = DB_fetchArray($r, false)) {
                Result::Delete($A['id']);
            }
        }
        Cache::clear();
    }


    /**
     * Determine if a specific user has a given access level to the quiz.
     *
     * @param   integer $level  Requested access level
     * @param   integer $uid    Optional user ID, current user if omitted.
     * @return  boolean     True if the user has access, false if not
     */
    public function hasAccess($level, $uid = 0)
    {
        global $_USER;

        if ($uid == 0) $uid = (int)$_USER['uid'];
        //if ($uid == $this->owner_id) return true;

        $retval = false;
        switch ($level) {
        case QUIZ_ACCESS_VIEW:
            if (SEC_inGroup($this->results_gid, $uid)) $retval = true;
            break;
        case QUIZ_ACCESS_FILL:
            if (SEC_inGroup($this->fill_gid, $uid)) $retval = true;
            break;
        case QUIZ_ACCESS_ADMIN:
            if (SEC_inGroup($this->group_id, $uid)) $retval = true;
            break;
        }
        return $retval;
    }


    /**
     * Duplicate this quiz.
     * Creates a copy of this quiz with all its fields.
     *
     * @uses    Question::Duplicate()
     * @return  string      Error message, empty if successful
     */
    public function Duplicate()
    {
        $this->name .= ' -Copy';
        $this->id = COM_makeSid();
        $this->isNew = true;
        $this->SaveDef();

        foreach ($this->questions as $Q) {
            $Q->frm_id = $this->id;
            $msg = $F->Duplicate();
            if (!empty($msg)) return $msg;
        }
        return '';
    }


    /**
     * Remove HTML and convert other characters.
     *
     * @param   string  $str    String to sanitize
     * @return  string          String with no quotes or tags
     */
    private static function _stripHtml($str)
    {
        return htmlentities(strip_tags($str));
    }


    /**
     * Toggle a boolean field in the database
     *
     * @param   string  $id     Field def ID
     * @param   string  $fld    DB variable to change
     * @param   integer $oldval Original value
     * @return  integer New value
     */
    public static function toggle($id, $fld, $oldval)
    {
        global $_TABLES;

        $id = DB_escapeString($id);
        $fld = DB_escapeString($fld);
        $oldval = $oldval == 0 ? 0 : 1;
        $newval = $oldval == 0 ? 1 : 0;
        $sql = "UPDATE {$_TABLES['quizzer_quizzes']}
                SET $fld = $newval
                WHERE id = '$id'";
        $res = DB_query($sql, 1);
        if (DB_error($res)) {
            COM_errorLog(__CLASS__ . '\\' . __FUNCTION__ . ':: ' . $sql);
            return $oldval;
        } else {
            return $newval;
        }
    }


    /**
     * Get the first active quiz, for when a quiz ID is not provided
     *
     * @return  object  Quiz object
     */
    public static function getFirst()
    {
        global $_TABLES;

        $sql = "SELECT quiz.*, COUNT(questions.q_id) as q_count
            FROM {$_TABLES['quizzer_quizzes']} AS quiz
            LEFT JOIN {$_TABLES['quizzer_questions']} AS questions
                ON quiz.id = questions.quiz_id
            WHERE quiz.enabled = 1 " .
            SEC_buildAccessSql('AND', 'quiz.fill_gid') .
            " GROUP BY quiz.id
            HAVING q_count > 0 
            ORDER BY id ASC
            LIMIT 1";
        //echo $sql;die;
        $res = DB_query($sql);
        if (DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
            $Q = new self($A);
        } else {
            $Q = new self();
        }
        return $Q;
    }


    /**
     * Get the class to use for the scoring progress bars
     *
     * @param   float   $pct    Percentage of correct answers
     * @return  string          Class to use
     */
    public function getGrade($pct)
    {
        static $scores = NULL;
        if ($scores === NULL) {
            $scores = explode('|', $this->levels);
        }
        $levels = array(self::PASSED, self::WARNING, self::FAILED);
        $max_i = min(count($scores), 3);
        $grade = array(
            'grade' => self::FAILED,
            'class' => self::$grades[self::FAILED],
        );
        for ($i = 0; $i < $max_i; $i++) {
            if ($pct >= (float)$scores[$i]) {
                $grade = array(
                    'grade' => $levels[$i],
                    'class' => self::$grades[$levels[$i]],
                );
                break;
            }
        }
        return $grade;
    }


    /**
     * Display a summary of results by question.
     * Shows each question and the average score for that question.
     *
     * @return  string  HTML for display
     */
    public function resultByQuestion()
    {
        global $_TABLES;

        $T = new \Template(QUIZ_PI_PATH . '/templates/admin');
        $T->set_file('results', 'resultsbyq.thtml');
        $T->set_var('quiz_name', $this->name);
        $sql = "SELECT * FROM {$_TABLES['quizzer_questions']}
                WHERE quiz_id = '{$this->id}'";
        $res = DB_query($sql);
        $questions = array();
        while ($A = DB_fetchArray($res, false)) {
            $questions[] = Question::getInstance($A);
        }
        $T->set_block('results', 'DataRows', 'dRow');
        foreach ($questions as $Q) {
            $total = 0;
            $correct = 0;
            $vals = Value::getByQuestion($Q->getID());
            foreach ($vals as $Val) {
                $total++;
                if ($Q->Verify($Val->getValue())) {
                    $correct++;
                }
            }
            if ($total > 0) {
                $pct = round(($correct / $total) * 100);
            } else {
                $pct = 0;
            }
            $prog_status = $this->getGrade($pct)['class'];
            $T->set_var(array(
                'question' => $Q->getQuestion(),
                'pct' => $pct,
                'correct' => $correct,
                'total' => $total,
                'prog_status' => $total > 0 ? $prog_status : false,
            ) );
            $T->parse('dRow', 'DataRows', true);
        }
        $T->parse('output', 'results');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Display a summary of results by submitter.
     * Shows intro field data and overall score.
     *
     * @return  string  HTML for display
     */
    public function resultSummary()
    {
        global $LANG_QUIZ;

        $T = new \Template(QUIZ_PI_PATH . '/templates/admin');
        $T->set_file('results', 'results.thtml');
        $T->set_var('quiz_name', $this->name);
        $intro = explode('|', $this->introfields);
        $keys = array();
        $T->set_block('results', 'hdrIntroFields', 'hdrIntro');
        foreach ($intro as $key) {
            if (!empty($key)) {
                $T->set_var('introfield_value', $key);
                $T->parse('hdrIntro', 'hdrIntroFields', true);
                $keys[] = COM_sanitizeId($key);
            }
        }
        $results = Result::findByQuiz($this->id);
        $T->set_block('results', 'DataRows', 'dRow');
        foreach ($results as $R) {
            $introfields = $R->getIntroFields();
            $T->set_block('results', 'dataIntroFields', 'dataIntro');
            $T->clear_var('dataIntro');
            foreach ($keys as $key) {
                $T->set_var('introfield_value', QUIZ_getVar($introfields, $key));
                $T->parse('dataIntro', 'dataIntroFields', true);
            }
            $correct = 0;
            $total_a = 0;
            foreach ($R->getValues() as $V) {
                $total_a++;
                $Q = Question::getInstance($V->getQuestionID());
                $correct += $Q->Verify($V->getValue());
                /*if ($Q->Verify($V->value)) {
                    $correct++;
                }*/
            }
            $total_q = $R->getAsked();
            // Adjust correct number for cleaner presentation
            if (!is_int($correct)) {
                $correct = round($correct, 2);
            }
            if ($total_q > 0) {
                $pct = (int)(($correct / $total_q) * 100);
            } else {
                $pct = 0;
            }
            $prog_status = $this->getGrade($pct)['class'];
            $T->set_var(array(
                'username' => COM_getDisplayName($R->getUid()),
                'pct' => $pct,
                'correct' => $correct,
                'total_a' => $total_a,
                'total' => $total_q,
                'prog_status' => $prog_status,
                'res_id' => $R->getID(),
                'all_answered' => $total_a == $total_q,
            ) );
            $T->parse('dRow', 'DataRows', true);
        }
        $T->parse('output', 'results');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Export user responses as a CSV.
     *
     * @return  string  CSV file contents
     */
    public function csvBySubmitter()
    {
        global $LANG_QUIZ;

        $questions = Question::getQuestions($this->id, 0, false);
        $intro = explode('|', $this->introfields);
        $total_q = count($questions);
        $headers = array();
        foreach ($intro as $key) {
            $headers[COM_sanitizeId($key, false)] = $key;
        }
        for ($i = 1; $i <= $total_q; $i++) {
            $headers['q_' . $i] = $LANG_QUIZ['question'] . ' ' . $i;
        }
        $retval = '"' . implode('","', $headers) . '"' . "\n";
        $results = Result::findByQuiz($this->id);
        foreach ($results as $R) {
            $result_arr = $headers;
            foreach ($result_arr as $key=>$val) $result_arr[$key] = '';

            foreach ($R->getIntroFields() as $key=>$val) {
                $result_arr[$key] = str_replace('"', "'", $val);
            }
            $correct = 0;
            foreach ($R->getValues() as $V) {
                $Q = Question::getInstance($V->getQuestionID());
                if ($Q->Verify($V->getValue())) {
                    $correct = 1;
                } else {
                    $correct = 0;
                }
                $result_arr['q_' . $Q->getID()] = $correct;
            }
            $retval .= '"' . implode('","', $result_arr) . '"' . "\n";
        }
        return $retval;
    }



    /**
     * Export questions, total responses and correct responses as a CSV.
     *
     * @return  string  CSV file contents
     */
    public function csvByQuestion()
    {
        global $_TABLES, $LANG_QUIZ;

        $questions = Question::getQuestions($this->id, 0, false);
        $sql = "SELECT * FROM {$_TABLES['quizzer_questions']}
                WHERE quiz_id = '{$this->id}'";
        $res = DB_query($sql);
        $questions = array();
        while ($A = DB_fetchArray($res, false)) {
            $questions[] = Question::getInstance($A);
        }
        $retval = '"' . $LANG_QUIZ['question'] . '","' .
                    $LANG_QUIZ['answers'] . '","' .
                    $LANG_QUIZ['correct'] . '"'. "\n";
        foreach ($questions as $Q) {
            $total = 0;
            $correct = 0;
            $vals = Value::getByQuestion($Q->q_id);
            foreach ($vals as $Val) {
                $total++;
                if ($Q->Verify($Val->value)) {
                    $correct++;
                }
            }
            // Make sure there are no embedded quotes
            $question = str_replace('"', "'", $Q->question);
            $retval .= '"' . $Q->question . '",' . $total . ',' . $correct . "\n";
        }
        return $retval;
    }


    /**
     * Get a dropdown of user groups to set the fill_gid field.
     * Sets the current fill_gid as selected if not empty.
     *
     * @return  string  Options for group dropdown
     */
    private function _groupDropdown()
    {
        $retval = '';
        $usergroups = SEC_getUserGroups();
        foreach ($usergroups as $ug_name => $ug_id) {
            $retval .= '<option value="' . $ug_id . '"';
            if ($this->fill_gid == $ug_id) {
                $retval .= ' selected="selected"';
            }
            $retval .= '>' . $ug_name . '</option>' . LB;
        }
        return $retval;
    }


    /**
     * Reset the quiz for the next taker.
     * Currently a noop.
     */
    public function Reset()
    {}


    /**
     * Get the No Access message for unauthorized access.
     * If a message is defined, return that. Otherwise return a default.
     *
     * @param   boolean $display    True to set the message up for display
     * @return  string  Message to display
     */
    public function noAccessMsg($display=true)
    {
        global $LANG_QUIZ;

        $msg = $this->noaccess_msg == '' ? $LANG_QUIZ['no_access_msg'] : $this->noaccess_msg;
        if ($display) {
            $msg = '<div class="uk-alert uk-alert-danger">' . $msg . '</div>';
        }
        return $msg;
    }


    public static function resetReward($r_id)
    {
        global $_TABLES;

        $sql = "UPDATE {$_TABLES['quizzer_quizzes']}
            SET reward_id = 0, reward_status = 0
            WHERE reward_id = " . (int)$r_id;
        DB_query($sql);
    }

    
    /**
     * Uses lib-admin to list the quizzer definitions and allow updating.
     *
     * @return  string  HTML for the list
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_QUIZ, $perm_sql;

        // Import administration functions
        USES_lib_admin();

        $retval = '';

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADMIN['copy'],
                'field' => 'copy',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_QUIZ['submissions'],
                'field' => 'submissions',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_QUIZ['quiz_name'],
                'field' => 'name',
                'sort' => true,
            ),
            array(
                'text' => $LANG_QUIZ['enabled'],
                'field' => 'enabled',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_QUIZ['action'],
                'field' => 'action',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_QUIZ['reset'] . '&nbsp;' . Icon::getHTML('question', 'tooltip', array(
                    'title' => $LANG_QUIZ['hlp_quiz_reset'],
                )),
                'field' => 'reset',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADMIN['delete'] . '&nbsp;' . Icon::getHTML('question', 'tooltip', array(
                    'title' => $LANG_QUIZ['hlp_quiz_delete'],
                )),
                'field' => 'delete',
                'sort' => false,
                'align' => 'center',
            ),
        );
        $sql = "SELECT q.*, (
                SELECT count(*) FROM {$_TABLES['quizzer_results']} r
                WHERE r.quiz_id = q.id
            ) as submissions 
            FROM {$_TABLES['quizzer_quizzes']} q
            WHERE 1=1 $perm_sql";
        $text_arr = array();
        $query_arr = array(
            'table' => 'quizzer_quizzes',
            'sql' => $sql,
            'query_fields' => array('name'),
            'default_filter' => ''
        );
        $defsort_arr = array('field' => 'name', 'direction' => 'ASC');
        $form_arr = array();
        $retval .= COM_createLink(
            $LANG_QUIZ['new_quiz'],
            QUIZ_ADMIN_URL . '/index.php?editquiz=0',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );
        $retval .= ADMIN_list(
            'quizzer_quizes',
            array(__CLASS__,  'getAdminField'),
            $header_arr,
            $text_arr, $query_arr, $defsort_arr, '', '', '', $form_arr
        );

        return $retval;
    }

    
    /**
     * Determine what to display in the admin list for each form.
     *
     * @param   string  $fieldname  Name of the field, from database
     * @param   mixed   $fieldvalue Value of the current field
     * @param   array   $A          Array of all name/field pairs
     * @param   array   $icon_arr   Array of system icons
     * @return  string              HTML for the field cell
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $LANG_ACCESS, $LANG_QUIZ, $_TABLES, $_CONF_QUIZ, $_LANG_ADMIN;

        $retval = '';

        switch($fieldname) {
        case 'id':
            $retval = COM_createLink(
                $fieldvalue,
                QUIZ_PI_URL . '/index.php?startquiz=' . $fieldvalue
            );
            break;

        case 'edit':
            $url = QUIZ_ADMIN_URL . "/index.php?editquiz=x&amp;quiz_id={$A['id']}";
            $retval = COM_createLink(
                Icon::getHTML('edit'),
                $url
            );
            break;

        case 'copy':
            $url = QUIZ_ADMIN_URL . "/index.php?copyform=x&amp;quiz_id={$A['id']}";
            $retval = COM_createLink(
                Icon::getHTML('copy'),
                $url
            );
            break;

        /*case 'questions':
            $url = QUIZ_ADMIN_URL . "/index.php?questions=x&amp;quiz_id={$A['id']}";
            $retval = COM_createLink(
                Icon::getHTML('question'),
                $url
            );
            break;*/

        case 'delete':
            $url = QUIZ_ADMIN_URL . "/index.php?delQuiz=x&quiz_id={$A['id']}";
            $retval = COM_createLink(
                Icon::getHTML('delete'),
                $url,
                array(
                    'onclick' => "return confirm('{$LANG_QUIZ['confirm_quiz_delete']}?');",
                )
            );
            break;

        case 'reset':
            $url = QUIZ_ADMIN_URL . "/index.php?resetquiz=x&quiz_id={$A['id']}";
            $retval = COM_createLink(
                Icon::getHTML('reset', 'uk-text-danger'),
                $url,
                array(
                    'onclick' => "return confirm('{$LANG_QUIZ['confirm_quiz_reset']}?');",
                )
            );
            break;

        case 'enabled':
            if ($A[$fieldname] == 1) {
                $chk = 'checked="checked"';
                $enabled = 1;
            } else {
                $chk = '';
                $enabled = 0;
            }
            $retval = "<input name=\"{$fieldname}_{$A['id']}\" " .
                "type=\"checkbox\" $chk " .
                "onclick='QUIZtoggleEnabled(this, \"{$A['id']}\", \"quiz\", \"{$fieldname}\", \"" . QUIZ_ADMIN_URL . "\");' " .
                "/>\n";
            break;

        case 'submissions':
            $url = QUIZ_ADMIN_URL . '/index.php?results=x&quiz_id=' . $A['id'];
            $retval = COM_createLink((int)$fieldvalue, $url,
                array(
                    'class' => 'tooltip',
                    'title' => $LANG_QUIZ['results'],
                )
            );
            break;

        case 'action':
            $retval = '<select name="action"
                onchange="javascript: document.location.href=\'' .
                QUIZ_ADMIN_URL . '/index.php?quiz_id=' . $A['id'] .
                '&action=\'+this.options[this.selectedIndex].value">'. "\n";
            $retval .= '<option value="">--' . $LANG_QUIZ['select'] . '--</option>'. "\n";
            $retval .= '<option value="resultsbyq">' . $LANG_QUIZ['resultsbyq'] . '</option>'. "\n";
            $retval .= '<option value="results">' . $LANG_QUIZ['results'] . '</option>'. "\n";
            $retval .= '<option value="csvbyq">' . $LANG_QUIZ['csvbyq'] . '</option>'. "\n";
            $retval .= '<option value="csvbysubmitter">' . $LANG_QUIZ['csvbysubmitter'] . '</option>'. "\n";
            $retval .= "</select>\n";
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}

?>
