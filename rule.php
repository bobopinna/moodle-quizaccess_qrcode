<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Implementaton of the quizaccess_qrcode plugin.
 *
 * @package    quizaccess
 * @subpackage qrcode
 * @copyright  2021 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A rule implementing the qrcode check.
 *
 * @copyright  2021 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_qrcode extends mod_quiz\local\access_rule_base {

    public static function make(mod_quiz\quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        if (empty($quizobj->get_quiz()->password) || empty($quizobj->get_quiz()->qrcodeenabled)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    public function is_preflight_check_required($attemptid) {
        global $SESSION;
        return empty($SESSION->passwordcheckedquizzes[$this->quiz->id]);
    }

    public function add_preflight_check_form_fields(mod_quiz\form\preflight_check_form $quizform, 
                                                    MoodleQuickForm $mform, $attemptid) {
        $qrcodestartbutton = '<button type="button" class="btn btn-secondary qrcodebutton" onclick="startReader();">';
        $qrcodestartbutton .= get_string('qrcodescan', 'quizaccess_qrcode') . '</button>';
        $qrcodestopbutton = '<button type="button" class="btn btn-secondary qrcodebutton" onclick="stopReader();">';
        $qrcodestopbutton .= get_string('qrcodestop', 'quizaccess_qrcode') . '</button>';

        $qrcodescanner = '<div id="qrcodeaccessrule">';
        $qrcodescanner .= '<div id="qrcodebutton">' . $qrcodestartbutton . '</div>';
        $qrcodescanner .= '<div id="qrcodereader"></div>';
        $qrcodescanner .= '</div>';
        $qrcodescanner .= '<script src="https://unpkg.com/html5-qrcode/html5-qrcode.min.js"></script>';
        $qrcodescanner .= '<script>
   
  const startButton = \'' . $qrcodestartbutton . '\';
  const stopButton = \'' . $qrcodestopbutton . '\';
  const formatsToSupport = [ Html5QrcodeSupportedFormats.QR_CODE ];
  const config = { fps: 10, qrbox: {width: 250, height: 250} };
  const html5Qrcode = new Html5Qrcode( "qrcodereader", {formatsToSupport: formatsToSupport });

  function onScanSuccess(decodedText, decodedResult) { 
    document.getElementById("id_quizpassword").value = decodedText;
    stopReader();
  }

  function stopReader() {
    html5Qrcode.stop();
    document.getElementById("qrcodeaccessrule").classList.remove("qrcodefull");
    document.getElementById("qrcodebutton").innerHTML = startButton;
  }

  function startReader() {
    document.getElementById("qrcodeaccessrule").classList.add("qrcodefull");
    document.getElementById("qrcodebutton").innerHTML = stopButton;
    html5Qrcode.start({ facingMode: "environment"}, config, onScanSuccess);
  }

</script>';
        
        $mform->addElement('html', $qrcodescanner);
    }

    /**
     * Add any fields that this rule requires to the quiz settings form. This
     * method is called from {@see mod_quiz_mod_form::definition()}, while the
     * security seciton is being built.
     *
     * @param mod_quiz_mod_form $quizform the quiz settings form that is being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        global $DB;

        $pluginconfig = get_config('quizaccess_qrcode');

        $mform->addElement('checkbox', 'qrcodeenabled', get_string('useqrcode', 'quizaccess_qrcode'));
        $mform->setDefault('qrcodeenabled', $pluginconfig->defaultenabled);
        $mform->setAdvanced('qrcodeenabled', $pluginconfig->defaultenabled_adv);
        $mform->addHelpButton('qrcodeenabled', 'useqrcode', 'quizaccess_qrcode');
        $mform->disabledIf('qrcodeenabled', 'quizpassword', 'eq', '');
    }

    /**
     * Save any submitted settings when the quiz settings form is submitted. This
     * is called from {@see quiz_after_add_or_update()} in lib.php.
     *
     * @param object $quiz the data from the quiz form, including $quiz->id
     *      which is the id of the quiz being saved.
     */
    public static function save_settings($quiz) {
        global $DB;

        if (empty($quiz->qrcodeenabled)) {
            $DB->delete_records('quizaccess_qrcode', array('quizid' => $quiz->id));
        } else {
            if (!$DB->record_exists('quizaccess_qrcode', array('quizid' => $quiz->id))) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->enabled = 1;
                $DB->insert_record('quizaccess_qrcode', $record);
            }
        }
    }

    /**
     * Delete any rule-specific settings when the quiz is deleted. This is called
     * from {@see quiz_delete_instance()} in lib.php.
     *
     * @param object $quiz the data from the database, including $quiz->id
     *      which is the id of the quiz being deleted.
     * @since Moodle 2.7.1, 2.6.4, 2.5.7
     */
    public static function delete_settings($quiz) {
        global $DB;

        $DB->delete_records('quizaccess_qrcode', array('quizid' => $quiz->id));
    }

    /**
     * Return the bits of SQL needed to load all the settings from all the access
     * plugins in one DB query. The easiest way to understand what you need to do
     * here is probalby to read the code of {@see quiz_access_manager::load_settings()}.
     *
     * @param int $quizid the id of the quiz we are loading settings for. This
     *     can also be accessed as quiz.id in the SQL. (quiz is a table alisas for {quiz}.)
     * @return array with three elements:
     *     1. fields: any fields to add to the select list. These should be alised
     *        if neccessary so that the field name starts the name of the plugin.
     *     2. joins: any joins (should probably be LEFT JOINS) with other tables that
     *        are needed.
     *     3. params: array of placeholder values that are needed by the SQL. You must
     *        used named placeholders, and the placeholder names should start with the
     *        plugin name, to avoid collisions.
     */
    public static function get_settings_sql($quizid) {
        return array(
            'quizaccess_qrcode.enabled qrcodeenabled',
            'LEFT JOIN {quizaccess_qrcode} quizaccess_qrcode ON quizaccess_qrcode.quizid = quiz.id',
            array());
    }
}
