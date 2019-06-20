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
 * Course list block.
 *
 * @package    block_custom_course_list
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include_once($CFG->dirroot . '/course/lib.php');
include_once($CFG->libdir . '/coursecatlib.php');


class block_custom_course_list extends block_list {
    function init() {
        $this->title = get_string('pluginname', 'block_custom_course_list');
    }

    function has_config() {
        return true;
    }

    /**
     * Obtiene los cursos que se traen a la vista del plugin course overview
     *
     * @param array_courseview $array_courseview Courses array
     * @return array
     */

    function theme_moove_get_courses($array_coursesview){

        $array_courses = array();

        if($array_coursesview['hascourses']){

            if(isset($array_coursesview['past'])){
                foreach($array_coursesview['past']['pages'] as $page){
                    foreach($page['courses'] as $course){
                        array_push($array_courses, $course);
                    }
                }
            }

            if(isset($array_coursesview['future'])){
                foreach($array_coursesview['future']['pages'] as $page){
                    foreach($page['courses'] as $course){
                        array_push($array_courses, $course);
                    }
                }
            }

            if(isset($array_coursesview['inprogress'])){
                foreach($array_coursesview['inprogress']['pages'] as $page){
                    foreach($page['courses'] as $course){
                        array_push($array_courses, $course);
                    }
                }
            }
        }

        return $array_courses;
    }

    /**
     * Ordena un arreglo de cursos a partir de la subcadena de fecha en su nombre corto
     *
     * @param array_courses $array_courses Courses array
     * @return array
     */

    function theme_moove_order_courses_by_shortname(&$array_courses){

        $grouped_courses_array = array();
        $regular_courses_array = array();
        $no_regular_courses_array = array();
        $counter = 0;

        foreach($array_courses as $key=>&$course){

            $idcourse = $course->id;
            $timecreated = $this->theme_moove_get_timecreated_course($idcourse);
            $timemodified = $this->theme_moove_get_timemodified_course($idcourse);
            $categoryid = $this->theme_moove_get_course_category($idcourse);

            $course->timecreated = $timecreated;
            $course->timemodified = $timemodified;
            $course->categoryid = $categoryid;

            // Validación para cursos regulares
            if($course->categoryid >= 30001 && $course->categoryid <= 30999){

                array_push($regular_courses_array, $course);

                $explode_course_shortname = explode("-", $course->shortname);

                // Se verifica que tenga en su nombre corto la especificación de fecha de creación
                // una vez identificada se le añade como atributo al curso
                if(count($explode_course_shortname) && preg_match("/^20/", $explode_course_shortname[3])){
                    $date_course = substr($explode_course_shortname[3], 0, -3);
                    $course->date_course = $date_course;
                }

            }else{
                // Cursos no regulares
                array_push($no_regular_courses_array, $course);
            }
        }

        $this->array_sort_by($regular_courses_array, 'timecreated', $order = SORT_DESC);
        $this->array_sort_by($no_regular_courses_array, 'timecreated', $order = SORT_DESC);

        $grouped_courses_array['regular_courses'] = $regular_courses_array;
        $grouped_courses_array['no_regular_courses'] = $no_regular_courses_array;

        return $grouped_courses_array;
    }

    /**
     * Agrupa los cursos por semestre y por categoría
     *
     * @param array_courses $array_courses Courses array
     * @param string $courses_type
     * @return array
     */

    function theme_moove_group_courses_by_semester($array_courses, $courses_type){

        $grouped_courses_array = array();
        $grouped_courses_array['inprogress_regular'] = array();
        $grouped_courses_array['past_regular'] = array();
        $grouped_courses_array['inprogress_no_regular'] = array();
        $grouped_courses_array['past_no_regular'] = array();

        // Periodo académico actual
        $current_period = $this->theme_moove_get_academic_period();

        // Periodos definidos para solucionar la anormalidad académica del periodo 2018-II
        $previous_period = new stdClass();
        $previous_period->year = '2018';
        $previous_period->period = '2';

        $p_previous_period = new stdClass();
        $p_previous_period->year = '2018';
        $p_previous_period->period = '1';

        $date_ranges_current_period = $this->theme_moove_date_ranges_academic_period($current_period);
        $date_ranges_previous_period = $this->theme_moove_date_ranges_academic_period($previous_period);
        $date_ranges_p_previous_period = $this->theme_moove_date_ranges_academic_period($p_previous_period);

        // Rangos de fechas fijos para solucionar la anormalidad académica del periodo 2019-I
        $date_ranges_current_period->start_date = 1546318800;
        $date_ranges_current_period->end_date = 1569905999;


        if($courses_type == 'regular'){

            foreach($array_courses as $course){
                if($course->timecreated >= $date_ranges_current_period->start_date){
                    array_push($grouped_courses_array['inprogress_regular'], $course);
                }else{
                    array_push($grouped_courses_array['past_regular'], $course);
                }
            }

            $past_courses_by_semester = array();
            $counter_semester = -1;
            $semester_name = "";
            $semester_code = "";

            foreach($grouped_courses_array['past_regular'] as $past_regular_course){

                if($semester_code == $past_regular_course->date_course){

                    array_push($past_courses_by_semester[$counter_semester]['courses'], $past_regular_course);

                }else{

                    $counter_semester += 1;

                    $semester_code = $past_regular_course->date_course;
                    $month_creation = intval(substr($past_regular_course->date_course, 4, 2));

                    if($month_creation <= 6){
                        $semester_name = "Semestre " . substr($past_regular_course->date_course, 0, 4) . " - I";
                    }else{
                        $semester_name = "Semestre " . substr($past_regular_course->date_course, 0, 4) . " - II";
                    }

                    $past_courses_by_semester[$counter_semester] = array();
                    $past_courses_by_semester[$counter_semester]['semester_name'] = $semester_name;
                    $past_courses_by_semester[$counter_semester]['semester_code'] = $semester_code;
                    $past_courses_by_semester[$counter_semester]['courses'] = array();
                    array_push($past_courses_by_semester[$counter_semester]['courses'], $past_regular_course);

                }
            }

            $grouped_courses_array['past_regular'] = array();
            $grouped_courses_array['past_regular'] = $past_courses_by_semester;

            return $grouped_courses_array;

        }else{
            $grouped_courses_array['past_no_regular']['semester_name'] = "No regulares";
            $grouped_courses_array['past_no_regular']['semester_code'] = "noregulars";
            $grouped_courses_array['past_no_regular']['courses'] = array();
            foreach($array_courses as $course){
                if($course->timecreated >= $date_ranges_current_period->start_date
                    || $course->timemodified > $date_ranges_p_previous_period->start_date){
                    array_push($grouped_courses_array['inprogress_no_regular'], $course);
                }else{
                    array_push($grouped_courses_array['past_no_regular']['courses'], $course);
                }
            }

            return $grouped_courses_array;
        }


    }

    /**
     * Ordena un arreglo a partir de uno de sus atributos
     *
     * @param array_initial $array_initial
     * @param col $col Atributo
     * @param order $order Tipo de ordenamiento, ascendente por defecto
     * @return array
     */

    function array_sort_by(&$array_initial, $col, $order = SORT_ASC){

        $arrAux = array();

        foreach ($array_initial as $key=> $row){
            $arrAux[$key] = is_object($row) ? $arrAux[$key] = $row->$col : $row[$col];
            $arrAux[$key] = strtolower($arrAux[$key]);
        }

        array_multisort($arrAux, $order, $array_initial);
    }

    /**
     * Dado el periodo académico actual, retorna el rango de fechas donde el semestre estaría definido
     *
     * @param current_period $current_period stdClass Periodo actual
     * @return stdClass
     */
    function theme_moove_date_ranges_academic_period($current_period){

        date_default_timezone_set('America/Bogota');
        $date_ranges = new stdClass();

        if($current_period->period == '1'){
            $human_start_date = $current_period->year."-01-01 00:00:00";
            $human_end_date = $current_period->year."-06-30 23:59:59";

            $timestamp_start_date = strtotime($human_start_date);
            $timestamp_end_date = strtotime($human_end_date);
        }else{
            $human_start_date = $current_period->year."-08-01 00:00:00";
            $human_end_date = $current_period->year."-12-31 23:59:59";

            $timestamp_start_date = strtotime($human_start_date);
            $timestamp_end_date = strtotime($human_end_date);
        }

        $date_ranges->start_date = $timestamp_start_date;
        $date_ranges->end_date = $timestamp_end_date;

        return $date_ranges;
    }

    /**
     * Retorna un objeto tipo stdClass con el periodo académico actual
     *
     * @return stdClass
     */

    function theme_moove_get_academic_period(){

        $current_period = new stdClass();

        $today = getdate();

        $current_period->year = $today['year'];

        if($today['mon'] > 0 && $today['mon'] <= 6){
            $current_period->period = "1";
        }else{
            $current_period->period = "2";
        }

        return $current_period;
    }

    /**
     * Dado el identificador retorna la fecha de creación de un curso
     *
     * @param $id  Identificador del curso
     * @return int
     */
    function theme_moove_get_timecreated_course($id){

        global $DB;

        $sql_query = "SELECT timecreated 
            FROM {course}
            WHERE id = $id";

        $timecreated = $DB->get_record_sql($sql_query)->timecreated;

        return $timecreated;
    }

    /**
     * Dado el identificador retorna la fecha de modificación de un curso
     *
     * @param $id  Identificador del curso
     * @return int
     */
    function theme_moove_get_timemodified_course($id){

        global $DB;

        $sql_query = "SELECT timemodified 
            FROM {course}
            WHERE id = $id";

        $timemodified = $DB->get_record_sql($sql_query)->timemodified;

        return $timemodified;

    }

    /**
     * Dado el identificador retorna la categoria de curso asociada en la base de datos
     *
     * @param $id  Identificador del curso
     * @return int
     */
    function theme_moove_get_course_category($id){

        global $DB;

        $sql_query = "SELECT category
                FROM {course}
                WHERE id = $id";

        $categoryid = $DB->get_record_sql($sql_query)->category;

        return $categoryid;
    }

    /**
     * order_courses_univalle
     * función que toma los cursos y los separa en presenciales y otros
     * @author Diego
     * @param array $courses
     * @return array $courses
     **/
    private function order_courses_univalle($courses){
        /****Ordenar los cursos porque están en forma ascendente*****/

        $courses = array_reverse($courses, true);
        $separated_courses = $this->separated_courses_by_category($courses);
        $presenciales = $separated_courses[0];
        $otros = $separated_courses[1];
        $courses = array_merge($presenciales,$otros);

        return $courses;
    }


    /**
     * separated_courses_by_category
     * recibe los cursos de un usuario y devuelve dos array con los cursos separados por presencial
     * y no presenciales.
     * @author Hernán
     * @param array $courses
     * @return array(array,array) $courses
     *
     */
    private function separated_courses_by_category($courses){
        $cursos_presenciales = array();
        $cursos_otros = array();
        foreach ($courses as $course) {
            //si pertenece a la categoría presencial añadimos un cero para que sea ordenado de primero
            if($course->category >= 30000){
                $cursos_presenciales[] = $course;
            }else{
                $cursos_otros[]=$course;
            }
        }
        $courses = [$cursos_presenciales,$cursos_otros];
        return $courses;
    }

    function get_content() {
        global $CFG, $USER, $DB, $OUTPUT;

        if($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        $icon = $OUTPUT->pix_icon('i/course', get_string('course'));

        $adminseesall = true;
        if (isset($CFG->block_custom_course_list_adminview)) {
           if ( $CFG->block_custom_course_list_adminview == 'own'){
               $adminseesall = false;
           }
        }

        if (empty($CFG->disablemycourses) and isloggedin() and !isguestuser() and
          !(has_capability('moodle/course:update', context_system::instance()) and $adminseesall)) {    // Just print My Courses
            if ($courses = enrol_get_all_users_courses($USER->id, true, null)) {

                $array_courses_group = array();
                // Función añadida para el Campus Virtual Univalle
                $courses = $this->order_courses_univalle($courses);
                $array_courses_order = $this->theme_moove_order_courses_by_shortname($courses);
                $array_courses_group['regular_courses'] = $this->theme_moove_group_courses_by_semester($array_courses_order['regular_courses'], 'regular');
                $array_courses_group['no_regular_courses'] = $this->theme_moove_group_courses_by_semester($array_courses_order['no_regular_courses'], 'noregular');
                //print_r($array_courses_group);
                //html = "";
                /*foreach ($array_courses_group as $course_group){
                    var_dump($course_group);
                }*/

                //Past Regular
                $html = "";
                foreach ($array_courses_group as $course_group){
                    //var_dump($course_group['past_regular']);
                    foreach ($course_group['past_regular'] as $courses_data){
                        $html .= "<div class=\"\">
	<div class=\"\" id=\"heading\" 201802=\"\">
		<h5 class=\"mb-0\">
		<button class=\"btn btn-link\" data-toggle=\"collapse\" data-target=\"#201802\" aria-expanded=\"true\" aria-controls=\"201802\">
		<b><span class=\"fa fa-caret-right\"></span>";
                        $html .= $courses_data['semester_name'];
                        $html .= "</b>
		</button>
		</h5>
	</div>";
                        $html .= "<div- id=\"201802\" class=\"collapse\" aria-labelledby=\"heading\" 201802=\"\" data-parent=\"#accordion\">
		<div class=\"card-body\">
			<ul>";
                        foreach ($courses_data['courses'] as $data){
                            $html .= "<li class=\"no_bullet_point\">
					<a class=\"fullname_course_myoverview\" href=\"http://10.162.18.238/moodle35/course/view.php?id=38476\">";

                            $html .= $data->fullname;
                            $html .= "</a>
				</li>";
                            //var_dump($data->shortname);
                            //var_dump($data->id);
                        }
                        $html .= "</ul>
		</div>
	</div>
</div>";
                    }
                }
                //End Past Regular

                var_dump($html);

                foreach ($courses as $course) {
                    $coursecontext = context_course::instance($course->id);
                    //var_dump($coursecontext);
                    $linkcss = $course->visible ? "" : " class=\"dimmed\" ";
                    $this->content->items[]="<a $linkcss title=\"" . format_string($course->shortname, true, array('context' => $coursecontext)) . "\" ".
                               "href=\"$CFG->wwwroot/course/view.php?id=$course->id\">".$icon.format_string(get_course_display_name_for_list($course)). "</a>";
                    //var_dump(get_course_display_name_for_list($course));
                }
                $this->title = get_string('mycourses');
            /// If we can update any course of the view all isn't hidden, show the view all courses link
                if (has_capability('moodle/course:update', context_system::instance()) || empty($CFG->block_custom_course_list_hideallcourseslink)) {
                    $this->content->footer = "<a href=\"$CFG->wwwroot/course/index.php\">".get_string("fulllistofcourses")."</a> ...";
                }
            }

            $this->get_remote_courses();
            if ($this->content->items) { // make sure we don't return an empty list
                //print_r($this->content);
                return $this->content;
            }
        }

        $categories = coursecat::get(0)->get_children();  // Parent = 0   ie top-level categories only
        if ($categories) {   //Check we have categories
            if (count($categories) > 1 || (count($categories) == 1 && $DB->count_records('course') > 200)) {     // Just print top level category links
                foreach ($categories as $category) {
                    $categoryname = $category->get_formatted_name();
                    $linkcss = $category->visible ? "" : " class=\"dimmed\" ";
                    $this->content->items[]="<a $linkcss href=\"$CFG->wwwroot/course/index.php?categoryid=$category->id\">".$icon . $categoryname . "</a>";
                }
            /// If we can update any course of the view all isn't hidden, show the view all courses link
                if (has_capability('moodle/course:update', context_system::instance()) || empty($CFG->block_custom_course_list_hideallcourseslink)) {
                    $this->content->footer .= "<a href=\"$CFG->wwwroot/course/index.php\">".get_string('fulllistofcourses').'</a> ...';
                }
                $this->title = get_string('categories');
            } else {                          // Just print course names of single category
                $category = array_shift($categories);
                $courses = get_courses($category->id);
                if ($courses) {
                    foreach ($courses as $course) {
                        $coursecontext = context_course::instance($course->id);
                        $linkcss = $course->visible ? "" : " class=\"dimmed\" ";

                        $this->content->items[]="<a $linkcss title=\""
                                   . format_string($course->shortname, true, array('context' => $coursecontext))."\" ".
                                   "href=\"$CFG->wwwroot/course/view.php?id=$course->id\">"
                                   .$icon. format_string(get_course_display_name_for_list($course), true, array('context' => context_course::instance($course->id))) . "</a>";
                    }
                /// If we can update any course of the view all isn't hidden, show the view all courses link
                    if (has_capability('moodle/course:update', context_system::instance()) || empty($CFG->block_custom_course_list_hideallcourseslink)) {
                        $this->content->footer .= "<a href=\"$CFG->wwwroot/course/index.php\">".get_string('fulllistofcourses').'</a> ...';
                    }
                    $this->get_remote_courses();
                } else {

                    $this->content->icons[] = '';
                    $this->content->items[] = get_string('nocoursesyet');
                    if (has_capability('moodle/course:create', context_coursecat::instance($category->id))) {
                        $this->content->footer = '<a href="'.$CFG->wwwroot.'/course/edit.php?category='.$category->id.'">'.get_string("addnewcourse").'</a> ...';
                    }
                    $this->get_remote_courses();
                }
                $this->title = get_string('courses');
            }
        }

        return $this->content;

    }

    function get_remote_courses() {
        global $CFG, $USER, $OUTPUT;

        if (!is_enabled_auth('mnet')) {
            // no need to query anything remote related
            return;
        }

        $icon = $OUTPUT->pix_icon('i/mnethost', get_string('host', 'mnet'));

        // shortcut - the rest is only for logged in users!
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        if ($courses = get_my_remotecourses()) {
            $this->content->items[] = get_string('remotecourses','mnet');
            $this->content->icons[] = '';
            foreach ($courses as $course) {
                $this->content->items[]="<a title=\"" . format_string($course->shortname, true) . "\" ".
                    "href=\"{$CFG->wwwroot}/auth/mnet/jump.php?hostid={$course->hostid}&amp;wantsurl=/course/view.php?id={$course->remoteid}\">"
                    .$icon. format_string(get_course_display_name_for_list($course)) . "</a>";
            }
            // if we listed courses, we are done
            return true;
        }

        if ($hosts = get_my_remotehosts()) {
            $this->content->items[] = get_string('remotehosts', 'mnet');
            $this->content->icons[] = '';
            foreach($USER->mnet_foreign_host_array as $somehost) {
                $this->content->items[] = $somehost['count'].get_string('courseson','mnet').'<a title="'.$somehost['name'].'" href="'.$somehost['url'].'">'.$icon.$somehost['name'].'</a>';
            }
            // if we listed hosts, done
            return true;
        }

        return false;
    }

    /**
     * Returns the role that best describes the course list block.
     *
     * @return string
     */
    public function get_aria_role() {
        return 'navigation';
    }

    /**
     * Render the contents of a block_list.
     *
     * @param array $icons the icon for each item.
     * @param array $items the content of each item.
     * @return string HTML
     */
    public function list_block_contents($icons, $items) {
        $row = 0;
        $lis = array();
        foreach ($items as $key => $string) {
            $item = html_writer::start_tag('li', array('class' => 'r' . $row));
            if (!empty($icons[$key])) { //test if the content has an assigned icon
                $item .= html_writer::tag('div', $icons[$key], array('class' => 'icon column c0'));
            }
            $item .= html_writer::tag('div', $string, array('class' => 'column c1'));
            $item .= html_writer::end_tag('li');
            $lis[] = $item;
            $row = 1 - $row; // Flip even/odd.
        }
        $data = html_writer::tag('ul', implode("\n", $lis), array('class' => 'unlist'));
        //var_dump($data);
        return $data;
    }


    protected function formatted_contents($output) {
        $this->get_content();
        $this->get_required_javascript();
        if (!empty($this->content->items)) {
            return $this->list_block_contents($this->content->icons, $this->content->items);
        } else {
            return '';
        }
    }

}


