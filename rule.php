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

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');


/**
 * A rule implementing the qrcode check.
 *
 * @copyright  2021 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_qrcode extends quiz_access_rule_base {

    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        if (empty($quizobj->get_quiz()->password)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    public function is_preflight_check_required($attemptid) {
        global $SESSION;
        return empty($SESSION->passwordcheckedquizzes[$this->quiz->id]);
    }

    public function add_preflight_check_form_fields(mod_quiz_preflight_check_form $quizform, MoodleQuickForm $mform, $attemptid) {
        $qrcodescanner = '<script src="https://unpkg.com/html5-qrcode/html5-qrcode.min.js"></script>';
        $qrcodescanner .= '<div id="reader"><button class="btn btn-secondary" onclick="html5Qrcode.render(onScanSuccess);">';
        $qrcodescanner .= get_string('qrcodescan', 'quizaccess_qrcode') . '</button></div>';
        $qrcodescanner .= '<script>
  function onScanSuccess(decodedText, decodedResult) { 
    $("#id_quizpassword").val(decodedText); 
    $("#reader__dashboard_section_csr button:last-child").click();
  }

  const formatsToSupport = [ Html5QrcodeSupportedFormats.QR_CODE ];
  const html5Qrcode = new Html5QrcodeScanner( "reader",
    { fps: 10, qrbox: {width: 250, height: 250}, formatsToSupport: formatsToSupport }, false);
</script>';
        
        $mform->addElement('html', $qrcodescanner);
    }
}
